import { Form, Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { PondShell } from '@/components/pond/ui';
import { store } from '@/routes/password/confirm';

/**
 * Asked for before a sensitive step such as setting up two-factor authentication. It stays inside the
 * member's or admin's own dashboard, so they are never sent out to a plain page.
 */
export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />
            <PondShell
                page="Confirm your password"
                subheading="This is a secure area of the application. Please confirm your password before continuing."
                demo={false}
            >
                <div className="ac-stack">
                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="pond-panel"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="pond-panel-header">
                                    <div>
                                        <h2>It&rsquo;s you, right?</h2>
                                        <p>
                                            Enter your password and we will take
                                            you straight back.
                                        </p>
                                    </div>
                                </div>
                                <label className="pond-form-label">
                                    Password
                                    <input
                                        className="pond-input"
                                        type="password"
                                        name="password"
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        autoFocus
                                        required
                                    />
                                    {errors.password && (
                                        <p className="pond-field-error">
                                            {errors.password}
                                        </p>
                                    )}
                                </label>
                                <div className="pond-save-bar">
                                    <button
                                        type="submit"
                                        className="pond-btn pond-btn-primary"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        <Lock size={16} />{' '}
                                        {processing
                                            ? 'Checking…'
                                            : 'Confirm password'}
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </PondShell>
        </>
    );
}
