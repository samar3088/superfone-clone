import { Head, usePage } from '@inertiajs/react';

import ConsoleLayout from '@/layouts/console-layout';

interface AuthUser {
    name: string;
    is_owner: boolean;
}

export default function Dashboard() {
    const { auth } = usePage<{ auth: { user: AuthUser } }>().props;
    const firstName = auth.user.name.split(' ')[0];

    const hour = new Date().getHours();
    const partOfDay = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    return (
        <ConsoleLayout
            title={`${partOfDay}, ${firstName}`}
            description={new Date().toLocaleDateString('en-IN', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            })}
        >
            <Head title="Dashboard" />

            {/* Summary before detail. */}
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Team members" value="10" note="8 active · 2 inactive" />
                <Stat label="Calls today" value="—" note="connects once telephony is live" muted />
                <Stat label="Leads captured" value="—" note="connects with Facebook sync" muted />
                <Stat label="Activity events" value="12" note="logged in the last 24h" />
            </section>

            <section className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-border bg-card p-6 lg:col-span-2">
                    <p className="eyebrow">Build progress</p>
                    <h2 className="mt-2 font-display text-xl font-bold">What's live right now</h2>
                    <ul className="mt-5 space-y-3.5">
                        {[
                            ['done', 'Mobile + OTP sign-in', 'Hashed codes, expiry, attempt limits, resend cooldown'],
                            ['done', 'Roles & permissions', 'Owner and Member, seeded with Spatie'],
                            ['done', 'Activity logging', 'Every create, update and delete is recorded'],
                            ['next', 'Team members', 'Create, edit and remove staff — server-side table + export'],
                            ['next', 'Settings', 'Tags, CRM stages and the Facebook lead integration'],
                            ['later', 'Telephony', 'VICI dial inbound / outbound calling and reports'],
                        ].map(([state, heading, detail]) => (
                            <li key={heading} className="flex gap-3.5">
                                <StatusDot state={state as 'done' | 'next' | 'later'} />
                                <div>
                                    <p className="font-semibold leading-tight">{heading}</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">{detail}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="rounded-xl border border-border bg-card p-6">
                    <p className="eyebrow">Your access</p>
                    <h2 className="mt-2 font-display text-xl font-bold">{auth.user.is_owner ? 'Owner' : 'Member'}</h2>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {auth.user.is_owner
                            ? 'You can manage team members, configure settings, and view every activity record.'
                            : 'You can view your team and manage your own profile. Settings are owner-only.'}
                    </p>
                    <div
                        className="mt-5 rounded-lg border p-4"
                        style={{
                            borderColor: 'color-mix(in srgb, var(--primary) 22%, transparent)',
                            background: 'var(--accent)',
                        }}
                    >
                        <p className="text-[13px] font-semibold" style={{ color: 'var(--accent-foreground)' }}>
                            Design review
                        </p>
                        <p
                            className="mt-1 text-[13px] leading-relaxed"
                            style={{ color: 'var(--accent-foreground)', opacity: 0.8 }}
                        >
                            This is the new visual direction. Approve it and the remaining screens follow this system.
                        </p>
                    </div>
                </div>
            </section>
        </ConsoleLayout>
    );
}

function Stat({ label, value, note, muted }: { label: string; value: string; note: string; muted?: boolean }) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <p className="eyebrow">{label}</p>
            <p className={`mt-2 font-display text-3xl font-bold tabular ${muted ? 'text-muted-foreground/40' : ''}`}>
                {value}
            </p>
            <p className="mt-1 text-[13px] text-muted-foreground">{note}</p>
        </div>
    );
}

function StatusDot({ state }: { state: 'done' | 'next' | 'later' }) {
    const styles = {
        done: { background: 'var(--good-soft)', color: 'var(--good)', label: '✓' },
        next: { background: 'var(--accent)', color: 'var(--accent-foreground)', label: '→' },
        later: { background: 'var(--muted)', color: 'var(--muted-foreground)', label: '·' },
    }[state];

    return (
        <span
            className="mt-0.5 grid size-5 flex-shrink-0 place-items-center rounded-full text-[11px] font-bold"
            style={{ background: styles.background, color: styles.color }}
            aria-label={state}
        >
            {styles.label}
        </span>
    );
}
