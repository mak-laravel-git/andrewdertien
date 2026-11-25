<?php

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RoleEnum;
use App\Jobs\CreditWallet;
use App\Models\PaymentSetting;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Payments\PondCheckout;
use App\Support\Wallet\Wallet;
use App\Support\Wallet\WalletSettlement;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['payments.driver' => 'sandbox', 'payments.currency' => 'usd']);

    $this->giver = User::factory()->create(['name' => 'Alex Giver']);
    $this->giver->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->adult()->create(['username' => 'jamie', 'name' => 'Jamie Pond']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->ball = Pond::factory()->create(['name' => 'Dream Ball', 'price_cents' => 5000]);
});

/**
 * A $50 toss that has not been paid for yet, with the 10% fee worked out at checkout.
 *
 * @param  array<string, mixed>  $attributes
 */
function unpaidToss(User $giver, ?User $recipient, Pond $ball, array $attributes = []): PondPurchase
{
    return PondPurchase::factory()->create([
        'user_id' => $giver->id,
        'recipient_id' => $recipient?->id,
        'recipient_name' => $recipient?->name,
        'pond_id' => $ball->id,
        'pond_name' => $ball->name,
        'amount_cents' => 5000,
        'fee_percent' => $recipient ? 10 : null,
        'fee_cents' => $recipient ? 500 : 0,
        'gateway' => 'sandbox',
        ...$attributes,
    ]);
}

function useStripeForWallets(): void
{
    config(['payments.driver' => 'stripe', 'services.stripe.secret' => 'sk_test_fake']);

    Http::preventStrayRequests();
}

/**
 * Stripe's answer for a $50 payment that cost $1.75 to process.
 *
 * @return array<string, mixed>
 */
function stripeSettled(int $amount = 5000, int $fee = 175, string $paid = 'usd', ?int $paidAmount = null): array
{
    return [
        'api.stripe.com/v1/payment_intents/pi_1*' => Http::response([
            'id' => 'pi_1',
            'latest_charge' => [
                'id' => 'ch_1',
                'amount' => $paidAmount ?? $amount,
                'currency' => $paid,
                'balance_transaction' => ['amount' => $amount, 'fee' => $fee, 'currency' => 'usd'],
            ],
        ]),
    ];
}

// ── Crediting the wallet ───────────────────────────────────────────────────

test('paying for a toss adds the member share to their wallet, less the platform fee and the processing fee', function () {
    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50, 'recipient_id' => $this->friend->id]);

    $purchase = PondPurchase::sole();
    expect($purchase->payout_status)->toBe(PayoutStatus::NONE);

    $this->actingAs($this->giver)->post(route('payments.sandbox.complete', $purchase), ['outcome' => 'pay'])->assertRedirect();

    expect($purchase->fresh())
        ->payout_status->toBe(PayoutStatus::CREDITED)
        ->fee_cents->toBe(500)
        ->processing_fee_cents->toBe(175)
        ->net_cents->toBe(4325)
        ->credited_at->not->toBeNull()
        ->payout_failure_message->toBeNull();

    $entry = WalletEntry::sole();

    expect($entry)
        ->user_id->toBe($this->friend->id)
        ->type->toBe(WalletEntry::TOSS_RECEIVED)
        ->amount_cents->toBe(4325)
        ->purchase_id->toBe($purchase->id)
        ->description->toBe('Dream Ball from Alex Giver');

    expect(app(Wallet::class)->summary($this->friend))
        ->total_cents->toBe(4325)
        ->withdrawable_cents->toBe(4325)
        ->pending_cents->toBe(0)
        ->earned_cents->toBe(4325);
});

test('the share and the fees always add up to what was paid', function (int $amount, int $fee) {
    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['amount_cents' => $amount, 'fee_cents' => $fee]);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    $paid = $purchase->fresh();

    expect($paid->payout_status)->toBe(PayoutStatus::CREDITED)
        ->and($paid->net_cents + $paid->processing_fee_cents + $paid->fee_cents)->toBe($amount)
        ->and(WalletEntry::sole()->amount_cents)->toBe($paid->net_cents);
})->with([
    'fifty dollars' => [5000, 500],
    'a hundred' => [10000, 1000],
    'a small toss' => [500, 50],
    'odd cents' => [1234, 123],
]);

test('a ball tossed into the Pond has nothing to credit', function () {
    $purchase = unpaidToss($this->giver, null, $this->ball);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    expect($purchase->fresh())->payout_status->toBe(PayoutStatus::NONE)->net_cents->toBeNull()
        ->and(WalletEntry::count())->toBe(0);
});

test('paying twice or settling twice credits the wallet once', function () {
    Queue::fake();
    $purchase = unpaidToss($this->giver, $this->friend, $this->ball);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');
    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    Queue::assertPushed(CreditWallet::class, 1);

    app(WalletSettlement::class)->settle($purchase);
    app(WalletSettlement::class)->settle($purchase);

    expect(WalletEntry::count())->toBe(1)->and($purchase->fresh()->payout_status)->toBe(PayoutStatus::CREDITED);
});

test('earnings can be held for a few days before they can be withdrawn', function () {
    PaymentSetting::current()->update(['wallet_hold_days' => 7]);
    $purchase = unpaidToss($this->giver, $this->friend, $this->ball);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    expect(app(Wallet::class)->summary($this->friend))
        ->total_cents->toBe(4325)
        ->withdrawable_cents->toBe(0)
        ->pending_cents->toBe(4325);

    $this->travel(8)->days();

    expect(app(Wallet::class)->summary($this->friend))->withdrawable_cents->toBe(4325)->pending_cents->toBe(0);
});

test('a toss with a dispute open is not credited until the dispute is won', function () {
    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['dispute_id' => 'dp_1', 'dispute_status' => 'needs_response']);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    expect($purchase->fresh()->payout_status)->toBe(PayoutStatus::PENDING)->and(WalletEntry::count())->toBe(0);

    $purchase->update(['dispute_status' => 'won']);
    app(WalletSettlement::class)->settle($purchase);

    expect($purchase->fresh()->payout_status)->toBe(PayoutStatus::CREDITED)->and(WalletEntry::count())->toBe(1);
});

// ── With Stripe ────────────────────────────────────────────────────────────

test('Stripe\'s real processing fee is what comes off the member share, not the estimate', function () {
    useStripeForWallets();
    Http::fake(stripeSettled(5000, 1000));

    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);
    app(PondCheckout::class)->markPaid($purchase, 'pi_1');

    expect($purchase->fresh())->processing_fee_cents->toBe(1000)->net_cents->toBe(3500)->payout_status->toBe(PayoutStatus::CREDITED)
        ->and(WalletEntry::sole()->amount_cents)->toBe(3500);

    // No money is sent anywhere: the share simply sits in the wallet.
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/transfers'));
});

test('a buyer who paid in euros is recorded, and the share is worked out from the dollars received', function () {
    useStripeForWallets();
    Http::fake(stripeSettled(5000, 250, 'eur', 4580));

    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);
    app(PondCheckout::class)->markPaid($purchase, 'pi_1');

    expect($purchase->fresh())
        ->presentment_currency->toBe('eur')
        ->presentment_amount_cents->toBe(4580)
        ->net_cents->toBe(4250)
        ->and(WalletEntry::sole()->amount_cents)->toBe(4250);
});

test('when the fees use up the whole toss nothing is credited', function () {
    useStripeForWallets();
    Http::fake(stripeSettled(100, 33));

    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe', 'amount_cents' => 100, 'fee_cents' => 100]);
    app(PondCheckout::class)->markPaid($purchase, 'pi_1');

    expect($purchase->fresh())->payout_status->toBe(PayoutStatus::NONE)->net_cents->toBe(0)
        ->and(WalletEntry::count())->toBe(0);
});

test('a payment Stripe has not settled yet is retried later, and then credited', function () {
    useStripeForWallets();
    Http::fake(['api.stripe.com/v1/payment_intents/pi_1*' => Http::sequence()
        ->push(['id' => 'pi_1', 'latest_charge' => null])
        ->push(['id' => 'pi_1', 'latest_charge' => ['id' => 'ch_1', 'amount' => 5000, 'currency' => 'usd', 'balance_transaction' => ['amount' => 5000, 'fee' => 175, 'currency' => 'usd']]])]);

    $purchase = unpaidToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);
    app(PondCheckout::class)->markPaid($purchase, 'pi_1');

    expect($purchase->fresh())
        ->status->toBe(PurchaseStatus::PAID)
        ->payout_status->toBe(PayoutStatus::FAILED)
        ->payout_attempts->toBe(1)
        ->payout_failure_message->toBe('Stripe has not finished settling this payment yet.')
        ->and(WalletEntry::count())->toBe(0);

    $this->artisan('wallet:settle')->expectsOutputToContain('Queued 1 wallet credit')->assertSuccessful();

    expect($purchase->fresh())->payout_status->toBe(PayoutStatus::CREDITED)->payout_failure_message->toBeNull()
        ->and(WalletEntry::sole()->amount_cents)->toBe(4325);
});

test('the retry only picks up what is paid and still waiting, and gives up after too many failures', function () {
    Queue::fake();

    $waiting = unpaidToss($this->giver, $this->friend, $this->ball, ['status' => 'paid', 'payout_status' => 'pending']);
    $failed = unpaidToss($this->giver, $this->friend, $this->ball, ['status' => 'paid', 'payout_status' => 'failed', 'payout_attempts' => 3]);
    unpaidToss($this->giver, $this->friend, $this->ball, ['status' => 'paid', 'payout_status' => 'credited']);
    unpaidToss($this->giver, null, $this->ball, ['status' => 'paid', 'payout_status' => 'none']);
    unpaidToss($this->giver, $this->friend, $this->ball, ['status' => 'paid', 'payout_status' => 'failed', 'payout_attempts' => WalletSettlement::MAX_ATTEMPTS]);
    unpaidToss($this->giver, $this->friend, $this->ball, ['status' => 'pending', 'payout_status' => 'pending']);

    $this->artisan('wallet:settle')->expectsOutputToContain('Queued 2 wallet credit')->assertSuccessful();

    Queue::assertPushed(CreditWallet::class, 2);
    Queue::assertPushed(CreditWallet::class, fn (CreditWallet $job) => in_array($job->purchaseId, [$waiting->id, $failed->id], true));
});

test('the retry is on the schedule', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('wallet:settle')->assertSuccessful();
});

test('not being able to read the payment details never stops a ball into the Pond counting', function () {
    useStripeForWallets();
    Http::fake(['api.stripe.com/v1/payment_intents/pi_1*' => Http::response([], 500)]);

    $purchase = unpaidToss($this->giver, null, $this->ball, ['gateway' => 'stripe']);
    app(PondCheckout::class)->markPaid($purchase, 'pi_1');

    expect($purchase->fresh())->status->toBe(PurchaseStatus::PAID)->processing_fee_cents->toBeNull();
});

test('purchase history rows carry the fee, the processing fee, the share and the wallet status', function () {
    $purchase = unpaidToss($this->giver, $this->friend, $this->ball);
    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    expect($purchase->fresh()->toHistoryRow())->fee_cents->toBe(500)->processing_fee_cents->toBe(175)->net_cents->toBe(4325)->payout_status->toBe('credited');
});
