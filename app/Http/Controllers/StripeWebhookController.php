<?php

namespace App\Http\Controllers;

use App\Jobs\CreditWallet;
use App\Models\PondPurchase;
use App\Support\Payments\PondCheckout;
use App\Support\Payments\StripeGateway;
use App\Support\Payments\StripeSettings;
use App\Support\Wallet\WalletReversal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Stripe's own record of what happened. This is what makes a purchase reliable
 * when the buyer closes their browser before returning to the site.
 */
class StripeWebhookController extends Controller
{
    /**
     * How old a signed event may be, in seconds, before it is treated as a replay.
     */
    private const SIGNATURE_TOLERANCE = 300;

    public function __invoke(Request $request, PondCheckout $checkout, StripeSettings $settings, WalletReversal $reversal): Response
    {
        abort_unless($this->hasValidSignature($request, $settings), 400);

        $event = $request->json()->all();
        $object = $event['data']['object'] ?? [];
        $type = $event['type'] ?? null;

        if ($type === 'charge.refunded') {
            $this->chargeRefunded($object, $checkout, $reversal);
        } elseif (in_array($type, ['charge.dispute.created', 'charge.dispute.closed'], true)) {
            $this->disputeChanged($type, $object, $checkout, $reversal);
        } elseif (is_string($type) && str_starts_with($type, 'checkout.session.') && $purchase = $this->findBySession($object)) {
            $this->sessionChanged($type, $object, $purchase, $checkout);
        }

        return response()->noContent();
    }

    /**
     * Delayed payment methods complete the session before the money arrives; they
     * are settled later by the async_payment_succeeded event.
     *
     * @param  array<string, mixed>  $session
     */
    private function sessionChanged(string $type, array $session, PondPurchase $purchase, PondCheckout $checkout): void
    {
        $paymentId = $session['payment_intent'] ?? null;

        match ($type) {
            'checkout.session.completed' => ($session['payment_status'] ?? null) === 'paid'
                ? $checkout->markPaid($purchase, $paymentId)
                : null,
            'checkout.session.async_payment_succeeded' => $checkout->markPaid($purchase, $paymentId),
            'checkout.session.async_payment_failed' => $checkout->markFailed($purchase, 'The payment did not go through.'),
            'checkout.session.expired' => $checkout->markCancelled($purchase),
            default => null,
        };
    }

    /**
     * A partial refund made in the Stripe dashboard leaves the member owning the pond.
     *
     * @param  array<string, mixed>  $charge
     */
    private function chargeRefunded(array $charge, PondCheckout $checkout, WalletReversal $reversal): void
    {
        if (($charge['refunded'] ?? false) !== true || empty($charge['payment_intent'])) {
            return;
        }

        $purchase = $this->findByPaymentIntent($charge['payment_intent']);

        if ($purchase?->isPaid()) {
            // The buyer already has their money back, so the member's share has to come back too.
            $reversal->undoQuietly($purchase);

            // Stripe reports the amount in the currency the buyer paid in.
            $checkout->markRefunded($purchase, isset($charge['amount_refunded']) ? $purchase->toPricedCents((int) $charge['amount_refunded']) : null);
        }
    }

    /**
     * The buyer's bank opened or closed a dispute (chargeback). Money that is disputed is not sent
     * to the member while the dispute is open. If it is lost the buyer has their money back, so the
     * member's share is taken back and the toss is treated as refunded. Either way it is logged for an admin.
     *
     * @param  array<string, mixed>  $dispute
     */
    private function disputeChanged(string $type, array $dispute, PondCheckout $checkout, WalletReversal $reversal): void
    {
        $purchase = empty($dispute['payment_intent']) ? null : $this->findByPaymentIntent($dispute['payment_intent']);

        if (! $purchase) {
            return;
        }

        $status = (string) ($dispute['status'] ?? 'needs_response');

        $purchase->update([
            'dispute_id' => $dispute['id'] ?? $purchase->dispute_id,
            'dispute_status' => $status,
            'disputed_at' => $purchase->disputed_at ?? now(),
        ]);

        if ($type === 'charge.dispute.created') {
            Log::critical('A buyer disputed a payment.', ['purchase' => $purchase->reference, 'dispute' => $dispute['id'] ?? null, 'reason' => $dispute['reason'] ?? null]);

            return;
        }

        if ($status === 'lost' && $purchase->isPaid()) {
            $reversal->undoQuietly($purchase);
            $checkout->markRefunded($purchase, isset($dispute['amount']) ? $purchase->toPricedCents((int) $dispute['amount']) : null);
        } elseif ($status === 'won' && $purchase->recipient_id) {
            // Nothing is owed back, so a share that was held back for the dispute can now be credited.
            CreditWallet::dispatch($purchase->id);
        }
    }

    private function findByPaymentIntent(string $paymentIntent): ?PondPurchase
    {
        return PondPurchase::query()
            ->where('gateway', StripeGateway::NAME)
            ->where('gateway_payment_id', $paymentIntent)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function findBySession(array $session): ?PondPurchase
    {
        if (empty($session['id'])) {
            return null;
        }

        return PondPurchase::query()
            ->where('gateway', StripeGateway::NAME)
            ->where('gateway_session_id', $session['id'])
            ->first();
    }

    /**
     * @see https://docs.stripe.com/webhooks#verify-manually
     */
    private function hasValidSignature(Request $request, StripeSettings $settings): bool
    {
        $secret = (string) $settings->webhookSecret();

        if ($secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
