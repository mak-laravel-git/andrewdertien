<?php

use App\Enums\RoleEnum;
use App\Mail\AdvertisingInquiryReceived;
use App\Models\AdvertisingInquiry;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the advertising form emails the site owner and keeps a copy', function () {
    Mail::fake();
    $admin = User::factory()->create(['email' => 'andrew@example.com']);
    $admin->assignRole(RoleEnum::ADMIN->value);

    $this->post(route('advertising-inquiries.store'), [
        'name' => 'Pat Podcaster',
        'email' => 'pat@example.com',
        'company' => 'Deep Pond Radio',
        'website' => 'https://deeppond.example.com',
        'message' => 'I would like to promote my podcast.',
    ])->assertSessionHasNoErrors();

    expect(AdvertisingInquiry::sole())
        ->name->toBe('Pat Podcaster')
        ->company->toBe('Deep Pond Radio');

    Mail::assertSent(AdvertisingInquiryReceived::class, fn (AdvertisingInquiryReceived $mail) => $mail->hasTo('andrew@example.com')
        && $mail->hasReplyTo('pat@example.com')
        && str_contains($mail->render(), 'I would like to promote my podcast.')
        && str_contains($mail->render(), 'Deep Pond Radio'));
});

test('inquiries go to the configured inbox when there is one', function () {
    Mail::fake();
    config(['mail.offers_to' => 'ads@example.com']);

    $this->post(route('advertising-inquiries.store'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there'])
        ->assertSessionHasNoErrors();

    Mail::assertSent(AdvertisingInquiryReceived::class, fn (AdvertisingInquiryReceived $mail) => $mail->hasTo('ads@example.com'));
    expect(AdvertisingInquiry::sole())->company->toBeNull()->website->toBeNull();
});

test('the advertising form needs a name, a real email and a message', function () {
    Mail::fake();

    $this->post(route('advertising-inquiries.store'), ['name' => '', 'email' => 'nope', 'message' => ''])
        ->assertSessionHasErrors(['name', 'email', 'message']);

    expect(AdvertisingInquiry::count())->toBe(0);
    Mail::assertNothingSent();
});

test('the advertising form is throttled', function () {
    Mail::fake();
    config(['mail.offers_to' => 'ads@example.com']);
    $payload = ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there'];

    foreach (range(1, 5) as $ignored) {
        $this->post(route('advertising-inquiries.store'), $payload)->assertSessionHasNoErrors();
    }

    $this->post(route('advertising-inquiries.store'), $payload)->assertStatus(429);
});
