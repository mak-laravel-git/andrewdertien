import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    MessageCircle,
    Send,
    Share2,
    Sparkles,
    ThumbsUp,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { categories } from '@/components/pond/data';
import { Avatar, CategoryPill, hueFor } from '@/components/pond/ui';
import posts from '@/routes/community/posts';
import { show as showMember } from '@/routes/members';
import messages from '@/routes/messages';

export type MemberChip = {
    id: number;
    name: string;
    username: string | null;
    avatar_url: string | null;
};
export type FeedComment = {
    id: number;
    body: string;
    time: string | null;
    author: MemberChip;
};
export type FeedPost = {
    id: number;
    category: string;
    body: string;
    time: string | null;
    author: MemberChip;
    reactions: {
        total: number;
        counts: Record<string, number>;
        mine: string | null;
    };
    comments: FeedComment[];
    can_delete: boolean;
};

/** The reactions a member can leave. The first one is what a plain tap on Like gives. */
export const reactionOptions = [
    { value: 'like', label: 'Like', emoji: '👍' },
    { value: 'love', label: 'Love', emoji: '❤️' },
    { value: 'celebrate', label: 'Celebrate', emoji: '🎉' },
    { value: 'support', label: 'Support', emoji: '🤗' },
    { value: 'wow', label: 'Wow', emoji: '😮' },
] as const;

/** Where a member's name should lead: their profile, when they have chosen a username. */
export function MemberName({ member }: { member: MemberChip }) {
    return member.username ? (
        <Link href={showMember(member.username)} className="cm-name">
            {member.name}
        </Link>
    ) : (
        <span className="cm-name">{member.name}</span>
    );
}

/** A member's picture, which opens their profile (where they can be messaged) when they have a username. */
export function MemberAvatar({
    member,
    size,
}: {
    member: MemberChip;
    size?: string;
}) {
    const avatar = (
        <Avatar
            name={member.name}
            hue={hueFor(member.id)}
            size={size}
            src={member.avatar_url}
        />
    );

    return member.username ? (
        <Link
            href={showMember(member.username)}
            className="cm-avatar-link"
            aria-label={`View ${member.name}'s profile`}
        >
            {avatar}
        </Link>
    ) : (
        avatar
    );
}

export default function CommunityPostCard({ post }: { post: FeedPost }) {
    const me =
        (usePage().props as { auth?: { user?: { id: number } | null } }).auth
            ?.user ?? null;
    const [showComments, setShowComments] = useState(false);
    const [confirmingRemove, setConfirmingRemove] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const holdTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const comment = useForm({ body: '' });
    const category = categories.find((option) => option.key === post.category);
    const canReachAuthor = post.author.username && post.author.id !== me?.id;
    const mine = reactionOptions.find(
        (option) => option.value === post.reactions.mine,
    );
    const topReactions = reactionOptions
        .filter((option) => (post.reactions.counts[option.value] ?? 0) > 0)
        .sort(
            (a, b) =>
                (post.reactions.counts[b.value] ?? 0) -
                (post.reactions.counts[a.value] ?? 0),
        )
        .slice(0, 3);

    // On a phone there is no hover, so the picker opens on a long press and closes on the next tap elsewhere.
    useEffect(() => {
        if (!pickerOpen) {
            return;
        }

        const close = () => setPickerOpen(false);
        document.addEventListener('pointerdown', close);

        return () => document.removeEventListener('pointerdown', close);
    }, [pickerOpen]);

    const react = (reaction: string) => {
        setPickerOpen(false);
        router.post(
            posts.react.url(post.id),
            { reaction },
            { preserveScroll: true },
        );
    };
    const remove = () =>
        router.delete(posts.destroy.url(post.id), { preserveScroll: true });
    const share = () => {
        const url = `${window.location.origin}${posts.show.url(post.id)}`;
        const touchDevice = window.matchMedia('(pointer: coarse)').matches;

        if (touchDevice && navigator.share) {
            navigator
                .share({
                    title: 'Ask Upon A Pond',
                    text: post.body.slice(0, 120),
                    url,
                })
                .catch(() => undefined);

            return;
        }

        navigator.clipboard
            ?.writeText(url)
            .then(() =>
                toast.success(
                    'Link copied. Paste it anywhere to share this post.',
                ),
            )
            .catch(() => toast.info(url));
    };
    const submitComment = (event: FormEvent) => {
        event.preventDefault();
        comment.post(posts.comment.url(post.id), {
            preserveScroll: true,
            onSuccess: () => comment.reset(),
        });
    };

    return (
        <article className="pond-feed-card" data-test="community-post">
            <div className="pond-feed-head">
                <MemberAvatar member={post.author} />
                <div className="pond-feed-meta">
                    <div>
                        <MemberName member={post.author} />{' '}
                        <span>
                            shared{' '}
                            {category ? `in ${category.title}` : 'something'}
                        </span>
                    </div>
                    <small>
                        <Link
                            href={posts.show.url(post.id)}
                            className="cm-time-link"
                        >
                            {post.time}
                        </Link>
                    </small>
                </div>
                {post.can_delete &&
                    (confirmingRemove ? (
                        <span className="cm-remove-confirm">
                            <button
                                type="button"
                                className="cm-remove-yes"
                                onClick={remove}
                            >
                                Remove
                            </button>
                            <button
                                type="button"
                                onClick={() => setConfirmingRemove(false)}
                            >
                                Keep
                            </button>
                        </span>
                    ) : (
                        <button
                            type="button"
                            className="pond-icon-btn"
                            onClick={() => setConfirmingRemove(true)}
                            aria-label="Remove this post"
                        >
                            <Trash2 size={17} />
                        </button>
                    ))}
            </div>

            <div className="pond-feed-copy">{post.body}</div>
            <div className="pond-feed-category">
                <CategoryPill category={category?.title ?? post.category} />
            </div>

            <div className="pond-feed-stats">
                <span
                    className="cm-reaction-summary"
                    data-test="reaction-summary"
                >
                    {post.reactions.total > 0 ? (
                        <>
                            <span className="cm-reaction-emojis">
                                {topReactions.map((option) => (
                                    <span
                                        key={option.value}
                                        title={`${option.label}: ${post.reactions.counts[option.value]}`}
                                    >
                                        {option.emoji}
                                    </span>
                                ))}
                            </span>{' '}
                            {post.reactions.total}
                        </>
                    ) : (
                        'Be the first to react'
                    )}
                </span>
                <span>
                    {post.comments.length}{' '}
                    {post.comments.length === 1 ? 'comment' : 'comments'}
                </span>
            </div>

            <div className="pond-post-actions cm-actions">
                <div
                    className={`cm-react ${pickerOpen ? 'is-open' : ''}`}
                    onPointerDown={(event) => event.stopPropagation()}
                    onTouchStart={() => {
                        holdTimer.current = setTimeout(
                            () => setPickerOpen(true),
                            450,
                        );
                    }}
                    onTouchEnd={() =>
                        holdTimer.current && clearTimeout(holdTimer.current)
                    }
                    onTouchMove={() =>
                        holdTimer.current && clearTimeout(holdTimer.current)
                    }
                    onContextMenu={(event) => event.preventDefault()}
                >
                    <div
                        className="cm-picker"
                        role="group"
                        aria-label="Choose a reaction"
                    >
                        {reactionOptions.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                title={option.label}
                                aria-label={option.label}
                                className={
                                    post.reactions.mine === option.value
                                        ? 'is-mine'
                                        : ''
                                }
                                onClick={() => react(option.value)}
                                data-test={`react-${option.value}`}
                            >
                                {option.emoji}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        className={mine ? 'liked' : ''}
                        onClick={() => react(mine?.value ?? 'like')}
                        aria-pressed={Boolean(mine)}
                        data-test="react-button"
                    >
                        {mine ? (
                            <span className="cm-mine-emoji">{mine.emoji}</span>
                        ) : (
                            <ThumbsUp size={17} />
                        )}{' '}
                        {mine?.label ?? 'Like'}
                    </button>
                </div>
                <button
                    type="button"
                    onClick={() => setShowComments((open) => !open)}
                    aria-expanded={showComments}
                >
                    <MessageCircle size={17} /> Comment
                </button>
                <button type="button" onClick={share} data-test="share-post">
                    <Share2 size={17} /> Share
                </button>
                {canReachAuthor && (
                    <Link
                        href={messages.show.url(post.author.username!)}
                        className="cm-give"
                        data-test="message-author"
                    >
                        <Send size={17} /> Message
                    </Link>
                )}
                {canReachAuthor && (
                    <Link
                        href={showMember(
                            { username: post.author.username! },
                            { query: { toss: 1 } },
                        )}
                        className="cm-give"
                    >
                        <Sparkles size={17} /> Toss a ball
                    </Link>
                )}
            </div>

            {showComments && (
                <div className="pond-comments">
                    {post.comments.length === 0 && (
                        <p className="pond-muted">
                            Be the first to leave a little encouragement.
                        </p>
                    )}
                    {post.comments.map((item) => (
                        <div className="pond-comment" key={item.id}>
                            <MemberAvatar
                                member={item.author}
                                size="avatar-small"
                            />
                            <span>
                                <MemberName member={item.author} /> {item.body}
                                <small className="cm-comment-time">
                                    {item.time}
                                </small>
                            </span>
                        </div>
                    ))}
                    <form
                        onSubmit={submitComment}
                        className="pond-comment-input"
                    >
                        <input
                            value={comment.data.body}
                            onChange={(event) =>
                                comment.setData('body', event.target.value)
                            }
                            placeholder="Leave a kind comment..."
                            aria-label="Comment text"
                            maxLength={300}
                        />
                        <button
                            type="submit"
                            aria-label="Post comment"
                            disabled={
                                comment.processing || !comment.data.body.trim()
                            }
                        >
                            <Send size={16} />
                        </button>
                    </form>
                    {comment.errors.body && (
                        <small className="cm-error">
                            {comment.errors.body}
                        </small>
                    )}
                </div>
            )}
        </article>
    );
}
