<?php

namespace App\Http\Controllers;

use App\Http\Requests\Community\StoreCommentRequest;
use App\Http\Requests\Community\StorePostRequest;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The community feed: what members share, and the celebrations and comments around it.
 */
class CommunityController extends Controller
{
    /**
     * The feed, newest first, optionally narrowed to one category or to my own posts.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $filters = $request->validate([
            'category' => ['nullable', Rule::in(CommunityPost::categories())],
            'mine' => ['nullable', 'boolean'],
        ]);

        $category = $filters['category'] ?? null;
        $mine = (bool) ($filters['mine'] ?? false);

        $posts = CommunityPost::query()
            ->with(['author', 'comments.author'])
            ->withCount('celebrations')
            ->withExists(['celebrations as celebrated_by_viewer' => fn ($query) => $query->where('users.id', $viewer->id)])
            ->when($category, fn ($query, string $category) => $query->where('category', $category))
            ->when($mine, fn ($query) => $query->where('user_id', $viewer->id))
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CommunityPost $post): array => $post->toFeedItem($viewer));

        return Inertia::render('pond/community', [
            'posts' => $posts,
            'filters' => ['category' => $category ?? '', 'mine' => $mine],
            'people' => User::query()
                ->discoverable()
                ->whereKeyNot($viewer->id)
                ->inRandomOrder()
                ->limit(4)
                ->get()
                ->map->toRecipient(),
        ]);
    }

    /**
     * Share something with the community.
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $request->user()->communityPosts()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shared with the pond.')]);

        return to_route('community.index');
    }

    /**
     * Remove a post. Its author can; so can an admin keeping the pond kind.
     */
    public function destroy(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post removed.')]);

        return back();
    }

    /**
     * Celebrate a post, or take the celebration back.
     */
    public function celebrate(Request $request, CommunityPost $post): RedirectResponse
    {
        $post->celebrations()->toggle($request->user()->id);

        return back();
    }

    /**
     * Leave a comment under a post.
     */
    public function comment(StoreCommentRequest $request, CommunityPost $post): RedirectResponse
    {
        $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back();
    }
}
