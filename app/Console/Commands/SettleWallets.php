<?php

namespace App\Console\Commands;

use App\Support\Wallet\WalletSettlement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wallet:settle')]
#[Description('Add members\' shares of paid tosses to their wallets where that has not happened yet')]
class SettleWallets extends Command
{
    public function handle(WalletSettlement $settlement): int
    {
        $this->info("Queued {$settlement->queueDue()} wallet credit(s).");

        return self::SUCCESS;
    }
}
