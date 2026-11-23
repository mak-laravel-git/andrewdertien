import { Head } from '@inertiajs/react';
import { Waves } from 'lucide-react';
import type { PondCard } from '@/components/pond/commerce';
import { DeepEnd } from '@/components/pond/deep-end';
import { PublicPondGrid } from '@/components/pond/public-pond-grid';
import { PublicShell } from '@/components/pond/public-shell';
import { EmptyState } from '@/components/pond/ui';

export default function PondsIndex({ ponds }: { ponds: PondCard[] }) {
    return (
        <>
            <Head title="The Balls" />
            <PublicShell title="Choose a Ball" intro="Choose a ball. Choose an amount. Toss it.">
                {ponds.length ? <PublicPondGrid ponds={ponds} /> : <EmptyState icon={<Waves size={25} />} title="The water is still" body="New balls are on their way. Check back soon." />}
                <DeepEnd />
            </PublicShell>
        </>
    );
}
