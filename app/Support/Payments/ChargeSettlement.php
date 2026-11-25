<?php

namespace App\Support\Payments;

/**
 * What the payment provider says a payment came to once it settled: the money it received,
 * the processing fee it kept, and which charge to send the member's share from.
 */
final readonly class ChargeSettlement
{
    public function __construct(
        public string $chargeId,
        public int $grossCents,
        public int $processingFeeCents,
        public string $currency,
        /** What the buyer actually paid, which differs from the amount received when they paid in their own currency. */
        public ?int $buyerPaidCents = null,
        public ?string $buyerCurrency = null,
    ) {}
}
