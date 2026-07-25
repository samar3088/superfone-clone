import { Head, router } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
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
}

export default function LeadsIndex({
    leads,
    filters,
}: {
    leads: Paginated<Lead>;
    filters: Record<string, string | undefined>;
}) {
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
                        <p className={`truncate ${l.viewed_at ? 'font-medium' : 'font-bold'}`}>{l.name}</p>
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
            key: 'status',
            header: 'Status',
            cell: (l) => <Pill tone={l.viewed_at ? 'neutral' : 'bad'}>{l.viewed_at ? 'Seen' : 'New'}</Pill>,
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
                searchPlaceholder="Search name, mobile or campaign…"
                emptyTitle="No leads yet"
                emptyHint="Once the Facebook integration is connected, campaign leads will land here automatically and the bell will light up."
            />
        </ConsoleLayout>
    );
}
