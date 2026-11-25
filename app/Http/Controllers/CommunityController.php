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
            ->when($category, fn ($query, string $category) => $query->where('category', $category))
            ->when($mine, fn ($query) => $query->where('user_id', $viewer->id))
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $summaries = CommunityPost::reactionSummaries($posts->pluck('id'), $viewer);
        $posts->through(fn (CommunityPost $post): array => $post->toFeedItem($viewer, $summaries[$post->id] ?? []));

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
     * One post on its own, which is where a shared link leads.
     */
    public function show(Request $request, CommunityPost $post): Response
    {
        $viewer = $request->user();
        $post->load(['author', 'comments.author']);

        return Inertia::render('pond/community-post', [
            'post' => $post->toFeedItem($viewer, CommunityPost::reactionSummaries([$post->id], $viewer)[$post->id] ?? []),
        ]);
    }

    /**
     * React to a post. Choosing the reaction you already gave takes it back; choosing another changes it.
     */
    public function react(Request $request, CommunityPost $post): RedirectResponse
    {
        $reaction = $request->validate([
            'reaction' => ['required', Rule::in(CommunityPost::REACTIONS)],
        ])['reaction'];

        $current = $post->reactions()->whereKey($request->user()->id)->first()?->pivot->reaction;

        if ($current === $reaction) {
            $post->reactions()->detach($request->user()->id);
        } else {
            $post->reactions()->syncWithoutDetaching([$request->user()->id => ['reaction' => $reaction]]);
        }

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
