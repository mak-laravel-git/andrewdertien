import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Modal } from '@/components/pond/ui';
import { store } from '@/routes/advertising-inquiries';

function InquiryForm({ onClose }: { onClose: () => void }) {
    const form = useForm({
        name: '',
        email: '',
        company: '',
        website: '',
        message: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal
            title="Advertise on the Pond"
            subtitle="Tell us a little about what you would like to share. A portion of every ad dollar goes back into the Pond."
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
                    Business or project (optional)
                    <input
                        value={form.data.company}
                        onChange={(event) =>
                            form.setData('company', event.target.value)
                        }
                        autoComplete="organization"
                        maxLength={160}
                    />
                    {form.errors.company && (
                        <small role="alert">{form.errors.company}</small>
                    )}
                </label>
                <label>
                    Website or link (optional)
                    <input
                        value={form.data.website}
                        onChange={(event) =>
                            form.setData('website', event.target.value)
                        }
                        autoComplete="url"
                        inputMode="url"
                        maxLength={255}
                        placeholder="https://"
                    />
                    {form.errors.website && (
                        <small role="alert">{form.errors.website}</small>
                    )}
                </label>
                <label>
                    What would you like to promote?
                    <textarea
                        value={form.data.message}
                        onChange={(event) =>
                            form.setData('message', event.target.value)
                        }
                        rows={4}
                        required
                        maxLength={3000}
                        placeholder="A podcast, a book, a business, a project..."
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
                    {form.processing ? 'Sending...' : 'Send inquiry'}
                </button>
            </form>
        </Modal>
    );
}

/**
 * The callout that sits between rows of members on Explore, inviting people to advertise on the Pond.
 * The button opens a short inquiry form that emails the site owner.
 */
export function AdvertiseCallout() {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <aside
                className="pond-advertise"
                aria-labelledby="pond-advertise-title"
            >
                <div className="pond-advertise-copy">
                    <h3 id="pond-advertise-title">
                        Got something you want to share?
                    </h3>
                    <p>
                        A podcast, a book, a business, a project you&rsquo;re
                        excited about. Get the word out on Ask Upon a Pond.
                    </p>
                    <p>
                        A portion of every ad dollar goes back into the Pond to
                        help fund what other Pond Players are asking for.
                    </p>
                    <button
                        type="button"
                        className="pond-advertise-btn"
                        onClick={() => setIsOpen(true)}
                    >
                        Advertise on the Pond
                    </button>
                </div>
                <div className="pond-advertise-art" aria-hidden="true">
                    <span className="pond-advertise-ripple" />
                    <span className="pond-advertise-ripple" />
                    <span className="pond-advertise-ripple" />
                    <img src="/askupon/balls/golf-white.svg" alt="" />
                </div>
            </aside>
            {isOpen && <InquiryForm onClose={() => setIsOpen(false)} />}
        </>
    );
}
