import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Gift, HandHeart, Play, UsersRound } from 'lucide-react';
import { DuskPondScene, FriendswoodSign } from '@/components/pond/dusk-scene';
import { useScrollReveal } from '@/components/pond/pond-motion';
import { PublicNavActions } from '@/components/pond/public-shell';
import { Brand, PondMark } from '@/components/pond/ui';
import { dashboard, register } from '@/routes';
import { index as pondsIndex } from '@/routes/ponds';

const ballTiles = [
    { title: 'Dreams', tone: 'dreams', image: '/askupon/balls/golf-blue.svg' },
    { title: 'Fun', tone: 'fun', image: '/askupon/balls/golf-purple.svg' },
    {
        title: 'Treats',
        tone: 'treats',
        image: '/askupon/balls/golf-orange.svg',
    },
    { title: 'Boost', tone: 'boost', image: '/askupon/balls/golf-white.svg' },
    {
        title: 'Support a Creator',
        tone: 'creator',
        image: '/askupon/balls/golf-aqua.svg',
    },
    {
        title: 'Flirt',
        caption: 'Fluttery feelings',
        tone: 'flirt',
        image: '/askupon/balls/golf-flirt.webp',
    },
];

const stories = [
    {
        title: 'The Boy and the Pond',
        image: '/askupon/story/boy.jpg',
        alt: 'Andrew as a small boy in a striped shirt',
        position: '50% 35%',
        paragraphs: [
            'At 10, I snuck out at night, met my friends, collected golf balls from a pond, and sold them back to golfers.',
            'Making money was fun. It was an adventure!',
            'Then came adulthood. And getting a real job.',
        ],
    },
    {
        title: 'The Hard Road',
        image: '/askupon/story/book-cover.jpg',
        alt: 'Cover of Andrew Dertien’s book Uber Dick: Down and Out in San Francisco',
        position: '50% 8%',
        paragraphs: [
            'Years later came loss, struggle, and rebuilding while supporting my kids.',
        ],
    },
    {
        title: 'My Son’s Vision',
        image: '/askupon/story/james.jpg',
        alt: 'James, Andrew’s son',
        position: '45% 30%',
        paragraphs: [
            'My son James has a dream. He wants to spend his last year of high school outside the city, on a piece of land where he can raise dogs, grow food, and learn to live off the land.',
            'That curriculum sure does beat SF public schools.',
            'And that’s my dream too! I’ve wanted to leave San Francisco for years.',
        ],
    },
    {
        title: 'Ask Upon a Pond',
        image: null,
        alt: '',
        position: '',
        paragraphs: [
            'So how are we going to make it happen?',
            'Start a GoFundMe? Eh. Why not create our own thing?',
            'A place where people can connect, share their worlds, give, receive, and help each other make things happen.',
        ],
    },
];

const actions = [
    {
        title: 'Collect',
        ball: 'blue',
        body: 'Find your favorites. Build your collection.',
    },
    {
        title: 'Give',
        ball: 'purple',
        body: 'Make someone’s day. Send a ball their way.',
    },
    {
        title: 'Receive',
        ball: 'aqua',
        body: 'Share your world. See what comes your way.',
    },
    {
        title: 'Play',
        ball: 'green',
        body: 'A little adventure. See where your balls go.',
    },
];

/** Placeholder profiles until James and Andrew send the real ones. */
const sampleProfiles = [
    {
        name: 'James',
        image: '/askupon/story/james.jpg',
        position: '45% 30%',
        dream: 'Spend my last year of high school outside the city, on a piece of land where I can raise dogs, grow food, and learn to live off the land.',
    },
    {
        name: 'Andrew',
        image: null,
        position: '',
        dream: 'Leave San Francisco, build a life on a little land of our own, and help others make things happen along the way.',
    },
];

const actionIcons = {
    Collect: UsersRound,
    Give: Gift,
    Receive: HandHeart,
    Play: Play,
};

function golfBall(color: string): string {
    return `/askupon/balls/golf-${color}.svg`;
}

export default function Welcome() {
    const { auth } = usePage().props;
    const joinHref = auth?.user ? dashboard() : register();

    useScrollReveal(
        '.hm-tile, .hm-story-card, .hm-muddy-copy, .hm-action, .hm-imagine h2, .hm-profile, .hm-final h2',
    );

    return (
        <div className="pond-home askupon-home hm-page">
            <Head title="Ask Upon A Pond — Connect. Share your world. Give and receive." />

            <section className="hm-hero">
                <DuskPondScene />
                <header className="hm-nav">
                    <nav className="askupon-nav">
                        <Brand dark />
                        <div className="hm-nav-links">
                            <a href="#explore">Explore the Pond</a>
                            <a href="#muddy-bottoms">How It Works</a>
                            <a href="#once-upon-a-pond">Our Story</a>
                            <Link href={pondsIndex()}>Community</Link>
                        </div>
                        <div className="askupon-nav-actions">
                            <PublicNavActions />
                        </div>
                    </nav>
                </header>

                <div className="hm-hero-copy">
                    <h1>
                        Connect. Share your world.{' '}
                        <span>Give and receive.</span>
                    </h1>
                    <p>
                        A little Insta. A little Cash App. A little GoFundMe.
                        Even a little Tinder (if you check that box). A whole
                        lot of experimentation. <em>All in one Pond.</em>
                    </p>
                    <div className="hm-hero-buttons">
                        <Link
                            href={pondsIndex()}
                            className="hm-btn hm-btn-explore"
                        >
                            Explore the Pond <ArrowRight size={18} />
                        </Link>
                        <Link href={joinHref} className="hm-btn hm-btn-join">
                            Join the Pond <ArrowRight size={18} />
                        </Link>
                    </div>
                </div>
            </section>

            <section
                className="hm-tiles-wrap"
                id="explore"
                aria-label="Ways to use the Pond"
            >
                <div className="hm-tiles">
                    {ballTiles.map((tile) => (
                        <div
                            key={tile.title}
                            className={`hm-tile hm-tile-${tile.tone}`}
                        >
                            <img src={tile.image} alt="" />
                            <strong>{tile.title}</strong>
                            {tile.caption && <small>{tile.caption}</small>}
                        </div>
                    ))}
                </div>
            </section>

            <section className="hm-once" id="once-upon-a-pond">
                <h2 className="hm-heading">
                    <span>Once Upon a Pond</span>
                </h2>
                <p className="hm-subheading">The story behind the Pond.</p>
                <div className="hm-stories">
                    {stories.map((story, index) => (
                        <article key={story.title} className="hm-story-card">
                            <div
                                className={`hm-story-media ${story.image ? '' : 'hm-story-media-pond'}`}
                            >
                                {story.image ? (
                                    <img
                                        src={story.image}
                                        alt={story.alt}
                                        style={{
                                            objectPosition: story.position,
                                        }}
                                        loading="lazy"
                                    />
                                ) : (
                                    <PondMark />
                                )}
                            </div>
                            <div className="hm-story-body">
                                <h3>
                                    <span>{index + 1}.</span> {story.title}
                                </h3>
                                {story.paragraphs.map((paragraph) => (
                                    <p key={paragraph}>{paragraph}</p>
                                ))}
                            </div>
                        </article>
                    ))}
                </div>
            </section>

            <section className="hm-muddy" id="muddy-bottoms">
                <div className="hm-muddy-card">
                    <FriendswoodSign />
                    <div className="hm-muddy-copy">
                        <h2 className="hm-heading hm-heading-left">
                            <span>Let&rsquo;s Talk Muddy Bottoms</span>
                        </h2>
                        <p>
                            Back in Friendswood, Texas, we&rsquo;d wade barefoot
                            into muddy ponds in the humid summer heat, feeling
                            around with our feet.
                        </p>
                        <p>
                            And then we&rsquo;d feel it. Something round.
                            Something solid.
                        </p>
                        <p>A golf ball!</p>
                        <p className="hm-chaching">CHA-CHING!</p>
                        <p>
                            Finding those golf balls felt like discovering
                            treasure. A little money. A little adventure. A
                            whole lot of possibility.
                        </p>
                        <p>
                            That&rsquo;s the feeling we want to bring to the
                            Pond.
                        </p>
                        <p>
                            Every time you collect a ball. Give a ball. Receive
                            a ball.
                        </p>
                        <p className="hm-quantum">
                            Maybe they&rsquo;re quantum balls. Part money. Part
                            hope. Who knows how far one might go?
                        </p>
                    </div>
                </div>

                <div className="hm-actions">
                    {actions.map((action) => {
                        const Icon =
                            actionIcons[
                                action.title as keyof typeof actionIcons
                            ];

                        return (
                            <article
                                key={action.title}
                                className={`hm-action hm-action-${action.title.toLowerCase()}`}
                            >
                                <span className="hm-action-icon">
                                    <img src={golfBall(action.ball)} alt="" />
                                    <Icon size={18} aria-hidden="true" />
                                </span>
                                <h3>{action.title}</h3>
                                <p>{action.body}</p>
                            </article>
                        );
                    })}
                </div>
            </section>

            <section className="hm-imagine" id="come-on-in">
                <h2>Imagine your asks have already been answered.</h2>
                <Link href={joinHref} className="hm-btn hm-btn-purple">
                    Come on in!
                </Link>
            </section>

            <section
                className="hm-profiles"
                id="dream-life"
                aria-labelledby="hm-profiles-title"
            >
                <h2 id="hm-profiles-title" className="hm-heading">
                    <span>Dream Life Profiles</span>
                </h2>
                <p className="hm-subheading">
                    See how it works with two real profiles.
                </p>
                <div className="hm-profile-grid">
                    {sampleProfiles.map((profile) => (
                        <article
                            key={profile.name}
                            className="hm-profile"
                            aria-label={`${profile.name}’s Dream Life profile`}
                        >
                            <div className="hm-profile-media">
                                {profile.image ? (
                                    <img
                                        src={profile.image}
                                        alt={`${profile.name}`}
                                        style={{
                                            objectPosition: profile.position,
                                        }}
                                        loading="lazy"
                                    />
                                ) : (
                                    <span aria-hidden="true">
                                        {profile.name.charAt(0)}
                                    </span>
                                )}
                            </div>
                            <div className="hm-profile-body">
                                <span className="hm-profile-tag">
                                    Dream Life
                                </span>
                                <h3>{profile.name}&rsquo;s Dream Life</h3>
                                <p>&ldquo;{profile.dream}&rdquo;</p>
                                <Link
                                    href={joinHref}
                                    className="hm-btn hm-btn-purple"
                                >
                                    Toss {profile.name} a Ball{' '}
                                    <ArrowRight size={16} />
                                </Link>
                            </div>
                        </article>
                    ))}
                </div>
            </section>

            <section className="hm-final">
                <h2>Your Pond. Your People. Your Adventure.</h2>
                <p>
                    Start collecting. Start connecting. Give. Receive. See where
                    it goes.
                </p>
                <Link
                    href={joinHref}
                    className="hm-btn hm-btn-join hm-btn-final"
                >
                    Join the Pond <ArrowRight size={18} />
                </Link>
            </section>

            <footer className="hm-footer">
                <Brand dark />
                <span>© 2026 Ask Upon A Pond · askuponapond.com</span>
            </footer>
        </div>
    );
}
