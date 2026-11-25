<?php

namespace App\Support\Wallet;

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Jobs\CreditWallet;
use App\Models\PondPurchase;
use App\Support\Payments\ChargeSettlement;
use App\Support\Payments\FeeCalculator;
use App\Support\Payments\FeeSettings;
use App\Support\Payments\PaymentGatewayException;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adds the member's share of a toss to their wallet once it is paid for. The share is what the payment
 * provider received, less the platform fee and less the provider's own processing fee, which the member
 * bears. The provider's fee is only known once the payment settles, so it is read from the provider
 * rather than guessed.
 *
 * Running it more than once for a purchase never credits the wallet twice.
 */
class WalletSettlement
{
    /** After this many failed attempts a purchase is left for an admin to look at. */
    public const MAX_ATTEMPTS = 10;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private Wallet $wallet,
    ) {}

    /**
     * Try to add the member's share of one purchase to their wallet.
     */
    public function settle(PondPurchase $purchase): void
    {
        $lock = Cache::lock("wallet-settle:{$purchase->id}", 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->run($purchase->fresh());
        } finally {
            $lock->release();
        }
    }

    /**
     * Queue everything still waiting to be credited: the retry for shares that could not be worked out earlier.
     *
     * @return int how many purchases were queued
     */
    public function queueDue(): int
    {
        $ids = PondPurchase::query()
            ->where('status', PurchaseStatus::PAID)
            ->whereNotNull('recipient_id')
            ->whereIn('payout_status', [PayoutStatus::PENDING, PayoutStatus::FAILED])
            ->where('payout_attempts', '<', self::MAX_ATTEMPTS)
            ->pluck('id');

        $ids->each(fn (int $id) => CreditWallet::dispatch($id));

        return $ids->count();
    }

    private function run(?PondPurchase $purchase): void
    {
        if (! $purchase || ! $purchase->isPaid()) {
            return;
        }

        // A ball tossed into the Pond has no member to pay, but what it cost to process is still recorded.
        if ($purchase->recipient_id === null) {
            $this->recordDetails($purchase);

            return;
        }

        if (! in_array($purchase->payout_status, [PayoutStatus::PENDING, PayoutStatus::FAILED], true)) {
            return;
        }

        // Money the buyer's bank is disputing is not added to the wallet until the dispute is settled.
        if ($purchase->hasOpenDispute()) {
            return;
        }

        try {
            $charge = $this->gateways->for($purchase->gateway)->fetchChargeSettlement($purchase);
        } catch (PaymentGatewayException $exception) {
            $this->failed($purchase, $exception->getMessage());

            return;
        }

        $platformFee = $this->platformFee($purchase, $charge);
        $net = max(0, $charge->grossCents - $charge->processingFeeCents - $platformFee);

        $details = [
            'fee_cents' => $platformFee,
            'processing_fee_cents' => $charge->processingFeeCents,
            ...$this->presentment($charge),
        ];

        if ($net === 0) {
            $purchase->update([
                ...$details,
                'net_cents' => 0,
                'payout_status' => PayoutStatus::NONE,
                'payout_failure_message' => 'The fees were as much as the toss, so there was nothing to add to the wallet.',
            ]);

            return;
        }

        DB::transaction(function () use ($purchase, $details, $net): void {
            $this->wallet->creditPurchase($purchase, $net);

            $purchase->update([
                ...$details,
                'net_cents' => $net,
                'payout_status' => PayoutStatus::CREDITED,
                'payout_failure_message' => null,
                'credited_at' => now(),
            ]);
        });
    }

    /**
     * Note what the provider charged to process a payment, and what the buyer actually paid when it
     * was in their own currency. Not being able to is only logged: nothing depends on it.
     */
    private function recordDetails(PondPurchase $purchase): void
    {
        if ($purchase->processing_fee_cents !== null) {
            return;
        }

        try {
            $charge = $this->gateways->for($purchase->gateway)->fetchChargeSettlement($purchase);

            $purchase->update(['processing_fee_cents' => $charge->processingFeeCents, ...$this->presentment($charge)]);
        } catch (Throwable $exception) {
            Log::warning('Could not record the processing details of a payment.', ['purchase' => $purchase->reference, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array{presentment_currency: string|null, presentment_amount_cents: int|null}
     */
    private function presentment(ChargeSettlement $charge): array
    {
        return [
            'presentment_currency' => $charge->buyerCurrency,
            'presentment_amount_cents' => $charge->buyerPaidCents,
        ];
    }

    /**
     * The platform fee worked out at checkout stands, unless the provider settled the payment in a
     * different currency, in which case the same percentage is taken from what it actually received.
     */
    private function platformFee(PondPurchase $purchase, ChargeSettlement $charge): int
    {
        if ($charge->currency === strtolower($purchase->currency)) {
            return $purchase->fee_cents;
        }

        return FeeCalculator::platformFeeCents(
            $charge->grossCents,
            (float) $purchase->fee_percent,
            app(FeeSettings::class)->minimumFeeCents(),
        );
    }

    private function failed(PondPurchase $purchase, string $message): void
    {
        $attempts = $purchase->payout_attempts + 1;

        $purchase->update([
            'payout_status' => PayoutStatus::FAILED,
            'payout_failure_message' => $message,
            'payout_attempts' => $attempts,
        ]);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Log::critical('A member could not be credited after repeated attempts.', ['purchase' => $purchase->reference, 'error' => $message]);
        } else {
            Log::warning('Working out a member\'s share failed and will be retried.', ['purchase' => $purchase->reference, 'attempt' => $attempts, 'error' => $message]);
        }
    }
}
