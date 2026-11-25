<?php

use App\Enums\RoleEnum;
use App\Models\PaymentSetting;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Support\Payments\FeeCalculator;
use App\Support\Payments\FeeSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['payments.driver' => 'sandbox', 'payments.currency' => 'usd']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->giver = User::factory()->create();
    $this->giver->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->adult()->create(['username' => 'jamie']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->ball = Pond::factory()->create(['price_cents' => 5000]);
});

// ── The calculation ────────────────────────────────────────────────────────

test('the fee is the percentage, but never below the minimum or above the toss', function (int $amount, float $percent, int $minimum, int $fee) {
    expect(FeeCalculator::platformFeeCents($amount, $percent, $minimum))->toBe($fee);
})->with([
    'ten percent of fifty dollars' => [5000, 10.0, 50, 500],
    'rounds half up' => [105, 10.0, 0, 11],
    'the minimum applies to small tosses' => [200, 10.0, 50, 50],
    'the fee cannot be more than the toss' => [30, 10.0, 50, 30],
    'a zero percent fee with no minimum' => [5000, 0.0, 0, 0],
    'a fractional percent' => [10000, 12.5, 0, 1250],
]);

test('the estimate for the member is what is left after the fee and the processing estimate', function () {
    config(['payments.processing_estimate' => ['percent' => 2.9, 'fixed_cents' => 30]]);

    $breakdown = FeeCalculator::breakdown(5000, new FeeSettings);

    expect($breakdown)->toBe([
        'amount_cents' => 5000,
        'platform_fee_cents' => 500,
        'processing_estimate_cents' => 175,
        'member_estimate_cents' => 4325,
    ]);
});

test('a tiny toss never leaves the member with a negative amount', function () {
    expect(FeeCalculator::breakdown(100, new FeeSettings)['member_estimate_cents'])->toBeGreaterThanOrEqual(0);
});

test('a fresh install already has a ten percent fee', function () {
    $fees = new FeeSettings;

    expect($fees->percent())->toBe(10.0)
        ->and($fees->minimumFeeCents())->toBe(50)
        ->and($fees->minimumTossCents())->toBe(100)
        ->and($fees->minimumWithdrawalCents())->toBe(1000)
        ->and($fees->walletHoldDays())->toBe(0);
});

// ── What a toss records ────────────────────────────────────────────────────

test('a toss for a member records the fee that applied', function () {
    $this->actingAs($this->giver)
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50, 'recipient_id' => $this->friend->id])
        ->assertRedirect();

    expect(PondPurchase::sole())
        ->amount_cents->toBe(5000)
        ->fee_cents->toBe(500)
        ->fee_percent->toBe('10.00');
});

test('a ball tossed into the Pond with no member carries no fee because it all goes to the platform', function () {
    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50])->assertRedirect();

    expect(PondPurchase::sole())->fee_cents->toBe(0)->fee_percent->toBeNull();
});

test('changing the fee later does not change what was already recorded', function () {
    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50, 'recipient_id' => $this->friend->id]);

    PaymentSetting::current()->update(['platform_fee_percent' => 25]);

    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => 50, 'recipient_id' => $this->friend->id]);

    expect(PondPurchase::orderBy('id')->pluck('fee_cents')->all())->toBe([500, 1250]);
});

// ── Admin settings ─────────────────────────────────────────────────────────

test('the admin sees the current fees and limits', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.stripe.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fees.platform_fee_percent', 10)
            ->where('fees.minimum_fee', 0.5)
            ->where('fees.minimum_toss', 1)
            ->where('fees.minimum_withdrawal', 10)
            ->where('fees.wallet_hold_days', 0)
            ->where('fees.adaptive_pricing', true)
            ->where('fees.maximum_percent', 50)
        );
});

test('an admin can set the fee and limits from the dashboard', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.stripe.fees.update'), [
            'platform_fee_percent' => '12.5',
            'minimum_fee' => '0.75',
            'minimum_toss' => '5',
            'minimum_withdrawal' => '25',
            'wallet_hold_days' => '7',
            'adaptive_pricing' => '0',
        ])
        ->assertRedirect(route('admin.stripe.edit'))
        ->assertSessionHasNoErrors();

    expect(PaymentSetting::current())
        ->platform_fee_percent->toBe('12.50')
        ->minimum_fee_cents->toBe(75)
        ->minimum_toss_cents->toBe(500)
        ->minimum_withdrawal_cents->toBe(2500)
        ->wallet_hold_days->toBe(7)
        ->adaptive_pricing->toBeFalse();

    $fees = new FeeSettings;
    expect($fees->percent())->toBe(12.5)->and($fees->minimumTossCents())->toBe(500)->and($fees->minimumWithdrawalCents())->toBe(2500)->and($fees->walletHoldDays())->toBe(7);
});

test('the fee settings are checked', function (array $input, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.stripe.fees.update'), [
            'platform_fee_percent' => '10',
            'minimum_fee' => '0.50',
            'minimum_toss' => '1',
            'minimum_withdrawal' => '10',
            'wallet_hold_days' => '0',
            'adaptive_pricing' => '1',
            ...$input,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'a fee above the limit' => [['platform_fee_percent' => '51'], 'platform_fee_percent'],
    'a negative fee' => [['platform_fee_percent' => '-1'], 'platform_fee_percent'],
    'too many decimals' => [['platform_fee_percent' => '10.123'], 'platform_fee_percent'],
    'not a number' => [['platform_fee_percent' => 'lots'], 'platform_fee_percent'],
    'a negative minimum fee' => [['minimum_fee' => '-1'], 'minimum_fee'],
    'a smallest toss under a dollar' => [['minimum_toss' => '0.50'], 'minimum_toss'],
    'a smallest withdrawal under a dollar' => [['minimum_withdrawal' => '0.50'], 'minimum_withdrawal'],
    'a negative hold' => [['wallet_hold_days' => '-1'], 'wallet_hold_days'],
    'a hold that is too long' => [['wallet_hold_days' => '91'], 'wallet_hold_days'],
    'a missing currency toggle' => [['adaptive_pricing' => null], 'adaptive_pricing'],
]);

test('only an admin can change the fees', function () {
    $this->actingAs($this->giver)
        ->put(route('admin.stripe.fees.update'), ['platform_fee_percent' => '5', 'minimum_fee' => '0', 'minimum_toss' => '1', 'minimum_withdrawal' => '10', 'wallet_hold_days' => '0', 'adaptive_pricing' => '1'])
        ->assertForbidden();

    expect(PaymentSetting::query()->count())->toBe(0);
});

// ── The smallest toss ──────────────────────────────────────────────────────

test('the smallest toss an admin allows is enforced', function () {
    PaymentSetting::current()->update(['minimum_toss_cents' => 500]);

    $this->actingAs($this->giver)
        ->from(route('member.ponds.index'))
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => '4.99'])
        ->assertSessionHasErrors(['amount' => 'A ball can be any amount from $5.']);

    expect(PondPurchase::count())->toBe(0);

    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug), ['amount' => '5'])->assertRedirect();

    expect(PondPurchase::sole()->amount_cents)->toBe(500);
});

test('signed in pages are told the fee and the smallest toss so they can explain it', function () {
    PaymentSetting::current()->update(['platform_fee_percent' => 15, 'minimum_toss_cents' => 300]);

    $this->actingAs($this->giver)
        ->get(route('member.ponds.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.fees.percent', 15)
            ->where('payments.fees.minimum_toss_cents', 300)
            ->where('payments.fees.minimum_fee_cents', 50)
        );
});
