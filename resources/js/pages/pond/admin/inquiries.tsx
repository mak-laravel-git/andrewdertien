import { Head, router } from '@inertiajs/react';
import { Check, Inbox, Mail, RotateCcw, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { StatTile } from '@/components/pond/commerce';
import { EmptyState, PondShell } from '@/components/pond/ui';
import adminInquiries from '@/routes/admin/inquiries';

type Inquiry = {
    id: number;
    name: string;
    email: string;
    company: string | null;
    website: string | null;
    message: string;
    received_at: string | null;
    handled_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    inquiries: Paginated<Inquiry>;
    totals: { new: number; handled: number };
    filters: { status: string; search: string };
};

const formatDateTime = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

/** A link target for what the visitor typed, or null when it does not look like a web address. */
function websiteHref(value: string): string | null {
    const text = value.trim();

    if (/^https?:\/\/\S+$/i.test(text)) {
        return text;
    }

    return /^[\w-]+(\.[\w-]+)+(\/\S*)?$/i.test(text) ? `https://${text}` : null;
}

export default function Inquiries({ inquiries, totals, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const query = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        return {
            status: merged.status || undefined,
            search: merged.search || undefined,
        };
    };
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            adminInquiries.index.url({ query: query(next) }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({ search: search.trim() });
    };
    const setHandled = (inquiry: Inquiry, handled: boolean) =>
        router.patch(
            adminInquiries.update.url(inquiry.id),
            { handled },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Enquiries" />
            <PondShell
                page="Enquiries"
                subheading="People who asked about advertising on the Pond. Reply by email, then mark each one handled."
                demo={false}
            >
                <div className="ad-money">
                    <StatTile
                        icon={Inbox}
                        label="New enquiries"
                        value={String(totals.new)}
                        hint="Waiting for a reply"
                        tone="pink"
                    />
                    <StatTile
                        icon={Check}
                        label="Handled"
                        value={String(totals.handled)}
                        tone="green"
                    />
                </div>

                <div className="pd-toolbar">
                    <form
                        onSubmit={submitSearch}
                        className="pond-inline-search"
                    >
                        <Search size={16} />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name, email, business or message..."
                            aria-label="Search enquiries"
                        />
                    </form>
                    <select
                        className="pond-input pd-select"
                        value={filters.status}
                        onChange={(event) =>
                            apply({ status: event.target.value })
                        }
                        aria-label="Filter by status"
                        data-test="inquiry-status"
                    >
                        <option value="">All enquiries</option>
                        <option value="new">New</option>
                        <option value="handled">Handled</option>
                    </select>
                </div>

                <section className="pond-panel">
                    {inquiries.data.length ? (
                        <div className="wd-list">
                            {inquiries.data.map((inquiry) => {
                                const href = inquiry.website
                                    ? websiteHref(inquiry.website)
                                    : null;

                                return (
                                    <article
                                        className="wd-card"
                                        key={inquiry.id}
                                        data-test="inquiry"
                                    >
                                        <header>
                                            <div>
                                                <strong>{inquiry.name}</strong>{' '}
                                                <span
                                                    className={`po-status ${inquiry.handled_at ? 'is-paid' : 'is-pending'}`}
                                                >
                                                    {inquiry.handled_at
                                                        ? 'Handled'
                                                        : 'New'}
                                                </span>
                                                <small>{inquiry.email}</small>
                                            </div>
                                            <small>
                                                {formatDateTime(
                                                    inquiry.received_at,
                                                )}
                                            </small>
                                        </header>

                                        <dl className="wd-details">
                                            {inquiry.company && (
                                                <>
                                                    <dt>Business or project</dt>
                                                    <dd>{inquiry.company}</dd>
                                                </>
                                            )}
                                            {inquiry.website && (
                                                <>
                                                    <dt>Website</dt>
                                                    <dd>
                                                        {href ? (
                                                            <a
                                                                href={href}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                {
                                                                    inquiry.website
                                                                }
                                                            </a>
                                                        ) : (
                                                            inquiry.website
                                                        )}
                                                    </dd>
                                                </>
                                            )}
                                            <dt>Wants to promote</dt>
                                            <dd className="wd-pay-to">
                                                <pre>{inquiry.message}</pre>
                                            </dd>
                                            {inquiry.handled_at && (
                                                <>
                                                    <dt>Handled</dt>
                                                    <dd>
                                                        {formatDateTime(
                                                            inquiry.handled_at,
                                                        )}
                                                    </dd>
                                                </>
                                            )}
                                        </dl>

                                        <footer>
                                            <a
                                                href={`mailto:${inquiry.email}?subject=${encodeURIComponent('Advertising on Ask Upon a Pond')}`}
                                                className="pond-btn pond-btn-primary pd-btn-small"
                                                data-test="reply"
                                            >
                                                <Mail size={15} /> Reply by
                                                email
                                            </a>
                                            {inquiry.handled_at ? (
                                                <button
                                                    type="button"
                                                    className="pond-btn pond-btn-outline-dark pd-btn-small"
                                                    onClick={() =>
                                                        setHandled(
                                                            inquiry,
                                                            false,
                                                        )
                                                    }
                                                    data-test="reopen"
                                                >
                                                    <RotateCcw size={15} /> Mark
                                                    as new
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="pond-btn pond-btn-outline-dark pd-btn-small"
                                                    onClick={() =>
                                                        setHandled(
                                                            inquiry,
                                                            true,
                                                        )
                                                    }
                                                    data-test="mark-handled"
                                                >
                                                    <Check size={15} /> Mark as
                                                    handled
                                                </button>
                                            )}
                                        </footer>
                                    </article>
                                );
                            })}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<Inbox size={25} />}
                            title="No enquiries here"
                            body="When someone fills in the Advertise on the Pond form, it will show up here."
                        />
                    )}

                    {inquiries.last_page > 1 && (
                        <div className="cm-pager">
                            {inquiries.prev_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(
                                            inquiries.prev_page_url!,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Newer
                                </button>
                            ) : (
                                <span />
                            )}
                            <span className="pond-muted">
                                Page {inquiries.current_page} of{' '}
                                {inquiries.last_page}
                            </span>
                            {inquiries.next_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(
                                            inquiries.next_page_url!,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Older
                                </button>
                            ) : (
                                <span />
                            )}
                        </div>
                    )}
                </section>
            </PondShell>
        </>
    );
}
