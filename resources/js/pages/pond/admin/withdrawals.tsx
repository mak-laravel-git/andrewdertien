import { Head, router } from '@inertiajs/react';
import { Check, Copy, Download, HandCoins, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { formatDate, formatMoney, StatTile } from '@/components/pond/commerce';
import { EmptyState, Modal, PondShell } from '@/components/pond/ui';
import adminWithdrawals from '@/routes/admin/withdrawals';

type Withdrawal = {
    id: number;
    reference: string;
    amount_cents: number;
    currency: string;
    method: string;
    method_label: string;
    status: 'pending' | 'paid' | 'rejected' | 'cancelled';
    note: string | null;
    admin_note: string | null;
    payment_reference: string | null;
    created_at: string | null;
    processed_at: string | null;
    details: string;
    member: {
        id: number;
        name: string;
        email: string;
        wallet_total_cents: number;
    };
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
    withdrawals: Paginated<Withdrawal>;
    totals: {
        waiting_count: number;
        waiting_cents: number;
        paid_cents: number;
    };
    currency: string;
    statuses: { value: string; label: string }[];
    filters: { status: string; search: string };
};

export default function Withdrawals({
    withdrawals,
    totals,
    currency,
    statuses,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [paying, setPaying] = useState<Withdrawal | null>(null);
    const [rejecting, setRejecting] = useState<Withdrawal | null>(null);
    const [paymentReference, setPaymentReference] = useState('');
    const [note, setNote] = useState('');
    const [reason, setReason] = useState('');

    const query = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        return { status: merged.status, search: merged.search || undefined };
    };
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            adminWithdrawals.index.url({ query: query(next) }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({ search: search.trim() });
    };

    const close = () => {
        setPaying(null);
        setRejecting(null);
        setPaymentReference('');
        setNote('');
        setReason('');
    };
    const confirmPaid = () => {
        if (!paying) {
            return;
        }

        router.post(
            adminWithdrawals.pay.url(paying.reference),
            { payment_reference: paymentReference, admin_note: note || null },
            { preserveScroll: true, onSuccess: close },
        );
    };
    const confirmRejected = () => {
        if (!rejecting) {
            return;
        }

        router.post(
            adminWithdrawals.reject.url(rejecting.reference),
            { reason },
            { preserveScroll: true, onSuccess: close },
        );
    };
    const copy = (text: string) =>
        navigator.clipboard
            ?.writeText(text)
            .then(() => toast.success('Copied'))
            .catch(() => toast.info(text));

    return (
        <>
            <Head title="Withdrawals" />
            <PondShell
                page="Withdrawals"
                subheading="Members asking for money in their wallet. You pay each one by hand, then mark it paid."
                demo={false}
            >
                <div className="ad-money">
                    <StatTile
                        icon={HandCoins}
                        label="Waiting to be paid"
                        value={formatMoney(totals.waiting_cents, currency)}
                        hint={`${totals.waiting_count} ${totals.waiting_count === 1 ? 'request' : 'requests'}`}
                        tone="pink"
                    />
                    <StatTile
                        icon={Check}
                        label="Paid out so far"
                        value={formatMoney(totals.paid_cents, currency)}
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
                            placeholder="Search member or reference..."
                            aria-label="Search withdrawals"
                        />
                    </form>
                    <select
                        className="pond-input pd-select"
                        value={filters.status}
                        onChange={(event) =>
                            apply({ status: event.target.value })
                        }
                        aria-label="Filter by status"
                        data-test="withdrawal-status"
                    >
                        <option value="">All requests</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <a
                        href={adminWithdrawals.export.url({ query: query({}) })}
                        className="pond-btn pond-btn-outline-dark pd-export"
                        data-test="export-withdrawals"
                    >
                        <Download size={16} /> Export CSV
                    </a>
                </div>

                <section className="pond-panel">
                    {withdrawals.data.length ? (
                        <div className="wd-list">
                            {withdrawals.data.map((withdrawal) => (
                                <article
                                    className="wd-card"
                                    key={withdrawal.reference}
                                    data-test="withdrawal"
                                >
                                    <header>
                                        <div>
                                            <strong>
                                                {formatMoney(
                                                    withdrawal.amount_cents,
                                                    withdrawal.currency,
                                                )}
                                            </strong>{' '}
                                            <span
                                                className={`po-status is-${withdrawal.status}`}
                                            >
                                                {withdrawal.status === 'pending'
                                                    ? 'Waiting'
                                                    : withdrawal.status ===
                                                        'rejected'
                                                      ? 'Turned down'
                                                      : withdrawal.status}
                                            </span>
                                            <small>
                                                {withdrawal.member.name} ·{' '}
                                                {withdrawal.member.email}
                                            </small>
                                        </div>
                                        <small className="wl-ref">
                                            {withdrawal.reference}
                                        </small>
                                    </header>

                                    <dl className="wd-details">
                                        <dt>Pay by</dt>
                                        <dd>{withdrawal.method_label}</dd>
                                        <dt>Pay to</dt>
                                        <dd className="wd-pay-to">
                                            <pre>{withdrawal.details}</pre>
                                            <button
                                                type="button"
                                                className="pond-icon-btn"
                                                onClick={() =>
                                                    copy(withdrawal.details)
                                                }
                                                aria-label="Copy payment details"
                                            >
                                                <Copy size={15} />
                                            </button>
                                        </dd>
                                        <dt>Asked</dt>
                                        <dd>
                                            {formatDate(withdrawal.created_at)}
                                        </dd>
                                        <dt>Member wallet</dt>
                                        <dd>
                                            {formatMoney(
                                                withdrawal.member
                                                    .wallet_total_cents,
                                                withdrawal.currency,
                                            )}{' '}
                                            left after this request
                                        </dd>
                                        {withdrawal.note && (
                                            <>
                                                <dt>Member note</dt>
                                                <dd>{withdrawal.note}</dd>
                                            </>
                                        )}
                                        {withdrawal.processed_at && (
                                            <>
                                                <dt>Decided</dt>
                                                <dd>
                                                    {formatDate(
                                                        withdrawal.processed_at,
                                                    )}
                                                    {withdrawal.payment_reference &&
                                                        ` · reference ${withdrawal.payment_reference}`}
                                                    {withdrawal.admin_note &&
                                                        ` · ${withdrawal.admin_note}`}
                                                </dd>
                                            </>
                                        )}
                                    </dl>

                                    {withdrawal.status === 'pending' && (
                                        <footer>
                                            <button
                                                type="button"
                                                className="pond-btn pond-btn-primary pd-btn-small"
                                                onClick={() =>
                                                    setPaying(withdrawal)
                                                }
                                                data-test="mark-paid"
                                            >
                                                <Check size={15} /> Mark as paid
                                            </button>
                                            <button
                                                type="button"
                                                className="pond-btn pond-btn-outline-dark pd-btn-small"
                                                onClick={() =>
                                                    setRejecting(withdrawal)
                                                }
                                                data-test="reject"
                                            >
                                                <X size={15} /> Turn down
                                            </button>
                                        </footer>
                                    )}
                                </article>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<HandCoins size={25} />}
                            title="No withdrawal requests here"
                            body="When a member asks to be paid, it will show up here."
                        />
                    )}

                    {withdrawals.last_page > 1 && (
                        <div className="cm-pager">
                            {withdrawals.prev_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(
                                            withdrawals.prev_page_url!,
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
                                Page {withdrawals.current_page} of{' '}
                                {withdrawals.last_page}
                            </span>
                            {withdrawals.next_page_url ? (
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={() =>
                                        router.get(
                                            withdrawals.next_page_url!,
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

                {paying && (
                    <Modal
                        title={`Mark ${formatMoney(paying.amount_cents, paying.currency)} as paid?`}
                        subtitle={`Do this after you have sent the money to ${paying.member.name} by ${paying.method_label}. They will be told.`}
                        onClose={close}
                    >
                        <label className="pond-form-label">
                            Payment reference or transaction ID
                            <input
                                className="pond-input"
                                value={paymentReference}
                                onChange={(event) =>
                                    setPaymentReference(event.target.value)
                                }
                                maxLength={255}
                                autoFocus
                                data-test="payment-reference"
                            />
                        </label>
                        <label className="pond-form-label">
                            A note for the member{' '}
                            <span className="pond-optional">(optional)</span>
                            <input
                                className="pond-input"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                maxLength={500}
                            />
                        </label>
                        <button
                            type="button"
                            className="pond-btn pond-btn-primary pond-wide"
                            onClick={confirmPaid}
                            disabled={!paymentReference.trim()}
                            data-test="confirm-paid"
                        >
                            <Check size={16} /> Mark as paid
                        </button>
                    </Modal>
                )}

                {rejecting && (
                    <Modal
                        title="Turn this request down?"
                        subtitle={`${formatMoney(rejecting.amount_cents, rejecting.currency)} goes back into ${rejecting.member.name}'s wallet and they are told why.`}
                        onClose={close}
                    >
                        <label className="pond-form-label">
                            Why?
                            <textarea
                                className="pond-textarea"
                                rows={3}
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                maxLength={500}
                                autoFocus
                                data-test="reject-reason"
                            />
                        </label>
                        <button
                            type="button"
                            className="pond-btn pd-btn-danger pond-wide"
                            onClick={confirmRejected}
                            disabled={!reason.trim()}
                            data-test="confirm-reject"
                        >
                            <X size={16} /> Turn it down
                        </button>
                    </Modal>
                )}
            </PondShell>
        </>
    );
}
