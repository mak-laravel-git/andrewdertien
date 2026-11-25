<?php

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RoleEnum;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Notifications\BallReceived;
use App\Support\Payments\PondCheckout;
use App\Support\Wallet\Wallet;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['payments.driver' => 'sandbox', 'payments.currency' => 'usd']);

    $this->giver = User::factory()->create(['name' => 'Alex Giver']);
    $this->giver->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->adult()->create(['name' => 'Jamie Pond', 'username' => 'jamie', 'dreams' => 'A piece of land.']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->ball = Pond::factory()->create(['name' => 'Dream Ball', 'price_cents' => 5000]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function reportedToss(User $giver, ?User $recipient, Pond $ball, array $attributes = []): PondPurchase
{
    return PondPurchase::factory()->create([
        'user_id' => $giver->id,
        'recipient_id' => $recipient?->id,
        'recipient_name' => $recipient?->name,
        'pond_id' => $ball->id,
        'pond_name' => $ball->name,
        'amount_cents' => 5000,
        'status' => PurchaseStatus::PAID,
        'paid_at' => now(),
        'gateway' => 'sandbox',
        ...$attributes,
    ]);
}

// ── Telling the member ─────────────────────────────────────────────────────

test('a member is told, in the bell and by email, when a ball is tossed to them', function () {
    Notification::fake();
    $purchase = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null, 'fee_cents' => 500, 'fee_percent' => 10]);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    Notification::assertSentTo($this->friend, BallReceived::class, fn (BallReceived $notification, array $channels) => $channels === ['database', 'mail'] && $notification->purchase->is($purchase));
    Notification::assertSentToTimes($this->friend, BallReceived::class, 1);
});

test('the bell message names who tossed what, and the email says it is in the wallet', function () {
    $purchase = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null, 'fee_cents' => 500, 'fee_percent' => 10]);

    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    expect($this->friend->notifications()->sole()->data)
        ->message->toBe('Alex Giver tossed you a Dream Ball worth $50.00.')
        ->url->toBe('/dashboard/my-ponds');

    $mail = (new BallReceived($purchase->fresh()))->toMail($this->friend);

    expect($mail->subject)->toBe('Alex Giver tossed you a ball!')
        ->and(implode(' ', $mail->introLines))->toContain('worth $50.00')->toContain('$43.25 has been added to your wallet');
});

test('nobody is told about a ball tossed into the Pond, and paying twice tells the member once', function () {
    Notification::fake();

    $pond = reportedToss($this->giver, null, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null]);
    app(PondCheckout::class)->markPaid($pond, 'pay_1');

    $toss = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null]);
    app(PondCheckout::class)->markPaid($toss, 'pay_2');
    app(PondCheckout::class)->markPaid($toss, 'pay_2');

    Notification::assertSentToTimes($this->friend, BallReceived::class, 1);
    Notification::assertCount(1);
});

test('the bell shows the latest notifications and can mark them all read', function () {
    $purchase = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null]);
    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    $this->actingAs($this->friend)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1)->where('notifications.items.0.read', false));

    $this->actingAs($this->friend)->post(route('notifications.read'))->assertRedirect();

    $this->actingAs($this->friend)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0)->where('notifications.items.0.read', true));
});

test('marking the bell read only touches the member\'s own notifications', function () {
    $purchase = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null]);
    app(PondCheckout::class)->markPaid($purchase, 'pay_1');

    $this->actingAs($this->giver)->post(route('notifications.read'))->assertRedirect();

    expect($this->friend->unreadNotifications()->count())->toBe(1);

    auth()->logout();
    $this->post(route('notifications.read'))->assertRedirect(route('login'));
});

test('the dashboard shows the wallet and how many new balls there are until the member looks at them', function () {
    foreach (range(1, 2) as $i) {
        $purchase = reportedToss($this->giver, $this->friend, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null, 'fee_cents' => 500, 'fee_percent' => 10]);
        app(PondCheckout::class)->markPaid($purchase, "pay_{$i}");
    }

    $this->actingAs($this->friend)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('newBalls', 2)->where('wallet.total_cents', 8650)->where('wallet.withdrawable_cents', 8650));

    $this->actingAs($this->friend)->get(route('member.ponds.owned'))->assertOk();

    $this->actingAs($this->friend)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('newBalls', 0)->where('notifications.unread', 0));
});

test('collected balls show what the member received and never the giver\'s email', function () {
    reportedToss($this->giver, $this->friend, $this->ball, ['fee_cents' => 500, 'processing_fee_cents' => 175, 'net_cents' => 4325, 'payout_status' => PayoutStatus::CREDITED]);

    $this->actingAs($this->friend)->get(route('member.ponds.owned'))
        ->assertInertia(fn (Assert $page) => $page->has('collected', 1)->where('collected.0.net_cents', 4325)->where('collected.0.buyer_name', 'Alex Giver')->missing('collected.0.buyer_email'));
});

// ── Who can be tossed a ball ───────────────────────────────────────────────

test('anyone can be tossed a ball without setting anything up first', function () {
    $newcomer = User::factory()->create(['username' => 'newbie', 'date_of_birth' => null]);

    foreach ([$this->friend, $newcomer] as $recipient) {
        $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 20, 'recipient_id' => $recipient->id])->assertSessionHasNoErrors()->assertRedirect();
    }

    expect(PondPurchase::count())->toBe(2);
});

test('a member under 18 cannot be tossed a ball', function () {
    $minor = User::factory()->create(['username' => 'kid', 'date_of_birth' => now()->subYears(16)->toDateString()]);

    $this->actingAs($this->giver)->from(route('member.ponds.index'))
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => 20, 'recipient_id' => $minor->id])
        ->assertSessionHasErrors(['recipient_id' => 'A ball cannot be tossed to '.$minor->name.' right now.']);

    expect(PondPurchase::count())->toBe(0);
});

test('screens are told who can be tossed a ball', function () {
    User::factory()->create(['username' => 'kid', 'date_of_birth' => now()->subYears(16)->toDateString()]);

    $this->actingAs($this->giver)->get(route('member.ponds.index'))
        ->assertInertia(fn (Assert $page) => $page->where('members', fn ($members) => collect($members)->pluck('can_receive', 'username')->only(['jamie', 'kid'])->sortKeys()->all() === ['jamie' => true, 'kid' => false]));

    $this->actingAs($this->giver)->get(route('members.show', 'kid'))
        ->assertInertia(fn (Assert $page) => $page->where('recipient.can_receive', false));
});

// ── The admin's view ───────────────────────────────────────────────────────

/**
 * One of each kind of toss, so the totals can be checked by hand.
 *
 * @return array<string, PondPurchase>
 */
function oneOfEachToss(): array
{
    $giver = test()->giver;
    $friend = test()->friend;
    $ball = test()->ball;
    $wallet = app(Wallet::class);

    $sent = reportedToss($giver, $friend, $ball, ['fee_cents' => 500, 'processing_fee_cents' => 175, 'net_cents' => 4325, 'payout_status' => PayoutStatus::CREDITED]);
    $wallet->creditPurchase($sent, 4325);

    $disputed = reportedToss($giver, $friend, $ball, ['amount_cents' => 1000, 'fee_cents' => 100, 'processing_fee_cents' => 50, 'net_cents' => 800, 'payout_status' => PayoutStatus::CREDITED, 'dispute_id' => 'dp_1', 'dispute_status' => 'needs_response']);
    $wallet->creditPurchase($disputed, 800);

    $refunded = reportedToss($giver, $friend, $ball, ['status' => PurchaseStatus::REFUNDED, 'refunded_at' => now(), 'refunded_cents' => 4500, 'fee_cents' => 500, 'payout_status' => PayoutStatus::REVERSED]);
    $wallet->creditPurchase($refunded, 4325);
    $wallet->reversePurchase($refunded);

    return [
        'sent' => $sent,
        'waiting' => reportedToss($giver, $friend, $ball, ['amount_cents' => 3000, 'fee_cents' => 300, 'payout_status' => PayoutStatus::PENDING]),
        'failed' => reportedToss($giver, $friend, $ball, ['amount_cents' => 2000, 'fee_cents' => 200, 'payout_status' => PayoutStatus::FAILED, 'payout_failure_message' => 'Stripe has not finished settling this payment yet.']),
        'refunded' => $refunded,
        'pond' => reportedToss($giver, null, $ball, ['amount_cents' => 2000, 'payout_status' => PayoutStatus::NONE]),
        'disputed' => $disputed,
        'unpaid' => reportedToss($giver, $friend, $ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null, 'fee_cents' => 999]),
    ];
}

test('the admin overview shows where the money went', function () {
    oneOfEachToss();
    $wallet = app(Wallet::class);
    $wallet->requestWithdrawal($this->friend, 2000, 'paypal', 'jamie@example.com');
    $paid = $wallet->requestWithdrawal($this->friend, 1500, 'paypal', 'jamie@example.com');
    $wallet->markPaid($paid, $this->admin, 'TXN-1');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('money.fees_cents', 1600)                 // 500 + 300 + 200 + 500 (kept on the refund) + 100
            ->where('money.pond_cents', 2000)
            ->where('money.owed_cents', 1625)                 // 4325 + 800 in the wallet, less 2000 set aside and 1500 paid out
            ->where('money.withdrawals_waiting_cents', 2000)
            ->where('money.withdrawals_waiting_count', 1)
            ->where('money.withdrawn_cents', 1500)
            ->where('money.waiting_cents', 4500)              // (3000 - 300) + (2000 - 200) not credited yet
            ->where('money.waiting_count', 2)
            ->where('money.processing_cents', 225)
            ->where('money.failed_payouts', 1)
            ->where('money.open_disputes', 1)
            ->where('money.negative_wallets', 0)
        );
});

test('the admin overview lists what needs attention, failed credits and open disputes', function () {
    $tosses = oneOfEachToss();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 2)
            ->where('attention', fn ($rows) => collect($rows)->pluck('reference')->sort()->values()->all() === collect([$tosses['failed']->reference, $tosses['disputed']->reference])->sort()->values()->all())
            ->where('attention', fn ($rows) => collect($rows)->firstWhere('reference', $tosses['failed']->reference)['payout_failure_message'] === 'Stripe has not finished settling this payment yet.')
        );
});

test('nothing needs attention when everything is fine', function () {
    reportedToss($this->giver, $this->friend, $this->ball, ['fee_cents' => 500, 'net_cents' => 4325, 'payout_status' => PayoutStatus::CREDITED]);
    reportedToss($this->giver, $this->friend, $this->ball, ['dispute_id' => 'dp_9', 'dispute_status' => 'won', 'payout_status' => PayoutStatus::CREDITED]);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('attention', 0)->where('money.open_disputes', 0));
});

test('a wallet below zero is counted on the overview', function () {
    app(Wallet::class)->adjust($this->friend, -500, 'A refund after a withdrawal', $this->admin);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('money.negative_wallets', 1)->where('money.owed_cents', -500));
});

test('the payments report can be narrowed by what happened to the member share', function (string $payout, array $expected) {
    $tosses = oneOfEachToss();

    $this->actingAs($this->admin)->get(route('admin.payments.index', ['payout' => $payout]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.payout', $payout)
            ->where('payments.data', fn ($rows) => collect($rows)->pluck('reference')->sort()->values()->all() === collect($expected)->map(fn (string $key) => $tosses[$key]->reference)->sort()->values()->all()));
})->with([
    'needs attention' => ['attention', ['failed', 'disputed']],
    'waiting' => ['pending', ['waiting']],
    'failed' => ['failed', ['failed']],
    'in the wallet' => ['credited', ['sent', 'disputed']],
    'taken back' => ['reversed', ['refunded']],
]);

test('the report rows say what each share was worked out as', function () {
    $tosses = oneOfEachToss();

    $this->actingAs($this->admin)->get(route('admin.payments.index', ['search' => $tosses['sent']->reference]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.fee_cents', 500)
            ->where('payments.data.0.processing_fee_cents', 175)
            ->where('payments.data.0.net_cents', 4325)
            ->where('payments.data.0.payout_status', 'credited'));
});

test('the spreadsheet carries the fee, the processing fee, the member share and the wallet status', function () {
    $tosses = oneOfEachToss();

    $csv = $this->actingAs($this->admin)->get(route('admin.payments.export', ['search' => $tosses['sent']->reference]))->streamedContent();

    expect($csv)->toContain('Platform fee', 'Processing fee', 'Member share', 'Payout status', 'Refunded to buyer', 'Dispute')
        ->toContain('50.00,USD,,,5.00,1.75,43.25,credited');

    $refunded = $this->actingAs($this->admin)->get(route('admin.payments.export', ['search' => $tosses['refunded']->reference]))->streamedContent();

    expect($refunded)->toContain('reversed,45.00');
});

test('only an admin sees the money overview and the report', function () {
    $this->actingAs($this->friend)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($this->friend)->get(route('admin.payments.index', ['payout' => 'attention']))->assertForbidden();
});

// ── The whole journey ──────────────────────────────────────────────────────

test('a ball is tossed, the member is paid into their wallet, withdraws, the admin pays, and a later refund leaves the wallet below zero', function () {
    $nia = User::factory()->adult()->create(['name' => 'Nia Newcomer', 'username' => 'nia']);
    $nia->assignRole(RoleEnum::USER->value);

    // Nia has set nothing up, and Alex can toss her a $50 ball right away.
    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50, 'recipient_id' => $nia->id])->assertRedirect();
    $purchase = PondPurchase::sole();
    $this->actingAs($this->giver)->post(route('payments.sandbox.complete', $purchase), ['outcome' => 'pay'])->assertRedirect();

    // The share is in her wallet: $50 less the $5 fee and $1.75 processing.
    expect($purchase->fresh())->payout_status->toBe(PayoutStatus::CREDITED)->fee_cents->toBe(500)->processing_fee_cents->toBe(175)->net_cents->toBe(4325);

    // She is told, and sees the money.
    $this->actingAs($nia)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('newBalls', 1)->where('notifications.unread', 1)->where('wallet.withdrawable_cents', 4325));
    $this->actingAs($nia)->get(route('member.wallet.index'))->assertInertia(fn (Assert $page) => $page->where('wallet.total_cents', 4325)->has('entries', 1));

    // She asks to withdraw $40.
    $this->actingAs($nia)->post(route('member.wallet.withdraw'), ['amount' => '40', 'method' => 'paypal', 'details' => 'nia@example.com'])->assertRedirect(route('member.wallet.index'));
    $withdrawal = WithdrawalRequest::sole();

    expect(app(Wallet::class)->summary($nia))->total_cents->toBe(325)->withdrawable_cents->toBe(325);

    // The admin pays it by hand and marks it paid. She is told.
    $this->actingAs($this->admin)->get(route('admin.withdrawals.index'))->assertInertia(fn (Assert $page) => $page->where('withdrawals.data.0.details', 'nia@example.com'));
    $this->actingAs($this->admin)->post(route('admin.withdrawals.pay', $withdrawal->reference), ['payment_reference' => 'PP-555'])->assertRedirect();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalRequest::PAID);
    expect($nia->notifications()->where('data->type', 'withdrawal_processed')->count())->toBe(1);

    // Later the toss is refunded: Alex gets $45 back, and Nia's wallet goes below zero.
    $this->actingAs($this->admin)->post(route('admin.payments.refund', $purchase))->assertRedirect();

    expect($purchase->fresh())->status->toBe(PurchaseStatus::REFUNDED)->refunded_cents->toBe(4500)->payout_status->toBe(PayoutStatus::REVERSED);
    expect(app(Wallet::class)->summary($nia))->total_cents->toBe(-4000)->withdrawn_cents->toBe(4000);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('money.fees_cents', 500)
        ->where('money.owed_cents', -4000)
        ->where('money.withdrawn_cents', 4000)
        ->where('money.negative_wallets', 1));

    $this->actingAs($this->admin)->get(route('admin.wallets.index', ['owing' => 1]))->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Nia Newcomer'));

    // Her next earnings pay it off first.
    app(PondCheckout::class)->markPaid(reportedToss($this->giver, $nia, $this->ball, ['status' => PurchaseStatus::PENDING, 'paid_at' => null, 'fee_cents' => 500, 'fee_percent' => 10]), 'pay_next');

    expect(app(Wallet::class)->summary($nia)['total_cents'])->toBe(325)
        ->and(WalletEntry::where('user_id', $nia->id)->count())->toBe(4);
});
