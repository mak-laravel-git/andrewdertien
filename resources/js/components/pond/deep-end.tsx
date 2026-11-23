import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Modal } from '@/components/pond/ui';
import { store } from '@/routes/ball-offers';

const deepEndBalls = [
    {
        name: 'The Zuckerberg Ball',
        price: '$10,000,000',
        image: '/askupon/balls/golf-blue.svg',
        copy: 'Want to buy the next social network while it still fits in a pond?',
    },
    {
        name: 'The Elon Musk Ball',
        price: '$44,000,000',
        image: '/askupon/balls/golf-white.svg',
        copy: 'You could buy a whole social network. Or you could start with one extremely expensive golf ball.',
    },
];

function OfferForm({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '', email: '', message: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal
            title="Make Me an Offer"
            subtitle="Tell Andrew who you are and what you have in mind. He will get back to you."
            onClose={onClose}
        >
            <form onSubmit={submit} className="de-form">
                <label>
                    Your name
                    <input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        autoComplete="name"
                        required
                        maxLength={120}
                    />
                    {form.errors.name && (
                        <small role="alert">{form.errors.name}</small>
                    )}
                </label>
                <label>
                    Your email
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        autoComplete="email"
                        required
                        maxLength={190}
                    />
                    {form.errors.email && (
                        <small role="alert">{form.errors.email}</small>
                    )}
                </label>
                <label>
                    Your message
                    <textarea
                        value={form.data.message}
                        onChange={(event) =>
                            form.setData('message', event.target.value)
                        }
                        rows={5}
                        required
                        maxLength={3000}
                        placeholder="Which ball, and what is your offer?"
                    />
                    {form.errors.message && (
                        <small role="alert">{form.errors.message}</small>
                    )}
                </label>
                <button
                    type="submit"
                    className="pond-btn pond-btn-primary pond-wide"
                    disabled={form.processing}
                >
                    <Send size={16} />{' '}
                    {form.processing ? 'Sending...' : 'Send my offer'}
                </button>
            </form>
        </Modal>
    );
}

/**
 * The Deep End: two very expensive balls that are not for sale at checkout. The prices are part
 * of the joke, so the only way in is an inquiry form that emails the site owner.
 */
export function DeepEnd() {
    const [isOffering, setIsOffering] = useState(false);

    return (
        <>
            <section className="de-section" aria-labelledby="deep-end-title">
                <h2 id="deep-end-title" className="de-title">
                    The Deep End
                </h2>
                <p className="de-intro">
                    For anyone who&rsquo;s ever looked at a pond and thought, I
                    could buy that.
                </p>
                <div className="de-cards">
                    {deepEndBalls.map((ball) => (
                        <article className="de-card" key={ball.name}>
                            <span className="de-ball" aria-hidden="true">
                                <img src={ball.image} alt="" />
                            </span>
                            <h3 className="de-name">{ball.name}</h3>
                            <strong className="de-price">{ball.price}</strong>
                            <p>{ball.copy}</p>
                        </article>
                    ))}
                </div>
                <p className="de-note">
                    Want to own part of the Pond? For ten million dollars, you
                    can definitely get Andrew on the phone.
                </p>
                <button
                    type="button"
                    className="pond-btn pond-btn-primary de-offer"
                    onClick={() => setIsOffering(true)}
                >
                    Make Me an Offer
                </button>
            </section>
            {isOffering && <OfferForm onClose={() => setIsOffering(false)} />}
        </>
    );
}
