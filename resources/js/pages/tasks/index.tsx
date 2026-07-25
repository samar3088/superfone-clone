import { Head, router } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import {
    FilterForm,
    Filters,
    FilterSearch,
    FilterSelect,
    MultiSelect,
} from '@/components/table-filters';
import { Button, Pill } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Task {
    id: number;
    trigger: string;
    type: string;
    title: string;
    due_at: string | null;
    completed_at: string | null;
    created_at: string;
    lead: { id: number; name: string; mobile: string; is_existing: boolean } | null;
    assignee: { id: number; name: string } | null;
}

interface Props {
    tasks: Paginated<Task>;
    filters: Filters;
    members: { id: number; name: string }[];
    types: string[];
    counts: { open: number; overdue: number };
}

const FILTER_KEYS = ['search', 'member', 'state', 'type'];

export default function TasksIndex({ tasks, filters, members, types, counts }: Props) {
    const columns: Column<Task>[] = [
        {
            key: 'title',
            header: 'To-do',
            cell: (t) => (
                <div className="min-w-0">
                    <p className={`truncate ${t.completed_at ? 'text-muted-foreground line-through' : 'font-medium'}`}>
                        {t.title}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">{t.type}</p>
                </div>
            ),
        },
        {
            key: 'lead',
            header: 'Lead',
            cell: (t) =>
                t.lead ? (
                    <div className="min-w-0">
                        <p className="flex items-center gap-1.5 truncate">
                            {t.lead.name}
                            {t.lead.is_existing && (
                                <span
                                    className="shrink-0 rounded px-1 py-0.5 text-[10px] font-bold uppercase"
                                    style={{ background: 'var(--warn-soft)', color: 'var(--warn)' }}
                                >
                                    Repeat
                                </span>
                            )}
                        </p>
                        <p className="data truncate text-xs text-muted-foreground">{t.lead.mobile}</p>
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'assignee',
            header: 'Owner',
            cell: (t) => t.assignee?.name ?? <span className="text-muted-foreground">Unassigned</span>,
        },
        {
            key: 'due_at',
            header: 'Due',
            sortable: true,
            cell: (t) => <Due at={t.due_at} done={!!t.completed_at} />,
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (t) =>
                t.completed_at ? (
                    <Button
                        variant="ghost"
                        className="px-2.5 py-1.5"
                        onClick={() => router.patch(`/todos/${t.id}/reopen`, {}, { preserveScroll: true })}
                    >
                        Reopen
                    </Button>
                ) : (
                    <Button
                        className="px-2.5 py-1.5"
                        onClick={() => router.patch(`/todos/${t.id}/complete`, {}, { preserveScroll: true })}
                    >
                        Done
                    </Button>
                ),
        },
    ];

    return (
        <ConsoleLayout
            title="To-Dos"
            description="Work raised automatically when leads arrive, and anything added by hand."
            actions={
                <div className="flex items-center gap-2">
                    <Pill tone="neutral">{counts.open} open</Pill>
                    {counts.overdue > 0 && <Pill tone="bad">{counts.overdue} overdue</Pill>}
                </div>
            }
        >
            <Head title="To-Dos" />

            <DataTable
                page={tasks}
                columns={columns}
                filters={filters}
                url="/todos"
                emptyTitle="Nothing to do"
                emptyHint="To-dos appear here when a campaign's rules raise one, or when you add one against a lead."
                ownSearch
                toolbar={
                    <FilterForm url="/todos" filters={filters} keys={FILTER_KEYS}>
                        <FilterSearch placeholder="Search to-dos…" />

                        <FilterSelect
                            name="state"
                            label="All states"
                            options={[
                                { value: 'open', label: 'Open' },
                                { value: 'overdue', label: 'Overdue' },
                                { value: 'done', label: 'Done' },
                            ]}
                        />

                        {members.length > 0 && (
                            <MultiSelect
                                name="member"
                                label="All owners"
                                searchPlaceholder="Search members…"
                                options={members.map((m) => ({ value: String(m.id), label: m.name }))}
                            />
                        )}

                        {types.length > 0 && (
                            <MultiSelect
                                name="type"
                                label="All types"
                                searchPlaceholder="Search types…"
                                options={types.map((t) => ({ value: t, label: t }))}
                            />
                        )}
                    </FilterForm>
                }
            />
        </ConsoleLayout>
    );
}

/** Overdue is the only thing worth colouring — it is the reason to look. */
function Due({ at, done }: { at: string | null; done: boolean }) {
    if (!at) {
        return <span className="text-muted-foreground">No deadline</span>;
    }

    const when = new Date(at);
    const overdue = !done && when.getTime() < Date.now();
    const label = when.toISOString().slice(0, 16).replace('T', ' ');

    return (
        <span className="data" style={overdue ? { color: 'var(--bad)', fontWeight: 600 } : undefined}>
            {label}
            {overdue && ' · overdue'}
        </span>
    );
}
