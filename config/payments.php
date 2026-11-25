<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    |
    | "stripe" charges real cards through Stripe Checkout. "sandbox" simulates a
    | checkout for local development and is refused in production.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'sandbox'),

    'currency' => strtolower((string) env('PAYMENT_CURRENCY', 'usd')),

    /*
    |--------------------------------------------------------------------------
    | Stripe processing fee estimate
    |--------------------------------------------------------------------------
    |
    | Stripe's processing fee comes out of what the member receives. These numbers
    | are only used to tell a giver roughly what the member will get; the real fee
    | is taken from Stripe once the payment succeeds.
    |
    */

    'processing_estimate' => [
        'percent' => 2.9,
        'fixed_cents' => 30,
    ],

];
