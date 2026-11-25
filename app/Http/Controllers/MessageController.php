<?php

namespace App\Http\Controllers;

use App\Http\Requests\Message\StoreMessageRequest;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Private messages between members.
 */
class MessageController extends Controller
{
    /**
     * Every conversation on the left, and the open one (if a member is chosen) on the right.
     * Opening a conversation marks what the other member sent as read.
     */
    public function index(Request $request, ?User $user = null): Response
    {
        $viewer = $request->user();

        abort_if($user?->is($viewer), 404);

        if ($user) {
            Message::query()
                ->where('sender_id', $user->id)
                ->where('recipient_id', $viewer->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return Inertia::render('pond/messages', [
            'conversations' => fn (): array => $this->conversationsFor($viewer),
            'active' => $user ? [
                ...$user->toRecipient(),
                'messages' => Message::query()
                    ->between($viewer, $user)
                    ->latest('id')
                    ->limit(200)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn (Message $message): array => $message->toChatBubble($viewer)),
            ] : null,
            'members' => Inertia::optional(fn () => User::query()
                ->discoverable()
                ->whereKeyNot($viewer->id)
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->map->toRecipient()),
        ]);
    }

    /**
     * Send a message to a member.
     */
    public function store(StoreMessageRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 404);

        Message::query()->create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => trim($request->validated('body')),
        ]);

        return to_route('messages.show', ['user' => $user->username]);
    }

    /**
     * One row per person the member has talked to: who, the last message, and how many are unread.
     *
     * @return list<array<string, mixed>>
     */
    private function conversationsFor(User $viewer): array
    {
        $lastMessageIds = Message::query()
            ->where(fn ($query) => $query->where('sender_id', $viewer->id)->orWhere('recipient_id', $viewer->id))
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END', [$viewer->id])
            ->pluck('id');

        $unread = Message::query()
            ->where('recipient_id', $viewer->id)
            ->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) as total')
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return Message::query()
            ->with(['sender', 'recipient'])
            ->whereIn('id', $lastMessageIds)
            ->latest('id')
            ->get()
            ->map(function (Message $message) use ($viewer, $unread): array {
                $partner = $message->sender_id === $viewer->id ? $message->recipient : $message->sender;

                return [
                    ...$partner->toMemberChip(),
                    'last_message' => $message->body,
                    'last_from_me' => $message->sender_id === $viewer->id,
                    'time' => $message->created_at?->diffForHumans(short: true),
                    'unread' => (int) ($unread[$partner->id] ?? 0),
                ];
            })
            ->all();
    }
}
