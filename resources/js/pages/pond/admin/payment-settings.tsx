import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    Copy,
    FlaskConical,
    KeyRound,
    PlugZap,
    Save,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { feeBreakdown } from '@/components/pond/fees';
import { paymentModeText } from '@/components/pond/payment-mode';
import type { PaymentMode } from '@/components/pond/payment-mode';
import { Modal, PondShell } from '@/components/pond/ui';
import adminStripe from '@/routes/admin/stripe';

type Props = {
    stripe: {
        enabled: boolean;
        source: 'dashboard' | 'environment' | 'none';
        mode: 'test' | 'live' | null;
        publishable_key: string | null;
        secret_key_hint: string | null;
        webhook_secret_hint: string | null;
    };
    gateway: {
        driver: string;
        mode: PaymentMode;
        is_test_mode: boolean;
        is_ready: boolean;
    };
    webhook: { url: string; events: string[] };
    fees: {
        platform_fee_percent: number;
        minimum_fee: number;
        minimum_toss: number;
        minimum_withdrawal: number;
        wallet_hold_days: number;
        adaptive_pricing: boolean;
        processing_percent: number;
        processing_fixed_cents: number;
        maximum_percent: number;
    };
    canEdit: boolean;
};

/** The mode a Stripe key belongs to, judged by its prefix, as the server does. */
function modeOfKey(key: string): 'test' | 'live' | null {
    if (/^(sk|rk|pk)_test_/.test(key)) {
        return 'test';
    }

    return /^(sk|rk|pk)_live_/.test(key) ? 'live' : null;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="pd-field-error">{message}</p> : null;
}

function ModeStatus({ gateway }: { gateway: Props['gateway'] }) {
    const text = paymentModeText[gateway.mode];

    if (gateway.mode === 'live' && gateway.is_ready) {
        return (
            <div
                className="pd-notice pd-notice-ok"
                data-test="payment-mode-status"
            >
                <CircleCheck size={19} />
                <div>
                    <strong>Live mode</strong>
                    <p>
                        Stripe is using live keys. Members are charged real
                        money and payments are recorded automatically.
                    </p>
                </div>
            </div>
        );
    }

    if (gateway.is_test_mode) {
        return (
            <div
                className="pd-notice pd-notice-warn"
                data-test="payment-mode-status"
            >
                <FlaskConical size={19} />
                <div>
                    <strong>Test mode</strong>
                    <p>{text.detail}</p>
                </div>
            </div>
        );
    }

    return (
        <div
            className="pd-notice pd-notice-error"
            data-test="payment-mode-status"
        >
            <CircleAlert size={19} />
            <div>
                <strong>Payments are not set up</strong>
                <p>{text.detail}</p>
            </div>
        </div>
    );
}

function DetectedMode({ value }: { value: string }) {
    const mode = modeOfKey(value);

    if (!value || !mode) {
        return null;
    }

    return (
        <span className={`pond-mode-chip pond-mode-chip-${mode}`}>
            {mode === 'test' ? 'Test mode key' : 'Live mode key'}
        </span>
    );
}

/** The platform fee and toss limits. They apply to tosses made after saving. */
function FeesPanel({
    fees,
    canEdit,
}: {
    fees: Props['fees'];
    canEdit: boolean;
}) {
    const form = useForm({
        platform_fee_percent: String(fees.platform_fee_percent),
        minimum_fee: String(fees.minimum_fee),
        minimum_toss: String(fees.minimum_toss),
        minimum_withdrawal: String(fees.minimum_withdrawal),
        wallet_hold_days: String(fees.wallet_hold_days),
        adaptive_pricing: fees.adaptive_pricing,
    });

    const exampleCents = 5000;
    const percent = Number(form.data.platform_fee_percent);
    const example = feeBreakdown(exampleCents, {
        percent: Number.isFinite(percent) ? percent : 0,
        minimum_fee_cents: Math.round(Number(form.data.minimum_fee) * 100) || 0,
        processing_percent: fees.processing_percent,
        processing_fixed_cents: fees.processing_fixed_cents,
    });
    const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(adminStripe.fees.update(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="pond-panel" data-test="fees-panel">
            <div className="pond-panel-header">
                <div>
                    <h2>Fees &amp; limits</h2>
                    <p>
                        The platform keeps a share of every ball tossed to a
                        member. A ball tossed into the Pond with no member goes
                        entirely to the platform. Changes apply to new tosses
                        only.
                    </p>
                </div>
            </div>

            <div className="fl-grid">
                <label className="pond-form-label">
                    Platform fee (%)
                    <input
                        className="pond-input"
                        type="number"
                        min={0}
                        max={fees.maximum_percent}
                        step="0.01"
                        inputMode="decimal"
                        value={form.data.platform_fee_percent}
                        onChange={(event) =>
                            form.setData(
                                'platform_fee_percent',
                                event.target.value,
                            )
                        }
                        disabled={!canEdit}
                        data-test="platform-fee-percent"
                    />
                    <span className="fl-hint">
                        0 to {fees.maximum_percent}%
                    </span>
                    <FieldError message={form.errors.platform_fee_percent} />
                </label>
                <label className="pond-form-label">
                    Minimum fee ($)
                    <input
                        className="pond-input"
                        type="number"
                        min={0}
                        step="0.01"
                        inputMode="decimal"
                        value={form.data.minimum_fee}
                        onChange={(event) =>
                            form.setData('minimum_fee', event.target.value)
                        }
                        disabled={!canEdit}
                        data-test="minimum-fee"
                    />
                    <span className="fl-hint">
                        Used when the percentage is smaller than this.
                    </span>
                    <FieldError message={form.errors.minimum_fee} />
                </label>
                <label className="pond-form-label">
                    Smallest toss ($)
                    <input
                        className="pond-input"
                        type="number"
                        min={1}
                        step="0.01"
                        inputMode="decimal"
                        value={form.data.minimum_toss}
                        onChange={(event) =>
                            form.setData('minimum_toss', event.target.value)
                        }
                        disabled={!canEdit}
                        data-test="minimum-toss"
                    />
                    <span className="fl-hint">
                        The least a ball can be tossed for.
                    </span>
                    <FieldError message={form.errors.minimum_toss} />
                </label>
            </div>

            <div className="fl-grid">
                <label className="pond-form-label">
                    Smallest withdrawal ($)
                    <input
                        className="pond-input"
                        type="number"
                        min={1}
                        step="0.01"
                        inputMode="decimal"
                        value={form.data.minimum_withdrawal}
                        onChange={(event) =>
                            form.setData(
                                'minimum_withdrawal',
                                event.target.value,
                            )
                        }
                        disabled={!canEdit}
                        data-test="minimum-withdrawal"
                    />
                    <span className="fl-hint">
                        The least a member can ask to be paid out.
                    </span>
                    <FieldError message={form.errors.minimum_withdrawal} />
                </label>
                <label className="pond-form-label">
                    Hold new earnings for (days)
                    <input
                        className="pond-input"
                        type="number"
                        min={0}
                        max={90}
                        step="1"
                        inputMode="numeric"
                        value={form.data.wallet_hold_days}
                        onChange={(event) =>
                            form.setData('wallet_hold_days', event.target.value)
                        }
                        disabled={!canEdit}
                        data-test="wallet-hold-days"
                    />
                    <span className="fl-hint">
                        0 means earnings can be withdrawn at once. A few days
                        protects you from refunds and disputes.
                    </span>
                    <FieldError message={form.errors.wallet_hold_days} />
                </label>
            </div>

            <label className="pond-toggle-line">
                <span>
                    Let buyers abroad pay in their own currency
                    <span className="fl-hint">
                        Balls stay priced in dollars and you always receive
                        dollars. Stripe converts, and the buyer pays
                        Stripe&rsquo;s conversion fee (about 2 to 4%), not you
                        or the member.
                    </span>
                </span>
                <input
                    type="checkbox"
                    checked={form.data.adaptive_pricing}
                    disabled={!canEdit}
                    onChange={(event) =>
                        form.setData('adaptive_pricing', event.target.checked)
                    }
                    data-test="adaptive-pricing"
                />
                <span className="pond-toggle-ui" />
            </label>
            <FieldError message={form.errors.adaptive_pricing} />

            <div className="fl-example" data-test="fee-example">
                Example: a <strong>{money(exampleCents)}</strong> ball tossed to
                a member. The platform keeps{' '}
                <strong>{money(example.platformFeeCents)}</strong>, Stripe takes
                about {money(example.processingEstimateCents)} to process it,
                and the member receives about{' '}
                <strong>{money(example.memberEstimateCents)}</strong>.
            </div>

            {canEdit && (
                <div className="pd-settings-actions">
                    <button
                        type="submit"
                        className="pond-btn pond-btn-primary"
                        disabled={form.processing}
                        data-test="save-fees"
                    >
                        <Save size={16} />{' '}
                        {form.processing ? 'Saving…' : 'Save fees & limits'}
                    </button>
                </div>
            )}
        </form>
    );
}

export default function PaymentSettings({
    stripe,
    gateway,
    webhook,
    fees,
    canEdit,
}: Props) {
    const [isRemoving, setIsRemoving] = useState(false);
    const form = useForm({
        stripe_enabled: stripe.enabled,
        stripe_publishable_key: stripe.publishable_key ?? '',
        stripe_secret_key: '',
        stripe_webhook_secret: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(adminStripe.update(), {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('stripe_secret_key', 'stripe_webhook_secret'),
        });
    };

    const copyWebhookUrl = () => {
        navigator.clipboard
            ?.writeText(webhook.url)
            .then(() => toast.success('Webhook URL copied'))
            .catch(() => toast.info(webhook.url));
    };

    const checkConnection = () =>
        router.post(adminStripe.test.url(), {}, { preserveScroll: true });

    const removeKeys = () => {
        router.delete(adminStripe.destroy.url(), {
            preserveScroll: true,
            onFinish: () => setIsRemoving(false),
        });
    };

    return (
        <>
            <Head title="Payment settings" />
            <PondShell
                page="Payment settings"
                subheading="Connect Stripe to take payments. Keys are stored encrypted and never shown again."
                demo={false}
            >
                <ModeStatus gateway={gateway} />

                {stripe.source === 'environment' && (
                    <div className="pd-notice pd-notice-warn">
                        <KeyRound size={19} />
                        <div>
                            <strong>
                                Keys are coming from the server settings
                            </strong>
                            <p>
                                Stripe is currently set up in the server&rsquo;s
                                .env file. Save your keys below to manage them
                                from here instead.
                            </p>
                        </div>
                    </div>
                )}

                <form onSubmit={submit} className="pd-settings-grid">
                    <section className="pond-panel">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Stripe keys</h2>
                                <p>
                                    Find them in the Stripe dashboard under
                                    Developers, then API keys. Test and live
                                    keys each start differently, and the site
                                    works out the mode from the secret key.
                                </p>
                            </div>
                        </div>

                        <label className="pond-toggle-line">
                            <span>Take payments with Stripe</span>
                            <input
                                type="checkbox"
                                checked={form.data.stripe_enabled}
                                disabled={!canEdit}
                                onChange={(event) =>
                                    form.setData(
                                        'stripe_enabled',
                                        event.target.checked,
                                    )
                                }
                                data-test="stripe-enabled"
                            />
                            <span className="pond-toggle-ui" />
                        </label>
                        <FieldError message={form.errors.stripe_enabled} />

                        <label className="pond-form-label">
                            Publishable key{' '}
                            <span className="pond-optional">(optional)</span>
                            <input
                                className="pond-input"
                                value={form.data.stripe_publishable_key}
                                onChange={(event) =>
                                    form.setData(
                                        'stripe_publishable_key',
                                        event.target.value,
                                    )
                                }
                                placeholder="pk_test_… or pk_live_…"
                                autoComplete="off"
                                spellCheck={false}
                                disabled={!canEdit}
                                name="stripe_publishable_key"
                            />
                            <DetectedMode
                                value={form.data.stripe_publishable_key}
                            />
                            <FieldError
                                message={form.errors.stripe_publishable_key}
                            />
                        </label>

                        <label className="pond-form-label">
                            Secret key
                            <input
                                className="pond-input"
                                type="password"
                                value={form.data.stripe_secret_key}
                                onChange={(event) => {
                                    const value = event.target.value;

                                    form.setData((data) => ({
                                        ...data,
                                        stripe_secret_key: value,
                                        stripe_enabled: value
                                            ? true
                                            : data.stripe_enabled,
                                    }));
                                }}
                                placeholder={
                                    stripe.secret_key_hint ??
                                    'sk_test_… or sk_live_…'
                                }
                                autoComplete="new-password"
                                spellCheck={false}
                                disabled={!canEdit}
                                name="stripe_secret_key"
                            />
                            {stripe.secret_key_hint &&
                                !form.data.stripe_secret_key && (
                                    <span className="pond-optional">
                                        Saved: {stripe.secret_key_hint}. Leave
                                        blank to keep it.
                                    </span>
                                )}
                            <DetectedMode
                                value={
                                    form.data.stripe_secret_key ||
                                    (stripe.mode === 'test'
                                        ? 'sk_test_'
                                        : stripe.mode === 'live'
                                          ? 'sk_live_'
                                          : '')
                                }
                            />
                            <FieldError
                                message={form.errors.stripe_secret_key}
                            />
                        </label>

                        <label className="pond-form-label">
                            Webhook signing secret{' '}
                            <span className="pond-optional">(recommended)</span>
                            <input
                                className="pond-input"
                                type="password"
                                value={form.data.stripe_webhook_secret}
                                onChange={(event) =>
                                    form.setData(
                                        'stripe_webhook_secret',
                                        event.target.value,
                                    )
                                }
                                placeholder={
                                    stripe.webhook_secret_hint ?? 'whsec_…'
                                }
                                autoComplete="new-password"
                                spellCheck={false}
                                disabled={!canEdit}
                                name="stripe_webhook_secret"
                            />
                            {stripe.webhook_secret_hint &&
                                !form.data.stripe_webhook_secret && (
                                    <span className="pond-optional">
                                        Saved: {stripe.webhook_secret_hint}.
                                        Leave blank to keep it.
                                    </span>
                                )}
                            <FieldError
                                message={form.errors.stripe_webhook_secret}
                            />
                        </label>

                        {canEdit && (
                            <div className="pd-settings-actions">
                                <button
                                    type="submit"
                                    className="pond-btn pond-btn-primary"
                                    disabled={form.processing}
                                    data-test="save-stripe-settings"
                                >
                                    <Save size={16} />{' '}
                                    {form.processing
                                        ? 'Checking with Stripe…'
                                        : 'Save settings'}
                                </button>
                                <button
                                    type="button"
                                    className="pond-btn pond-btn-outline-dark"
                                    onClick={checkConnection}
                                    disabled={
                                        form.processing ||
                                        !stripe.secret_key_hint
                                    }
                                >
                                    <PlugZap size={16} /> Test connection
                                </button>
                                {stripe.source === 'dashboard' && (
                                    <button
                                        type="button"
                                        className="pond-text-btn pd-danger-text"
                                        onClick={() => setIsRemoving(true)}
                                    >
                                        <Trash2 size={14} /> Remove keys
                                    </button>
                                )}
                            </div>
                        )}
                    </section>

                    <section className="pond-panel">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Webhook</h2>
                                <p>
                                    Members pay on Stripe&rsquo;s own page and
                                    are sent back here once done, which confirms
                                    the payment. The webhook is a safety net: it
                                    records the payment even if a member closes
                                    their browser before returning.
                                </p>
                            </div>
                        </div>

                        <ol className="pd-steps">
                            <li>
                                In the Stripe dashboard open Developers, then
                                Webhooks, then Add endpoint.
                            </li>
                            <li>
                                Paste this endpoint URL:
                                <span className="pd-copy-line">
                                    <code>{webhook.url}</code>
                                    <button
                                        type="button"
                                        className="pond-icon-btn"
                                        onClick={copyWebhookUrl}
                                        aria-label="Copy webhook URL"
                                    >
                                        <Copy size={16} />
                                    </button>
                                </span>
                            </li>
                            <li>
                                Select these events:
                                <ul className="pd-event-list">
                                    {webhook.events.map((event) => (
                                        <li key={event}>
                                            <code>{event}</code>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                            <li>
                                Copy the endpoint&rsquo;s signing secret (it
                                starts with whsec_) into the field on the left.
                            </li>
                        </ol>

                        <div className="pond-form-note">
                            <ShieldCheck size={15} /> Secret keys are encrypted
                            before they are saved and are never sent back to
                            your browser.
                        </div>
                    </section>
                </form>

                <FeesPanel fees={fees} canEdit={canEdit} />

                {isRemoving && (
                    <Modal
                        title="Remove Stripe keys?"
                        subtitle="Members will not be able to pay until Stripe is connected again."
                        onClose={() => setIsRemoving(false)}
                    >
                        <button
                            className="pond-btn pond-btn-primary pond-wide"
                            onClick={removeKeys}
                            data-test="confirm-remove-keys"
                        >
                            <Trash2 size={16} /> Remove keys
                        </button>
                    </Modal>
                )}
            </PondShell>
        </>
    );
}
