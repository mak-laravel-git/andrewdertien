import { router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CreditCard,
    FlaskConical,
    Search,
    X,
} from 'lucide-react';
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

/**
 * Who the ball is for. Tossing "into the Pond" needs no one in particular; a member can also be
 * searched for by name, username or what they are asking for.
 */
function RecipientPicker({
    members,
    chosen,
    onChoose,
}: {
    members: Recipient[];
    chosen: Recipient | null;
    onChoose: (member: Recipient | null) => void;
}) {
    const [query, setQuery] = useState('');
    const term = query.trim().toLowerCase();
    const matches = members
        .filter(
            (member) =>
                term === '' ||
                member.name.toLowerCase().includes(term) ||
                (member.username ?? '').toLowerCase().includes(term) ||
                (member.ask ?? '').toLowerCase().includes(term),
        )
        .slice(0, 5);

    if (chosen) {
        return (
            <div className="tb-recipient" data-test="toss-recipient">
                <Avatar
                    name={chosen.name}
                    hue={hueFor(chosen.id)}
                    size="avatar-small"
                    src={chosen.avatar_url}
                />
                <div>
                    <strong>{chosen.name}</strong>
                    {chosen.ask && <p>&ldquo;{chosen.ask}&rdquo;</p>}
                </div>
                <button
                    type="button"
                    className="tb-change"
                    onClick={() => onChoose(null)}
                    aria-label={`Choose someone other than ${chosen.name}`}
                >
                    <X size={14} /> Change
                </button>
            </div>
        );
    }

    return (
        <div className="tb-picker" data-test="toss-picker">
            <div className="tb-label">Who is it for? (optional)</div>
            <p className="tb-into-pond">
                Leave this empty to toss it into the Pond, or pick a member.
            </p>
            <label className="tb-search">
                <Search size={16} aria-hidden="true" />
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search members by name or ask"
                    aria-label="Search members"
                    autoComplete="off"
                />
            </label>
            {members.length === 0 ? (
                <p className="tb-none">No other members to choose from yet.</p>
            ) : matches.length === 0 ? (
                <p className="tb-none">
                    No members match &ldquo;{query}&rdquo;.
                </p>
            ) : (
                <ul className="tb-matches">
                    {matches.map((member) => (
                        <li key={member.id}>
                            <button
                                type="button"
                                onClick={() => onChoose(member)}
                            >
                                <Avatar
                                    name={member.name}
                                    hue={hueFor(member.id)}
                                    size="avatar-small"
                                    src={member.avatar_url}
                                />
                                <span>
                                    <strong>{member.name}</strong>
                                    {member.ask && <small>{member.ask}</small>}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

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
    members,
    initialBallId,
    initialCategory,
    onClose,
}: {
    balls: PondCard[];
    recipient?: Recipient | null;
    /** When given, the giver can choose (or change) who the ball is for. */
    members?: Recipient[];
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
    const [chosen, setChosen] = useState<Recipient | null>(recipient);

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
        setError(null);

        // If the browser has not left for the payment page after a while, say so instead of sitting silent.
        const slowTimer = window.setTimeout(() => {
            setIsRedirecting(false);
            setError(
                'The payment page is taking longer than expected. Please try again.',
            );
        }, 15000);

        const stopWaiting = () => {
            window.clearTimeout(slowTimer);
            setIsRedirecting(false);
        };

        router.post(
            memberCheckout.store.url(ball.slug),
            { amount: amountNumber, recipient_id: chosen?.id ?? null },
            {
                // A successful visit means we were sent back here (for example with an error message),
                // not on to the payment page, which leaves this page entirely.
                onSuccess: stopWaiting,
                onError: (errors) => {
                    stopWaiting();
                    setError(
                        errors.amount ??
                            errors.recipient_id ??
                            'We could not start that payment. Please try again.',
                    );
                },
                onNetworkError: () => {
                    stopWaiting();
                    setError(
                        'We could not reach the server. Please check your connection and try again.',
                    );
                },
            },
        );
    };

    return (
        <Modal
            title="Choose a Ball"
            subtitle={
                chosen
                    ? `Toss ${chosen.name} a ball, then choose an amount.`
                    : 'Pick the ball that feels right, then choose an amount.'
            }
            onClose={onClose}
        >
            {members ? (
                <RecipientPicker
                    members={members}
                    chosen={chosen}
                    onChoose={setChosen}
                />
            ) : (
                chosen && (
                    <div className="tb-recipient" data-test="toss-recipient">
                        <Avatar
                            name={chosen.name}
                            hue={hueFor(chosen.id)}
                            size="avatar-small"
                            src={chosen.avatar_url}
                        />
                        <div>
                            <strong>{chosen.name}</strong>
                            {chosen.ask && <p>&ldquo;{chosen.ask}&rdquo;</p>}
                        </div>
                    </div>
                )
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
