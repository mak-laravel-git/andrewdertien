<?php

namespace App\Support\Payments;

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Jobs\CreditWallet;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Notifications\BallReceived;
use App\Support\Wallet\WalletReversal;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every change to a purchase's status goes through here, so the browser return,
 * the provider's webhook and admin actions can arrive in any order, any number
 * of times, and still leave one correct result.
 */
class PondCheckout
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    /**
     * Open a checkout for this member and return the URL to send them to. Without an
     * amount the ball's suggested amount is used; a ball can be tossed again and again.
     *
     * @throws PaymentGatewayException
     */
    public function start(User $user, Pond $pond, ?int $amountCents = null, ?User $recipient = null): string
    {
        if ($recipient && ! $recipient->canBeTossedABall()) {
            throw new PaymentGatewayException("A ball cannot be tossed to {$recipient->name} right now.");
        }

        $lock = Cache::lock("pond-checkout:{$user->id}:{$pond->id}", 30);

        if (! $lock->get()) {
            throw new PaymentGatewayException('Your checkout is already being prepared.');
        }

        try {
            $this->closeOpenCheckouts($user, $pond);

            return $this->openCheckout($user, $pond, $amountCents ?? $pond->price_cents, $recipient);
        } finally {
            $lock->release();
        }
    }

    /**
     * Close a member's abandoned checkout, or record it as paid if the provider
     * says the money did arrive.
     *
     * @throws PaymentGatewayException
     */
    public function closeOrSettle(PondPurchase $purchase): void
    {
        $gateway = $this->gateways->for($purchase->gateway);
        $status = $gateway->fetchStatus($purchase);

        if ($status->isPaid) {
            $this->markPaid($purchase, $status->paymentId);

            return;
        }

        if ($status->isOpen) {
            $gateway->cancel($purchase);
        }

        $this->markCancelled($purchase);
    }

    /**
     * Money was received. This wins over any earlier cancelled or failed state.
     */
    public function markPaid(PondPurchase $purchase, ?string $paymentId): void
    {
        $newlyPaid = false;

        $this->transition($purchase, function (PondPurchase $current) use ($paymentId, &$newlyPaid): void {
            if (in_array($current->status, [PurchaseStatus::PAID, PurchaseStatus::REFUNDED], true)) {
                return;
            }

            $current->update([
                'status' => PurchaseStatus::PAID,
                'gateway_payment_id' => $paymentId,
                'failure_message' => null,
                'paid_at' => now(),
                // A toss for a member is then sent on to them; one into the Pond is not.
                'payout_status' => $current->recipient_id ? PayoutStatus::PENDING : PayoutStatus::NONE,
            ]);

            $newlyPaid = true;
        });

        // Sends the member their share, and notes what the payment cost to process and what the buyer paid.
        if ($newlyPaid) {
            CreditWallet::dispatch($purchase->id);

            $this->tellRecipient($purchase);
        }
    }

    public function markFailed(PondPurchase $purchase, string $message): void
    {
        $this->transition($purchase, function (PondPurchase $current) use ($message): void {
            if ($current->isPending()) {
                $current->update(['status' => PurchaseStatus::FAILED, 'failure_message' => $message]);
            }
        });
    }

    public function markCancelled(PondPurchase $purchase): void
    {
        $this->transition($purchase, function (PondPurchase $current): void {
            if ($current->isPending()) {
                $current->update(['status' => PurchaseStatus::CANCELLED]);
            }
        });
    }

    /**
     * Let the member know a ball was tossed to them. Failing to is only logged: the payment stands.
     */
    private function tellRecipient(PondPurchase $purchase): void
    {
        if (! $purchase->recipient_id) {
            return;
        }

        try {
            $purchase->recipient?->notify(new BallReceived($purchase->refresh()));
        } catch (Throwable $exception) {
            Log::warning('Could not tell a member a ball was tossed to them.', ['purchase' => $purchase->reference, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * @param  int|null  $refundedCents  what the buyer got back, when known
     */
    public function markRefunded(PondPurchase $purchase, ?int $refundedCents = null): void
    {
        $this->transition($purchase, function (PondPurchase $current) use ($refundedCents): void {
            if ($current->isPaid()) {
                $current->update([
                    'status' => PurchaseStatus::REFUNDED,
                    'refunded_at' => now(),
                    'refunded_cents' => $refundedCents ?? $current->amount_cents,
                ]);
            }
        });
    }

    /**
     * Refund a paid purchase. The buyer gets back everything except the platform fee, which is kept,
     * and the member's payout is taken back first so the platform is never left out of pocket.
     * The member stops owning the pond. If the refund fails after the payout was taken back, trying
     * again only repeats the refund.
     *
     * @throws PaymentGatewayException
     */
    public function refund(PondPurchase $purchase): void
    {
        if (! $purchase->isPaid()) {
            throw new PaymentGatewayException('Only a paid purchase can be refunded.');
        }

        if ($purchase->hasOpenDispute()) {
            throw new PaymentGatewayException('The buyer’s bank is disputing this payment. Respond to the dispute in Stripe instead of refunding it.');
        }

        app(WalletReversal::class)->undo($purchase);

        $refundCents = $purchase->refundAmountCents();

        if ($refundCents > 0) {
            $this->gateways->for($purchase->gateway)->refund($purchase, $refundCents);
        }

        $this->markRefunded($purchase, $refundCents);
    }

    /**
     * @throws PaymentGatewayException
     */
    private function closeOpenCheckouts(User $user, Pond $pond): void
    {
        $user->pondPurchases()
            ->where('pond_id', $pond->id)
            ->where('status', PurchaseStatus::PENDING)
            ->get()
            ->each(fn (PondPurchase $purchase) => $this->closeOrSettle($purchase));
    }

    /**
     * @throws PaymentGatewayException
     */
    private function openCheckout(User $user, Pond $pond, int $amountCents, ?User $recipient): string
    {
        $gateway = $this->gateways->active();
        $fees = app(FeeSettings::class);

        // A toss for a member carries the platform fee; one into the Pond goes wholly to the platform.
        $feeCents = $recipient
            ? FeeCalculator::platformFeeCents($amountCents, $fees->percent(), $fees->minimumFeeCents())
            : 0;

        $purchase = $user->pondPurchases()->create([
            'fee_percent' => $recipient ? $fees->percent() : null,
            'fee_cents' => $feeCents,
            'pond_id' => $pond->id,
            'recipient_id' => $recipient?->id,
            'buyer_name' => $user->name,
            'buyer_email' => $user->email,
            'recipient_name' => $recipient?->name,
            'pond_name' => $pond->name,
            'amount_cents' => $amountCents,
            'currency' => config('payments.currency'),
            'status' => PurchaseStatus::PENDING,
            'gateway' => $gateway->name(),
            'is_test' => $this->gateways->isTestMode(),
        ]);

        try {
            $session = $gateway->createCheckout(
                $purchase,
                route('payments.return', $purchase),
                route('payments.cancel', $purchase),
            );
        } catch (PaymentGatewayException $exception) {
            $this->markFailed($purchase, $exception->getMessage());

            throw $exception;
        }

        $purchase->update(['gateway_session_id' => $session->id]);

        return $session->url;
    }

    /**
     * @param  Closure(PondPurchase): void  $change
     */
    private function transition(PondPurchase $purchase, Closure $change): void
    {
        DB::transaction(function () use ($purchase, $change): void {
            $change(PondPurchase::query()->lockForUpdate()->findOrFail($purchase->id));
        });

        $purchase->refresh();
    }
}
