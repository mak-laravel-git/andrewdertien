import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, CreditCard, Gem, HandHeart, ReceiptText, Users } from 'lucide-react';
import { useState } from 'react';
import { formatMoney, StatTile } from '@/components/pond/commerce';
import type { PondCard } from '@/components/pond/commerce';
import { TossBallModal } from '@/components/pond/toss-ball';
import type { Recipient } from '@/components/pond/toss-ball';
import { Avatar, EmptyState, hueFor, PondShell } from '@/components/pond/ui';
import memberPayments from '@/routes/member/payments';
import { edit } from '@/routes/member-profile';
import { index as explore, show } from '@/routes/members';

type Props = {
    stats: { collected: number; asks: number; given_cents: number; completed_payments: number };
    currency: string;
    asks: Recipient[];
    balls: PondCard[];
};

export default function MemberDashboard({ stats, currency, asks, balls }: Props) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];
    const [tossing, setTossing] = useState<Recipient | null>(null);

    return (
        <>
            <Head title="My dashboard" />
            <PondShell page={`Welcome back, ${firstName}`} subheading="Your balls and payments, all in one place." demo={false}>
                <div className="pond-dashboard-hero">
                    <div className="pond-dashboard-hero-text">
                        <span className="pond-hero-label">✦ YOUR LITTLE CORNER OF THE UNIVERSE</span>
                        <h2>
                            Big dreams begin
                            <br />
                            with <em>little ripples.</em>
                        </h2>
                        <p>Give a ball, share an ask, and keep the balls rolling.</p>
                        <div>
                            <Link href={explore()} className="pond-btn pond-btn-light">
                                Explore the Pond <ArrowRight size={16} />
                            </Link>
                            <Link href={edit()} className="pond-btn-ghost-white">
                                Share an Ask <ArrowUpRight size={16} />
                            </Link>
                        </div>
                    </div>
                    <img className="pond-dashboard-hero-ball hero-ball-main" src="/askupon/balls/golf-blue.svg" alt="" />
                    <img className="pond-dashboard-hero-ball hero-ball-secondary" src="/askupon/balls/golf-gold.svg" alt="" />
                    <div className="pond-dashboard-circle" />
                </div>

                <div className="pd-stats">
                    <StatTile icon={Gem} label="Balls collected" value={String(stats.collected)} />
                    <StatTile icon={Users} label="Asks to explore" value={String(stats.asks)} tone="green" />
                    <StatTile icon={CreditCard} label="Total given" value={formatMoney(stats.given_cents, currency)} tone="pink" />
                    <StatTile icon={ReceiptText} label="Completed payments" value={String(stats.completed_payments)} />
                </div>
                <p className="tb-history-link">
                    Payment details live in <Link href={memberPayments.index()}>Payment History</Link>.
                </p>

                <section className="pd-section">
                    <div className="pond-panel-header">
                        <h2>Asks from the Pond</h2>
                        <Link href={explore()}>
                            See everyone <ArrowRight size={16} />
                        </Link>
                    </div>
                    {asks.length ? (
                        <div className="pd-pond-grid">
                            {asks.map((member) => (
                                <article className="pd-pond-card tb-ask" key={member.id}>
                                    <div className="pd-pond-card-body">
                                        <div className="tb-ask-head">
                                            <Avatar name={member.name} hue={hueFor(member.id)} size="avatar-small" src={member.avatar_url} />
                                            {member.username ? (
                                                <Link href={show(member.username)} className="pd-link">
                                                    <strong>{member.name}</strong>
                                                </Link>
                                            ) : (
                                                <strong>{member.name}</strong>
                                            )}
                                        </div>
                                        <p className="tb-ask-text">&ldquo;{member.ask}&rdquo;</p>
                                        <div className="pd-pond-card-foot">
                                            <span />
                                            <button className="pond-btn pond-btn-primary pd-btn-small" onClick={() => setTossing(member)}>
                                                Toss a Ball <ArrowRight size={15} />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="pond-panel">
                            <EmptyState icon={<HandHeart size={25} />} title="No asks yet" body="When members share what they are dreaming about, you can toss them a ball here." />
                        </div>
                    )}
                </section>

                {tossing && <TossBallModal balls={balls} recipient={tossing} onClose={() => setTossing(null)} />}
            </PondShell>
        </>
    );
}
