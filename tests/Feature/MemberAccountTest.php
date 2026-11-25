<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->withProfile()->create([
        'name' => 'Sam Member',
        'username' => 'sam_member',
        'email' => 'sam@example.com',
        'phone' => null,
        'location' => 'Portland',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountPayload(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'phone' => null,
        'location' => $user->location,
        ...$overrides,
    ];
}

test('guests are sent to log in', function () {
    $this->get(route('member-account.edit'))->assertRedirect(route('login'));
    $this->patch(route('member-account.update'), [])->assertRedirect(route('login'));
});

test('the account page shows the member their own private details', function () {
    $this->actingAs($this->user)
        ->get(route('member-account.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/account')
            ->where('account.name', 'Sam Member')
            ->where('account.username', 'sam_member')
            ->where('account.email', 'sam@example.com')
            ->where('account.phone', null)
            ->where('account.date_of_birth', null)
            ->where('twoFactorEnabled', false)
        );
});

test('a member can update every personal detail', function () {
    $this->actingAs($this->user)
        ->patch(route('member-account.update'), accountPayload($this->user, [
            'name' => 'Samantha Member',
            'username' => 'samantha_m',
            'email' => 'samantha@example.com',
            'phone' => '+1 (555) 123-4567',
            'location' => 'Seattle',
            'date_of_birth' => '1990-05-04',
        ]))
        ->assertRedirect(route('member-account.edit'))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh())
        ->name->toBe('Samantha Member')
        ->username->toBe('samantha_m')
        ->email->toBe('samantha@example.com')
        ->phone->toBe('+1 (555) 123-4567')
        ->location->toBe('Seattle')
        ->date_of_birth->format('Y-m-d')->toBe('1990-05-04');
});

test('changing the email address clears verification, keeping it does not', function () {
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['name' => 'Sam Renamed']));
    expect($this->user->fresh()->email_verified_at)->not->toBeNull();

    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['email' => 'new@example.com']));
    expect($this->user->fresh()->email_verified_at)->toBeNull();
});

test('the phone number, location and email can be cleared or must be valid', function () {
    $this->user->update(['phone' => '+1 555 000 1111']);

    $this->actingAs($this->user)
        ->patch(route('member-account.update'), accountPayload($this->user, ['phone' => null, 'location' => null]))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh())->phone->toBeNull()->location->toBeNull();

    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['phone' => 'call me maybe']))->assertSessionHasErrors('phone');
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['email' => 'not-an-email']))->assertSessionHasErrors('email');
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['name' => '']))->assertSessionHasErrors('name');
});

test('usernames and emails stay unique, and a username cannot be removed once chosen', function () {
    User::factory()->create(['username' => 'taken_name', 'email' => 'taken@example.com']);

    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['username' => 'taken_name']))->assertSessionHasErrors('username');
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['email' => 'taken@example.com']))->assertSessionHasErrors('email');
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['username' => null]))->assertSessionHasErrors('username');
    $this->actingAs($this->user)->patch(route('member-account.update'), accountPayload($this->user, ['username' => 'bad name!']))->assertSessionHasErrors('username');
});

test('a member without a username yet can save details and choose one later', function () {
    $newcomer = User::factory()->create(['username' => null, 'email' => 'new@example.com']);

    $this->actingAs($newcomer)
        ->patch(route('member-account.update'), accountPayload($newcomer, ['name' => 'Newcomer', 'username' => null]))
        ->assertSessionHasNoErrors();

    expect($newcomer->fresh())->name->toBe('Newcomer')->username->toBeNull();

    $this->actingAs($newcomer)->patch(route('member-account.update'), accountPayload($newcomer, ['username' => 'newcomer_1']))->assertSessionHasNoErrors();
    expect($newcomer->fresh()->username)->toBe('newcomer_1');
});

test('a saved date of birth is locked', function () {
    $this->user->update(['date_of_birth' => '1980-01-01']);

    $this->actingAs($this->user)
        ->patch(route('member-account.update'), accountPayload($this->user, ['date_of_birth' => '1999-09-09']))
        ->assertSessionHasErrors('date_of_birth');

    expect($this->user->fresh()->date_of_birth->format('Y-m-d'))->toBe('1980-01-01');

    $this->actingAs($this->user)
        ->get(route('member-account.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('account.date_of_birth', '1980-01-01'));
});

test('a date of birth in the future is refused', function () {
    $this->actingAs($this->user)
        ->patch(route('member-account.update'), accountPayload($this->user, ['date_of_birth' => now()->addDay()->format('Y-m-d')]))
        ->assertSessionHasErrors('date_of_birth');
});

test('the password can be changed from the account page with the current password', function () {
    $this->actingAs($this->user)
        ->from(route('member-account.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'A-new-Passw0rd!',
            'password_confirmation' => 'A-new-Passw0rd!',
        ])
        ->assertRedirect(route('member-account.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('A-new-Passw0rd!', $this->user->fresh()->password))->toBeTrue();
});

test('a wrong current password or a mismatched confirmation is refused', function () {
    $this->actingAs($this->user)
        ->from(route('member-account.edit'))
        ->put(route('user-password.update'), ['current_password' => 'wrong', 'password' => 'A-new-Passw0rd!', 'password_confirmation' => 'A-new-Passw0rd!'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($this->user)
        ->from(route('member-account.edit'))
        ->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'A-new-Passw0rd!', 'password_confirmation' => 'different'])
        ->assertSessionHasErrors('password');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

test('a member can delete their own account with their password', function () {
    $this->actingAs($this->user)
        ->from(route('member-account.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(User::find($this->user->id))->not->toBeNull();

    $this->actingAs($this->user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(User::find($this->user->id))->toBeNull();
    $this->assertGuest();
});

test('the account page reports whether two-factor is on', function () {
    $secured = User::factory()->withTwoFactor()->create();

    $this->actingAs($secured)
        ->get(route('member-account.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorEnabled', true));
});
