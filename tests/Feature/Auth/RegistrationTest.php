<?php

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new members are sent to step 2 of sign-up straight after registering', function () {
    $this->post(route('register.store'), registrationPayload())->assertRedirect(route('splash.show'));

    $this->get(route('splash.show'))->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    $response->assertRedirect(route('splash.show', absolute: false));

    expect(User::firstWhere('email', 'test@example.com')->getRoleNames()->all())->toBe(['user']);

    expect(User::firstWhere('email', 'test@example.com'))
        ->username->toBe('test_user')
        ->open_to_romance->toBeFalse()
        ->date_of_birth->toBeNull();
});

test('live passwords need upper and lower case letters, a number and a symbol', function (string $weakPassword) {
    $this->app['env'] = 'production';

    $validator = Validator::make(
        ['password' => $weakPassword, 'password_confirmation' => $weakPassword],
        ['password' => ['required', 'string', Password::default(), 'confirmed']],
    );

    expect($validator->fails())->toBeTrue();
})->with([
    'no symbol' => 'Password123',
    'no uppercase' => 'password123!',
    'no lowercase' => 'PASSWORD123!',
    'no number' => 'Password!!!',
    'too short' => 'Pa1!',
]);

test('a password needs at least 8 characters, and exactly 8 is accepted', function () {
    $this->post(route('register.store'), registrationPayload([
        'password' => 'short12',
        'password_confirmation' => 'short12',
    ]))->assertSessionHasErrors('password');

    $this->assertGuest();

    $this->post(route('register.store'), registrationPayload([
        'password' => 'Eight8!x',
        'password_confirmation' => 'Eight8!x',
    ]))->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('a username is required and must be unique', function () {
    User::factory()->create(['username' => 'test_user']);

    $this->post(route('register.store'), registrationPayload())->assertSessionHasErrors('username');
    $this->post(route('register.store'), registrationPayload(['username' => '']))->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('adults can opt in to romantic connections when registering', function () {
    $this->post(route('register.store'), registrationPayload([
        'open_to_romance' => '1',
        'date_of_birth' => now()->subYears(21)->toDateString(),
    ]))->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'test@example.com')->isOpenToRomance())->toBeTrue();
});

test('under 18s cannot opt in to romantic connections when registering', function () {
    $this->post(route('register.store'), registrationPayload([
        'open_to_romance' => '1',
        'date_of_birth' => now()->subYears(16)->toDateString(),
    ]))->assertSessionHasErrors('open_to_romance');

    $this->assertGuest();
});
