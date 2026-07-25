import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import { Button, Field, Pill, inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Profile {
    name: string;
    email: string;
    mobile: string;
    mobile_verified: boolean;
    has_password: boolean;
    last_login_at: string | null;
}

export default function ProfilePage({ profile }: { profile: Profile }) {
    return (
        <ConsoleLayout title="My Profile" description="Your details and how you sign in.">
            <Head title="My Profile" />

            <div className="grid gap-5 lg:grid-cols-2">
                <DetailsCard profile={profile} />
                <PasswordCard hasPassword={profile.has_password} lastLogin={profile.last_login_at} />
            </div>
        </ConsoleLayout>
    );
}

function DetailsCard({ profile }: { profile: Profile }) {
    const form = useForm({
        name: profile.name,
        email: profile.email,
        mobile: profile.mobile,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/profile', { preserveScroll: true });
    };

    const mobileChanged = form.data.mobile !== profile.mobile;

    return (
        <section className="rounded-xl border border-border bg-card p-6">
            <h2 className="font-display text-lg font-bold">Details</h2>
            <p className="mt-1 text-sm text-muted-foreground">Keep these current — your mobile is how you sign in.</p>

            <form onSubmit={submit} className="mt-5 space-y-4">
                <Field label="Full name" required error={form.errors.name}>
                    <input
                        className={inputClass}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                </Field>

                <Field label="Email" required error={form.errors.email}>
                    <input
                        type="email"
                        className={inputClass}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>

                <Field
                    label="Mobile number"
                    required
                    error={form.errors.mobile}
                    hint={
                        mobileChanged
                            ? 'You will verify this number with a one-time code the next time you sign in.'
                            : undefined
                    }
                >
                    <div className="flex items-stretch overflow-hidden rounded-lg border border-input bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                        <span className="data grid place-items-center border-r border-input bg-muted px-3 text-muted-foreground">
                            +91
                        </span>
                        <input
                            type="tel"
                            inputMode="numeric"
                            maxLength={10}
                            className="data w-full bg-transparent px-3.5 py-2.5 text-sm outline-none"
                            value={form.data.mobile}
                            onChange={(e) => form.setData('mobile', e.target.value.replace(/\D/g, ''))}
                        />
                    </div>
                </Field>

                {!mobileChanged && (
                    <Pill tone={profile.mobile_verified ? 'good' : 'warn'}>
                        {profile.mobile_verified ? 'Mobile verified' : 'Mobile not verified'}
                    </Pill>
                )}

                <div className="flex items-center gap-3 pt-1">
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </Button>
                    {form.recentlySuccessful && (
                        <span className="text-sm font-medium" style={{ color: 'var(--good)' }}>
                            Saved
                        </span>
                    )}
                </div>
            </form>
        </section>
    );
}

function PasswordCard({ hasPassword, lastLogin }: { hasPassword: boolean; lastLogin: string | null }) {
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section className="rounded-xl border border-border bg-card p-6">
            <h2 className="font-display text-lg font-bold">{hasPassword ? 'Change password' : 'Set a password'}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                You normally sign in with a one-time code. A password is an optional alternative.
            </p>

            <form onSubmit={submit} className="mt-5 space-y-4">
                {hasPassword && (
                    <Field label="Current password" required error={form.errors.current_password}>
                        <input
                            type="password"
                            autoComplete="current-password"
                            className={inputClass}
                            value={form.data.current_password}
                            onChange={(e) => form.setData('current_password', e.target.value)}
                        />
                    </Field>
                )}

                <Field
                    label="New password"
                    required
                    error={form.errors.password}
                    hint="At least 8 characters, with letters and numbers."
                >
                    <input
                        type="password"
                        autoComplete="new-password"
                        className={inputClass}
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </Field>

                <Field label="Confirm new password" required>
                    <input
                        type="password"
                        autoComplete="new-password"
                        className={inputClass}
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    />
                </Field>

                <div className="flex items-center gap-3 pt-1">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : hasPassword ? 'Change password' : 'Set password'}
                    </Button>
                    {form.recentlySuccessful && (
                        <span className="text-sm font-medium" style={{ color: 'var(--good)' }}>
                            Updated
                        </span>
                    )}
                </div>
            </form>

            {lastLogin && (
                <p className="mt-6 border-t border-border pt-4 text-xs text-muted-foreground">
                    Last signed in {lastLogin}
                </p>
            )}
        </section>
    );
}
