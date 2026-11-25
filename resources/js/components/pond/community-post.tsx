import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Heart, MessageCircle, Send, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { categories } from '@/components/pond/data';
import { Avatar, CategoryPill, hueFor } from '@/components/pond/ui';
import posts from '@/routes/community/posts';
import { show as showMember } from '@/routes/members';

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
    celebrations: number;
    celebrated: boolean;
    comments: FeedComment[];
    can_delete: boolean;
};

/** Where a member's name should lead: their profile, when they have chosen a username. */
function MemberName({ member }: { member: MemberChip }) {
    return member.username ? (
        <Link href={showMember(member.username)} className="cm-name">
            {member.name}
        </Link>
    ) : (
        <span className="cm-name">{member.name}</span>
    );
}

export default function CommunityPostCard({ post }: { post: FeedPost }) {
    const me =
        (usePage().props as { auth?: { user?: { id: number } | null } }).auth
            ?.user ?? null;
    const [showComments, setShowComments] = useState(false);
    const [confirmingRemove, setConfirmingRemove] = useState(false);
    const comment = useForm({ body: '' });
    const category = categories.find((option) => option.key === post.category);
    const canToss = post.author.username && post.author.id !== me?.id;

    const celebrate = () =>
        router.post(posts.celebrate.url(post.id), {}, { preserveScroll: true });
    const remove = () =>
        router.delete(posts.destroy.url(post.id), { preserveScroll: true });
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
                <Avatar
                    name={post.author.name}
                    hue={hueFor(post.author.id)}
                    src={post.author.avatar_url}
                />
                <div className="pond-feed-meta">
                    <div>
                        <MemberName member={post.author} />{' '}
                        <span>
                            shared{' '}
                            {category ? `in ${category.title}` : 'something'}
                        </span>
                    </div>
                    <small>{post.time}</small>
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
                <span>
                    💜 {post.celebrations}{' '}
                    {post.celebrations === 1 ? 'celebration' : 'celebrations'}
                </span>
                <span>
                    {post.comments.length}{' '}
                    {post.comments.length === 1 ? 'comment' : 'comments'}
                </span>
            </div>

            <div className="pond-post-actions">
                <button
                    type="button"
                    className={post.celebrated ? 'liked' : ''}
                    onClick={celebrate}
                    aria-pressed={post.celebrated}
                >
                    <Heart
                        size={17}
                        fill={post.celebrated ? 'currentColor' : 'none'}
                    />{' '}
                    Celebrate
                </button>
                <button
                    type="button"
                    onClick={() => setShowComments((open) => !open)}
                    aria-expanded={showComments}
                >
                    <MessageCircle size={17} /> Comment
                </button>
                {canToss && (
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
                            <Avatar
                                name={item.author.name}
                                hue={hueFor(item.author.id)}
                                size="avatar-small"
                                src={item.author.avatar_url}
                            />
                            <span>
                                <strong>{item.author.name}</strong> {item.body}
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
