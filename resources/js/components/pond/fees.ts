/** The platform fee and toss limits an admin set, as the server shares them. */
export type FeeRules = {
    percent: number;
    minimum_fee_cents: number;
    minimum_toss_cents: number;
    processing_percent: number;
    processing_fixed_cents: number;
    adaptive_pricing?: boolean;
};

export type FeeBreakdown = {
    amountCents: number;
    platformFeeCents: number;
    processingEstimateCents: number;
    memberEstimateCents: number;
};

/**
 * What a toss for a member splits into. This mirrors App\Support\Payments\FeeCalculator,
 * so the numbers on screen match what is charged. The Stripe processing fee is only an estimate here.
 */
export function feeBreakdown(
    amountCents: number,
    rules: Pick<
        FeeRules,
        | 'percent'
        | 'minimum_fee_cents'
        | 'processing_percent'
        | 'processing_fixed_cents'
    >,
): FeeBreakdown {
    const fee = Math.max(
        0,
        Math.min(
            amountCents,
            Math.max(
                Math.round((amountCents * rules.percent) / 100),
                rules.minimum_fee_cents,
            ),
        ),
    );
    const processing = Math.min(
        amountCents,
        Math.round((amountCents * rules.processing_percent) / 100) +
            rules.processing_fixed_cents,
    );

    return {
        amountCents,
        platformFeeCents: fee,
        processingEstimateCents: processing,
        memberEstimateCents: Math.max(0, amountCents - fee - processing),
    };
}

/** Cents as a plain dollar figure without trailing ".00", such as "5" or "7.50". */
export function plainDollars(cents: number): string {
    const value = cents / 100;

    return Number.isInteger(value) ? String(value) : value.toFixed(2);
}
