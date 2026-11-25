<?php

namespace App\Support\Payments;

/**
 * Splits a toss for a member into the platform's fee and what the member keeps.
 * All amounts are whole cents, and the pieces always add back up to the amount.
 */
class FeeCalculator
{
    /**
     * The platform fee on a toss: the percentage, but never less than the minimum fee
     * and never more than the toss itself.
     */
    public static function platformFeeCents(int $amountCents, float $percent, int $minimumFeeCents): int
    {
        $fee = (int) round($amountCents * $percent / 100, 0, PHP_ROUND_HALF_UP);

        return max(0, min($amountCents, max($fee, $minimumFeeCents)));
    }

    /**
     * A rough guess at Stripe's processing fee, used only to tell a giver what the member
     * will get. The real fee is read from Stripe once the payment goes through.
     */
    public static function processingEstimateCents(int $amountCents): int
    {
        $percent = (float) config('payments.processing_estimate.percent');
        $fixed = (int) config('payments.processing_estimate.fixed_cents');

        return min($amountCents, (int) round($amountCents * $percent / 100, 0, PHP_ROUND_HALF_UP) + $fixed);
    }

    /**
     * @return array{amount_cents: int, platform_fee_cents: int, processing_estimate_cents: int, member_estimate_cents: int}
     */
    public static function breakdown(int $amountCents, FeeSettings $settings): array
    {
        $fee = self::platformFeeCents($amountCents, $settings->percent(), $settings->minimumFeeCents());
        $processing = self::processingEstimateCents($amountCents);

        return [
            'amount_cents' => $amountCents,
            'platform_fee_cents' => $fee,
            'processing_estimate_cents' => $processing,
            'member_estimate_cents' => max(0, $amountCents - $fee - $processing),
        ];
    }
}
