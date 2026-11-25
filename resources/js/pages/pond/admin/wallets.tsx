import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Search, Users, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { formatMoney, StatTile } from '@/components/pond/commerce';
import { EmptyState, PondShell } from '@/components/pond/ui';
import adminWallets from '@/routes/admin/wallets';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    members: Paginated<{
        id: number;
        name: string;
        email: string;
        username: string | null;
        total_cents: number;
        available_cents: number;
    }>;
    totals: { owed_cents: number; negative_count: number };
    currency: string;
    filters: { search: string; owing: boolean };
};

export default function Wallets({ members, totals, currency, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        router.get(
            adminWallets.index.url({
                query: {
                    search: merged.search || undefined,
                    owing: merged.owing ? 1 : undefined,
                },
            }),
            {},
            { preserveState: true },
        );
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ search: search.trim() });
    };

    return (
        <>
            <Head title="Wallets" />
            <PondShell
                page="Wallets"
                subheading="What each member has earned and is owed. Open one to see the ledger or put a balance right."
                demo={false}
            >
                <div className="ad-money">
                    <StatTile
                        icon={Wallet}
                        label="Owed to members"
                        value={formatMoney(totals.owed_cents, currency)}
                        hint="Everything in members' wallets"
                        tone="green"
                    />
                    <StatTile
                        icon={Users}
                        label="Wallets below zero"
                        value={String(totals.negative_count)}
                        hint="A refund came after a withdrawal"
                        tone="pink"
                    />
                </div>

                <div className="pd-toolbar">
                    <form onSubmit={submit} className="pond-inline-search">
                        <Search size={16} />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search member..."
                            aria-label="Search wallets"
                        />
                    </form>
                    <label className="pond-toggle-line wl-owing">
                        <span>Only wallets below zero</span>
                        <input
                            type="checkbox"
                            checked={filters.owing}
                            onChange={(event) =>
                                apply({ owing: event.target.checked })
                            }
                            data-test="owing-filter"
                        />
                        <span className="pond-toggle-ui" />
                    </label>
                </div>

                <section className="pond-panel">
                    {members.data.length ? (
                        <div className="po-earn-list">
                            {members.data.map((member) => (
                                <div
                                    className="po-earn-row"
                                    key={member.id}
                                    data-test="wallet-row"
                                >
                                    <span>
                                        <strong>{member.name}</strong>
                                        <small className="wl-sub">
                                            {member.email}
                                        </small>
                                    </span>
                                    <strong
                                        className={
                                            member.total_cents < 0
                                                ? 'wl-out'
                                                : ''
                                        }
                                    >
                                        {formatMoney(
                                            member.total_cents,
                                            currency,
                                        )}
                                    </strong>
                                    <small>
                                        {formatMoney(
                                            Math.max(0, member.available_cents),
                                            currency,
                                        )}{' '}
                                        can be withdrawn
                                        <Link
                                            href={adminWallets.show.url(
                                                member.id,
                                            )}
                                            className="pd-link wl-open"
                                        >
                                            Open <ArrowRight size={13} />
                                        </Link>
                                    </small>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<Wallet size={25} />}
                            title="No wallets yet"
                            body="A member's wallet appears here once a ball is tossed to them."
                        />
                    )}
                    {members.last_page > 1 && (
                        <div className="cm-pager">
                            {members.prev_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(members.prev_page_url!)
                                    }
                                >
                                    Previous
                                </button>
                            ) : (
                                <span />
                            )}
                            <span className="pond-muted">
                                Page {members.current_page} of{' '}
                                {members.last_page}
                            </span>
                            {members.next_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(members.next_page_url!)
                                    }
                                >
                                    Next
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
