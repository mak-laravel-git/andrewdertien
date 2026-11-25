<?php

namespace App\Jobs;

use App\Models\PondPurchase;
use App\Support\Wallet\WalletSettlement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Adds a member's share of one paid toss to their wallet. A failure is recorded on the purchase and
 * picked up again by the scheduled retry, so this job never needs to fail loudly.
 */
class CreditWallet implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $purchaseId)
    {
        $this->afterCommit();
    }

    public function handle(WalletSettlement $settlement): void
    {
        $purchase = PondPurchase::query()->find($this->purchaseId);

        if ($purchase) {
            $settlement->settle($purchase);
        }
    }
}
