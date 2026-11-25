import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Gem } from 'lucide-react';
import { formatDate, formatMoney, PondArt } from '@/components/pond/commerce';
import type { PaymentRow, PondCard } from '@/components/pond/commerce';
import { EmptyState, PondShell } from '@/components/pond/ui';
import memberPonds from '@/routes/member/ponds';

type BallRow = PaymentRow & { pond: PondCard };

type Props = {
    tossed: BallRow[];
    collected: BallRow[];
};

function BallCards({
    rows,
    caption,
    received = false,
}: {
    rows: BallRow[];
    caption: (row: BallRow) => string;
    received?: boolean;
}) {
    return (
        <div className="pd-pond-grid">
            {rows.map((row) => (
                <article className="pd-pond-card" key={row.reference}>
                    <PondArt pond={row.pond} />
                    <div className="pd-pond-card-body">
                        <span
                            className={`pd-category pd-category-${row.pond.category}`}
                        >
                            {row.pond.category_label}
                        </span>
                        <h3>{row.pond_name}</h3>
                        <div className="pd-pond-card-foot">
                            <small>{caption(row)}</small>
                            <strong>
                                {formatMoney(row.amount_cents, row.currency)}
                            </strong>
                        </div>
                        {received && row.net_cents !== null && (
                            <small
                                className="po-received"
                                data-test="ball-received"
                            >
                                You received{' '}
                                {formatMoney(row.net_cents, row.currency)}
                            </small>
                        )}
                    </div>
                </article>
            ))}
        </div>
    );
}

export default function MyBalls({ tossed, collected }: Props) {
    return (
        <>
            <Head title="My Balls" />
            <PondShell
                page="My Balls"
                subheading="The balls you have collected, and the ones you have tossed."
                demo={false}
                action={
                    <Link
                        href={memberPonds.index()}
                        className="pond-btn pond-btn-primary"
                    >
                        Toss another <ArrowRight size={16} />
                    </Link>
                }
            >
                <section className="pd-section">
                    <div className="pond-panel-header">
                        <h2>Balls collected</h2>
                    </div>
                    {collected.length ? (
                        <BallCards
                            received
                            rows={collected}
                            caption={(row) =>
                                `From ${row.buyer_name} · ${formatDate(row.paid_at)}`
                            }
                        />
                    ) : (
                        <div className="pond-panel">
                            <EmptyState
                                icon={<Gem size={25} />}
                                title="No balls collected yet"
                                body="When someone tosses you a ball, it will land here."
                            />
                        </div>
                    )}
                </section>

                <section className="pd-section">
                    <div className="pond-panel-header">
                        <h2>Balls I&rsquo;ve tossed</h2>
                    </div>
                    {tossed.length ? (
                        <BallCards
                            rows={tossed}
                            caption={(row) =>
                                `${row.recipient_name ? `To ${row.recipient_name}` : 'Into the Pond'} · ${formatDate(row.paid_at)}`
                            }
                        />
                    ) : (
                        <div className="pond-panel">
                            <EmptyState
                                icon={<Gem size={25} />}
                                title="No balls tossed yet"
                                body="Choose a ball and toss it to someone, and it will be listed here."
                            />
                        </div>
                    )}
                </section>
            </PondShell>
        </>
    );
}
