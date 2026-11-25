<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private message from one member to another.
 *
 * @property int $id
 * @property int $sender_id
 * @property int $recipient_id
 * @property string $body
 */
#[Fillable(['sender_id', 'recipient_id', 'body'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Messages passing between two members, in either direction.
     *
     * @param  Builder<Message>  $query
     */
    public function scopeBetween(Builder $query, User $one, User $other): void
    {
        $query->where(function (Builder $inner) use ($one, $other): void {
            $inner->where(fn (Builder $sent) => $sent->where('sender_id', $one->id)->where('recipient_id', $other->id))
                ->orWhere(fn (Builder $received) => $received->where('sender_id', $other->id)->where('recipient_id', $one->id));
        });
    }

    /**
     * @return array{id: int, body: string, mine: bool, time: string|null, created_at: string|null}
     */
    public function toChatBubble(User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->sender_id === $viewer->id,
            'time' => $this->created_at?->format('M j, g:i A'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
