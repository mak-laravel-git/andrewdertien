<?php

use App\Enums\RoleEnum;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalProcessed;
use App\Support\Wallet\Wallet;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['payments.driver' => 'sandbox', 'payments.currency' => 'usd']);

    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->member = User::factory()->adult()->create(['name' => 'Jamie Pond', 'username' => 'jamie']);
    $this->member->assignRole(RoleEnum::USER->value);

    // $100.00 in the wallet, ready to withdraw.
    app(Wallet::class)->adjust($this->member, 10000, 'Starting balance', $this->admin);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function withdrawalInput(array $overrides = []): array
{
    return ['amount' => '40', 'method' => 'paypal', 'details' => 'jamie@example.com', 'note' => null, ...$overrides];
}

// ── The wallet page ────────────────────────────────────────────────────────

test('guests are sent to log in', function () {
    $this->get(route('member.wallet.index'))->assertRedirect(route('login'));
    $this->post(route('member.wallet.withdraw'), withdrawalInput())->assertRedirect(route('login'));
});

test('the wallet page shows the balances, the activity and the rules', function () {
    $this->actingAs($this->member)
        ->get(route('member.wallet.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/member/wallet')
            ->where('wallet.total_cents', 10000)
            ->where('wallet.withdrawable_cents', 10000)
            ->where('wallet.pending_cents', 0)
            ->has('entries', 1)
            ->where('entries.0.description', 'Starting balance')
            ->where('rules.minimum_withdrawal_cents', 1000)
            ->where('rules.can_withdraw', true)
            ->where('rules.needs_birth_date', false)
            ->has('methods', 3)
        );
});

test('an admin can reach the wallet page too, as a member would', function () {
    $this->actingAs($this->admin)->get(route('member.wallet.index'))->assertForbidden();
});

// ── Asking to be paid ──────────────────────────────────────────────────────

test('a member can ask to withdraw, and the money is set aside at once', function () {
    $this->actingAs($this->member)
        ->post(route('member.wallet.withdraw'), withdrawalInput(['note' => 'Thank you!']))
        ->assertRedirect(route('member.wallet.index'))
        ->assertSessionHasNoErrors();

    $withdrawal = WithdrawalRequest::sole();

    expect($withdrawal)
        ->user_id->toBe($this->member->id)
        ->amount_cents->toBe(4000)
        ->method->toBe('paypal')
        ->details->toBe('jamie@example.com')
        ->note->toBe('Thank you!')
        ->status->toBe(WithdrawalRequest::PENDING)
        ->reference->toStartWith('WD-');

    expect(WalletEntry::where('type', WalletEntry::WITHDRAWAL_HOLD)->sole())
        ->amount_cents->toBe(-4000)
        ->withdrawal_request_id->toBe($withdrawal->id);

    expect(app(Wallet::class)->summary($this->member))
        ->total_cents->toBe(6000)
        ->withdrawable_cents->toBe(6000)
        ->requested_cents->toBe(4000);
});

test('the payout details are stored encrypted and never sent back to the member', function () {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput(['details' => 'IBAN GB00 1234']));

    expect(DB::table('withdrawal_requests')->value('details'))->not->toContain('IBAN GB00 1234');

    $this->actingAs($this->member)
        ->get(route('member.wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->has('withdrawals', 1)->missing('withdrawals.0.details'));
});

test('a member cannot withdraw more than they have, or less than the smallest withdrawal', function (string $amount, string $message) {
    $this->actingAs($this->member)
        ->from(route('member.wallet.index'))
        ->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => $amount]))
        ->assertSessionHasErrors(['amount' => $message]);

    expect(WithdrawalRequest::count())->toBe(0);
})->with([
    'more than the wallet' => ['100.01', 'You can withdraw up to $100.00 right now.'],
    'under the minimum' => ['9.99', 'The smallest withdrawal is $10.00.'],
]);

test('the smallest withdrawal is whatever the admin set', function () {
    PaymentSetting::current()->update(['minimum_withdrawal_cents' => 5000]);

    $this->actingAs($this->member)->from(route('member.wallet.index'))->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '40']))
        ->assertSessionHasErrors(['amount' => 'The smallest withdrawal is $50.00.']);

    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '50']))->assertSessionHasNoErrors();

    expect(WithdrawalRequest::sole()->amount_cents)->toBe(5000);
});

test('the same money cannot be asked for twice', function () {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '70']))->assertSessionHasNoErrors();

    $this->actingAs($this->member)
        ->from(route('member.wallet.index'))
        ->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '40']))
        ->assertSessionHasErrors(['amount' => 'You can withdraw up to $30.00 right now.']);

    expect(WithdrawalRequest::count())->toBe(1);
});

test('money still on hold cannot be withdrawn yet', function () {
    PaymentSetting::current()->update(['wallet_hold_days' => 5]);
    $newcomer = User::factory()->adult()->create();
    $newcomer->assignRole(RoleEnum::USER->value);

    // An earning that becomes available in five days.
    WalletEntry::factory()->create(['user_id' => $newcomer->id, 'type' => WalletEntry::TOSS_RECEIVED, 'amount_cents' => 5000, 'available_at' => now()->addDays(5)]);

    $this->actingAs($newcomer)->get(route('member.wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->where('wallet.total_cents', 5000)->where('wallet.withdrawable_cents', 0)->where('wallet.pending_cents', 5000));

    $this->actingAs($newcomer)->from(route('member.wallet.index'))->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '20']))
        ->assertSessionHasErrors('amount');

    $this->travel(6)->days();

    $this->actingAs($newcomer)->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '20']))->assertSessionHasNoErrors();
});

test('the method and where to send the money are needed', function (array $input, string $field) {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput($input))->assertSessionHasErrors($field);

    expect(WithdrawalRequest::count())->toBe(0);
})->with([
    'no amount' => [['amount' => ''], 'amount'],
    'not a number' => [['amount' => 'lots'], 'amount'],
    'no method' => [['method' => ''], 'method'],
    'an unknown method' => [['method' => 'cash'], 'method'],
    'no details' => [['details' => ''], 'details'],
]);

test('only an adult can withdraw, and a member without a date of birth is told to add one', function () {
    $noBirthDate = User::factory()->create(['date_of_birth' => null]);
    $noBirthDate->assignRole(RoleEnum::USER->value);
    app(Wallet::class)->adjust($noBirthDate, 10000, 'Gift', $this->admin);

    $this->actingAs($noBirthDate)->get(route('member.wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rules.can_withdraw', false)->where('rules.needs_birth_date', true));

    $this->actingAs($noBirthDate)->from(route('member.wallet.index'))->post(route('member.wallet.withdraw'), withdrawalInput())
        ->assertSessionHasErrors('amount');

    $minor = User::factory()->create(['date_of_birth' => now()->subYears(16)->toDateString()]);
    $minor->assignRole(RoleEnum::USER->value);
    app(Wallet::class)->adjust($minor, 10000, 'Gift', $this->admin);

    $this->actingAs($minor)->get(route('member.wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rules.can_withdraw', false)->where('rules.needs_birth_date', false));

    $this->actingAs($minor)->from(route('member.wallet.index'))->post(route('member.wallet.withdraw'), withdrawalInput())->assertSessionHasErrors('amount');

    expect(WithdrawalRequest::count())->toBe(0);
});

// ── Taking a request back ──────────────────────────────────────────────────

test('a member can cancel a request that has not been paid, and the money returns', function () {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput());
    $withdrawal = WithdrawalRequest::sole();

    $this->actingAs($this->member)->post(route('member.wallet.withdrawals.cancel', $withdrawal->reference))->assertRedirect(route('member.wallet.index'));

    expect($withdrawal->fresh())->status->toBe(WithdrawalRequest::CANCELLED)->processed_at->not->toBeNull();
    expect(WalletEntry::where('type', WalletEntry::WITHDRAWAL_RELEASED)->sole())->amount_cents->toBe(4000);
    expect(app(Wallet::class)->summary($this->member))->total_cents->toBe(10000)->requested_cents->toBe(0);
});

test('a request that was already dealt with cannot be cancelled, and nobody else can cancel it', function () {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput());
    $withdrawal = WithdrawalRequest::sole();

    $other = User::factory()->adult()->create();
    $other->assignRole(RoleEnum::USER->value);

    $this->actingAs($other)->post(route('member.wallet.withdrawals.cancel', $withdrawal->reference))->assertNotFound();

    app(Wallet::class)->markPaid($withdrawal, $this->admin, 'TXN-1');

    $this->actingAs($this->member)->post(route('member.wallet.withdrawals.cancel', $withdrawal->reference))->assertRedirect();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalRequest::PAID)
        ->and(WalletEntry::where('type', WalletEntry::WITHDRAWAL_RELEASED)->count())->toBe(0);
});

// ── The member is told what the admin decided ──────────────────────────────

test('the member is told, in the bell and by email, when a withdrawal is paid', function () {
    Notification::fake();
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput());
    $withdrawal = WithdrawalRequest::sole();

    app(Wallet::class)->markPaid($withdrawal, $this->admin, 'TXN-99', 'Sent by PayPal');

    Notification::assertSentTo($this->member, WithdrawalProcessed::class, fn (WithdrawalProcessed $notification, array $channels) => $channels === ['database', 'mail']
        && $notification->withdrawal->status === WithdrawalRequest::PAID);
});

test('the notification says what was paid, or why it was not', function () {
    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput());
    $paid = WithdrawalRequest::sole();
    app(Wallet::class)->markPaid($paid, $this->admin, 'TXN-99');

    $this->actingAs($this->member)->post(route('member.wallet.withdraw'), withdrawalInput(['amount' => '20']));
    $rejected = WithdrawalRequest::where('amount_cents', 2000)->sole();
    app(Wallet::class)->reject($rejected, $this->admin, 'The PayPal address bounced');

    $messages = $this->member->notifications()->latest('created_at')->get()->pluck('data.message')->all();

    expect($messages)->toContain('Your withdrawal of $40.00 was paid.')
        ->toContain('Your withdrawal of $20.00 was not paid: The PayPal address bounced');

    $mail = (new WithdrawalProcessed($paid->fresh()))->toMail($this->member);
    expect($mail->subject)->toBe('Your withdrawal of $40.00 was paid')->and(implode(' ', $mail->introLines))->toContain('TXN-99');

    $rejectedMail = (new WithdrawalProcessed($rejected->fresh()))->toMail($this->member);
    expect(implode(' ', $rejectedMail->introLines))->toContain('The PayPal address bounced')->toContain('back in your wallet');
});

// ── Deleting an account that has money in it ───────────────────────────────

test('an account with money in its wallet cannot be deleted', function () {
    $this->actingAs($this->member)
        ->from(route('member-account.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect(User::find($this->member->id))->not->toBeNull();
});

test('an account with a withdrawal waiting cannot be deleted either', function () {
    app(Wallet::class)->adjust($this->member, -6000, 'Paid out', $this->admin);
    $this->member->withdrawalRequests()->create(['amount_cents' => 4000, 'currency' => 'usd', 'method' => 'paypal', 'details' => 'x@example.com']);
    // Everything is set aside, so the balance is zero but a request is waiting.
    app(Wallet::class)->adjust($this->member, -4000, 'Set aside', $this->admin);

    $this->actingAs($this->member)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');

    expect(User::find($this->member->id))->not->toBeNull();
});

test('an account with an empty wallet can be deleted', function () {
    app(Wallet::class)->adjust($this->member, -10000, 'Paid out', $this->admin);

    $this->actingAs($this->member)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(User::find($this->member->id))->toBeNull();
});
