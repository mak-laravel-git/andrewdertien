<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    Cache::flush();
});

/**
 * A member with confirmed two factor and a real secret (the factory's placeholder is too short to produce codes).
 */
function memberWithTwoFactor(): User
{
    return User::factory()->create([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

function currentAuthenticatorCode(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
}

test('a member can switch on two factor authentication and confirm it with a code from their app', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');
    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();

    $this->post(route('two-factor.confirm'), ['code' => currentAuthenticatorCode($user)])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and(json_decode(decrypt($user->two_factor_recovery_codes)))->toHaveCount(8);
});

test('switching on two factor needs the password to be confirmed first', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('two-factor.enable'))
        ->assertStatus(423);

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

test('a member with two factor is only logged in after a correct authenticator code', function () {
    $user = memberWithTwoFactor();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => '111111'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => currentAuthenticatorCode($user)])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('a recovery code logs a member in once and is then used up', function () {
    $user = memberWithTwoFactor();
    $recoveryCode = json_decode(decrypt($user->two_factor_recovery_codes))[0];

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(json_decode(decrypt($user->refresh()->two_factor_recovery_codes)))->not->toContain($recoveryCode);

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});

test('two factor can be switched off again', function () {
    $user = memberWithTwoFactor();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->delete(route('two-factor.disable'))->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});
