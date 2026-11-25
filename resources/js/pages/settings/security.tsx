import { Form, Head, Link } from '@inertiajs/react';
import { KeyRound, ShieldCheck, ShieldOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PondShell } from '@/components/pond/ui';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { edit as editAccount } from '@/routes/member-account';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

/**
 * Two-factor authentication, inside the member's or admin's own dashboard so nobody is sent
 * out to a plain page while setting it up.
 */
export default function Security({
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    return (
        <>
            <Head title="Security settings" />
            <PondShell
                page="Security"
                subheading="Keep your account safe with a second step at login."
                demo={false}
            >
                <div className="ac-stack">
                    {canManageTwoFactor && (
                        <section
                            className="pond-panel"
                            data-test="two-factor-panel"
                        >
                            <div className="pond-panel-header">
                                <div>
                                    <h2>Two-factor authentication</h2>
                                    <p>
                                        When it is on, you are asked for a
                                        secure code from an authenticator app on
                                        your phone each time you log in.
                                    </p>
                                </div>
                            </div>

                            <div className="ac-security-row">
                                <span
                                    className={`ac-pill ${twoFactorEnabled ? 'is-on' : ''}`}
                                >
                                    {twoFactorEnabled ? (
                                        <ShieldCheck size={15} />
                                    ) : (
                                        <ShieldOff size={15} />
                                    )}
                                    {twoFactorEnabled ? 'On' : 'Off'}
                                </span>

                                {twoFactorEnabled ? (
                                    <Form {...disable.form()}>
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                className="pond-btn ac-danger-btn"
                                                disabled={processing}
                                                data-test="disable-two-factor"
                                            >
                                                Disable 2FA
                                            </button>
                                        )}
                                    </Form>
                                ) : hasSetupData ? (
                                    <button
                                        type="button"
                                        className="pond-btn pond-btn-primary"
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck size={16} /> Continue setup
                                    </button>
                                ) : (
                                    <Form
                                        {...enable.form()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                className="pond-btn pond-btn-primary"
                                                disabled={processing}
                                                data-test="enable-two-factor"
                                            >
                                                <ShieldCheck size={16} /> Enable
                                                2FA
                                            </button>
                                        )}
                                    </Form>
                                )}
                            </div>

                            {twoFactorEnabled && (
                                <TwoFactorRecoveryCodes
                                    recoveryCodesList={recoveryCodesList}
                                    fetchRecoveryCodes={fetchRecoveryCodes}
                                    errors={errors}
                                />
                            )}

                            <TwoFactorSetupModal
                                isOpen={showSetupModal}
                                onClose={() => setShowSetupModal(false)}
                                requiresConfirmation={requiresConfirmation}
                                twoFactorEnabled={twoFactorEnabled}
                                qrCodeSvg={qrCodeSvg}
                                manualSetupKey={manualSetupKey}
                                clearSetupData={clearSetupData}
                                fetchSetupData={fetchSetupData}
                                errors={errors}
                            />
                        </section>
                    )}

                    <section className="pond-panel">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Password</h2>
                                <p>
                                    You change your password in My Account,
                                    together with your other details.
                                </p>
                            </div>
                        </div>
                        <Link
                            href={editAccount()}
                            className="pond-btn pond-btn-outline-dark"
                        >
                            <KeyRound size={16} /> Go to My Account
                        </Link>
                    </section>
                </div>
            </PondShell>
        </>
    );
}
