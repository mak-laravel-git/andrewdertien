<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Support\Wallet\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->member = User::factory()->adult()->create(['name' => 'Jamie Pond', 'email' => 'jamie@example.com']);
    $this->member->assignRole(RoleEnum::USER->value);

    $this->other = User::factory()->adult()->create(['name' => 'Nia Newcomer']);
    $this->other->assignRole(RoleEnum::USER->value);

    app(Wallet::class)->adjust($this->member, 10000, 'Starting balance', $this->admin);
    app(Wallet::class)->adjust($this->other, 3000, 'Starting balance', $this->admin);

    $this->request = app(Wallet::class)->requestWithdrawal($this->member, 4000, 'bank', "Jamie Pond\nIBAN GB00 TEST 1234", 'Please pay on Friday');
});

// ── Who can see and do what ────────────────────────────────────────────────

test('only an admin reaches the withdrawals, the wallets and the actions on them', function () {
    $this->actingAs($this->member)->get(route('admin.withdrawals.index'))->assertForbidden();
    $this->actingAs($this->member)->get(route('admin.withdrawals.export'))->assertForbidden();
    $this->actingAs($this->member)->post(route('admin.withdrawals.pay', $this->request->reference), ['payment_reference' => 'X'])->assertForbidden();
    $this->actingAs($this->member)->post(route('admin.withdrawals.reject', $this->request->reference), ['reason' => 'X'])->assertForbidden();
    $this->actingAs($this->member)->get(route('admin.wallets.index'))->assertForbidden();
    $this->actingAs($this->member)->get(route('admin.wallets.show', $this->member))->assertForbidden();
    $this->actingAs($this->member)->post(route('admin.wallets.adjust', $this->member), ['amount' => '5', 'reason' => 'X'])->assertForbidden();

    expect($this->request->fresh()->status)->toBe(WithdrawalRequest::PENDING);

    auth()->logout();
    $this->get(route('admin.withdrawals.index'))->assertRedirect(route('login'));
});

// ── The withdrawals list ───────────────────────────────────────────────────

test('the admin sees waiting requests first, with where to pay and the member', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.withdrawals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/admin/withdrawals')
            ->where('filters.status', 'pending')
            ->has('withdrawals.data', 1)
            ->where('withdrawals.data.0.reference', $this->request->reference)
            ->where('withdrawals.data.0.amount_cents', 4000)
            ->where('withdrawals.data.0.method_label', 'Bank transfer')
            ->where('withdrawals.data.0.details', "Jamie Pond\nIBAN GB00 TEST 1234")
            ->where('withdrawals.data.0.note', 'Please pay on Friday')
            ->where('withdrawals.data.0.member.name', 'Jamie Pond')
            ->where('withdrawals.data.0.member.email', 'jamie@example.com')
            ->where('withdrawals.data.0.member.wallet_total_cents', 6000)
            ->where('totals.waiting_count', 1)
            ->where('totals.waiting_cents', 4000)
            ->where('totals.paid_cents', 0)
        );
});

test('requests can be filtered by status and searched by member or reference', function () {
    $done = app(Wallet::class)->requestWithdrawal($this->other, 2000, 'paypal', 'nia@example.com');
    app(Wallet::class)->markPaid($done, $this->admin, 'TXN-7');

    $this->actingAs($this->admin)->get(route('admin.withdrawals.index', ['status' => 'paid']))
        ->assertInertia(fn (Assert $page) => $page->has('withdrawals.data', 1)->where('withdrawals.data.0.reference', $done->reference)->where('totals.paid_cents', 2000));

    $this->actingAs($this->admin)->get(route('admin.withdrawals.index', ['status' => '']))
        ->assertInertia(fn (Assert $page) => $page->has('withdrawals.data', 2));

    $this->actingAs($this->admin)->get(route('admin.withdrawals.index', ['status' => '', 'search' => 'Jamie']))
        ->assertInertia(fn (Assert $page) => $page->has('withdrawals.data', 1)->where('withdrawals.data.0.reference', $this->request->reference));

    $this->actingAs($this->admin)->get(route('admin.withdrawals.index', ['status' => '', 'search' => $done->reference]))
        ->assertInertia(fn (Assert $page) => $page->has('withdrawals.data', 1)->where('withdrawals.data.0.reference', $done->reference));
});

test('an unknown status shows everything rather than failing', function () {
    $this->actingAs($this->admin)->get(route('admin.withdrawals.index', ['status' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', '')->has('withdrawals.data', 1));
});

test('the spreadsheet carries where each request is to be paid, and is safe to open', function () {
    $evil = app(Wallet::class)->requestWithdrawal($this->other, 2000, 'other', '=HYPERLINK("http://evil.test")');

    $csv = $this->actingAs($this->admin)->get(route('admin.withdrawals.export', ['status' => '']))->streamedContent();

    expect($csv)
        ->toContain('Reference', 'Pay to', 'Payment reference')
        ->toContain($this->request->reference)
        ->toContain('40.00,USD,"Bank transfer"')
        ->toContain('IBAN GB00 TEST 1234')
        ->not->toContain(',=HYPERLINK')
        ->toContain("'=HYPERLINK");

    expect($evil->fresh()->status)->toBe(WithdrawalRequest::PENDING);
});

// ── Paying ─────────────────────────────────────────────────────────────────

test('an admin marks a request as paid with a reference, and nothing else changes in the wallet', function () {
    $before = app(Wallet::class)->summary($this->member);

    $this->actingAs($this->admin)
        ->post(route('admin.withdrawals.pay', $this->request->reference), ['payment_reference' => 'WISE-123', 'admin_note' => 'Sent from the business account'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->request->fresh())
        ->status->toBe(WithdrawalRequest::PAID)
        ->payment_reference->toBe('WISE-123')
        ->admin_note->toBe('Sent from the business account')
        ->processed_by->toBe($this->admin->id)
        ->processed_at->not->toBeNull();

    $after = app(Wallet::class)->summary($this->member);

    expect($after['total_cents'])->toBe($before['total_cents'])
        ->and($after['requested_cents'])->toBe(0)
        ->and($after['withdrawn_cents'])->toBe(4000);
});

test('marking a request as paid needs the payment reference', function () {
    $this->actingAs($this->admin)->from(route('admin.withdrawals.index'))
        ->post(route('admin.withdrawals.pay', $this->request->reference), ['payment_reference' => ''])
        ->assertSessionHasErrors('payment_reference');

    expect($this->request->fresh()->status)->toBe(WithdrawalRequest::PENDING);
});

test('a request that was already dealt with cannot be paid or turned down again', function () {
    $this->actingAs($this->admin)->post(route('admin.withdrawals.pay', $this->request->reference), ['payment_reference' => 'WISE-123']);

    $this->actingAs($this->admin)->post(route('admin.withdrawals.pay', $this->request->reference), ['payment_reference' => 'WISE-456'])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.withdrawals.reject', $this->request->reference), ['reason' => 'Changed my mind'])->assertRedirect();

    expect($this->request->fresh())->status->toBe(WithdrawalRequest::PAID)->payment_reference->toBe('WISE-123')
        ->and(WalletEntry::where('type', WalletEntry::WITHDRAWAL_RELEASED)->count())->toBe(0);
});

// ── Turning down ───────────────────────────────────────────────────────────

test('turning a request down needs a reason, and gives the money back to the member', function () {
    $this->actingAs($this->admin)->from(route('admin.withdrawals.index'))
        ->post(route('admin.withdrawals.reject', $this->request->reference), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($this->request->fresh()->status)->toBe(WithdrawalRequest::PENDING);

    $this->actingAs($this->admin)
        ->post(route('admin.withdrawals.reject', $this->request->reference), ['reason' => 'The IBAN is not valid'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->request->fresh())
        ->status->toBe(WithdrawalRequest::REJECTED)
        ->admin_note->toBe('The IBAN is not valid')
        ->processed_by->toBe($this->admin->id);

    expect(WalletEntry::where('type', WalletEntry::WITHDRAWAL_RELEASED)->sole())->amount_cents->toBe(4000)->user_id->toBe($this->member->id);
    expect(app(Wallet::class)->summary($this->member))->total_cents->toBe(10000)->requested_cents->toBe(0)->withdrawn_cents->toBe(0);
});

// ── Wallets ────────────────────────────────────────────────────────────────

test('the admin sees every wallet with its balance, richest first, and can search', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.wallets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/admin/wallets')
            ->has('members.data', 2)
            ->where('members.data.0.name', 'Jamie Pond')
            ->where('members.data.0.total_cents', 6000)
            ->where('members.data.1.name', 'Nia Newcomer')
            ->where('members.data.1.total_cents', 3000)
            ->where('totals.owed_cents', 9000)
            ->where('totals.negative_count', 0)
        );

    $this->actingAs($this->admin)->get(route('admin.wallets.index', ['search' => 'Nia']))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Nia Newcomer'));
});

test('wallets below zero can be found', function () {
    app(Wallet::class)->adjust($this->other, -5000, 'A refund after a withdrawal', $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.wallets.index', ['owing' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.name', 'Nia Newcomer')
            ->where('members.data.0.total_cents', -2000)
            ->where('totals.negative_count', 1)
        );
});

test('one wallet shows its ledger and withdrawals', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.wallets.show', $this->member))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/admin/wallet')
            ->where('member.name', 'Jamie Pond')
            ->where('wallet.total_cents', 6000)
            ->has('entries', 2)
            ->where('entries.0.type', WalletEntry::WITHDRAWAL_HOLD)
            ->where('entries.1.by', 'Ada Admin')
            ->has('withdrawals', 1)
            ->where('withdrawals.0.reference', $this->request->reference)
        );
});

test('an admin can put a balance right by hand, with a reason that is kept', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.wallets.adjust', $this->other), ['amount' => '12.50', 'reason' => 'A bonus for the launch'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('admin.wallets.adjust', $this->other), ['amount' => '-2', 'reason' => 'A fee paid twice'])
        ->assertSessionHasNoErrors();

    $entries = WalletEntry::where('user_id', $this->other->id)->where('type', WalletEntry::ADJUSTMENT)->orderBy('id')->get();

    expect($entries->pluck('amount_cents')->all())->toBe([3000, 1250, -200])
        ->and($entries->last())->description->toBe('A fee paid twice')->created_by->toBe($this->admin->id)
        ->and(app(Wallet::class)->summary($this->other)['total_cents'])->toBe(4050);
});

test('an adjustment needs an amount and a reason', function (array $input, string $field) {
    $this->actingAs($this->admin)->from(route('admin.wallets.show', $this->other))
        ->post(route('admin.wallets.adjust', $this->other), $input)
        ->assertSessionHasErrors($field);

    expect(app(Wallet::class)->summary($this->other)['total_cents'])->toBe(3000);
})->with([
    'no amount' => [['amount' => '', 'reason' => 'Because'], 'amount'],
    'zero' => [['amount' => '0', 'reason' => 'Because'], 'amount'],
    'not a number' => [['amount' => 'lots', 'reason' => 'Because'], 'amount'],
    'too many decimals' => [['amount' => '1.234', 'reason' => 'Because'], 'amount'],
    'no reason' => [['amount' => '5', 'reason' => ''], 'reason'],
]);
