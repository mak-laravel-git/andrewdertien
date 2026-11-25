<?php

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RoleEnum;
use App\Jobs\CreditWallet;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Payments\PaymentGatewayException;
use App\Support\Payments\PondCheckout;
use App\Support\Wallet\Wallet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'payments.driver' => 'sandbox',
        'payments.currency' => 'usd',
        'services.stripe.secret' => null,
        'services.stripe.webhook_secret' => 'whsec_main',
    ]);

    $this->giver = User::factory()->create(['name' => 'Alex Giver']);
    $this->giver->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->adult()->create(['username' => 'jamie', 'name' => 'Jamie Pond']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->ball = Pond::factory()->create(['name' => 'Dream Ball', 'price_cents' => 5000]);
});

/**
 * A $50 toss, paid and already in the member's wallet: $5 platform fee, $1.75 Stripe fee, $43.25 to the member.
 *
 * @param  array<string, mixed>  $attributes
 */
function creditedToss(User $giver, ?User $recipient, Pond $ball, array $attributes = []): PondPurchase
{
    $purchase = PondPurchase::factory()->create([
        'user_id' => $giver->id,
        'recipient_id' => $recipient?->id,
        'recipient_name' => $recipient?->name,
        'pond_id' => $ball->id,
        'pond_name' => $ball->name,
        'amount_cents' => 5000,
        'fee_percent' => $recipient ? 10 : null,
        'fee_cents' => $recipient ? 500 : 0,
        'processing_fee_cents' => $recipient ? 175 : null,
        'net_cents' => $recipient ? 4325 : null,
        'status' => PurchaseStatus::PAID,
        'paid_at' => now(),
        'gateway' => 'sandbox',
        'gateway_payment_id' => 'pi_1',
        'payout_status' => $recipient ? PayoutStatus::CREDITED : PayoutStatus::NONE,
        ...$attributes,
    ]);

    if ($recipient && ($attributes['payout_status'] ?? PayoutStatus::CREDITED) === PayoutStatus::CREDITED) {
        app(Wallet::class)->creditPurchase($purchase, 4325);
    }

    return $purchase;
}

function useStripeForWalletRefunds(): void
{
    config(['payments.driver' => 'stripe', 'services.stripe.secret' => 'sk_test_fake']);

    Http::preventStrayRequests();
}

/**
 * @param  array<string, mixed>  $event
 */
function postWalletEvent(array $event)
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_main');

    return test()->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

// ── Refunding from the dashboard ───────────────────────────────────────────

test('a refund gives the buyer everything except the platform fee and takes the share back out of the wallet', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball);

    app(PondCheckout::class)->refund($purchase);

    expect($purchase->fresh())
        ->status->toBe(PurchaseStatus::REFUNDED)
        ->refunded_cents->toBe(4500)
        ->refunded_at->not->toBeNull()
        ->payout_status->toBe(PayoutStatus::REVERSED);

    expect(WalletEntry::where('type', WalletEntry::REFUND_REVERSAL)->sole())
        ->user_id->toBe($this->friend->id)
        ->amount_cents->toBe(-4325)
        ->purchase_id->toBe($purchase->id);

    expect(app(Wallet::class)->summary($this->friend))->total_cents->toBe(0)->earned_cents->toBe(0);
});

test('a refund of a toss that was never credited just cancels the credit', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['payout_status' => PayoutStatus::PENDING, 'net_cents' => null, 'processing_fee_cents' => null]);

    app(PondCheckout::class)->refund($purchase);

    expect($purchase->fresh())
        ->status->toBe(PurchaseStatus::REFUNDED)
        ->refunded_cents->toBe(4500)
        ->payout_status->toBe(PayoutStatus::NONE)
        ->and(WalletEntry::count())->toBe(0);
});

test('a ball tossed into the Pond has no fee, so it is refunded in full', function () {
    $purchase = creditedToss($this->giver, null, $this->ball);

    app(PondCheckout::class)->refund($purchase);

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->refunded_cents->toBe(5000)->payout_status->toBe(PayoutStatus::NONE);
});

test('refunding twice takes the share back once', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball);

    app(PondCheckout::class)->refund($purchase);

    expect(fn () => app(PondCheckout::class)->refund($purchase->fresh()))->toThrow(PaymentGatewayException::class, 'Only a paid purchase can be refunded.');
    expect(WalletEntry::where('type', WalletEntry::REFUND_REVERSAL)->count())->toBe(1);
});

test('a refund after the member withdrew the money leaves their wallet below zero, and later earnings cover it', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball);
    app(Wallet::class)->adjust($this->friend, -4325, 'Paid out by hand', $this->admin);

    app(PondCheckout::class)->refund($purchase);

    expect(app(Wallet::class)->summary($this->friend))->total_cents->toBe(-4325)->withdrawable_cents->toBe(0);

    $next = creditedToss($this->giver, $this->friend, $this->ball);

    expect($next->payout_status)->toBe(PayoutStatus::CREDITED)
        ->and(app(Wallet::class)->summary($this->friend))->total_cents->toBe(0);
});

test('an admin refund through the dashboard works and the toss no longer counts', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball);

    $this->actingAs($this->admin)->post(route('admin.payments.refund', $purchase))->assertRedirect();

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::REFUNDED)
        ->and($this->friend->receivedPurchases()->where('status', PurchaseStatus::PAID)->count())->toBe(0);
});

test('the history row says what a refund would give back', function () {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball);

    expect($purchase->toHistoryRow())->refund_cents->toBe(4500)->refunded_cents->toBeNull();
});

// ── With Stripe ────────────────────────────────────────────────────────────

test('Stripe refunds the buyer less the fee, and nothing has to be taken back from Stripe', function () {
    useStripeForWalletRefunds();
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    app(PondCheckout::class)->refund($purchase);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request['payment_intent'] === 'pi_1'
        && $request['amount'] === 4500
        && $request->hasHeader('Idempotency-Key', "refund-{$purchase->reference}"));

    Http::assertSentCount(1);
    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->payout_status->toBe(PayoutStatus::REVERSED);
});

test('a toss with no fee is refunded without naming an amount, which means in full', function () {
    useStripeForWalletRefunds();
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);
    $purchase = creditedToss($this->giver, null, $this->ball, ['gateway' => 'stripe']);

    app(PondCheckout::class)->refund($purchase);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/refunds' && ! isset($request['amount']));
});

test('a buyer who paid in euros is refunded the same share in euros', function () {
    useStripeForWalletRefunds();
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe', 'presentment_currency' => 'eur', 'presentment_amount_cents' => 4580]);

    app(PondCheckout::class)->refund($purchase);

    // 90% of the €45.80 paid: everything except the 10% platform fee.
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/refunds' && $request['amount'] === 4122);

    expect($purchase->fresh())->refunded_cents->toBe(4500);
});

test('if the buyer refund fails after the wallet was debited, trying again only repeats the refund', function () {
    useStripeForWalletRefunds();
    Http::fake(['api.stripe.com/v1/refunds' => Http::sequence()
        ->push(['error' => ['message' => 'nope']], 400)
        ->push(['error' => ['message' => 'nope']], 400) // the gateway tries a failed request twice
        ->push(['id' => 're_1'])]);
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    expect(fn () => app(PondCheckout::class)->refund($purchase))->toThrow(PaymentGatewayException::class);

    expect($purchase->fresh())->status->toBe(PurchaseStatus::PAID)->payout_status->toBe(PayoutStatus::REVERSED);

    app(PondCheckout::class)->refund($purchase->fresh());

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)
        ->and(WalletEntry::where('type', WalletEntry::REFUND_REVERSAL)->count())->toBe(1);
});

// ── A refund made in the Stripe dashboard ──────────────────────────────────

test('a full refund made in Stripe also takes the share out of the wallet', function () {
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    postWalletEvent(['type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_1', 'refunded' => true, 'amount_refunded' => 5000]]])->assertNoContent();

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->refunded_cents->toBe(5000)->payout_status->toBe(PayoutStatus::REVERSED)
        ->and(app(Wallet::class)->summary($this->friend)['total_cents'])->toBe(0);
});

test('the same refund event arriving again changes nothing', function () {
    useStripeForWalletRefunds();
    creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    $event = ['type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_1', 'refunded' => true, 'amount_refunded' => 5000]]];
    postWalletEvent($event)->assertNoContent();
    postWalletEvent($event)->assertNoContent();

    expect(WalletEntry::where('type', WalletEntry::REFUND_REVERSAL)->count())->toBe(1);
});

test('a partial refund made in Stripe changes nothing', function () {
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    postWalletEvent(['type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_1', 'refunded' => false, 'amount_refunded' => 1000]]])->assertNoContent();

    expect($purchase->fresh())->status->toBe(PurchaseStatus::PAID)->payout_status->toBe(PayoutStatus::CREDITED);
});

test('a refund in euros made in Stripe is recorded in dollars', function () {
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe', 'presentment_currency' => 'eur', 'presentment_amount_cents' => 4580]);

    postWalletEvent(['type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_1', 'refunded' => true, 'amount_refunded' => 4580]]])->assertNoContent();

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->refunded_cents->toBe(5000);
});

// ── Disputes ───────────────────────────────────────────────────────────────

test('a dispute is recorded and blocks a refund from the dashboard', function () {
    useStripeForWalletRefunds();
    Log::spy();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    postWalletEvent(['type' => 'charge.dispute.created', 'data' => ['object' => ['id' => 'dp_1', 'payment_intent' => 'pi_1', 'status' => 'needs_response', 'reason' => 'fraudulent', 'amount' => 5000]]])->assertNoContent();

    expect($purchase->fresh())
        ->dispute_id->toBe('dp_1')
        ->dispute_status->toBe('needs_response')
        ->disputed_at->not->toBeNull()
        ->hasOpenDispute()->toBeTrue()
        ->status->toBe(PurchaseStatus::PAID);

    Log::shouldHaveReceived('critical')->once();

    expect(fn () => app(PondCheckout::class)->refund($purchase->fresh()))->toThrow(PaymentGatewayException::class, 'disputing');
});

test('winning a dispute lets a held share be credited', function () {
    Queue::fake();
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe', 'payout_status' => PayoutStatus::PENDING, 'net_cents' => null, 'processing_fee_cents' => null]);

    postWalletEvent(['type' => 'charge.dispute.created', 'data' => ['object' => ['id' => 'dp_2', 'payment_intent' => 'pi_1', 'status' => 'needs_response', 'amount' => 5000]]]);
    postWalletEvent(['type' => 'charge.dispute.closed', 'data' => ['object' => ['id' => 'dp_2', 'payment_intent' => 'pi_1', 'status' => 'won', 'amount' => 5000]]])->assertNoContent();

    expect($purchase->fresh())->dispute_status->toBe('won')->hasOpenDispute()->toBeFalse();
    Queue::assertPushed(CreditWallet::class, fn (CreditWallet $job) => $job->purchaseId === $purchase->id);
});

test('a lost dispute takes the share out of the wallet and the toss counts as refunded', function () {
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    postWalletEvent(['type' => 'charge.dispute.created', 'data' => ['object' => ['id' => 'dp_3', 'payment_intent' => 'pi_1', 'status' => 'needs_response', 'amount' => 5000]]]);
    postWalletEvent(['type' => 'charge.dispute.closed', 'data' => ['object' => ['id' => 'dp_3', 'payment_intent' => 'pi_1', 'status' => 'lost', 'amount' => 5000]]])->assertNoContent();

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->refunded_cents->toBe(5000)->dispute_status->toBe('lost')->payout_status->toBe(PayoutStatus::REVERSED)
        ->and(app(Wallet::class)->summary($this->friend)['total_cents'])->toBe(0);
});

test('disputes for payments we do not know are ignored', function () {
    useStripeForWalletRefunds();
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['gateway' => 'stripe']);

    postWalletEvent(['type' => 'charge.dispute.created', 'data' => ['object' => ['id' => 'dp_9', 'payment_intent' => 'pi_unknown', 'status' => 'needs_response']]])->assertNoContent();

    expect($purchase->fresh())->dispute_id->toBeNull();
});

test('only disputes that are not settled count as open', function (?string $id, ?string $status, bool $open) {
    $purchase = creditedToss($this->giver, $this->friend, $this->ball, ['dispute_id' => $id, 'dispute_status' => $status]);

    expect($purchase->hasOpenDispute())->toBe($open);
})->with([
    'none' => [null, null, false],
    'needs a response' => ['dp_1', 'needs_response', true],
    'under review' => ['dp_1', 'under_review', true],
    'won' => ['dp_1', 'won', false],
    'lost' => ['dp_1', 'lost', false],
]);

test('amounts Stripe reports in the buyer\'s currency are turned back into dollars', function (int $buyerCents, int $dollarCents) {
    $purchase = creditedToss($this->giver, null, $this->ball, ['presentment_currency' => 'eur', 'presentment_amount_cents' => 4580]);

    expect($purchase->toPricedCents($buyerCents))->toBe($dollarCents)
        ->and($purchase->toBuyerCents($dollarCents))->toBeBetween($buyerCents - 1, $buyerCents + 1);
})->with([
    'all of it' => [4580, 5000],
    'half' => [2290, 2500],
    'ninety percent' => [4122, 4500],
]);

test('a payment in dollars needs no conversion', function () {
    $purchase = creditedToss($this->giver, null, $this->ball, ['presentment_currency' => 'usd', 'presentment_amount_cents' => 5000]);

    expect($purchase->paidInOtherCurrency())->toBeFalse()
        ->and($purchase->toPricedCents(1234))->toBe(1234)
        ->and($purchase->toBuyerCents(1234))->toBe(1234)
        ->and($purchase->toHistoryRow())->presentment_currency->toBeNull();
});
