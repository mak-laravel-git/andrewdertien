<?php

use App\Enums\PurchaseStatus;
use App\Enums\RoleEnum;
use App\Mail\BallOfferReceived;
use App\Models\BallOffer;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['payments.driver' => 'sandbox', 'payments.currency' => 'usd']);

    $this->giver = User::factory()->create();
    $this->giver->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->adult()->create(['name' => 'Jamie Pond', 'username' => 'jamie', 'dreams' => 'A piece of land and some dogs.']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->ball = Pond::factory()->create(['price_cents' => 10000]);
});

test('a ball can be tossed for any amount from a dollar', function (string $amount, int $cents) {
    $this->actingAs($this->giver)
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => $amount])
        ->assertRedirect();

    expect(PondPurchase::sole())->amount_cents->toBe($cents);
})->with([
    'one dollar' => ['1', 100],
    'a few dollars and cents' => ['7.50', 750],
    'a big one' => ['2500', 250000],
]);

test('the ball suggested amount is used when no amount is chosen', function () {
    $this->actingAs($this->giver)->post(route('member.checkout.store', $this->ball->slug))->assertRedirect();

    expect(PondPurchase::sole())->amount_cents->toBe(10000);
});

test('amounts under a dollar or over the limit are refused', function (string $amount) {
    $this->actingAs($this->giver)
        ->from(route('member.ponds.index'))
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => $amount])
        ->assertSessionHasErrors('amount');

    expect(PondPurchase::count())->toBe(0);
})->with(['half a dollar' => '0.50', 'zero' => '0', 'negative' => '-5', 'too much' => '10000.01', 'not a number' => 'lots']);

test('a ball tossed to a member remembers who it was for', function () {
    $this->actingAs($this->giver)
        ->post(route('member.checkout.store', $this->ball->slug), ['amount' => 20, 'recipient_id' => $this->friend->id])
        ->assertRedirect();

    expect(PondPurchase::sole())
        ->recipient_id->toBe($this->friend->id)
        ->recipient_name->toBe('Jamie Pond')
        ->amount_cents->toBe(2000);
});

test('a ball cannot be tossed to yourself or to someone who does not exist', function () {
    $this->actingAs($this->giver)
        ->post(route('member.checkout.store', $this->ball->slug), ['recipient_id' => $this->giver->id])
        ->assertSessionHasErrors('recipient_id');

    $this->actingAs($this->giver)
        ->post(route('member.checkout.store', $this->ball->slug), ['recipient_id' => 999999])
        ->assertSessionHasErrors('recipient_id');

    expect(PondPurchase::count())->toBe(0);
});

test('the recipient collects a ball once it is paid and the giver sees it as tossed', function () {
    PondPurchase::factory()->paid()->create([
        'user_id' => $this->giver->id,
        'recipient_id' => $this->friend->id,
        'recipient_name' => $this->friend->name,
        'pond_id' => $this->ball->id,
    ]);
    PondPurchase::factory()->create([
        'user_id' => $this->giver->id,
        'recipient_id' => $this->friend->id,
        'pond_id' => $this->ball->id,
        'status' => PurchaseStatus::PENDING,
    ]);

    $this->actingAs($this->friend)->get(route('member.ponds.owned'))->assertInertia(fn (Assert $page) => $page
        ->has('collected', 1)
        ->where('collected.0.buyer_name', $this->giver->name)
        ->has('tossed', 0)
    );

    $this->actingAs($this->giver)->get(route('member.ponds.owned'))->assertInertia(fn (Assert $page) => $page
        ->has('tossed', 1)
        ->where('tossed.0.recipient_name', 'Jamie Pond')
    );

    $this->actingAs($this->friend)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('stats.collected', 1));
});

test('The Balls page can start a toss for a member', function () {
    $this->actingAs($this->giver)
        ->get(route('member.ponds.index', ['to' => 'jamie']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/member/ponds')
            ->where('recipient.name', 'Jamie Pond')
            ->where('recipient.ask', 'A piece of land and some dogs.')
            ->where('ponds.0.suggested_amounts', [500, 2000, 5000, 10000])
        );

    $this->actingAs($this->giver)
        ->get(route('member.ponds.index', ['to' => $this->giver->username]))
        ->assertInertia(fn (Assert $page) => $page->where('recipient', null));
});

test('the dashboard shows asks from other members with a way to toss them a ball', function () {
    User::factory()->create(['username' => 'quiet_one']);

    $this->actingAs($this->giver)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/member/dashboard')
            ->has('asks', 1)
            ->where('asks.0.name', 'Jamie Pond')
            ->where('asks.0.ask', 'A piece of land and some dogs.')
            ->has('balls', 1)
            ->where('stats.asks', 1)
        );
});

test('a member profile gives the page what it needs to toss that person a ball', function () {
    $this->actingAs($this->giver)
        ->get(route('members.show', ['user' => 'jamie', 'toss' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipient.id', $this->friend->id)
            ->has('balls', 1)
            ->where('openToss', true)
        );

    $this->actingAs($this->giver)
        ->get(route('members.show', ['user' => 'jamie']))
        ->assertInertia(fn (Assert $page) => $page->where('openToss', false));
});

test('a signed in member who picks a person on the website lands on that profile ready to toss', function () {
    $this->actingAs($this->giver)
        ->get(route('toss', 'jamie'))
        ->assertRedirect(route('members.show', ['user' => 'jamie', 'toss' => 1]));

    $this->actingAs($this->giver)
        ->get(route('toss', 'nobody'))
        ->assertRedirect(route('members.index'));
});

test('a visitor who picks a person signs up and is sent back to finish the gift', function () {
    $this->get(route('toss', 'jamie'))->assertRedirect(route('register'));

    $this->post(route('register.store'), [
        'name' => 'New Giver',
        'username' => 'new_giver',
        'email' => 'giver@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('splash.show', absolute: false));

    $this->post(route('splash.store'), [])->assertRedirect(route('members.show', ['user' => 'jamie', 'toss' => 1]));

    // It is used once: finishing step 2 again goes to the dashboard.
    $this->post(route('splash.store'), [])->assertRedirect(route('dashboard'));
});

test('a visitor who logs in instead is also sent back to the person they picked', function () {
    $this->get(route('toss', 'jamie'));

    $this->post(route('login.store'), ['email' => $this->giver->email, 'password' => 'password']);

    $this->get(route('dashboard'))->assertRedirect(route('members.show', ['user' => 'jamie', 'toss' => 1]));
});

test('picking someone who is not a member just goes to sign up', function () {
    $this->get(route('toss', 'nobody'))->assertRedirect(route('register'));

    $this->actingAs($this->giver)->get(route('dashboard'))->assertOk();
});

test('The Deep End offer form emails the site owner and keeps a copy', function () {
    Mail::fake();
    $admin = User::factory()->create(['email' => 'andrew@example.com']);
    $admin->assignRole(RoleEnum::ADMIN->value);

    $this->post(route('ball-offers.store'), [
        'name' => 'Rich Person',
        'email' => 'rich@example.com',
        'message' => 'I would like the Zuckerberg Ball.',
    ])->assertSessionHasNoErrors();

    expect(BallOffer::sole())->name->toBe('Rich Person')->email->toBe('rich@example.com');

    Mail::assertSent(BallOfferReceived::class, fn (BallOfferReceived $mail) => $mail->hasTo('andrew@example.com')
        && $mail->hasReplyTo('rich@example.com')
        && str_contains($mail->render(), 'I would like the Zuckerberg Ball.'));
});

test('offers go to the configured inbox when there is one', function () {
    Mail::fake();
    config(['mail.offers_to' => 'offers@example.com']);

    $this->post(route('ball-offers.store'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there'])->assertSessionHasNoErrors();

    Mail::assertSent(BallOfferReceived::class, fn (BallOfferReceived $mail) => $mail->hasTo('offers@example.com'));
});

test('the offer form needs a name, a real email and a message', function () {
    Mail::fake();

    $this->post(route('ball-offers.store'), ['name' => '', 'email' => 'nope', 'message' => ''])
        ->assertSessionHasErrors(['name', 'email', 'message']);

    expect(BallOffer::count())->toBe(0);
    Mail::assertNothingSent();
});

test('the Deep End balls cannot be bought through checkout or the ball picker', function () {
    Pond::factory()->create(['name' => 'Dream Ball']);

    $this->actingAs($this->giver)->get(route('member.ponds.index'))->assertInertia(fn (Assert $page) => $page
        ->has('ponds', 2)
        ->where('ponds', fn ($ponds) => collect($ponds)->every(fn ($ball) => ! str_contains($ball['name'], 'Zuckerberg') && ! str_contains($ball['name'], 'Musk')))
    );
});

test('The Balls page lists other members so a giver can pick who a ball is for', function () {
    User::factory()->create(['username' => null, 'name' => 'No Username']);

    $this->actingAs($this->giver)
        ->get(route('member.ponds.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/member/ponds')
            ->has('members', 1)
            ->where('members.0.id', $this->friend->id)
            ->where('members.0.name', 'Jamie Pond')
            ->where('members.0.ask', 'A piece of land and some dogs.')
            ->where('recipient', null)
        );
});
