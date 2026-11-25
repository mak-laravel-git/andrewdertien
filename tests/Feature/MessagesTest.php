<?php

use App\Enums\RoleEnum;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->member = User::factory()->create(['name' => 'Sam Member', 'username' => 'sam']);
    $this->member->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->create(['name' => 'Jamie Pond', 'username' => 'jamie', 'dreams' => 'A piece of land.']);
    $this->friend->assignRole(RoleEnum::USER->value);

    $this->stranger = User::factory()->create(['name' => 'Alex Other', 'username' => 'alex']);
    $this->stranger->assignRole(RoleEnum::USER->value);
});

test('guests are sent to log in', function () {
    $this->get(route('messages.index'))->assertRedirect(route('login'));
    $this->post(route('messages.store', 'jamie'), ['body' => 'Hi'])->assertRedirect(route('login'));
});

test('a member can send a message and it lands in the conversation', function () {
    $this->actingAs($this->member)
        ->post(route('messages.store', 'jamie'), ['body' => '  Hello Jamie!  '])
        ->assertRedirect(route('messages.show', 'jamie'));

    expect(Message::sole())
        ->sender_id->toBe($this->member->id)
        ->recipient_id->toBe($this->friend->id)
        ->body->toBe('Hello Jamie!')
        ->read_at->toBeNull();

    $this->actingAs($this->member)
        ->get(route('messages.show', 'jamie'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/messages')
            ->where('active.name', 'Jamie Pond')
            ->has('active.messages', 1)
            ->where('active.messages.0.body', 'Hello Jamie!')
            ->where('active.messages.0.mine', true)
        );
});

test('messages need some words, and cannot be sent to yourself or a missing member', function () {
    $this->actingAs($this->member)->post(route('messages.store', 'jamie'), ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($this->member)->post(route('messages.store', 'jamie'), ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
    $this->actingAs($this->member)->post(route('messages.store', 'sam'), ['body' => 'Hi me'])->assertNotFound();
    $this->actingAs($this->member)->post(route('messages.store', 'nobody'), ['body' => 'Hi'])->assertNotFound();
    $this->actingAs($this->member)->get(route('messages.show', 'sam'))->assertNotFound();

    expect(Message::count())->toBe(0);
});

test('the conversation list has one row per person, newest first, with unread counts', function () {
    Message::factory()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id, 'body' => 'First from Jamie']);
    Message::factory()->create(['sender_id' => $this->member->id, 'recipient_id' => $this->friend->id, 'body' => 'Reply to Jamie']);
    Message::factory()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id, 'body' => 'Latest from Jamie']);
    Message::factory()->create(['sender_id' => $this->stranger->id, 'recipient_id' => $this->member->id, 'body' => 'Hey from Alex']);
    Message::factory()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->stranger->id, 'body' => 'Not mine']);

    $this->actingAs($this->member)
        ->get(route('messages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations', 2)
            ->where('conversations.0.name', 'Alex Other')
            ->where('conversations.0.last_message', 'Hey from Alex')
            ->where('conversations.0.unread', 1)
            ->where('conversations.1.name', 'Jamie Pond')
            ->where('conversations.1.last_message', 'Latest from Jamie')
            ->where('conversations.1.last_from_me', false)
            ->where('conversations.1.unread', 2)
            ->where('active', null)
            ->where('unreadMessages', 3)
        );
});

test('opening a conversation marks what the other person sent as read, and nothing else', function () {
    $fromJamie = Message::factory()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id]);
    $fromAlex = Message::factory()->create(['sender_id' => $this->stranger->id, 'recipient_id' => $this->member->id]);
    $toJamie = Message::factory()->create(['sender_id' => $this->member->id, 'recipient_id' => $this->friend->id]);

    $this->actingAs($this->member)->get(route('messages.show', 'jamie'))->assertOk();

    expect($fromJamie->fresh()->read_at)->not->toBeNull()
        ->and($fromAlex->fresh()->read_at)->toBeNull()
        ->and($toJamie->fresh()->read_at)->toBeNull();
});

test('a conversation only contains the two people in it', function () {
    Message::factory()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id, 'body' => 'For Sam']);
    Message::factory()->create(['sender_id' => $this->member->id, 'recipient_id' => $this->friend->id, 'body' => 'For Jamie']);
    Message::factory()->create(['sender_id' => $this->stranger->id, 'recipient_id' => $this->friend->id, 'body' => 'Private from Alex']);

    $this->actingAs($this->member)
        ->get(route('messages.show', 'jamie'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('active.messages', 2)
            ->where('active.messages.0.body', 'For Sam')
            ->where('active.messages.0.mine', false)
            ->where('active.messages.1.body', 'For Jamie')
            ->where('active.messages.1.mine', true)
        );

    $this->actingAs($this->stranger)
        ->get(route('messages.show', 'jamie'))
        ->assertInertia(fn (Assert $page) => $page->has('active.messages', 1)->where('active.messages.0.body', 'Private from Alex'));
});

test('the member list for a new message is loaded on request and leaves out yourself', function () {
    $this->actingAs($this->member)
        ->get(route('messages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('members')
            ->reloadOnly('members', fn (Assert $reload) => $reload
                ->has('members', 2)
                ->where('members.0.name', 'Alex Other')
                ->where('members.1.name', 'Jamie Pond')
            )
        );
});

test('the unread count is shared with every signed in page', function () {
    Message::factory()->count(2)->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id]);
    Message::factory()->read()->create(['sender_id' => $this->friend->id, 'recipient_id' => $this->member->id]);

    $this->actingAs($this->member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('unreadMessages', 2));
    $this->actingAs($this->friend)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('unreadMessages', 0));
});
