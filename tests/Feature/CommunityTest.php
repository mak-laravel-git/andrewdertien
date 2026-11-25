<?php

use App\Enums\RoleEnum;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->member = User::factory()->create(['name' => 'Sam Member']);
    $this->member->assignRole(RoleEnum::USER->value);

    $this->friend = User::factory()->create(['name' => 'Jamie Pond', 'username' => 'jamie']);
    $this->friend->assignRole(RoleEnum::USER->value);
});

test('guests are sent to log in', function () {
    $this->get(route('community.index'))->assertRedirect(route('login'));
    $this->post(route('community.posts.store'), ['category' => 'dreams', 'body' => 'Hello'])->assertRedirect(route('login'));
});

test('the community shows real posts newest first, with the author and counts', function () {
    $older = CommunityPost::factory()->for($this->friend, 'author')->create(['body' => 'An older post', 'created_at' => now()->subDay()]);
    $newer = CommunityPost::factory()->for($this->friend, 'author')->create(['body' => 'A newer post']);
    CommunityComment::factory()->for($newer, 'post')->for($this->member, 'author')->create(['body' => 'Lovely!']);
    $newer->reactions()->attach($this->member, ['reaction' => 'love']);

    $this->actingAs($this->member)
        ->get(route('community.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/community')
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $newer->id)
            ->where('posts.data.0.author.name', 'Jamie Pond')
            ->where('posts.data.0.reactions.total', 1)
            ->where('posts.data.0.reactions.mine', 'love')
            ->where('posts.data.0.reactions.counts.love', 1)
            ->where('posts.data.0.comments.0.body', 'Lovely!')
            ->where('posts.data.0.comments.0.author.name', 'Sam Member')
            ->where('posts.data.0.can_delete', false)
            ->where('posts.data.1.id', $older->id)
            ->where('posts.data.1.reactions.total', 0)
            ->where('posts.data.1.reactions.mine', null)
            ->has('people', 1)
        );
});

test('the feed can be narrowed to a category or to my own posts', function () {
    CommunityPost::factory()->for($this->friend, 'author')->create(['category' => 'fun']);
    CommunityPost::factory()->for($this->friend, 'author')->create(['category' => 'boost']);
    CommunityPost::factory()->for($this->member, 'author')->create(['category' => 'boost']);

    $this->actingAs($this->member)->get(route('community.index', ['category' => 'boost']))
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 2)->where('filters.category', 'boost'));

    $this->actingAs($this->member)->get(route('community.index', ['mine' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.can_delete', true));

    $this->actingAs($this->member)->get(route('community.index', ['category' => 'nonsense']))
        ->assertSessionHasErrors('category');
});

test('a member can share something with the community', function () {
    $this->actingAs($this->member)
        ->post(route('community.posts.store'), ['category' => 'treats', 'body' => 'A coffee would make my day.'])
        ->assertRedirect(route('community.index'));

    expect(CommunityPost::sole())
        ->user_id->toBe($this->member->id)
        ->category->toBe('treats')
        ->body->toBe('A coffee would make my day.');
});

test('a post needs a real category and some words', function (array $data) {
    $this->actingAs($this->member)->post(route('community.posts.store'), $data)->assertSessionHasErrors();

    expect(CommunityPost::count())->toBe(0);
})->with([
    'no body' => [['category' => 'dreams', 'body' => '']],
    'unknown category' => [['category' => 'flirt', 'body' => 'Hello there']],
    'too long' => [['category' => 'dreams', 'body' => str_repeat('a', 501)]],
]);

test('a member can react to a post, change the reaction and take it back', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();

    $this->actingAs($this->member)->post(route('community.posts.react', $post), ['reaction' => 'like'])->assertRedirect();
    expect($post->reactions()->count())->toBe(1)
        ->and($post->reactions()->first()->pivot->reaction)->toBe('like');

    $this->actingAs($this->member)->post(route('community.posts.react', $post), ['reaction' => 'wow'])->assertRedirect();
    expect($post->reactions()->count())->toBe(1)
        ->and($post->reactions()->first()->pivot->reaction)->toBe('wow');

    $this->actingAs($this->member)->post(route('community.posts.react', $post), ['reaction' => 'wow'])->assertRedirect();
    expect($post->reactions()->count())->toBe(0);
});

test('reactions from several members are counted by kind', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();
    $post->reactions()->attach($this->friend, ['reaction' => 'like']);
    $post->reactions()->attach($this->member, ['reaction' => 'like']);
    $post->reactions()->attach(User::factory()->create(), ['reaction' => 'support']);

    $this->actingAs($this->member)
        ->get(route('community.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.reactions.total', 3)
            ->where('posts.data.0.reactions.counts.like', 2)
            ->where('posts.data.0.reactions.counts.support', 1)
            ->where('posts.data.0.reactions.mine', 'like')
        );
});

test('only the known reactions are accepted', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();

    $this->actingAs($this->member)->post(route('community.posts.react', $post), ['reaction' => 'angry'])->assertSessionHasErrors('reaction');
    $this->actingAs($this->member)->post(route('community.posts.react', $post), [])->assertSessionHasErrors('reaction');

    expect($post->reactions()->count())->toBe(0);
});

test('a post has its own page that a shared link can open', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create(['body' => 'Look at this dream']);
    CommunityComment::factory()->for($post, 'post')->for($this->member, 'author')->create(['body' => 'Nice!']);

    $this->actingAs($this->member)
        ->get(route('community.posts.show', $post))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pond/community-post')
            ->where('post.id', $post->id)
            ->where('post.body', 'Look at this dream')
            ->where('post.author.username', 'jamie')
            ->has('post.comments', 1)
        );

    $this->actingAs($this->member)->get(route('community.posts.show', 999999))->assertNotFound();

    auth()->logout();
    $this->get(route('community.posts.show', $post))->assertRedirect(route('login'));
});

test('a member can comment on a post, but not with an empty comment', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();

    $this->actingAs($this->member)->post(route('community.posts.comment', $post), ['body' => 'So happy for you!'])->assertRedirect();

    expect(CommunityComment::sole())
        ->user_id->toBe($this->member->id)
        ->community_post_id->toBe($post->id)
        ->body->toBe('So happy for you!');

    $this->actingAs($this->member)->post(route('community.posts.comment', $post), ['body' => ''])->assertSessionHasErrors('body');
});

test('only the author or an admin can remove a post', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();

    $this->actingAs($this->member)->delete(route('community.posts.destroy', $post))->assertForbidden();
    expect(CommunityPost::count())->toBe(1);

    $this->actingAs($this->friend)->delete(route('community.posts.destroy', $post))->assertRedirect();
    expect(CommunityPost::count())->toBe(0);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMIN->value);
    $another = CommunityPost::factory()->for($this->friend, 'author')->create();

    $this->actingAs($admin)->get(route('community.index'))->assertInertia(fn (Assert $page) => $page->where('posts.data.0.can_delete', true));
    $this->actingAs($admin)->delete(route('community.posts.destroy', $another))->assertRedirect();
    expect(CommunityPost::count())->toBe(0);
});

test('removing a post removes its comments and reactions', function () {
    $post = CommunityPost::factory()->for($this->friend, 'author')->create();
    CommunityComment::factory()->for($post, 'post')->create();
    $post->reactions()->attach($this->member, ['reaction' => 'like']);

    $this->actingAs($this->friend)->delete(route('community.posts.destroy', $post));

    expect(CommunityComment::count())->toBe(0)
        ->and(DB::table('community_post_reactions')->count())->toBe(0);
});
