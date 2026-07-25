import { Head, Link, usePage } from '@inertiajs/react';

import ConsoleLayout from '@/layouts/console-layout';

interface AuthUser {
    name: string;
    is_owner: boolean;
}

export default function Dashboard() {
    const { auth, notifications } = usePage<{
        auth: { user: AuthUser };
        notifications?: { unread_leads: number };
    }>().props;

    const firstName = auth.user.name.split(' ')[0];
    const hour = new Date().getHours();
    const partOfDay = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const newLeads = notifications?.unread_leads ?? 0;

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

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat
                    label="New leads"
                    value={String(newLeads)}
                    note={newLeads > 0 ? 'waiting to be actioned' : 'nothing unread'}
                    href="/leads"
                    highlight={newLeads > 0}
                />
                <Stat label="Team members" value="10" note="8 active · 2 inactive" href="/team" />
                <Stat label="Calls today" value="—" note="connects once telephony is live" muted />
                <Stat label="Activity events" value="—" note="see the full log" href="/activity" />
            </section>

            <section className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-border bg-card p-6 lg:col-span-2">
                    <p className="eyebrow">Build progress</p>
                    <h2 className="mt-2 font-display text-xl font-bold">What's live right now</h2>
                    <ul className="mt-5 space-y-3.5">
                        {[
                            ['done', 'Mobile + OTP sign-in', 'Hashed codes, expiry, attempt limits, resend cooldown'],
                            ['done', 'Profile & password', 'Update your name, email and mobile; set or reset a password'],
                            ['done', 'Team members', 'Add, edit and remove staff — searchable table and CSV export'],
                            ['done', 'Activity log', 'Every change recorded, with who made it'],
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
                    <p className="eyebrow">Quick actions</p>
                    <h2 className="mt-2 font-display text-xl font-bold">Jump back in</h2>
                    <div className="mt-4 space-y-2">
                        <QuickLink href="/leads" label="Review new leads" />
                        <QuickLink href="/team" label="Manage team members" />
                        <QuickLink href="/profile" label="Update my profile" />
                        {auth.user.is_owner && <QuickLink href="/activity" label="Open activity log" />}
                    </div>
                </div>
            </section>
        </ConsoleLayout>
    );
}

function Stat({
    label,
    value,
    note,
    muted,
    href,
    highlight,
}: {
    label: string;
    value: string;
    note: string;
    muted?: boolean;
    href?: string;
    highlight?: boolean;
}) {
    const body = (
        <>
            <p className="eyebrow">{label}</p>
            <p
                className={`mt-2 font-display text-3xl font-bold tabular ${muted ? 'text-muted-foreground/40' : ''}`}
                style={highlight ? { color: 'var(--bad)' } : undefined}
            >
                {value}
            </p>
            <p className="mt-1 text-[13px] text-muted-foreground">{note}</p>
        </>
    );

    const className = `block rounded-xl border border-border bg-card p-5 ${
        href ? 'transition hover:border-primary/40' : ''
    }`;

    return href ? (
        <Link href={href} className={className}>
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

function QuickLink({ href, label }: { href: string; label: string }) {
    return (
        <Link
            href={href}
            className="flex items-center justify-between rounded-lg border border-border px-3.5 py-2.5 text-sm font-medium transition hover:border-primary/40 hover:bg-muted"
        >
            {label}
            <span aria-hidden className="text-muted-foreground">
                →
            </span>
        </Link>
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
