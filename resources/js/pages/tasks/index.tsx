import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { AdvancedFilters, FilterGroup } from '@/components/advanced-filters';
import { DataTable, Paginated } from '@/components/data-table';
import {
    FilterForm,
    Filters,
    FilterSearch,
    FilterSelect,
    MultiSelect,
} from '@/components/table-filters';
import ConsoleLayout from '@/layouts/console-layout';

interface Task {
    id: number;
    trigger: string;
    type: string;
    title: string;
    due_at: string | null;
    completed_at: string | null;
    created_at: string;
    lead: {
        id: number;
        name: string;
        mobile: string;
        is_existing: boolean;
        stage: { id: number; name: string; emoji: string | null; type: string } | null;
    } | null;
    assignee: { id: number; name: string } | null;
}

interface Props {
    tasks: Paginated<Task>;
    tab: string;
    filters: Filters;
    members: { id: number; name: string }[];
    teams: { id: number; name: string }[];
    types: string[];
    tabCounts: Record<string, number>;
    usageByTeam: { team: string; total: number }[];
}

/**
 * Kept in step with TaskService::TABS.
 *
 * The split is whether anyone has acted on the lead yet — not what raised the
 * work. Each tab says what it holds when it is empty, because an empty list
 * with no explanation reads as a broken screen.
 */
const TABS = [
    {
        key: 'fresh',
        label: 'Fresh Leads',
        emptyTitle: 'Nothing untouched',
        emptyHint: 'Every lead with work outstanding has already been picked up. New enquiries land here first.',
    },
    {
        key: 'followups',
        label: 'Follow Ups',
        emptyTitle: 'Nothing to chase',
        emptyHint: 'Work on leads someone has already started appears here — once a lead moves stage or a to-do is ticked off.',
    },
    {
        key: 'reminders',
        label: 'Reminders',
        emptyTitle: 'Not built yet',
        emptyHint: 'Held empty on purpose until the client tells us what a Reminder should be. Nothing is hidden here — every to-do is on one of the other two tabs.',
    },
];

/*
 | Everything Reset clears — including the two that have no control in the row.
 |
 | "type" is the chip row above the list, and the last four live behind
 | "Add more filters". They are listed here anyway, because clearing the filters
 | should clear the filters, wherever the person happened to set them.
 */
const FILTER_KEYS = [
    'search', 'member', 'status', 'type',
    'team', 'due_from', 'due_to', 'lead_from', 'lead_to',
];

export default function TasksIndex({
    tasks,
    tab,
    filters,
    members,
    teams,
    types,
    tabCounts,
    usageByTeam,
}: Props) {
    const [moreFilters, setMoreFilters] = useState(false);

    const current = TABS.find((t) => t.key === tab) ?? TABS[0];

    /*
     | Three controls stay in the row: what you are looking for, whether it is
     | still open, and whose it is. Those are the ones reached for constantly.
     |
     | The rest go behind "Add more filters" rather than growing the row until
     | it scrolls sideways and hides its own Export button — which is exactly
     | what happened on Leads.
     */
    const filterGroups: FilterGroup[] = [
        { name: 'due', label: 'Task due date', kind: 'dates', fromName: 'due_from', toName: 'due_to' },
        {
            name: 'team',
            label: 'Team name',
            options: teams.map((t) => ({ value: String(t.id), label: t.name })),
        },
        { name: 'created', label: 'Lead created date', kind: 'dates', fromName: 'lead_from', toName: 'lead_to' },
    ];

    // On the button, so the count survives a page load.
    const advancedCount = filterGroups.filter((g) =>
        g.kind === 'dates' ? filters[g.fromName!] || filters[g.toName!] : filters[g.name],
    ).length;

    /*
     | The tab and the type chip are not part of the filter form — they apply on
     | click rather than waiting for the Filter button, because they read as
     | navigation. Everything else in the query string rides along untouched.
     */
    const go = (params: Record<string, string | undefined>) =>
        router.get('/todos', { ...filters, tab, ...params, page: undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const activeType = filters.type ?? '';

    return (
        <ConsoleLayout
            title="To-Dos"
            description="Work raised automatically when leads arrive, and anything added by hand."
        >
            <Head title="To-Dos" />

            <div className="space-y-4">
                {/* The tab rides along so applying or resetting a filter keeps
                    you on the list you were reading. */}
                <FilterForm url="/todos" filters={{ ...filters, tab }} keys={FILTER_KEYS}>
                    <FilterSearch placeholder="Search to-dos…" />

                    <FilterSelect
                        name="status"
                        label="All statuses"
                        options={[
                            { value: 'open', label: 'Open' },
                            { value: 'overdue', label: 'Overdue' },
                            { value: 'done', label: 'Done' },
                        ]}
                    />

                    {/* A member only ever has their own work, so the picker
                        would offer a list of one. */}
                    {members.length > 0 && (
                        <MultiSelect
                            name="member"
                            label="Assigned to"
                            searchPlaceholder="Search members…"
                            options={members.map((m) => ({ value: String(m.id), label: m.name }))}
                        />
                    )}

                    <button
                        type="button"
                        onClick={() => setMoreFilters(true)}
                        className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-dashed border-input px-3 text-sm font-medium transition hover:bg-muted"
                    >
                        ＋ Add more filters
                        {advancedCount > 0 && (
                            <span className="tabular rounded-full bg-primary px-1.5 text-xs font-bold text-primary-foreground">
                                {advancedCount}
                            </span>
                        )}
                    </button>
                </FilterForm>

                {usageByTeam.length > 0 && <UsageByTeam rows={usageByTeam} />}

                <div className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="flex overflow-x-auto border-b border-border">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                onClick={() => go({ tab: t.key })}
                                aria-current={tab === t.key ? 'page' : undefined}
                                className={`relative shrink-0 px-5 py-3 text-sm font-semibold transition ${
                                    tab === t.key
                                        ? 'text-primary'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {t.label}
                                <span
                                    className={`tabular ml-2 rounded-full px-1.5 py-0.5 text-xs ${
                                        tab === t.key
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {tabCounts[t.key] ?? 0}
                                </span>
                                {tab === t.key && (
                                    <span className="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary" />
                                )}
                            </button>
                        ))}
                    </div>

                    {types.length > 0 && (
                        <div className="flex flex-wrap gap-2 px-4 py-3">
                            <Chip active={activeType === ''} onClick={() => go({ type: undefined })}>
                                All
                            </Chip>
                            {types.map((t) => (
                                <Chip key={t} active={activeType === t} onClick={() => go({ type: t })}>
                                    {t}
                                </Chip>
                            ))}
                        </div>
                    )}
                </div>

                <DataTable
                    page={tasks}
                    filters={{ ...filters, tab }}
                    url="/todos"
                    ownSearch
                    emptyTitle={current.emptyTitle}
                    emptyHint={current.emptyHint}
                    renderCard={(t) => <TaskCard task={t} />}
                />
            </div>

            {moreFilters && (
                <AdvancedFilters
                    url="/todos"
                    // The tab rides along, so applying from the panel keeps you
                    // on the list you were reading.
                    filters={{ ...filters, tab }}
                    groups={filterGroups}
                    onClose={() => setMoreFilters(false)}
                />
            )}
        </ConsoleLayout>
    );
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide transition ${
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border text-muted-foreground hover:border-primary/50 hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

/** Open work per team — where the pressure is, before opening a single card. */
function UsageByTeam({ rows }: { rows: { team: string; total: number }[] }) {
    const highest = Math.max(...rows.map((r) => r.total), 1);

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <h2 className="eyebrow mb-3">Usage by team</h2>

            <div className="space-y-2.5">
                {rows.map((r) => (
                    <div key={r.team} className="flex items-center gap-3">
                        <span className="w-40 shrink-0 truncate text-sm font-medium" title={r.team}>
                            {r.team}
                        </span>
                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{ width: `${(r.total / highest) * 100}%` }}
                            />
                        </div>
                        <span className="tabular w-10 shrink-0 text-right text-sm font-semibold">
                            {r.total}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}

function TaskCard({ task }: { task: Task }) {
    const done = !!task.completed_at;
    const lead = task.lead;

    return (
        <div className="flex flex-wrap items-center gap-3 px-4 py-3.5 transition hover:bg-muted/40">
            <Avatar name={lead?.name ?? task.title} />

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    {lead ? (
                        <Link
                            href={`/leads/${lead.id}`}
                            className={`truncate font-semibold hover:text-primary ${done ? 'text-muted-foreground line-through' : ''}`}
                        >
                            {lead.name}
                        </Link>
                    ) : (
                        <span className="truncate font-semibold">{task.title}</span>
                    )}

                    {lead?.stage && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold">
                            {lead.stage.emoji} {lead.stage.name}
                        </span>
                    )}

                    {lead?.is_existing && (
                        <span
                            className="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            style={{ background: 'var(--warn-soft)', color: 'var(--warn)' }}
                        >
                            Repeat
                        </span>
                    )}
                </div>

                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                    <span className="font-semibold uppercase tracking-wide">{task.type}</span>
                    <span aria-hidden>·</span>
                    <span className="truncate">{task.title}</span>
                    {lead && (
                        <>
                            <span aria-hidden>·</span>
                            <span className="data">{lead.mobile}</span>
                        </>
                    )}
                </p>
            </div>

            <Due at={task.due_at} done={done} />

            <span className="w-32 shrink-0 truncate text-sm text-muted-foreground" title={task.assignee?.name}>
                {task.assignee?.name ?? 'Unassigned'}
            </span>

            <div className="flex shrink-0 items-center gap-1.5">
                {lead && (
                    <>
                        <IconLink href={`tel:${lead.mobile}`} label={`Call ${lead.name}`} tone="var(--good)">
                            <path d="M4 5c0-1 1-2 2-2h1.5l1.5 4-2 1a12 12 0 0 0 5 5l1-2 4 1.5V19c0 1-1 2-2 2A16 16 0 0 1 4 5Z" />
                        </IconLink>

                        <IconLink
                            href={`https://wa.me/91${lead.mobile}`}
                            label={`WhatsApp ${lead.name}`}
                            tone="#25d366"
                            external
                        >
                            <path d="M3.5 20.5 5 16a8 8 0 1 1 3 3l-4.5 1.5Z" />
                            <path d="M8.5 9.5c0 3 3 6 6 6l1.5-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2-2 1Z" />
                        </IconLink>
                    </>
                )}

                <button
                    type="button"
                    onClick={() =>
                        router.patch(`/todos/${task.id}/${done ? 'reopen' : 'complete'}`, {}, { preserveScroll: true })
                    }
                    className={`h-8 rounded-lg px-3 text-sm font-semibold transition hover:opacity-85 ${
                        done ? 'border border-border' : 'bg-primary text-primary-foreground'
                    }`}
                >
                    {done ? 'Reopen' : 'Done'}
                </button>
            </div>
        </div>
    );
}

/** Initials on a tinted disc — the same person keeps the same colour. */
function Avatar({ name }: { name: string }) {
    const initials = name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    // Deterministic hue from the name, so the list is scannable by colour.
    const hue = [...name].reduce((sum, c) => sum + c.charCodeAt(0), 0) % 360;

    return (
        <span
            aria-hidden
            className="grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold"
            style={{ background: `hsl(${hue} 70% 92%)`, color: `hsl(${hue} 55% 32%)` }}
        >
            {initials || '?'}
        </span>
    );
}

/**
 * How late the work is, in the words someone would use.
 *
 * Overdue is the only thing worth colouring — it is the reason to look.
 */
function Due({ at, done }: { at: string | null; done: boolean }) {
    if (!at) {
        return <span className="w-32 shrink-0 text-sm text-muted-foreground">No deadline</span>;
    }

    const when = new Date(at);
    const minutes = Math.round((Date.now() - when.getTime()) / 60000);
    const overdue = !done && minutes > 0;

    return (
        <span
            className={`w-32 shrink-0 text-sm font-semibold ${overdue ? '' : 'text-muted-foreground'}`}
            title={when.toLocaleString()}
            style={overdue ? { color: 'var(--bad)' } : undefined}
        >
            {overdue ? `${relative(minutes)} overdue` : `Due ${relative(-minutes)}`}
        </span>
    );
}

/** "3 days", "20 mins" — the largest unit that still says something useful. */
function relative(minutes: number): string {
    const n = Math.abs(minutes);

    if (n < 60) return `${n} min${n === 1 ? '' : 's'}`;

    const hours = Math.round(n / 60);
    if (hours < 24) return `${hours} hr${hours === 1 ? '' : 's'}`;

    const days = Math.round(hours / 24);
    return `${days} day${days === 1 ? '' : 's'}`;
}

function IconLink({
    href,
    label,
    tone,
    external = false,
    children,
}: {
    href: string;
    label: string;
    tone: string;
    external?: boolean;
    children: React.ReactNode;
}) {
    return (
        <a
            href={href}
            aria-label={label}
            title={label}
            {...(external ? { target: '_blank', rel: 'noreferrer' } : {})}
            className="grid size-8 place-items-center rounded-lg border border-border transition hover:opacity-80"
            style={{ color: tone }}
        >
            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                {children}
            </svg>
        </a>
    );
}
