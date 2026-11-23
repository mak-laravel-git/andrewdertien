import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarRange,
    Clock3,
    CreditCard,
    Download,
    Eye,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    formatDate,
    formatMoney,
    Pagination,
    paymentStatuses,
    PaymentsTable,
    StatTile,
    StatusBadge,
} from '@/components/pond/commerce';
import type { PaginatedRows, PaymentRow } from '@/components/pond/commerce';
import { EmptyState, Modal, PondShell } from '@/components/pond/ui';
import adminPayments from '@/routes/admin/payments';
import adminUsers from '@/routes/admin/users';

type AdminPaymentRow = PaymentRow & {
    gateway_session_id: string | null;
    gateway_payment_id: string | null;
};

type Filters = {
    search: string;
    status: string;
    pond: number | null;
    period: string;
    from: string;
    to: string;
};

type Props = {
    payments: PaginatedRows<AdminPaymentRow>;
    totals: { paid_cents: number; refunded_cents: number; pending: number };
    currency: string;
    ponds: { id: number; name: string }[];
    filters: Filters;
};

const periods = [
    { value: 'all', label: 'All time' },
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This week' },
    { value: 'month', label: 'This month' },
    { value: 'year', label: 'This year' },
    { value: 'custom', label: 'Custom range' },
];

/** The query string for a set of filters, leaving out anything that is not set. */
function queryFor(filters: Filters) {
    const isCustom = filters.period === 'custom';

    return {
        search: filters.search || undefined,
        status: filters.status || undefined,
        pond: filters.pond || undefined,
        period: filters.period !== 'all' ? filters.period : undefined,
        from: isCustom ? filters.from || undefined : undefined,
        to: isCustom ? filters.to || undefined : undefined,
    };
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt>{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

export default function AdminPayments({
    payments,
    totals,
    currency,
    ponds,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [refunding, setRefunding] = useState<AdminPaymentRow | null>(null);
    const [viewing, setViewing] = useState<AdminPaymentRow | null>(null);

    const applyFilters = (change: Partial<Filters>) => {
        router.get(
            adminPayments.index.url({
                query: queryFor({ ...filters, search, ...change }),
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportUrl = adminPayments.export.url({
        query: queryFor({ ...filters, search }),
    });

    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timer = setTimeout(() => applyFilters({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search]);

    return (
        <>
            <Head title="Purchases & payments" />
            <PondShell
                page="Purchases & payments"
                subheading="Who tossed which ball, and every payment behind it."
                demo={false}
            >
                <div className="pd-stats pd-stats-3">
                    <StatTile
                        icon={CreditCard}
                        label="Collected"
                        value={formatMoney(totals.paid_cents, currency)}
                        tone="green"
                    />
                    <StatTile
                        icon={RotateCcw}
                        label="Refunded"
                        value={formatMoney(totals.refunded_cents, currency)}
                        tone="pink"
                    />
                    <StatTile
                        icon={Clock3}
                        label="Awaiting payment"
                        value={String(totals.pending)}
                    />
                </div>

                <div className="pd-toolbar">
                    <div className="pond-inline-search">
                        <Search size={18} />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search member, ball or reference..."
                            aria-label="Search payments"
                        />
                    </div>
                    <select
                        className="pond-input pd-select"
                        value={filters.status}
                        onChange={(event) =>
                            applyFilters({ status: event.target.value })
                        }
                        aria-label="Filter by status"
                    >
                        <option value="">All statuses</option>
                        {paymentStatuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="pond-input pd-select"
                        value={filters.pond ?? ''}
                        onChange={(event) =>
                            applyFilters({
                                pond: Number(event.target.value) || null,
                            })
                        }
                        aria-label="Filter by ball"
                    >
                        <option value="">All balls</option>
                        {ponds.map((pond) => (
                            <option key={pond.id} value={pond.id}>
                                {pond.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="pond-input pd-select"
                        value={filters.period}
                        onChange={(event) =>
                            applyFilters({ period: event.target.value })
                        }
                        aria-label="Filter by period"
                        data-test="period-filter"
                    >
                        {periods.map((period) => (
                            <option key={period.value} value={period.value}>
                                {period.label}
                            </option>
                        ))}
                    </select>
                    <a
                        href={exportUrl}
                        className="pond-btn pond-btn-outline-dark pd-export"
                        data-test="export-payments"
                    >
                        <Download size={16} /> Export CSV
                    </a>
                </div>

                {filters.period === 'custom' && (
                    <div className="pd-date-range" data-test="custom-range">
                        <CalendarRange size={18} aria-hidden="true" />
                        <label>
                            From
                            <input
                                type="date"
                                className="pond-input"
                                value={filters.from}
                                max={filters.to || undefined}
                                onChange={(event) =>
                                    applyFilters({ from: event.target.value })
                                }
                            />
                        </label>
                        <label>
                            To
                            <input
                                type="date"
                                className="pond-input"
                                value={filters.to}
                                min={filters.from || undefined}
                                onChange={(event) =>
                                    applyFilters({ to: event.target.value })
                                }
                            />
                        </label>
                    </div>
                )}

                <section className="pond-panel">
                    {payments.data.length ? (
                        <PaymentsTable
                            rows={payments.data}
                            showBuyer
                            buyerHref={(row) =>
                                row.user_id
                                    ? adminUsers.show(row.user_id).url
                                    : null
                            }
                            actions={(row) => (
                                <>
                                    <button
                                        className="pond-btn pond-btn-outline-dark pd-btn-small"
                                        onClick={() =>
                                            setViewing(row as AdminPaymentRow)
                                        }
                                        data-test="view-payment"
                                    >
                                        <Eye size={14} /> Details
                                    </button>
                                    {row.status === 'paid' && (
                                        <button
                                            className="pond-btn pond-btn-outline-dark pd-btn-small"
                                            onClick={() =>
                                                setRefunding(
                                                    row as AdminPaymentRow,
                                                )
                                            }
                                        >
                                            <RotateCcw size={14} /> Refund
                                        </button>
                                    )}
                                </>
                            )}
                        />
                    ) : (
                        <EmptyState
                            icon={<CreditCard size={25} />}
                            title="No payments found"
                            body="Try a different search, filter or date range."
                        />
                    )}
                    <Pagination page={payments} label="Payment pages" />
                </section>

                {viewing && (
                    <Modal
                        title={viewing.pond_name}
                        subtitle={`Payment ${viewing.reference}`}
                        onClose={() => setViewing(null)}
                    >
                        <dl
                            className="pd-detail-list"
                            data-test="payment-details"
                        >
                            <DetailRow label="Status">
                                <StatusBadge status={viewing.status} />
                                {viewing.is_test && (
                                    <span className="pond-mode-badge pond-mode-small">
                                        Test
                                    </span>
                                )}
                            </DetailRow>
                            <DetailRow label="Amount">
                                {formatMoney(
                                    viewing.amount_cents,
                                    viewing.currency,
                                )}
                            </DetailRow>
                            <DetailRow label="Member">
                                {viewing.user_id ? (
                                    <Link
                                        href={
                                            adminUsers.show(viewing.user_id).url
                                        }
                                        className="pd-link"
                                    >
                                        {viewing.buyer_name}
                                    </Link>
                                ) : (
                                    viewing.buyer_name
                                )}
                                <small>{viewing.buyer_email}</small>
                            </DetailRow>
                            <DetailRow label="Started">
                                {formatDate(viewing.created_at)}
                            </DetailRow>
                            {viewing.paid_at && (
                                <DetailRow label="Paid">
                                    {formatDate(viewing.paid_at)}
                                </DetailRow>
                            )}
                            {viewing.refunded_at && (
                                <DetailRow label="Refunded">
                                    {formatDate(viewing.refunded_at)}
                                </DetailRow>
                            )}
                            <DetailRow label="Paid through">
                                <span className="pd-capitalize">
                                    {viewing.gateway}
                                </span>
                            </DetailRow>
                            {viewing.gateway_payment_id && (
                                <DetailRow label="Stripe payment ID">
                                    <span className="pd-mono">
                                        {viewing.gateway_payment_id}
                                    </span>
                                </DetailRow>
                            )}
                            {viewing.failure_message && (
                                <DetailRow label="Note">
                                    {viewing.failure_message}
                                </DetailRow>
                            )}
                        </dl>
                    </Modal>
                )}

                {refunding && (
                    <Modal
                        title={`Refund ${formatMoney(refunding.amount_cents, refunding.currency)}?`}
                        subtitle={`${refunding.buyer_name} gets the full amount back for ${refunding.pond_name}. This cannot be undone.`}
                        onClose={() => setRefunding(null)}
                    >
                        <div className="pd-modal-actions">
                            <button
                                className="pond-btn pond-btn-outline-dark"
                                onClick={() => setRefunding(null)}
                            >
                                Keep payment
                            </button>
                            <button
                                className="pond-btn pd-btn-danger"
                                onClick={() => {
                                    router.post(
                                        adminPayments.refund.url(
                                            refunding.reference,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    );
                                    setRefunding(null);
                                }}
                            >
                                <RotateCcw size={16} /> Refund payment
                            </button>
                        </div>
                    </Modal>
                )}
            </PondShell>
        </>
    );
}
