<?php

namespace App\Models;

use Database\Factories\CommunityPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Members who celebrated this post.
     *
     * @return BelongsToMany<User, $this>
     */
    public function celebrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_celebrations')->withTimestamps();
    }

    /**
     * How the community page shows this post to the given viewer.
     * Needs author, celebrations count, comments (with authors) and "celebrated by viewer" loaded.
     *
     * @return array<string, mixed>
     */
    public function toFeedItem(User $viewer): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'time' => $this->created_at?->diffForHumans(),
            'author' => $this->author->toMemberChip(),
            'celebrations' => (int) $this->celebrations_count,
            'celebrated' => (bool) $this->celebrated_by_viewer,
            'comments' => $this->comments->map->toFeedItem()->all(),
            'can_delete' => $viewer->id === $this->user_id || $viewer->isAdmin(),
        ];
    }
}
