import { Head, router } from '@inertiajs/react';
import { Waves } from 'lucide-react';
import { useState } from 'react';
import type { PondCard } from '@/components/pond/commerce';
import { DeepEnd } from '@/components/pond/deep-end';
import { BallGrid, TossBallModal } from '@/components/pond/toss-ball';
import type { Recipient } from '@/components/pond/toss-ball';
import { EmptyState, PondShell } from '@/components/pond/ui';
import memberPonds from '@/routes/member/ponds';

type Props = {
    ponds: PondCard[];
    recipient: Recipient | null;
    categories: { value: string; label: string }[];
    filters: { category: string };
};

export default function MemberBalls({ ponds, recipient, categories, filters }: Props) {
    const [chosen, setChosen] = useState<PondCard | null>(null);
    const chooseCategory = (category: string) =>
        router.get(memberPonds.index.url({ query: { category: category || undefined, to: recipient?.username || undefined } }), {}, { preserveState: true, preserveScroll: true });

    return (
        <>
            <Head title="The Balls" />
            <PondShell
                page="The Balls"
                subheading={recipient ? `Choose a ball to toss ${recipient.name}.` : 'Choose a ball, choose an amount, toss it.'}
                demo={false}
            >
                <div className="pond-filter-chips pd-chips" role="group" aria-label="Filter by category">
                    <button className={filters.category === '' ? 'active' : ''} onClick={() => chooseCategory('')}>
                        All
                    </button>
                    {categories.map((category) => (
                        <button key={category.value} className={filters.category === category.value ? 'active' : ''} onClick={() => chooseCategory(category.value)}>
                            {category.label}
                        </button>
                    ))}
                </div>

                {ponds.length ? (
                    <BallGrid balls={ponds} onChoose={setChosen} />
                ) : (
                    <EmptyState icon={<Waves size={25} />} title="No balls here yet" body="Try another category, or check back soon." />
                )}

                <DeepEnd />

                {chosen && <TossBallModal balls={ponds} recipient={recipient} initialBallId={chosen.id} onClose={() => setChosen(null)} />}
            </PondShell>
        </>
    );
}
