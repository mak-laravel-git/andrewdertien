import { Form, Head, Link } from '@inertiajs/react';
import {
    Check,
    KeyRound,
    Lock,
    ShieldCheck,
    ShieldOff,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import MemberProfileController from '@/actions/App/Http/Controllers/MemberProfileController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { Avatar, hueFor, PondShell } from '@/components/pond/ui';
import { edit as editProfile } from '@/routes/member-profile';
import { edit as editSecurity } from '@/routes/security';

type Account = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    phone: string | null;
    location: string | null;
    date_of_birth: string | null;
    avatar_url: string | null;
    member_since: string;
};

function FieldError({ message }: { message?: string }) {
    return message ? <p className="pond-field-error">{message}</p> : null;
}

function SavedNote({ show }: { show: boolean }) {
    return show ? (
        <span className="pond-saved-note">
            <Check size={15} /> Saved
        </span>
    ) : null;
}

export default function AccountPage({
    account,
    twoFactorEnabled,
}: {
    account: Account;
    twoFactorEnabled: boolean;
}) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    return (
        <>
            <Head title="My account" />
            <PondShell
                page="My account"
                subheading="Your details, password and security, all in one place."
                demo={false}
            >
                <div className="ac-stack">
                    <div className="pond-panel ac-identity">
                        <Avatar
                            name={account.name}
                            hue={hueFor(account.id)}
                            size="avatar-large"
                            src={account.avatar_url}
                        />
                        <div>
                            <h2>{account.name}</h2>
                            <p>
                                {account.username
                                    ? `@${account.username}`
                                    : 'No username yet'}
                            </p>
                            <span>◉ Member since {account.member_since}</span>
                        </div>
                        <Link
                            href={editProfile()}
                            className="pond-btn pond-btn-outline-dark"
                        >
                            <UserRound size={16} /> Edit my public profile
                        </Link>
                    </div>

                    <Form
                        {...MemberProfileController.updateAccount.form()}
                        options={{ preserveScroll: true }}
                        className="pond-panel"
                        id="details"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="pond-panel-header">
                                    <div>
                                        <h2>Personal details</h2>
                                        <p>
                                            Only you can see your email, phone
                                            number and date of birth.
                                        </p>
                                    </div>
                                </div>
                                <div className="pond-form-two">
                                    <label className="pond-form-label">
                                        Full name
                                        <input
                                            className="pond-input"
                                            name="name"
                                            defaultValue={account.name}
                                            required
                                            maxLength={255}
                                            autoComplete="name"
                                        />
                                        <FieldError message={errors.name} />
                                    </label>
                                    <label className="pond-form-label">
                                        Username
                                        <input
                                            className="pond-input"
                                            name="username"
                                            defaultValue={
                                                account.username ?? ''
                                            }
                                            required={account.username !== null}
                                            minLength={3}
                                            maxLength={30}
                                            pattern="[A-Za-z0-9_]+"
                                            title="Letters, numbers and underscores only"
                                            autoComplete="username"
                                        />
                                        <FieldError message={errors.username} />
                                    </label>
                                </div>
                                <div className="pond-form-two">
                                    <label className="pond-form-label">
                                        Email address
                                        <input
                                            className="pond-input"
                                            type="email"
                                            name="email"
                                            defaultValue={account.email}
                                            required
                                            maxLength={255}
                                            autoComplete="email"
                                        />
                                        <FieldError message={errors.email} />
                                    </label>
                                    <label className="pond-form-label">
                                        Phone number{' '}
                                        <span className="pond-optional">
                                            (optional)
                                        </span>
                                        <input
                                            className="pond-input"
                                            type="tel"
                                            name="phone"
                                            defaultValue={account.phone ?? ''}
                                            maxLength={30}
                                            autoComplete="tel"
                                            placeholder="+1 555 123 4567"
                                        />
                                        <FieldError message={errors.phone} />
                                    </label>
                                </div>
                                <div className="pond-form-two">
                                    <label className="pond-form-label">
                                        Location{' '}
                                        <span className="pond-optional">
                                            (optional, such as your city)
                                        </span>
                                        <input
                                            className="pond-input"
                                            name="location"
                                            defaultValue={
                                                account.location ?? ''
                                            }
                                            maxLength={100}
                                            autoComplete="address-level2"
                                        />
                                        <FieldError message={errors.location} />
                                    </label>
                                    <label className="pond-form-label">
                                        Date of birth{' '}
                                        {account.date_of_birth ? null : (
                                            <span className="pond-optional">
                                                (optional)
                                            </span>
                                        )}
                                        {account.date_of_birth ? (
                                            <>
                                                <input
                                                    className="pond-input"
                                                    type="date"
                                                    value={
                                                        account.date_of_birth
                                                    }
                                                    readOnly
                                                    disabled
                                                />
                                                <span className="pond-optional">
                                                    Saved. For safety it cannot
                                                    be changed. Contact us if it
                                                    is wrong.
                                                </span>
                                            </>
                                        ) : (
                                            <>
                                                <input
                                                    className="pond-input"
                                                    type="date"
                                                    name="date_of_birth"
                                                    autoComplete="bday"
                                                />
                                                <span className="pond-optional">
                                                    Never shown on your profile,
                                                    and it cannot be changed
                                                    once saved.
                                                </span>
                                            </>
                                        )}
                                        <FieldError
                                            message={errors.date_of_birth}
                                        />
                                    </label>
                                </div>
                                <p className="pond-form-note">
                                    If you change your email address, you may be
                                    asked to confirm the new one.
                                </p>
                                <div className="pond-save-bar">
                                    <SavedNote show={recentlySuccessful} />
                                    <button
                                        type="submit"
                                        className="pond-btn pond-btn-primary"
                                        disabled={processing}
                                        data-test="save-account-details"
                                    >
                                        <Check size={16} />{' '}
                                        {processing
                                            ? 'Saving…'
                                            : 'Save details'}
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>

                    <Form
                        {...SecurityController.update.form()}
                        options={{ preserveScroll: true }}
                        errorBag="password"
                        resetOnSuccess
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        className="pond-panel"
                        id="password"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="pond-panel-header">
                                    <div>
                                        <h2>Password</h2>
                                        <p>
                                            Use a long, unique password that you
                                            don’t use anywhere else.
                                        </p>
                                    </div>
                                </div>
                                <label className="pond-form-label">
                                    Current password
                                    <input
                                        className="pond-input"
                                        type="password"
                                        name="current_password"
                                        autoComplete="current-password"
                                        required
                                    />
                                    <FieldError
                                        message={errors.current_password}
                                    />
                                </label>
                                <div className="pond-form-two">
                                    <label className="pond-form-label">
                                        New password
                                        <input
                                            className="pond-input"
                                            type="password"
                                            name="password"
                                            autoComplete="new-password"
                                            minLength={8}
                                            required
                                        />
                                        <FieldError message={errors.password} />
                                    </label>
                                    <label className="pond-form-label">
                                        Confirm new password
                                        <input
                                            className="pond-input"
                                            type="password"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            minLength={8}
                                            required
                                        />
                                    </label>
                                </div>
                                <div className="pond-save-bar">
                                    <SavedNote show={recentlySuccessful} />
                                    <button
                                        type="submit"
                                        className="pond-btn pond-btn-primary"
                                        disabled={processing}
                                        data-test="update-password-button"
                                    >
                                        <KeyRound size={16} />{' '}
                                        {processing
                                            ? 'Updating…'
                                            : 'Update password'}
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>

                    <div className="pond-panel" id="security">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Two-factor authentication</h2>
                                <p>
                                    Add a second step at login with an
                                    authenticator app.
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
                            <Link
                                href={editSecurity()}
                                className="pond-btn pond-btn-outline-dark"
                                data-test="manage-two-factor"
                            >
                                <Lock size={16} />{' '}
                                {twoFactorEnabled
                                    ? 'Manage two-factor'
                                    : 'Set up two-factor'}
                            </Link>
                        </div>
                    </div>

                    <div className="pond-panel ac-danger" id="delete">
                        <div className="pond-panel-header">
                            <div>
                                <h2>Delete my account</h2>
                                <p>
                                    This removes your profile, posts and
                                    messages for good. It cannot be undone.
                                </p>
                            </div>
                        </div>
                        {confirmingDelete ? (
                            <Form
                                {...ProfileController.destroy.form()}
                                errorBag="deleteAccount"
                                resetOnError={['password']}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label className="pond-form-label">
                                            Enter your password to confirm
                                            <input
                                                className="pond-input"
                                                type="password"
                                                name="password"
                                                autoComplete="current-password"
                                                required
                                                autoFocus
                                            />
                                            <FieldError
                                                message={errors.password}
                                            />
                                        </label>
                                        <div className="pond-save-bar">
                                            <button
                                                type="button"
                                                className="pond-btn pond-btn-outline-dark"
                                                onClick={() =>
                                                    setConfirmingDelete(false)
                                                }
                                            >
                                                Keep my account
                                            </button>
                                            <button
                                                type="submit"
                                                className="pond-btn ac-danger-btn"
                                                disabled={processing}
                                                data-test="confirm-delete-account"
                                            >
                                                <Trash2 size={16} />{' '}
                                                {processing
                                                    ? 'Deleting…'
                                                    : 'Delete my account'}
                                            </button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <button
                                type="button"
                                className="pond-btn ac-danger-btn"
                                onClick={() => setConfirmingDelete(true)}
                                data-test="delete-account"
                            >
                                <Trash2 size={16} /> Delete my account
                            </button>
                        )}
                    </div>
                </div>
            </PondShell>
        </>
    );
}
