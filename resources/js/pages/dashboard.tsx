import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

import { Button } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface CallInsights {
    outgoing: number;
    incoming: number;
    connected: number;
    received: number;
    missed: number;
    talk_time: string;
    avg_outgoing: string;
    avg_incoming: string;
}

interface StaffRow {
    id: number;
    name: string;
    team: string;
    outgoing_calls: number;
    incoming_calls: number;
    connected_calls: number;
    received_calls: number;
    missed_calls: number;
    talk_time: string;
    avg_outgoing: string;
}

interface Props {
    range: { from: string; to: string };
    syncedAt: string;
    callInsights: CallInsights;
    customerInsights: { unique_customers: number; missed_customers: number };
    staffInsights: StaffRow[];
}

export default function Dashboard({ range, syncedAt, callInsights, customerInsights, staffInsights }: Props) {
    const { auth } = usePage<{ auth: { user: { is_owner: boolean } } }>().props;
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);
    const [staffOpen, setStaffOpen] = useState(true);

    const applyRange = (nextFrom = from, nextTo = to) => {
        router.get('/dashboard', { from: nextFrom, to: nextTo }, { preserveState: true, preserveScroll: true });
    };

    const query = `?from=${range.from}&to=${range.to}`;
    const prettyRange =
        range.from === range.to
            ? formatDate(range.from)
            : `${formatDate(range.from)} – ${formatDate(range.to)}`;

    return (
        <ConsoleLayout title="Dashboard" description={`Showing ${prettyRange}`}>
            <Head title="Dashboard" />

            {/* Range picker */}
            <section className="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4">
                <label className="space-y-1.5">
                    <span className="eyebrow block">From</span>
                    <input
                        type="date"
                        value={from}
                        max={to}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                    />
                </label>
                <label className="space-y-1.5">
                    <span className="eyebrow block">To</span>
                    <input
                        type="date"
                        value={to}
                        min={from}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                    />
                </label>

                <Button onClick={() => applyRange()}>Apply</Button>

                <div className="flex flex-wrap gap-2">
                    {[
                        ['Today', 0],
                        ['7 days', 6],
                        ['30 days', 29],
                    ].map(([label, days]) => (
                        <button
                            key={label as string}
                            onClick={() => {
                                const end = new Date();
                                const start = new Date();
                                start.setDate(start.getDate() - (days as number));
                                const iso = (d: Date) => d.toISOString().slice(0, 10);
                                setFrom(iso(start));
                                setTo(iso(end));
                                applyRange(iso(start), iso(end));
                            }}
                            className="rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:bg-muted"
                        >
                            {label as string}
                        </button>
                    ))}
                </div>

                <p className="ml-auto self-center text-xs text-muted-foreground">Synced {syncedAt}</p>
            </section>

            {/* Call insights */}
            <Panel
                title="Call Insights"
                exportHref={`/dashboard/export/calls${query}`}
                exportLabel="Export call records"
            >
                <div className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-border lg:grid-cols-4">
                    <Metric label="Outgoing Calls" value={callInsights.outgoing} />
                    <Metric label="Incoming Calls" value={callInsights.incoming} />
                    <Metric label="Connected Calls" value={callInsights.connected} />
                    <Metric label="Received Calls" value={callInsights.received} />
                    <Metric label="Missed Calls" value={callInsights.missed} tone={callInsights.missed > 0 ? 'bad' : undefined} />
                    <Metric label="Total Talk Time" value={callInsights.talk_time} />
                    <Metric label="Avg. Outgoing Call Duration" value={callInsights.avg_outgoing} />
                    <Metric label="Avg. Incoming Call Duration" value={callInsights.avg_incoming} />
                </div>
            </Panel>

            {/* Customer insights */}
            <Panel
                title="Customer Insights"
                exportHref={`/dashboard/export/customers${query}`}
                exportLabel="Export customers"
                className="mt-5"
            >
                <div className="grid grid-cols-1 gap-px overflow-hidden rounded-lg bg-border sm:grid-cols-2">
                    <Metric label="Unique Customers" value={customerInsights.unique_customers} />
                    <Metric
                        label="Missed Customers"
                        value={customerInsights.missed_customers}
                        tone={customerInsights.missed_customers > 0 ? 'bad' : undefined}
                    />
                </div>
            </Panel>

            {/* Staff level insights — collapsible */}
            <section className="mt-5 overflow-hidden rounded-xl border border-border bg-card">
                <div className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <button
                        onClick={() => setStaffOpen((o) => !o)}
                        aria-expanded={staffOpen}
                        className="flex items-center gap-2 font-display text-lg font-bold"
                    >
                        <svg
                            className={`size-4 transition-transform ${staffOpen ? '' : '-rotate-90'}`}
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2.5"
                            aria-hidden
                        >
                            <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Staff Level Insights
                    </button>

                    <a href={`/dashboard/export/staff${query}`} className="ml-auto">
                        <Button variant="ghost" className="px-3 py-1.5">
                            Export staff insights
                        </Button>
                    </a>
                </div>

                {staffOpen && (
                    <>
                        <p className="px-5 pb-3 text-sm text-muted-foreground">
                            From <b className="text-foreground">{formatDate(range.from)}</b> to{' '}
                            <b className="text-foreground">{formatDate(range.to)}</b>
                        </p>
                        <div className="overflow-x-auto border-t border-border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border">
                                        {[
                                            'Staff Name', 'Team Name', 'Outgoing', 'Incoming', 'Connected',
                                            'Received', 'Missed', 'Total Talk Time', 'Avg Call Duration',
                                        ].map((h, i) => (
                                            <th
                                                key={h}
                                                className={`whitespace-nowrap px-4 py-3 ${i > 1 ? 'text-right' : 'text-left'}`}
                                            >
                                                <span className="eyebrow">{h}</span>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {staffInsights.map((s) => (
                                        <tr key={s.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                                            <td className="whitespace-nowrap px-4 py-3 font-medium">{s.name}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{s.team}</td>
                                            <td className="px-4 py-3 text-right tabular">{s.outgoing_calls}</td>
                                            <td className="px-4 py-3 text-right tabular">{s.incoming_calls}</td>
                                            <td className="px-4 py-3 text-right tabular">{s.connected_calls}</td>
                                            <td className="px-4 py-3 text-right tabular">{s.received_calls}</td>
                                            <td className="px-4 py-3 text-right tabular">{s.missed_calls}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right data">{s.talk_time}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right data">{s.avg_outgoing}</td>
                                        </tr>
                                    ))}
                                    {staffInsights.length === 0 && (
                                        <tr>
                                            <td colSpan={9} className="px-4 py-12 text-center text-muted-foreground">
                                                No staff activity in this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </section>

            {/* Build progress + quick links */}
            <section className="mt-5 grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-border bg-card p-6 lg:col-span-2">
                    <p className="eyebrow">Build progress</p>
                    <h2 className="mt-2 font-display text-xl font-bold">What&rsquo;s live right now</h2>
                    <ul className="mt-5 space-y-3.5">
                        {[
                            ['done', 'Mobile + OTP sign-in', 'Hashed codes, expiry, attempt limits, resend cooldown'],
                            ['done', 'Profile & password', 'Update name, email and mobile; set or reset a password'],
                            ['done', 'Team members', 'Add, edit and remove staff — searchable table and CSV export'],
                            ['done', 'Customers & leads', 'One customer across campaigns, with duplicate merging'],
                            ['done', 'Settings', 'Tags, CRM stages and groups, custom fields, Facebook mapping'],
                            ['done', 'Activity log', 'Every change recorded, with who made it'],
                            ['next', 'Facebook lead sync', 'Needs a Meta app so live campaign leads flow in'],
                            ['later', 'Telephony', 'VICI dial calling — these insights fill in once it is connected'],
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
                        <QuickLink href="/customers" label="Browse customers" />
                        <QuickLink href="/team" label="Manage team members" />
                        <QuickLink href="/profile" label="Update my profile" />
                        {auth.user.is_owner && <QuickLink href="/settings" label="Open settings" />}
                        {auth.user.is_owner && <QuickLink href="/activity" label="Open activity log" />}
                    </div>
                </div>
            </section>
        </ConsoleLayout>
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

function Panel({
    title,
    exportHref,
    exportLabel,
    className = '',
    children,
}: {
    title: string;
    exportHref: string;
    exportLabel: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <section className={`rounded-xl border border-border bg-card p-5 ${className}`}>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h2 className="font-display text-lg font-bold">{title}</h2>
                <a href={exportHref} className="ml-auto">
                    <Button variant="ghost" className="px-3 py-1.5">
                        {exportLabel}
                    </Button>
                </a>
            </div>
            {children}
        </section>
    );
}

function Metric({ label, value, tone }: { label: string; value: string | number; tone?: 'bad' }) {
    return (
        <div className="bg-card p-4">
            <p className="text-[13px] text-muted-foreground">{label}</p>
            <p
                className="mt-1.5 font-display text-2xl font-bold tabular"
                style={tone === 'bad' ? { color: 'var(--bad)' } : undefined}
            >
                {value}
            </p>
        </div>
    );
}

function formatDate(iso: string): string {
    return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
