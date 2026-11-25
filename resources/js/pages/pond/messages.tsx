import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, Plus, Search, Send } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import type { MemberChip } from '@/components/pond/community-post';
import type { Recipient } from '@/components/pond/toss-ball';
import {
    Avatar,
    EmptyState,
    hueFor,
    Modal,
    PondShell,
} from '@/components/pond/ui';
import { show as showMember } from '@/routes/members';
import messages from '@/routes/messages';

type Conversation = MemberChip & {
    last_message: string;
    last_from_me: boolean;
    time: string | null;
    unread: number;
};
type Bubble = { id: number; body: string; mine: boolean; time: string | null };
type Active = Recipient & { messages: Bubble[] };

type Props = {
    conversations: Conversation[];
    active: Active | null;
    members?: Recipient[];
};

/** Pick a member to start a conversation with. */
function NewMessageModal({
    members,
    onClose,
}: {
    members: Recipient[];
    onClose: () => void;
}) {
    const [search, setSearch] = useState('');
    const matches = useMemo(() => {
        const term = search.trim().toLowerCase();

        return members
            .filter(
                (member) =>
                    member.username &&
                    (!term ||
                        member.name.toLowerCase().includes(term) ||
                        (member.username ?? '').toLowerCase().includes(term)),
            )
            .slice(0, 30);
    }, [members, search]);

    return (
        <Modal
            title="New message"
            subtitle="Choose a member to say hello to."
            onClose={onClose}
        >
            <div className="pond-search-field">
                <Search size={19} />
                <input
                    autoFocus
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search members..."
                    aria-label="Search members"
                    maxLength={100}
                />
            </div>
            <div className="ms-picker" data-test="message-picker">
                {matches.map((member) => (
                    <Link
                        key={member.id}
                        href={messages.show.url(member.username!)}
                        className="ms-picker-row"
                        onClick={onClose}
                    >
                        <Avatar
                            name={member.name}
                            hue={hueFor(member.id)}
                            size="avatar-small"
                            src={member.avatar_url}
                        />
                        <span>
                            <strong>{member.name}</strong>
                            <small>@{member.username}</small>
                        </span>
                    </Link>
                ))}
                {matches.length === 0 && (
                    <p className="pond-muted">No members match that search.</p>
                )}
            </div>
        </Modal>
    );
}

export default function Messages({ conversations, active, members }: Props) {
    const [picking, setPicking] = useState(false);
    const scroller = useRef<HTMLDivElement>(null);
    const form = useForm({ body: '' });
    const messageCount = active?.messages.length ?? 0;

    // New messages show up on their own while this page is open.
    usePoll(5000, { only: ['conversations', 'active', 'unreadMessages'] });

    useEffect(() => {
        if (scroller.current) {
            scroller.current.scrollTop = scroller.current.scrollHeight;
        }
    }, [active?.id, messageCount]);

    const openPicker = () => {
        if (members) {
            setPicking(true);

            return;
        }

        router.reload({ only: ['members'], onSuccess: () => setPicking(true) });
    };

    const send = (event: FormEvent) => {
        event.preventDefault();

        if (!active?.username || !form.data.body.trim()) {
            return;
        }

        form.post(messages.store.url(active.username), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Messages" />
            <PondShell
                page="Messages"
                subheading="Good conversations begin with a little hello."
                demo={false}
                action={
                    <button
                        type="button"
                        className="pond-btn pond-btn-primary"
                        onClick={openPicker}
                    >
                        <Plus size={17} /> New message
                    </button>
                }
            >
                <div
                    className={`pond-messages-card ms-card ${active ? 'ms-has-active' : ''}`}
                >
                    <div
                        className="pond-message-threads"
                        data-test="conversations"
                    >
                        <div>
                            <h2>Conversations</h2>
                            <p>A little hello goes a long way.</p>
                        </div>
                        {conversations.map((conversation) => (
                            <Link
                                key={conversation.id}
                                href={messages.show.url(
                                    conversation.username ?? '',
                                )}
                                preserveScroll
                                className={`pond-thread ${active?.id === conversation.id ? 'active' : ''}`}
                            >
                                <Avatar
                                    name={conversation.name}
                                    hue={hueFor(conversation.id)}
                                    src={conversation.avatar_url}
                                />
                                <span>
                                    <strong>{conversation.name}</strong>
                                    <small>
                                        {conversation.last_from_me
                                            ? 'You: '
                                            : ''}
                                        {conversation.last_message}
                                    </small>
                                </span>
                                {conversation.unread > 0 ? (
                                    <span
                                        className="ms-unread"
                                        aria-label={`${conversation.unread} unread`}
                                    >
                                        {conversation.unread}
                                    </span>
                                ) : (
                                    <small className="ms-time">
                                        {conversation.time}
                                    </small>
                                )}
                            </Link>
                        ))}
                        {conversations.length === 0 && (
                            <p className="ms-none">
                                No conversations yet. Start one with New
                                message.
                            </p>
                        )}
                    </div>

                    <div className="pond-chat ms-chat">
                        {active ? (
                            <>
                                <div className="pond-chat-header">
                                    <Link
                                        href={messages.index()}
                                        className="ms-back"
                                        aria-label="Back to conversations"
                                    >
                                        <ArrowLeft size={19} />
                                    </Link>
                                    <Avatar
                                        name={active.name}
                                        hue={hueFor(active.id)}
                                        src={active.avatar_url}
                                    />
                                    <div>
                                        <strong>{active.name}</strong>
                                        {active.username && (
                                            <small>
                                                <Link
                                                    href={showMember(
                                                        active.username,
                                                    )}
                                                >
                                                    @{active.username}
                                                </Link>
                                            </small>
                                        )}
                                    </div>
                                </div>
                                <div
                                    className="pond-chat-messages ms-scroll"
                                    ref={scroller}
                                    data-test="chat-messages"
                                >
                                    {active.messages.length === 0 && (
                                        <p className="ms-none">
                                            Say hello to{' '}
                                            {active.name.split(' ')[0]}. Your
                                            message stays between the two of
                                            you.
                                        </p>
                                    )}
                                    {active.messages.map((message) => (
                                        <div
                                            key={message.id}
                                            className={`pond-chat-bubble ${message.mine ? 'mine' : 'theirs'}`}
                                            title={message.time ?? undefined}
                                        >
                                            {message.body}
                                        </div>
                                    ))}
                                </div>
                                <form
                                    className="pond-chat-send"
                                    onSubmit={send}
                                >
                                    <input
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Write a kind message..."
                                        aria-label="Message"
                                        maxLength={2000}
                                        autoComplete="off"
                                    />
                                    <button
                                        type="submit"
                                        aria-label="Send message"
                                        disabled={
                                            form.processing ||
                                            !form.data.body.trim()
                                        }
                                    >
                                        <Send size={18} />
                                    </button>
                                </form>
                                {form.errors.body ? (
                                    <p className="pond-chat-disclaimer ms-error">
                                        {form.errors.body}
                                    </p>
                                ) : (
                                    <p className="pond-chat-disclaimer">
                                        Only you and {active.name.split(' ')[0]}{' '}
                                        can see this conversation.
                                    </p>
                                )}
                            </>
                        ) : (
                            <div className="ms-empty">
                                <EmptyState
                                    icon={<MessageCircle size={25} />}
                                    title="Pick a conversation"
                                    body="Choose someone on the left, or start a new message."
                                />
                            </div>
                        )}
                    </div>
                </div>

                {picking && members && (
                    <NewMessageModal
                        members={members}
                        onClose={() => setPicking(false)}
                    />
                )}
            </PondShell>
        </>
    );
}
