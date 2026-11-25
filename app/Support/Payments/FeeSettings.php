<?php

namespace App\Support\Payments;

use App\Models\PaymentSetting;

/**
 * The platform fee and toss limits an admin chose in the dashboard. Until an admin saves
 * anything, the defaults below apply, so a fresh install already has a 10% fee.
 */
class FeeSettings
{
    public const DEFAULT_PERCENT = 10.0;

    public const DEFAULT_MINIMUM_FEE_CENTS = 50;

    public const DEFAULT_MINIMUM_TOSS_CENTS = 100;

    public const DEFAULT_MINIMUM_WITHDRAWAL_CENTS = 1000;

    /** The fee can never be set above this share of a toss. */
    public const MAXIMUM_PERCENT = 50.0;

    private ?PaymentSetting $row = null;

    private bool $loaded = false;

    /**
     * The platform's share of a toss that is for a member, as a percentage (10 means 10%).
     */
    public function percent(): float
    {
        return (float) ($this->row()?->platform_fee_percent ?? self::DEFAULT_PERCENT);
    }

    /**
     * The smallest fee the platform takes from a toss for a member, in cents.
     */
    public function minimumFeeCents(): int
    {
        return (int) ($this->row()?->minimum_fee_cents ?? self::DEFAULT_MINIMUM_FEE_CENTS);
    }

    /**
     * The smallest amount a ball can be tossed for, in cents.
     */
    public function minimumTossCents(): int
    {
        return (int) ($this->row()?->minimum_toss_cents ?? self::DEFAULT_MINIMUM_TOSS_CENTS);
    }

    /**
     * The least a member can ask to withdraw from their wallet, in cents.
     */
    public function minimumWithdrawalCents(): int
    {
        return (int) ($this->row()?->minimum_withdrawal_cents ?? self::DEFAULT_MINIMUM_WITHDRAWAL_CENTS);
    }

    /**
     * How many days new earnings are held in a wallet before they can be withdrawn.
     */
    public function walletHoldDays(): int
    {
        return (int) ($this->row()?->wallet_hold_days ?? 0);
    }

    /**
     * Whether a buyer abroad can pay in their own currency. Balls stay priced in dollars; the
     * payment provider converts, and the buyer carries its conversion fee.
     */
    public function adaptivePricing(): bool
    {
        return (bool) ($this->row()?->adaptive_pricing ?? true);
    }

    /**
     * What the toss screens need to explain the fee to a giver.
     *
     * @return array{percent: float, minimum_fee_cents: int, minimum_toss_cents: int, processing_percent: float, processing_fixed_cents: int, adaptive_pricing: bool}
     */
    public function forDisplay(): array
    {
        return [
            'percent' => $this->percent(),
            'minimum_fee_cents' => $this->minimumFeeCents(),
            'minimum_toss_cents' => $this->minimumTossCents(),
            'processing_percent' => (float) config('payments.processing_estimate.percent'),
            'processing_fixed_cents' => (int) config('payments.processing_estimate.fixed_cents'),
            'adaptive_pricing' => $this->adaptivePricing(),
        ];
    }

    /**
     * Forget what was read, after the settings have changed.
     */
    public function flush(): void
    {
        $this->row = null;
        $this->loaded = false;
    }

    private function row(): ?PaymentSetting
    {
        if (! $this->loaded) {
            $this->row = PaymentSetting::query()->first();
            $this->loaded = true;
        }

        return $this->row;
    }
}
