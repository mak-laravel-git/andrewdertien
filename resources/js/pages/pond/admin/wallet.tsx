import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Wallet as WalletIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { formatDate, formatMoney, StatTile } from '@/components/pond/commerce';
import { EmptyState, PondShell } from '@/components/pond/ui';
import adminWallets from '@/routes/admin/wallets';

type Props = {
    member: {
        id: number;
        name: string;
        email: string;
        username: string | null;
    };
    wallet: {
        total_cents: number;
        available_cents: number;
        pending_cents: number;
        withdrawable_cents: number;
        earned_cents: number;
        withdrawn_cents: number;
        requested_cents: number;
        currency: string;
    };
    entries: {
        id: number;
        type: string;
        amount_cents: number;
        currency: string;
        description: string;
        created_at: string | null;
        by: string | null;
    }[];
    withdrawals: {
        reference: string;
        amount_cents: number;
        currency: string;
        method_label: string;
        status: string;
        created_at: string | null;
    }[];
};

function FieldError({ message }: { message?: string }) {
    return message ? <p className="pond-field-error">{message}</p> : null;
}

export default function WalletOfMember({
    member,
    wallet,
    entries,
    withdrawals,
}: Props) {
    const form = useForm({ amount: '', reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(adminWallets.adjust.url(member.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={`${member.name}'s wallet`} />
            <PondShell
                page={`${member.name}'s wallet`}
                subheading={member.email}
                demo={false}
                action={
                    <Link
                        href={adminWallets.index()}
                        className="pond-btn pond-btn-outline-dark"
                    >
                        <ArrowLeft size={16} /> All wallets
                    </Link>
                }
            >
                <div className="ad-money">
                    <StatTile
                        icon={WalletIcon}
                        label="In the wallet"
                        value={formatMoney(wallet.total_cents, wallet.currency)}
                        hint={`${formatMoney(wallet.withdrawable_cents, wallet.currency)} can be withdrawn`}
                        tone="green"
                    />
                    <StatTile
                        icon={WalletIcon}
                        label="Earned in total"
                        value={formatMoney(
                            wallet.earned_cents,
                            wallet.currency,
                        )}
                        hint={`${formatMoney(wallet.withdrawn_cents, wallet.currency)} paid out`}
                    />
                </div>

                <div className="wl-grid">
                    <form
                        onSubmit={submit}
                        className="pond-panel"
                        data-test="adjust-form"
                    >
                        <div className="pond-panel-header">
                            <div>
                                <h2>Put the balance right</h2>
                                <p>
                                    Add money, or take it away with a minus
                                    amount. The reason is kept in the ledger.
                                </p>
                            </div>
                        </div>
                        <label className="pond-form-label">
                            Amount ($)
                            <input
                                className="pond-input"
                                type="number"
                                step="0.01"
                                inputMode="decimal"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                                placeholder="10.00 or -10.00"
                                data-test="adjust-amount"
                            />
                            <FieldError message={form.errors.amount} />
                        </label>
                        <label className="pond-form-label">
                            Reason
                            <input
                                className="pond-input"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={255}
                                data-test="adjust-reason"
                            />
                            <FieldError message={form.errors.reason} />
                        </label>
                        <div className="pond-save-bar">
                            <button
                                type="submit"
                                className="pond-btn pond-btn-primary"
                                disabled={form.processing}
                                data-test="adjust-submit"
                            >
                                Save adjustment
                            </button>
                        </div>
                    </form>

                    <section className="pond-panel">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Withdrawals</h2>
                            </div>
                        </div>
                        {withdrawals.length ? (
                            <div className="po-earn-list">
                                {withdrawals.map((withdrawal) => (
                                    <div
                                        className="po-earn-row"
                                        key={withdrawal.reference}
                                    >
                                        <span>
                                            {formatMoney(
                                                withdrawal.amount_cents,
                                                withdrawal.currency,
                                            )}{' '}
                                            by {withdrawal.method_label}
                                            <span
                                                className={`po-status is-${withdrawal.status}`}
                                            >
                                                {withdrawal.status}
                                            </span>
                                        </span>
                                        <small className="wl-ref">
                                            {withdrawal.reference}
                                        </small>
                                        <small>
                                            {formatDate(withdrawal.created_at)}
                                        </small>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="pond-muted">No withdrawals.</p>
                        )}
                    </section>
                </div>

                <section className="pond-panel">
                    <div className="pond-panel-header">
                        <div>
                            <h2>Ledger</h2>
                            <p>
                                Every change, newest first. The balance is the
                                sum of these lines.
                            </p>
                        </div>
                    </div>
                    {entries.length ? (
                        <div className="po-earn-list" data-test="ledger">
                            {entries.map((entry) => (
                                <div className="po-earn-row" key={entry.id}>
                                    <span>
                                        {entry.description}
                                        <span className="po-status">
                                            {entry.type.replace(/_/g, ' ')}
                                        </span>
                                    </span>
                                    <strong
                                        className={
                                            entry.amount_cents < 0
                                                ? 'wl-out'
                                                : 'wl-in'
                                        }
                                    >
                                        {entry.amount_cents < 0 ? '−' : '+'}
                                        {formatMoney(
                                            Math.abs(entry.amount_cents),
                                            entry.currency,
                                        )}
                                    </strong>
                                    <small>
                                        {formatDate(entry.created_at)}
                                        {entry.by && ` · by ${entry.by}`}
                                    </small>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<WalletIcon size={25} />}
                            title="Nothing here yet"
                            body="The ledger is empty."
                        />
                    )}
                </section>
            </PondShell>
        </>
    );
}
