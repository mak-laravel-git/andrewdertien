<?php

use App\Enums\RoleEnum;
use App\Models\AdvertisingInquiry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->admin->assignRole(RoleEnum::ADMIN->value);

    $this->member = User::factory()->adult()->create();
    $this->member->assignRole(RoleEnum::USER->value);
});

test('only an admin can read or change the advertising enquiries', function () {
    $inquiry = AdvertisingInquiry::factory()->create();

    $this->actingAs($this->member)->get(route('admin.inquiries.index'))->assertForbidden();
    $this->actingAs($this->member)->patch(route('admin.inquiries.update', $inquiry), ['handled' => true])->assertForbidden();

    expect($inquiry->fresh()->handled_at)->toBeNull();

    auth()->logout();
    $this->get(route('admin.inquiries.index'))->assertRedirect(route('login'));
});

test('an admin sees every enquiry with its details, newest first', function () {
    AdvertisingInquiry::factory()->create(['name' => 'Older Olivia']);
    AdvertisingInquiry::factory()->create([
        'name' => 'Pat Podcaster',
        'email' => 'pat@example.com',
        'company' => 'Deep Pond Radio',
        'website' => 'https://deeppond.example.com',
        'message' => 'I would like to promote my podcast.',
    ]);

    $this->actingAs($this->admin)->get(route('admin.inquiries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/admin/inquiries')
            ->where('totals', ['new' => 2, 'handled' => 0])
            ->has('inquiries.data', 2)
            ->where('inquiries.data.0.name', 'Pat Podcaster')
            ->where('inquiries.data.0.email', 'pat@example.com')
            ->where('inquiries.data.0.company', 'Deep Pond Radio')
            ->where('inquiries.data.0.website', 'https://deeppond.example.com')
            ->where('inquiries.data.0.message', 'I would like to promote my podcast.')
            ->where('inquiries.data.0.handled_at', null)
            ->where('inquiries.data.1.name', 'Older Olivia'));
});

test('an enquiry can be marked handled and put back among the new ones', function () {
    $inquiry = AdvertisingInquiry::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.inquiries.update', $inquiry), ['handled' => true])->assertRedirect();
    expect($inquiry->fresh()->isHandled())->toBeTrue();

    $this->actingAs($this->admin)->patch(route('admin.inquiries.update', $inquiry), ['handled' => false])->assertRedirect();
    expect($inquiry->fresh()->isHandled())->toBeFalse();
});

test('enquiries can be filtered by status and searched', function () {
    AdvertisingInquiry::factory()->create(['name' => 'Fresh Fiona', 'company' => 'Fiona Books']);
    AdvertisingInquiry::factory()->create(['name' => 'Done Dana', 'handled_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['status' => 'new']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('inquiries.data', 1)
            ->where('inquiries.data.0.name', 'Fresh Fiona')
            ->where('filters.status', 'new'));

    $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['status' => 'handled']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('inquiries.data', 1)
            ->where('inquiries.data.0.name', 'Done Dana'));

    $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['search' => 'Fiona Books']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('inquiries.data', 1)
            ->where('inquiries.data.0.name', 'Fresh Fiona'));

    $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['status' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 2)->where('filters.status', ''));
});

test('an enquiry sent from the public form shows up for the admin', function () {
    config(['mail.offers_to' => 'ads@example.com']);

    $this->post(route('advertising-inquiries.store'), [
        'name' => 'Sam Sender',
        'email' => 'sam@example.com',
        'company' => 'Sender Studio',
        'message' => 'Tell me about advertising.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('admin.inquiries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.new', 1)
            ->where('inquiries.data.0.name', 'Sam Sender')
            ->where('inquiries.data.0.company', 'Sender Studio'));
});
