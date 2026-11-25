<?php

namespace App\Support\Wallet;

use App\Enums\PayoutStatus;
use App\Models\PondPurchase;
use App\Support\Payments\PaymentGatewayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Takes a member's share of a toss back out of their wallet when the toss is refunded or lost in a
 * dispute. If the share was never added, it is simply cancelled. Doing it again for the same
 * purchase changes nothing.
 */
class WalletReversal
{
    public function __construct(private Wallet $wallet) {}

    /**
     * @throws PaymentGatewayException when the purchase is being processed at this very moment
     */
    public function undo(PondPurchase $purchase): void
    {
        // The same lock as crediting the wallet, so the two can never run at once.
        $lock = Cache::lock("wallet-settle:{$purchase->id}", 60);

        if (! $lock->get()) {
            throw new PaymentGatewayException('This payment is being processed right now. Please try again in a moment.');
        }

        try {
            $this->run($purchase->fresh());
        } finally {
            $lock->release();
        }

        $purchase->refresh();
    }

    /**
     * The same, for when the buyer has already been refunded and a problem can only be reported.
     *
     * @return bool whether the member's share is now taken back or was never credited
     */
    public function undoQuietly(PondPurchase $purchase): bool
    {
        try {
            $this->undo($purchase);

            return true;
        } catch (PaymentGatewayException $exception) {
            $purchase->update(['payout_failure_message' => 'The buyer was refunded, but the wallet could not be debited yet. This needs attention.']);

            Log::critical('A refunded toss could not be taken back from the member wallet.', [
                'purchase' => $purchase->reference,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function run(?PondPurchase $purchase): void
    {
        if (! $purchase) {
            return;
        }

        if ($purchase->payout_status === PayoutStatus::CREDITED) {
            $this->wallet->reversePurchase($purchase);

            $purchase->update(['payout_status' => PayoutStatus::REVERSED, 'payout_failure_message' => null]);

            return;
        }

        if (in_array($purchase->payout_status, [PayoutStatus::PENDING, PayoutStatus::FAILED], true)) {
            $purchase->update([
                'payout_status' => PayoutStatus::NONE,
                'payout_failure_message' => 'The toss was refunded before the member was credited.',
            ]);
        }
    }
}
