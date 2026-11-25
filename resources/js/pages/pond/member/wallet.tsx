import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Clock,
    HandCoins,
    UserRound,
    Wallet as WalletIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { formatDate, formatMoney, StatTile } from '@/components/pond/commerce';
import { EmptyState, PondShell } from '@/components/pond/ui';
import memberWallet from '@/routes/member/wallet';
import { edit as editAccount } from '@/routes/member-account';

type Summary = {
    total_cents: number;
    available_cents: number;
    pending_cents: number;
    withdrawable_cents: number;
    earned_cents: number;
    withdrawn_cents: number;
    requested_cents: number;
    currency: string;
};

type Entry = {
    id: number;
    type: string;
    amount_cents: number;
    currency: string;
    description: string;
    created_at: string | null;
    available_at: string | null;
    is_available: boolean;
};

type Withdrawal = {
    id: number;
    reference: string;
    amount_cents: number;
    currency: string;
    method_label: string;
    status: 'pending' | 'paid' | 'rejected' | 'cancelled';
    note: string | null;
    admin_note: string | null;
    payment_reference: string | null;
    created_at: string | null;
    processed_at: string | null;
};

type Props = {
    wallet: Summary;
    entries: Entry[];
    withdrawals: Withdrawal[];
    rules: {
        minimum_withdrawal_cents: number;
        hold_days: number;
        can_withdraw: boolean;
        needs_birth_date: boolean;
    };
    methods: { value: string; label: string }[];
};

const statusText: Record<Withdrawal['status'], string> = {
    pending: 'Waiting to be paid',
    paid: 'Paid',
    rejected: 'Not paid',
    cancelled: 'Cancelled',
};

const detailsHelp: Record<string, string> = {
    bank: 'Account holder name, bank name, and your account number or IBAN (plus SWIFT or routing number if needed)',
    paypal: 'The email address of your PayPal account',
    other: 'Tell us how you would like to be paid, and the details we need',
};

function FieldError({ message }: { message?: string }) {
    return message ? <p className="pond-field-error">{message}</p> : null;
}

export default function WalletPage({
    wallet,
    entries,
    withdrawals,
    rules,
    methods,
}: Props) {
    const form = useForm({
        amount: '',
        method: methods[0]?.value ?? 'paypal',
        details: '',
        note: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(memberWallet.withdraw.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('amount', 'details', 'note'),
        });
    };

    const withdrawAll = () =>
        form.setData('amount', (wallet.withdrawable_cents / 100).toFixed(2));

    return (
        <>
            <Head title="My wallet" />
            <PondShell
                page="My wallet"
                subheading="What balls tossed to you have earned, and asking to be paid."
                demo={false}
            >
                <div className="pd-stats" data-test="wallet-balances">
                    <StatTile
                        icon={WalletIcon}
                        label="You can withdraw"
                        value={formatMoney(
                            wallet.withdrawable_cents,
                            wallet.currency,
                        )}
                        hint="Available right now"
                        tone="green"
                    />
                    <StatTile
                        icon={Clock}
                        label="On hold"
                        value={formatMoney(
                            Math.max(0, wallet.pending_cents),
                            wallet.currency,
                        )}
                        hint={
                            rules.hold_days > 0
                                ? `New earnings are held for ${rules.hold_days} ${rules.hold_days === 1 ? 'day' : 'days'}`
                                : 'Nothing is held back'
                        }
                    />
                    <StatTile
                        icon={HandCoins}
                        label="Waiting to be paid"
                        value={formatMoney(
                            wallet.requested_cents,
                            wallet.currency,
                        )}
                        hint="Withdrawals you have asked for"
                        tone="pink"
                    />
                    <StatTile
                        icon={WalletIcon}
                        label="Paid out so far"
                        value={formatMoney(
                            wallet.withdrawn_cents,
                            wallet.currency,
                        )}
                        hint={`${formatMoney(wallet.earned_cents, wallet.currency)} earned in total`}
                    />
                </div>

                {wallet.total_cents < 0 && (
                    <div
                        className="pd-notice pd-notice-warn"
                        data-test="negative-wallet"
                    >
                        <Clock size={19} />
                        <div>
                            <strong>Your wallet is below zero</strong>
                            <p>
                                A ball was refunded after you had already
                                withdrawn it. Your next earnings will cover it
                                first.
                            </p>
                        </div>
                    </div>
                )}

                <div className="wl-grid">
                    <section className="pond-panel" data-test="withdraw-panel">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Withdraw</h2>
                                <p>
                                    Ask for money in your wallet to be paid out.
                                    We pay it by hand and tell you when it is
                                    done. The smallest withdrawal is{' '}
                                    {formatMoney(
                                        rules.minimum_withdrawal_cents,
                                        wallet.currency,
                                    )}
                                    .
                                </p>
                            </div>
                        </div>

                        {!rules.can_withdraw ? (
                            <div
                                className="pond-form-note"
                                data-test="withdraw-blocked"
                            >
                                {rules.needs_birth_date ? (
                                    <>
                                        We only pay members who are 18 or older,
                                        so we need your date of birth first.{' '}
                                        <Link
                                            href={editAccount()}
                                            className="pd-link"
                                        >
                                            <UserRound size={14} /> Add it in My
                                            Account
                                        </Link>
                                    </>
                                ) : (
                                    'Withdrawals are for members aged 18 or older.'
                                )}
                            </div>
                        ) : (
                            <form onSubmit={submit}>
                                <label className="pond-form-label">
                                    Amount ($)
                                    <input
                                        className="pond-input"
                                        type="number"
                                        min={
                                            rules.minimum_withdrawal_cents / 100
                                        }
                                        step="0.01"
                                        inputMode="decimal"
                                        value={form.data.amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'amount',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0.00"
                                        data-test="withdraw-amount"
                                    />
                                    {wallet.withdrawable_cents > 0 && (
                                        <button
                                            type="button"
                                            className="pond-text-btn"
                                            onClick={withdrawAll}
                                        >
                                            Withdraw everything available
                                        </button>
                                    )}
                                    <FieldError message={form.errors.amount} />
                                </label>
                                <label className="pond-form-label">
                                    How would you like to be paid?
                                    <select
                                        className="pond-input"
                                        value={form.data.method}
                                        onChange={(event) =>
                                            form.setData(
                                                'method',
                                                event.target.value,
                                            )
                                        }
                                        data-test="withdraw-method"
                                    >
                                        {methods.map((method) => (
                                            <option
                                                key={method.value}
                                                value={method.value}
                                            >
                                                {method.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={form.errors.method} />
                                </label>
                                <label className="pond-form-label">
                                    Where should we send it?
                                    <textarea
                                        className="pond-textarea"
                                        rows={3}
                                        maxLength={1000}
                                        value={form.data.details}
                                        onChange={(event) =>
                                            form.setData(
                                                'details',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            detailsHelp[form.data.method]
                                        }
                                        data-test="withdraw-details"
                                    />
                                    <span className="pond-optional">
                                        Only the admin who pays you will see
                                        this.
                                    </span>
                                    <FieldError message={form.errors.details} />
                                </label>
                                <label className="pond-form-label">
                                    A note{' '}
                                    <span className="pond-optional">
                                        (optional)
                                    </span>
                                    <input
                                        className="pond-input"
                                        maxLength={500}
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError message={form.errors.note} />
                                </label>
                                <div className="pond-save-bar">
                                    <button
                                        type="submit"
                                        className="pond-btn pond-btn-primary"
                                        disabled={form.processing}
                                        data-test="withdraw-submit"
                                    >
                                        <HandCoins size={16} />{' '}
                                        {form.processing
                                            ? 'Sending…'
                                            : 'Ask to be paid'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </section>

                    <section className="pond-panel" data-test="withdrawals">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Your withdrawals</h2>
                                <p>
                                    What you have asked for, and what happened
                                    to it.
                                </p>
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
                                            <strong>
                                                {formatMoney(
                                                    withdrawal.amount_cents,
                                                    withdrawal.currency,
                                                )}
                                            </strong>{' '}
                                            by {withdrawal.method_label}
                                            <span
                                                className={`po-status is-${withdrawal.status}`}
                                            >
                                                {statusText[withdrawal.status]}
                                            </span>
                                        </span>
                                        <small className="wl-ref">
                                            {withdrawal.reference}
                                        </small>
                                        <small>
                                            Asked{' '}
                                            {formatDate(withdrawal.created_at)}
                                            {withdrawal.processed_at &&
                                                ` · decided ${formatDate(withdrawal.processed_at)}`}
                                            {withdrawal.payment_reference &&
                                                ` · reference ${withdrawal.payment_reference}`}
                                        </small>
                                        {withdrawal.status === 'rejected' &&
                                            withdrawal.admin_note && (
                                                <small className="wl-reason">
                                                    Reason:{' '}
                                                    {withdrawal.admin_note}
                                                </small>
                                            )}
                                        {withdrawal.status === 'pending' && (
                                            <button
                                                type="button"
                                                className="pond-text-btn"
                                                onClick={() =>
                                                    router.post(
                                                        memberWallet.withdrawals.cancel.url(
                                                            withdrawal.reference,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                data-test="cancel-withdrawal"
                                            >
                                                Cancel this request
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={<HandCoins size={25} />}
                                title="No withdrawals yet"
                                body="When you ask to be paid, it will be listed here."
                            />
                        )}
                    </section>
                </div>

                <section className="pond-panel" data-test="wallet-activity">
                    <div className="pond-panel-header">
                        <div>
                            <h2>Wallet activity</h2>
                            <p>Every change to your wallet, newest first.</p>
                        </div>
                    </div>
                    {entries.length ? (
                        <div className="po-earn-list">
                            {entries.map((entry) => (
                                <div className="po-earn-row" key={entry.id}>
                                    <span>
                                        {entry.description}
                                        {!entry.is_available &&
                                            entry.amount_cents > 0 && (
                                                <span className="po-status is-pending">
                                                    On hold until{' '}
                                                    {formatDate(
                                                        entry.available_at,
                                                    )}
                                                </span>
                                            )}
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
                                    </small>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<WalletIcon size={25} />}
                            title="Nothing in your wallet yet"
                            body="When someone tosses you a ball, your share is added here."
                        />
                    )}
                </section>
            </PondShell>
        </>
    );
}
