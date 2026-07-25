import { Head, router } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import {
    DateRangeFilter,
    FilterForm,
    Filters,
    FilterSearch,
    FilterSelect,
    MultiSelect,
} from '@/components/table-filters';
import { Button, Pill } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Lead {
    id: number;
    name: string;
    mobile: string;
    email: string | null;
    source: string;
    campaign: string | null;
    viewed_at: string | null;
    created_at: string;
    assignee: { id: number; name: string } | null;
    stage: { id: number; name: string; type: string; emoji: string | null } | null;
    is_existing: boolean;
}

interface Stage {
    id: number;
    name: string;
    emoji: string | null;
    type: string;
}

interface Props {
    leads: Paginated<Lead>;
    filters: Filters;
    stages: Stage[];
    members: { id: number; name: string }[];
}

/** Everything the Reset button clears. */
const FILTER_KEYS = ['search', 'member', 'stage', 'date_from', 'date_to', 'source', 'unread', 'kind'];

export default function LeadsIndex({ leads, filters, stages, members }: Props) {
    const columns: Column<Lead>[] = [
        {
            key: 'name',
            header: 'Lead',
            sortable: true,
            cell: (l) => (
                <div className="flex items-center gap-2.5">
                    {!l.viewed_at && (
                        <span
                            className="size-2 flex-shrink-0 rounded-full"
                            style={{ background: 'var(--bad)' }}
                            aria-label="Unread"
                        />
                    )}
                    <div className="min-w-0">
                        <p className={`flex items-center gap-2 truncate ${l.viewed_at ? 'font-medium' : 'font-bold'}`}>
                            {l.name}
                            {/* They have enquired on this campaign before. */}
                            {l.is_existing && (
                                <span
                                    className="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                    style={{ background: 'var(--warn-soft)', color: 'var(--warn)' }}
                                    title="Repeat enquiry on this campaign"
                                >
                                    Repeat
                                </span>
                            )}
                        </p>
                        {l.email && <p className="truncate text-xs text-muted-foreground">{l.email}</p>}
                    </div>
                </div>
            ),
        },
        { key: 'mobile', header: 'Mobile', cell: (l) => <span className="data">{l.mobile}</span> },
        {
            key: 'source',
            header: 'Source',
            sortable: true,
            cell: (l) => (
                <div>
                    <span className="capitalize">{l.source}</span>
                    {l.campaign && <p className="truncate text-xs text-muted-foreground">{l.campaign}</p>}
                </div>
            ),
        },
        {
            key: 'assignee',
            header: 'Assigned to',
            cell: (l) =>
                l.assignee ? l.assignee.name : <span className="text-muted-foreground">Unassigned</span>,
        },
        {
            /*
                Shows the CRM stage rather than read/unread, so it matches the
                Status filter beside it. Unread is still visible — it is the red
                dot against the lead's name.
            */
            key: 'status',
            header: 'Status',
            cell: (l) =>
                l.stage ? (
                    <Pill tone={stageTone(l.stage.type)}>
                        {l.stage.emoji ? `${l.stage.emoji} ` : ''}
                        {l.stage.name}
                    </Pill>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <ConsoleLayout
            title="Leads"
            description="Enquiries captured from your campaigns, assigned to the team."
            actions={
                <Button variant="ghost" onClick={() => router.post('/leads/mark-read', {}, { preserveScroll: true })}>
                    Mark all read
                </Button>
            }
        >
            <Head title="Leads" />

            <DataTable
                page={leads}
                columns={columns}
                filters={filters}
                url="/leads"
                emptyTitle="No leads match"
                emptyHint="Try widening the date range, or reset the filters."
                ownSearch
                toolbar={
                    <FilterForm
                        url="/leads"
                        filters={filters}
                        keys={FILTER_KEYS}
                        exportPath="/leads/export"
                    >
                        <FilterSearch placeholder="Search name, mobile or campaign…" />

                        {members.length > 0 && (
                            <MultiSelect
                                name="member"
                                label="All members"
                                searchPlaceholder="Search members…"
                                options={[
                                    { value: 'unassigned', label: 'Unassigned' },
                                    ...members.map((m) => ({ value: String(m.id), label: m.name })),
                                ]}
                            />
                        )}

                        <MultiSelect
                            name="stage"
                            label="All statuses"
                            searchPlaceholder="Search statuses…"
                            options={stages.map((s) => ({
                                value: String(s.id),
                                label: s.emoji ? `${s.emoji} ${s.name}` : s.name,
                            }))}
                        />

                        <FilterSelect
                            name="kind"
                            label="Fresh & repeat"
                            options={[
                                { value: 'fresh', label: 'Fresh only' },
                                { value: 'existing', label: 'Repeat only' },
                            ]}
                        />

                        <DateRangeFilter />
                    </FilterForm>
                }
            />
        </ConsoleLayout>
    );
}

function stageTone(type: string): 'good' | 'warn' | 'bad' | 'neutral' {
    if (type === 'FINAL_POSITIVE') return 'good';
    if (type === 'FINAL_NEGATIVE') return 'bad';
    if (type === 'INITIAL') return 'warn';
    return 'neutral';
}
