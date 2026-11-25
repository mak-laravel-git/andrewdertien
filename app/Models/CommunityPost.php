<?php

namespace App\Models;

use Database\Factories\CommunityPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Something a member shares with the community: a dream, a bit of fun, a treat or a boost.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property string $body
 */
#[Fillable(['user_id', 'category', 'body'])]
class CommunityPost extends Model
{
    /** @use HasFactory<CommunityPostFactory> */
    use HasFactory;

    /**
     * The reactions a member can leave on a post.
     *
     * @var list<string>
     */
    public const REACTIONS = ['like', 'love', 'celebrate', 'support', 'wow'];

    /**
     * The categories a post can be shared under (the four profile sections).
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return User::PROFILE_CATEGORIES;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CommunityComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class)->oldest();
    }

    /**
     * Members who reacted to this post, with the reaction each one chose.
     *
     * @return BelongsToMany<User, $this>
     */
    public function reactions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_reactions')->withPivot('reaction')->withTimestamps();
    }

    /**
     * How many of each reaction the given posts have, and which one the viewer chose.
     *
     * @param  iterable<int>  $postIds
     * @return array<int, array{counts: array<string, int>, mine: string|null}>
     */
    public static function reactionSummaries(iterable $postIds, User $viewer): array
    {
        $summaries = [];

        DB::table('community_post_reactions')
            ->whereIn('community_post_id', collect($postIds)->all())
            ->get(['community_post_id', 'user_id', 'reaction'])
            ->each(function (object $row) use (&$summaries, $viewer): void {
                $summaries[$row->community_post_id]['counts'][$row->reaction] = ($summaries[$row->community_post_id]['counts'][$row->reaction] ?? 0) + 1;
                $summaries[$row->community_post_id]['mine'] ??= null;

                if ((int) $row->user_id === $viewer->id) {
                    $summaries[$row->community_post_id]['mine'] = $row->reaction;
                }
            });

        return $summaries;
    }

    /**
     * How the community page shows this post to the given viewer.
     * Needs author and comments (with authors) loaded.
     *
     * @param  array{counts?: array<string, int>, mine?: string|null}  $summary  from reactionSummaries()
     * @return array<string, mixed>
     */
    public function toFeedItem(User $viewer, array $summary = []): array
    {
        $counts = $summary['counts'] ?? [];

        return [
            'id' => $this->id,
            'category' => $this->category,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'time' => $this->created_at?->diffForHumans(),
            'author' => $this->author->toMemberChip(),
            'author_can_receive' => $this->author->canBeTossedABall(),
            'reactions' => [
                'total' => array_sum($counts),
                'counts' => (object) $counts,
                'mine' => $summary['mine'] ?? null,
            ],
            'comments' => $this->comments->map->toFeedItem()->all(),
            'can_delete' => $viewer->id === $this->user_id || $viewer->isAdmin(),
        ];
    }
}
