import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    MessageCircle,
    Plus,
    Send,
    Waves,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import CommunityPostCard, {
    MemberAvatar,
    MemberName,
} from '@/components/pond/community-post';
import type { FeedPost } from '@/components/pond/community-post';
import { categories } from '@/components/pond/data';
import type { Recipient } from '@/components/pond/toss-ball';
import {
    Avatar,
    EmptyState,
    hueFor,
    Modal,
    PondShell,
} from '@/components/pond/ui';
import community from '@/routes/community';
import posts from '@/routes/community/posts';
import { show as showMember } from '@/routes/members';
import messages from '@/routes/messages';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    posts: Paginated<FeedPost>;
    filters: { category: string; mine: boolean };
    people: Recipient[];
};

function ComposeModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ category: 'dreams', body: '' });
    const category = categories.find(
        (option) => option.key === form.data.category,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(posts.store.url(), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal
            title="Share a little of your story"
            subtitle="Tell the pond what matters to you. No pressure, just possibility."
            onClose={onClose}
        >
            <form onSubmit={submit}>
                <label className="pond-form-label">
                    Category
                    <select
                        className="pond-input"
                        value={form.data.category}
                        onChange={(event) =>
                            form.setData('category', event.target.value)
                        }
                    >
                        {categories.map((option) => (
                            <option key={option.key} value={option.key}>
                                {option.title}: {option.subtitle}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="pond-form-label">
                    What would you like to share?
                    <textarea
                        className="pond-textarea"
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        maxLength={500}
                        rows={4}
                        autoFocus
                        placeholder={category?.placeholder}
                    />
                </label>
                {form.errors.body && (
                    <p className="cm-error">{form.errors.body}</p>
                )}
                {form.errors.category && (
                    <p className="cm-error">{form.errors.category}</p>
                )}
                <button
                    type="submit"
                    className="pond-btn pond-btn-primary pond-wide"
                    disabled={form.processing}
                >
                    <Send size={16} /> Share with the pond
                </button>
            </form>
        </Modal>
    );
}

export default function Community({ posts: feed, filters, people }: Props) {
    const user =
        (
            usePage().props as {
                auth?: {
                    user?: {
                        id: number;
                        name: string;
                        avatar?: string | null;
                    } | null;
                };
            }
        ).auth?.user ?? null;
    const [composing, setComposing] = useState(false);
    const avatarSrc = user?.avatar
        ? user.avatar.startsWith('http')
            ? user.avatar
            : `/storage/${user.avatar}`
        : null;

    const filter = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };
        router.get(
            community.index.url({
                query: {
                    category: merged.category || undefined,
                    mine: merged.mine ? 1 : undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="The Pond" />
            <PondShell
                page="The Pond"
                subheading="Discover the beautiful things members are sharing."
                demo={false}
                action={
                    <button
                        type="button"
                        onClick={() => setComposing(true)}
                        className="pond-btn pond-btn-primary"
                    >
                        <Plus size={17} /> Share a moment
                    </button>
                }
            >
                <div className="pond-feed-layout">
                    <div className="pond-feed-center">
                        <div className="pond-panel pond-composer">
                            <Avatar
                                name={user?.name ?? 'You'}
                                hue={hueFor(user?.id ?? 0)}
                                src={avatarSrc}
                            />
                            <button
                                type="button"
                                className="pond-composer-input"
                                onClick={() => setComposing(true)}
                            >
                                What&rsquo;s on your heart today?
                            </button>
                            <button
                                type="button"
                                className="pond-compose-add"
                                onClick={() => setComposing(true)}
                                aria-label="Create a post"
                            >
                                <Plus size={21} />
                            </button>
                        </div>

                        <div className="pond-feed-toolbar">
                            <div className="pond-tab-group">
                                <button
                                    type="button"
                                    className={filters.mine ? '' : 'active'}
                                    onClick={() => filter({ mine: false })}
                                >
                                    Everyone
                                </button>
                                <button
                                    type="button"
                                    className={filters.mine ? 'active' : ''}
                                    onClick={() => filter({ mine: true })}
                                >
                                    My posts
                                </button>
                            </div>
                            <select
                                aria-label="Filter posts by category"
                                value={filters.category}
                                onChange={(event) =>
                                    filter({ category: event.target.value })
                                }
                                className="pond-filter-select"
                            >
                                <option value="">All posts</option>
                                {categories.map((option) => (
                                    <option key={option.key} value={option.key}>
                                        {option.title}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {feed.data.map((post) => (
                            <CommunityPostCard key={post.id} post={post} />
                        ))}

                        {feed.data.length === 0 && (
                            <EmptyState
                                icon={<Waves size={25} />}
                                title={
                                    filters.mine
                                        ? 'You have not shared anything yet'
                                        : 'A quiet moment in the pond'
                                }
                                body={
                                    filters.mine
                                        ? 'Share a dream, a little fun, a treat or a boost to get started.'
                                        : 'Be the first to share something with the community.'
                                }
                            />
                        )}

                        {feed.last_page > 1 && (
                            <div className="cm-pager">
                                {feed.prev_page_url ? (
                                    <Link
                                        href={feed.prev_page_url}
                                        preserveScroll
                                        className="pond-btn pond-btn-outline-dark"
                                    >
                                        Newer
                                    </Link>
                                ) : (
                                    <span />
                                )}
                                <span className="pond-muted">
                                    Page {feed.current_page} of {feed.last_page}
                                </span>
                                {feed.next_page_url ? (
                                    <Link
                                        href={feed.next_page_url}
                                        preserveScroll
                                        className="pond-btn pond-btn-outline-dark"
                                    >
                                        Older
                                    </Link>
                                ) : (
                                    <span />
                                )}
                            </div>
                        )}
                    </div>

                    <aside className="pond-feed-aside">
                        <div className="pond-panel cm-aside-card">
                            <div className="pond-aside-title">
                                <span className="pond-aside-icon">✦</span>
                                <h3>A little reminder</h3>
                            </div>
                            <p>
                                Someone out there could use exactly the kindness
                                you have to give today.
                            </p>
                            <Link
                                href="/dashboard/ponds"
                                className="pond-aside-link"
                            >
                                Explore the Balls <ArrowRight size={15} />
                            </Link>
                        </div>

                        {people.length > 0 && (
                            <div className="pond-panel cm-aside-card">
                                <div className="pond-aside-title">
                                    <h3>People to know</h3>
                                </div>
                                <div className="pond-side-people">
                                    {people.map((person) => (
                                        <div key={person.id}>
                                            <MemberAvatar
                                                member={person}
                                                size="avatar-small"
                                            />
                                            <div>
                                                <MemberName member={person} />
                                                <small>
                                                    {person.ask ?? 'Say hello'}
                                                </small>
                                            </div>
                                            {person.username && (
                                                <span className="cm-person-links">
                                                    <Link
                                                        href={messages.show.url(
                                                            person.username,
                                                        )}
                                                        aria-label={`Message ${person.name}`}
                                                    >
                                                        <MessageCircle
                                                            size={16}
                                                        />
                                                    </Link>
                                                    <Link
                                                        href={showMember(
                                                            person.username,
                                                        )}
                                                        aria-label={`View ${person.name}`}
                                                    >
                                                        <ArrowUpRight
                                                            size={16}
                                                        />
                                                    </Link>
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </aside>
                </div>

                {composing && (
                    <ComposeModal onClose={() => setComposing(false)} />
                )}
            </PondShell>
        </>
    );
}
