<?php

namespace App\Support\Payments;

use App\Models\PondPurchase;

/**
 * A provider that takes payment for a pond purchase on a hosted checkout page.
 */
interface PaymentGateway
{
    /**
     * The name stored on each purchase so later actions reach the same provider.
     */
    public function name(): string;

    /**
     * @throws PaymentGatewayException
     */
    public function createCheckout(PondPurchase $purchase, string $returnUrl, string $cancelUrl): CheckoutSession;

    /**
     * Ask the provider for the true state of a purchase's checkout.
     *
     * @throws PaymentGatewayException
     */
    public function fetchStatus(PondPurchase $purchase): CheckoutStatus;

    /**
     * Close an open checkout so it can no longer be paid.
     *
     * @throws PaymentGatewayException
     */
    public function cancel(PondPurchase $purchase): void;

    /**
     * Return money from a paid purchase to the buyer: the full amount, or less when the platform
     * keeps its fee. Asking twice for the same purchase must refund once.
     *
     * @throws PaymentGatewayException
     */
    public function refund(PondPurchase $purchase, int $amountCents): void;

    /**
     * What a paid purchase came to once the provider settled it, including the provider's own fee.
     *
     * @throws PaymentGatewayException
     */
    public function fetchChargeSettlement(PondPurchase $purchase): ChargeSettlement;
}
