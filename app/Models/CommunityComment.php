<?php

namespace App\Models;

use Database\Factories\CommunityCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A kind word under a community post.
 *
 * @property int $id
 * @property int $community_post_id
 * @property int $user_id
 * @property string $body
 */
#[Fillable(['community_post_id', 'user_id', 'body'])]
class CommunityComment extends Model
{
    /** @use HasFactory<CommunityCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CommunityPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array{id: int, body: string, time: string|null, author: array<string, mixed>}
     */
    public function toFeedItem(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'time' => $this->created_at?->diffForHumans(),
            'author' => $this->author->toMemberChip(),
        ];
    }
}
