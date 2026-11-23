import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Settings2 } from 'lucide-react';
import { PondArt } from '@/components/pond/commerce';
import type { PondCard } from '@/components/pond/commerce';
import { PublicShell } from '@/components/pond/public-shell';
import { register } from '@/routes';
import adminPonds from '@/routes/admin/ponds';
import memberPonds from '@/routes/member/ponds';
import { index } from '@/routes/ponds';

export default function PondShow({ pond }: { pond: PondCard }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <>
            <Head title={pond.name} />
            <PublicShell title={pond.name} intro={pond.tagline ?? undefined}>
                <Link href={index()} className="pd-back">
                    <ArrowLeft size={16} /> All balls
                </Link>
                <article className="pd-pond-detail">
                    <PondArt pond={pond} />
                    <div>
                        <span className={`pd-category pd-category-${pond.category}`}>{pond.category_label}</span>
                        {pond.description && <p>{pond.description}</p>}
                        <div className="pd-summary">
                            <span>Choose your amount at checkout</span>
                            <strong>From $1</strong>
                        </div>
                        {user?.is_admin ? (
                            <Link href={adminPonds.index()} className="askupon-btn askupon-btn-navy askupon-btn-lg">
                                <Settings2 size={18} /> Manage balls
                            </Link>
                        ) : user ? (
                            <Link href={memberPonds.index()} className="askupon-btn askupon-btn-pink askupon-btn-lg">
                                Toss this ball <ArrowRight size={18} />
                            </Link>
                        ) : (
                            <Link href={register()} className="askupon-btn askupon-btn-pink askupon-btn-lg">
                                Join the Pond to toss one <ArrowRight size={18} />
                            </Link>
                        )}
                    </div>
                </article>
            </PublicShell>
        </>
    );
}
