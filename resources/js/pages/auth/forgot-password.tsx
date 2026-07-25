import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

import { SignalMark } from '@/components/brand/signal-mark';
import { Button, Field, inputClass } from '@/components/ui-kit';

interface OtpFlash {
    sent: boolean;
    dev_code: string | null;
}

export default function ForgotPassword({ otpLength }: { otpLength: number }) {
    const { props } = usePage<{ flash?: { otp?: OtpFlash } }>();
    const [step, setStep] = useState<'mobile' | 'reset'>('mobile');
    const [devCode, setDevCode] = useState<string | null>(null);

    const mobileForm = useForm({ mobile: '' });
    const resetForm = useForm({ mobile: '', code: '', password: '', password_confirmation: '' });

    useEffect(() => {
        const otp = props.flash?.otp;
        if (otp?.sent) {
            setStep('reset');
            setDevCode(otp.dev_code);
            resetForm.setData('mobile', mobileForm.data.mobile);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [props.flash?.otp]);

    const sendCode = (e: FormEvent) => {
        e.preventDefault();
        mobileForm.post('/forgot-password', { preserveScroll: true });
    };

    const reset = (e: FormEvent) => {
        e.preventDefault();
        resetForm.post('/reset-password');
    };

    return (
        <>
            <Head title="Reset password" />

            <div className="grid min-h-svh lg:grid-cols-[1.05fr_1fr]">
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-rail p-10 text-rail-foreground lg:flex xl:p-14">
                    <SignalMark />
                    <div className="relative flex items-center gap-3">
                        <span className="grid size-9 place-items-center rounded-lg bg-primary font-display text-lg font-bold text-primary-foreground">
                            V
                        </span>
                        <span className="font-display text-xl font-bold tracking-tight">VVT</span>
                    </div>
                    <div className="relative max-w-lg">
                        <p className="eyebrow" style={{ color: 'var(--primary)' }}>
                            Account recovery
                        </p>
                        <h1 className="mt-4 font-display text-4xl font-extrabold leading-[1.08] tracking-tight">
                            Locked out? Your mobile is the key.
                        </h1>
                        <p className="mt-5 max-w-md text-[15px] leading-relaxed opacity-65">
                            We'll send a one-time code to your registered number so you can set a new password.
                        </p>
                    </div>
                    <div />
                </aside>

                <main className="flex items-center justify-center px-6 py-12 sm:px-10">
                    <div className="w-full max-w-sm">
                        {step === 'mobile' ? (
                            <form onSubmit={sendCode} className="space-y-6">
                                <header>
                                    <h2 className="font-display text-3xl font-bold">Reset password</h2>
                                    <p className="mt-2 text-[15px] text-muted-foreground">
                                        Enter your registered mobile number.
                                    </p>
                                </header>

                                <Field label="Mobile number" required error={mobileForm.errors.mobile}>
                                    <div className="flex items-stretch overflow-hidden rounded-lg border border-input bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                                        <span className="data grid place-items-center border-r border-input bg-muted px-3.5 text-muted-foreground">
                                            +91
                                        </span>
                                        <input
                                            type="tel"
                                            inputMode="numeric"
                                            maxLength={10}
                                            autoFocus
                                            className="data w-full bg-transparent px-3.5 py-2.5 text-base outline-none"
                                            value={mobileForm.data.mobile}
                                            onChange={(e) => mobileForm.setData('mobile', e.target.value.replace(/\D/g, ''))}
                                        />
                                    </div>
                                </Field>

                                <Button
                                    type="submit"
                                    className="w-full py-3"
                                    disabled={mobileForm.processing || mobileForm.data.mobile.length !== 10}
                                >
                                    {mobileForm.processing ? 'Sending code…' : 'Send code'}
                                </Button>

                                <p className="text-center text-sm">
                                    <Link href="/login" className="font-medium text-muted-foreground hover:text-foreground">
                                        Back to sign in
                                    </Link>
                                </p>
                            </form>
                        ) : (
                            <form onSubmit={reset} className="space-y-5">
                                <header>
                                    <h2 className="font-display text-3xl font-bold">Set a new password</h2>
                                    <p className="mt-2 text-[15px] text-muted-foreground">
                                        Enter the code sent to{' '}
                                        <span className="data font-medium text-foreground">+91 {resetForm.data.mobile}</span>
                                    </p>
                                </header>

                                {devCode && (
                                    <p
                                        className="rounded-lg px-4 py-3 text-sm"
                                        style={{
                                            background: 'var(--warn-soft)',
                                            color: 'var(--warn)',
                                            border: '1px solid color-mix(in srgb, var(--warn) 25%, transparent)',
                                        }}
                                    >
                                        <span className="font-semibold">Development mode</span> — your code is{' '}
                                        <span className="data font-semibold">{devCode}</span>
                                    </p>
                                )}

                                <Field label={`${otpLength}-digit code`} required error={resetForm.errors.code}>
                                    <input
                                        inputMode="numeric"
                                        maxLength={otpLength}
                                        className={`${inputClass} data tracking-[0.4em]`}
                                        value={resetForm.data.code}
                                        onChange={(e) => resetForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                    />
                                </Field>

                                <Field
                                    label="New password"
                                    required
                                    error={resetForm.errors.password}
                                    hint="At least 8 characters, with letters and numbers."
                                >
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={inputClass}
                                        value={resetForm.data.password}
                                        onChange={(e) => resetForm.setData('password', e.target.value)}
                                    />
                                </Field>

                                <Field label="Confirm password" required>
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={inputClass}
                                        value={resetForm.data.password_confirmation}
                                        onChange={(e) => resetForm.setData('password_confirmation', e.target.value)}
                                    />
                                </Field>

                                <Button type="submit" className="w-full py-3" disabled={resetForm.processing}>
                                    {resetForm.processing ? 'Updating…' : 'Update password'}
                                </Button>
                            </form>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
