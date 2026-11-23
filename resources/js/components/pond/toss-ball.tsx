import { router } from '@inertiajs/react';
import { ArrowRight, Check, CreditCard, FlaskConical } from 'lucide-react';
import { useState } from 'react';
import { formatMoney, PondArt } from '@/components/pond/commerce';
import type { PondCard } from '@/components/pond/commerce';
import { usePaymentMode } from '@/components/pond/payment-mode';
import { Avatar, hueFor, Modal } from '@/components/pond/ui';
import memberCheckout from '@/routes/member/checkout';

/** The person a ball is being tossed to, with what they are asking for. */
export type Recipient = {
    id: number;
    name: string;
    username: string | null;
    avatar_url: string | null;
    ask: string | null;
};

function dollars(cents: number): string {
    return String(cents / 100);
}

/**
 * Choose a Ball, choose an amount, toss it. The ball's own price is only the suggested amount:
 * any ball can be any amount from a dollar.
 */
export function TossBallModal({
    balls,
    recipient = null,
    initialBallId,
    initialCategory,
    onClose,
}: {
    balls: PondCard[];
    recipient?: Recipient | null;
    initialBallId?: number;
    initialCategory?: string;
    onClose: () => void;
}) {
    const payments = usePaymentMode();
    const firstBall =
        balls.find((ball) => ball.id === initialBallId) ??
        balls.find((ball) => ball.category === initialCategory) ??
        balls[0];
    const [ball, setBall] = useState<PondCard | undefined>(firstBall);
    const [amount, setAmount] = useState(
        firstBall ? dollars(firstBall.price_cents) : '',
    );
    const [isOther, setIsOther] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isRedirecting, setIsRedirecting] = useState(false);

    if (!ball) {
        return (
            <Modal
                title="Choose a Ball"
                subtitle="There are no balls to toss just yet. Check back soon."
                onClose={onClose}
            >
                <button
                    className="pond-btn pond-btn-primary pond-wide"
                    onClick={onClose}
                >
                    Close
                </button>
            </Modal>
        );
    }

    const chooseBall = (next: PondCard) => {
        setBall(next);
        setAmount(dollars(next.price_cents));
        setIsOther(false);
        setError(null);
    };

    const chooseAmount = (cents: number) => {
        setAmount(dollars(cents));
        setIsOther(false);
        setError(null);
    };

    const amountNumber = Number(amount);
    const isValid =
        amount !== '' && Number.isFinite(amountNumber) && amountNumber >= 1;
    const shownError =
        error ??
        (amount !== '' && !isValid
            ? 'A ball can be any amount from $1.'
            : null);

    const toss = () => {
        if (!isValid) {
            setError('A ball can be any amount from $1.');

            return;
        }

        setIsRedirecting(true);
        router.post(
            memberCheckout.store.url(ball.slug),
            { amount: amountNumber, recipient_id: recipient?.id ?? null },
            {
                onError: (errors) =>
                    setError(
                        errors.amount ??
                            errors.recipient_id ??
                            'We could not start that payment. Please try again.',
                    ),
                onFinish: () => setIsRedirecting(false),
            },
        );
    };

    return (
        <Modal
            title="Choose a Ball"
            subtitle={
                recipient
                    ? `Toss ${recipient.name} a ball, then choose an amount.`
                    : 'Pick the ball that feels right, then choose an amount.'
            }
            onClose={onClose}
        >
            {recipient && (
                <div className="tb-recipient" data-test="toss-recipient">
                    <Avatar
                        name={recipient.name}
                        hue={hueFor(recipient.id)}
                        size="avatar-small"
                        src={recipient.avatar_url}
                    />
                    <div>
                        <strong>{recipient.name}</strong>
                        {recipient.ask && <p>&ldquo;{recipient.ask}&rdquo;</p>}
                    </div>
                </div>
            )}

            <div className="tb-label">1. Choose a Ball</div>
            <div
                className="tb-balls"
                role="radiogroup"
                aria-label="Choose a ball"
            >
                {balls.map((option) => (
                    <button
                        type="button"
                        key={option.id}
                        role="radio"
                        aria-checked={option.id === ball.id}
                        className={`tb-ball ${option.id === ball.id ? 'selected' : ''}`}
                        onClick={() => chooseBall(option)}
                    >
                        <PondArt pond={option} />
                        <strong>{option.name}</strong>
                    </button>
                ))}
            </div>
            {ball.tagline && <p className="tb-tagline">{ball.tagline}</p>}

            <div className="tb-label">2. Choose an Amount</div>
            <div
                className="tb-amounts"
                role="radiogroup"
                aria-label="Choose an amount"
            >
                {ball.suggested_amounts.map((cents) => {
                    const isSelected =
                        !isOther && Number(amount) === cents / 100;

                    return (
                        <button
                            type="button"
                            key={cents}
                            role="radio"
                            aria-checked={isSelected}
                            className={`tb-amount ${isSelected ? 'selected' : ''}`}
                            onClick={() => chooseAmount(cents)}
                        >
                            {formatMoney(cents, ball.currency).replace(
                                /\.00$/,
                                '',
                            )}
                            {cents === ball.price_cents && (
                                <small>Suggested</small>
                            )}
                        </button>
                    );
                })}
                <button
                    type="button"
                    role="radio"
                    aria-checked={isOther}
                    className={`tb-amount ${isOther ? 'selected' : ''}`}
                    onClick={() => {
                        setIsOther(true);
                        setAmount('');
                    }}
                >
                    Other
                </button>
            </div>

            {isOther && (
                <label className="tb-other">
                    <span>$</span>
                    <input
                        type="number"
                        inputMode="decimal"
                        min={1}
                        step="0.01"
                        autoFocus
                        value={amount}
                        onChange={(event) => {
                            setAmount(event.target.value);
                            setError(null);
                        }}
                        placeholder="Any amount from 1"
                        aria-label="Amount in dollars"
                    />
                </label>
            )}

            {shownError && (
                <p className="tb-error" role="alert">
                    {shownError}
                </p>
            )}

            {payments?.isTestMode && (
                <div
                    className="pond-form-note pd-test-note"
                    data-test="checkout-test-note"
                >
                    <FlaskConical size={15} />
                    {payments.mode === 'test' ? (
                        <span>
                            Test mode: you will not be charged. On the payment
                            page use the test card{' '}
                            <strong>4242 4242 4242 4242</strong> with any future
                            expiry date and any 3-digit CVC.
                        </span>
                    ) : (
                        <span>
                            Test mode: this checkout is simulated and you will
                            not be charged.
                        </span>
                    )}
                </div>
            )}

            <button
                className="pond-btn pond-btn-primary pond-wide"
                onClick={toss}
                disabled={isRedirecting || !isValid}
            >
                {isRedirecting ? (
                    <>
                        <CreditCard size={17} /> Opening secure checkout...
                    </>
                ) : (
                    <>
                        <Check size={17} /> Toss It
                        {isValid
                            ? ` · ${formatMoney(Math.round(amountNumber * 100), ball.currency)}`
                            : ''}{' '}
                        <ArrowRight size={16} />
                    </>
                )}
            </button>
        </Modal>
    );
}

/**
 * The ball types as a grid. Prices are left off on purpose: people pick the ball that
 * feels right first, and decide what to spend afterwards.
 */
export function BallGrid({
    balls,
    onChoose,
    actionLabel = 'Choose',
}: {
    balls: PondCard[];
    onChoose: (ball: PondCard) => void;
    actionLabel?: string;
}) {
    return (
        <div className="pd-pond-grid">
            {balls.map((ball) => (
                <article className="pd-pond-card" key={ball.id}>
                    <PondArt pond={ball} />
                    <div className="pd-pond-card-body">
                        <span
                            className={`pd-category pd-category-${ball.category}`}
                        >
                            {ball.category_label}
                        </span>
                        <h3>{ball.name}</h3>
                        {ball.tagline && <p>{ball.tagline}</p>}
                        <div className="pd-pond-card-foot">
                            <small>Any amount from $1</small>
                            <button
                                className="pond-btn pond-btn-primary pd-btn-small"
                                onClick={() => onChoose(ball)}
                            >
                                {actionLabel} <ArrowRight size={15} />
                            </button>
                        </div>
                    </div>
                </article>
            ))}
        </div>
    );
}
