import { Head, router } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import { inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface ActivityRow {
    id: number;
    log_name: string;
    description: string;
    subject: string | null;
    causer: string;
    created_at: string;
    ago: string;
}

export default function ActivityIndex({
    activities,
    filters,
    logNames,
}: {
    activities: Paginated<ActivityRow>;
    filters: Record<string, string | undefined>;
    logNames: string[];
}) {
    const columns: Column<ActivityRow>[] = [
        {
            key: 'description',
            header: 'Event',
            cell: (a) => (
                <div>
                    <p className="font-medium capitalize">{a.description}</p>
                    {a.subject && <p className="text-xs text-muted-foreground">{a.subject}</p>}
                </div>
            ),
        },
        {
            key: 'log_name',
            header: 'Area',
            sortable: true,
            cell: (a) => (
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold capitalize">{a.log_name}</span>
            ),
        },
        { key: 'causer', header: 'By', cell: (a) => a.causer },
        {
            key: 'created_at',
            header: 'When',
            sortable: true,
            align: 'right',
            cell: (a) => (
                <span title={a.created_at} className="text-muted-foreground">
                    {a.ago}
                </span>
            ),
        },
    ];

    return (
        <ConsoleLayout title="Activity Log" description="Every change made in the system, and who made it.">
            <Head title="Activity Log" />

            <DataTable
                page={activities}
                columns={columns}
                filters={filters}
                url="/activity"
                searchPlaceholder="Search events…"
                emptyTitle="No activity recorded yet"
                toolbar={
                    <select
                        value={filters.log_name ?? ''}
                        onChange={(e) =>
                            router.get(
                                '/activity',
                                { ...filters, log_name: e.target.value || undefined, page: undefined },
                                { preserveState: true, replace: true }
                            )
                        }
                        aria-label="Filter by area"
                        className={`${inputClass} w-auto py-2 capitalize`}
                    >
                        <option value="">All areas</option>
                        {logNames.map((n) => (
                            <option key={n} value={n} className="capitalize">
                                {n}
                            </option>
                        ))}
                    </select>
                }
            />
        </ConsoleLayout>
    );
}
