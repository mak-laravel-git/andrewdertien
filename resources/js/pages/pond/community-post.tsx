import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CommunityPostCard from '@/components/pond/community-post';
import type { FeedPost } from '@/components/pond/community-post';
import { PondShell } from '@/components/pond/ui';
import community from '@/routes/community';

export default function CommunityPostPage({ post }: { post: FeedPost }) {
    return (
        <>
            <Head title={`${post.author.name} on The Pond`} />
            <PondShell
                page="The Pond"
                subheading={`A moment shared by ${post.author.name}.`}
                demo={false}
            >
                <div className="cm-single">
                    <Link
                        href={community.index()}
                        className="pond-text-btn cm-back"
                    >
                        <ArrowLeft size={16} /> Back to The Pond
                    </Link>
                    <CommunityPostCard post={post} />
                </div>
            </PondShell>
        </>
    );
}
