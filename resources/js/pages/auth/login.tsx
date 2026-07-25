import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

import { SignalMark } from '@/components/brand/signal-mark';

type Step = 'mobile' | 'code' | 'password';

interface OtpFlash {
    sent: boolean;
    dev_code: string | null;
    cooldown: number;
}

export default function Login({ otpLength, resendCooldown }: { otpLength: number; resendCooldown: number }) {
    const { props } = usePage<{ flash?: { otp?: OtpFlash } }>();
    const [step, setStep] = useState<Step>('mobile');
    const [cooldown, setCooldown] = useState(0);
    const [devCode, setDevCode] = useState<string | null>(null);

    const mobileForm = useForm({ mobile: '' });
    const codeForm = useForm({ mobile: '', code: '' });
    const passwordForm = useForm({ mobile: '', password: '' });

    // A code was issued — advance to the verify step.
    useEffect(() => {
        const otp = props.flash?.otp;
        if (otp?.sent) {
            setStep('code');
            setDevCode(otp.dev_code);
            setCooldown(otp.cooldown ?? resendCooldown);
        }
    }, [props.flash?.otp, resendCooldown]);

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = setTimeout(() => setCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [cooldown]);

    const sendCode = (e?: FormEvent) => {
        e?.preventDefault();
        mobileForm.post(route('login.otp.request'), {
            preserveScroll: true,
            onSuccess: () => {
                codeForm.setData('mobile', mobileForm.data.mobile);
                passwordForm.setData('mobile', mobileForm.data.mobile);
            },
        });
    };

    /**
     * Submitted with an explicit code so a keystroke that lands before React
     * re-renders can't send a stale value.
     */
    const submitCode = (code: string) => {
        router.post(route('login.otp.verify'), { mobile: codeForm.data.mobile, code });
    };

    const verify = (e: FormEvent) => {
        e.preventDefault();
        submitCode(codeForm.data.code);
    };

    const loginWithPassword = (e: FormEvent) => {
        e.preventDefault();
        passwordForm.post(route('login.password'));
    };

    return (
        <>
            <Head title="Sign in" />

            <div className="grid min-h-svh lg:grid-cols-[1.05fr_1fr]">
                {/* ── Brand panel ── */}
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
                            Operations console
                        </p>
                        <h1 className="mt-4 font-display text-4xl font-extrabold leading-[1.08] tracking-tight xl:text-5xl">
                            Every call, every lead, every teammate — on one board.
                        </h1>
                        <p className="mt-5 max-w-md text-[15px] leading-relaxed opacity-65">
                            Sign in with your mobile number. We'll text you a one-time code — no password to remember.
                        </p>
                    </div>

                    <dl className="relative grid grid-cols-3 gap-6 border-t border-white/10 pt-7">
                        {[
                            ['Uptime', '99.9%'],
                            ['Avg. answer', '11s'],
                            ['Leads synced', '7,048'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-[11px] uppercase tracking-[0.14em] opacity-45">{label}</dt>
                                <dd className="mt-1 font-display text-2xl font-bold tabular">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </aside>

                {/* ── Form panel ── */}
                <main className="flex items-center justify-center px-6 py-12 sm:px-10">
                    <div className="w-full max-w-sm">
                        <div className="mb-9 flex items-center gap-3 lg:hidden">
                            <span className="grid size-9 place-items-center rounded-lg bg-primary font-display text-lg font-bold text-primary-foreground">
                                V
                            </span>
                            <span className="font-display text-xl font-bold">VVT</span>
                        </div>

                        {step === 'mobile' && (
                            <form onSubmit={sendCode} className="space-y-6">
                                <header>
                                    <h2 className="font-display text-3xl font-bold">Sign in</h2>
                                    <p className="mt-2 text-[15px] text-muted-foreground">
                                        Enter the mobile number registered with your team.
                                    </p>
                                </header>

                                <div className="space-y-2">
                                    <label htmlFor="mobile" className="text-sm font-semibold">
                                        Mobile number
                                    </label>
                                    <div className="flex items-stretch overflow-hidden rounded-lg border border-input bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                                        <span className="data grid place-items-center border-r border-input bg-muted px-3.5 text-muted-foreground">
                                            +91
                                        </span>
                                        <input
                                            id="mobile"
                                            name="mobile"
                                            type="tel"
                                            inputMode="numeric"
                                            autoComplete="tel"
                                            autoFocus
                                            maxLength={10}
                                            placeholder="98765 43210"
                                            value={mobileForm.data.mobile}
                                            onChange={(e) => mobileForm.setData('mobile', e.target.value.replace(/\D/g, ''))}
                                            className="data w-full bg-transparent px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground/50"
                                        />
                                    </div>
                                    {mobileForm.errors.mobile && (
                                        <p className="text-sm font-medium text-destructive">{mobileForm.errors.mobile}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={mobileForm.processing || mobileForm.data.mobile.length !== 10}
                                    className="w-full rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-40"
                                >
                                    {mobileForm.processing ? 'Sending code…' : 'Send code'}
                                </button>

                                <button
                                    type="button"
                                    onClick={() => {
                                        passwordForm.setData('mobile', mobileForm.data.mobile);
                                        setStep('password');
                                    }}
                                    className="w-full text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Use password instead
                                </button>
                            </form>
                        )}

                        {step === 'code' && (
                            <form onSubmit={verify} className="space-y-6">
                                <header>
                                    <h2 className="font-display text-3xl font-bold">Enter the code</h2>
                                    <p className="mt-2 text-[15px] text-muted-foreground">
                                        Sent to <span className="data font-medium text-foreground">+91 {codeForm.data.mobile}</span>{' '}
                                        <button
                                            type="button"
                                            onClick={() => setStep('mobile')}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            change
                                        </button>
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

                                <CodeInput
                                    length={otpLength}
                                    onChange={(v) => codeForm.setData('code', v)}
                                    onComplete={submitCode}
                                />
                                {codeForm.errors.code && (
                                    <p className="text-sm font-medium text-destructive">{codeForm.errors.code}</p>
                                )}
                                {codeForm.errors.mobile && (
                                    <p className="text-sm font-medium text-destructive">{codeForm.errors.mobile}</p>
                                )}

                                <button
                                    type="submit"
                                    disabled={codeForm.processing || codeForm.data.code.length !== otpLength}
                                    className="w-full rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-40"
                                >
                                    {codeForm.processing ? 'Verifying…' : 'Verify & sign in'}
                                </button>

                                <p className="text-center text-sm text-muted-foreground">
                                    {cooldown > 0 ? (
                                        <>
                                            Resend available in <span className="data font-medium">{cooldown}s</span>
                                        </>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => sendCode()}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Resend code
                                        </button>
                                    )}
                                </p>
                            </form>
                        )}

                        {step === 'password' && (
                            <form onSubmit={loginWithPassword} className="space-y-6">
                                <header>
                                    <h2 className="font-display text-3xl font-bold">Sign in</h2>
                                    <p className="mt-2 text-[15px] text-muted-foreground">Mobile number and password.</p>
                                </header>

                                <div className="space-y-2">
                                    <label htmlFor="pmobile" className="text-sm font-semibold">
                                        Mobile number
                                    </label>
                                    <div className="flex items-stretch overflow-hidden rounded-lg border border-input bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                                        <span className="data grid place-items-center border-r border-input bg-muted px-3.5 text-muted-foreground">
                                            +91
                                        </span>
                                        <input
                                            id="pmobile"
                                            type="tel"
                                            inputMode="numeric"
                                            maxLength={10}
                                            value={passwordForm.data.mobile}
                                            onChange={(e) => passwordForm.setData('mobile', e.target.value.replace(/\D/g, ''))}
                                            className="data w-full bg-transparent px-3.5 py-2.5 text-base outline-none"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="password" className="text-sm font-semibold">
                                        Password
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        className="w-full rounded-lg border border-input bg-card px-3.5 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                                    />
                                    {passwordForm.errors.mobile && (
                                        <p className="text-sm font-medium text-destructive">{passwordForm.errors.mobile}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="w-full rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-40"
                                >
                                    {passwordForm.processing ? 'Signing in…' : 'Sign in'}
                                </button>

                                <div className="flex flex-col gap-2 text-center text-sm">
                                    <button
                                        type="button"
                                        onClick={() => setStep('mobile')}
                                        className="font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        Use a one-time code instead
                                    </button>
                                    <Link
                                        href="/forgot-password"
                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Forgot your password?
                                    </Link>
                                </div>
                            </form>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}

/**
 * Per-digit code entry with paste support and auto-advance.
 *
 * The inputs are uncontrolled and the code is read back out of the DOM on
 * every change. A controlled version drops digits when the user (or a
 * password manager) types faster than React re-renders.
 */
function CodeInput({
    length,
    onChange,
    onComplete,
}: {
    length: number;
    onChange: (v: string) => void;
    onComplete: (code: string) => void;
}) {
    const refs = useRef<(HTMLInputElement | null)[]>([]);

    useEffect(() => {
        refs.current[0]?.focus();
    }, []);

    const readCode = () => refs.current.map((el) => el?.value ?? '').join('');

    const publish = (code: string) => {
        onChange(code);
        if (code.length === length) onComplete(code);
    };

    const handleChange = (index: number, raw: string) => {
        const digit = raw.replace(/\D/g, '').slice(-1);
        const el = refs.current[index];
        if (el) el.value = digit;

        if (digit && index < length - 1) refs.current[index + 1]?.focus();
        publish(readCode());
    };

    const handlePaste = (event: React.ClipboardEvent) => {
        event.preventDefault();
        const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, length);

        refs.current.forEach((el, i) => {
            if (el) el.value = digits[i] ?? '';
        });
        refs.current[Math.min(digits.length, length - 1)]?.focus();
        publish(readCode());
    };

    return (
        <div className="flex gap-2.5" role="group" aria-label="One-time code">
            {Array.from({ length }).map((_, i) => (
                <input
                    key={i}
                    ref={(el) => {
                        refs.current[i] = el;
                    }}
                    type="text"
                    inputMode="numeric"
                    autoComplete={i === 0 ? 'one-time-code' : 'off'}
                    maxLength={1}
                    aria-label={`Digit ${i + 1}`}
                    defaultValue=""
                    onChange={(e) => handleChange(i, e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Backspace' && !e.currentTarget.value && i > 0) {
                            refs.current[i - 1]?.focus();
                        }
                    }}
                    onPaste={handlePaste}
                    className="data h-14 w-full rounded-lg border border-input bg-card text-center text-xl font-medium outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                />
            ))}
        </div>
    );
}
