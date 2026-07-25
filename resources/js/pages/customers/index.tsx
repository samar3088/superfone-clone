import { Head, Link } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import {
    DateRangeFilter,
    ExportLink,
    FilterRow,
    Filters,
    FilterSelect,
    ResetFilters,
} from '@/components/table-filters';
import ConsoleLayout from '@/layouts/console-layout';

interface Customer {
    id: number;
    name: string;
    mobile: string;
    email: string | null;
    city: string | null;
    leads_count: number;
    last_activity_at: string | null;
}

/** Everything the Reset button clears. */
const FILTER_KEYS = ['search', 'member', 'leads', 'date_from', 'date_to'];

export default function CustomersIndex({
    customers,
    filters,
    members,
}: {
    customers: Paginated<Customer>;
    filters: Filters;
    members: { id: number; name: string }[];
}) {
    const ctx = { filters, url: '/customers' };

    const columns: Column<Customer>[] = [
        {
            key: 'name',
            header: 'Customer',
            sortable: true,
            cell: (c) => (
                <Link href={`/customers/${c.id}`} className="font-semibold hover:text-primary">
                    {c.name}
                    {c.city && <span className="ml-2 text-xs font-normal text-muted-foreground">{c.city}</span>}
                </Link>
            ),
        },
        { key: 'mobile', header: 'Mobile', cell: (c) => <span className="data">{c.mobile}</span> },
        { key: 'email', header: 'Email', cell: (c) => c.email ?? <span className="text-muted-foreground">—</span> },
        {
            key: 'leads',
            header: 'Leads',
            align: 'right',
            cell: (c) => <span className="tabular font-medium">{c.leads_count}</span>,
        },
        {
            key: 'last_activity_at',
            header: 'Last activity',
            sortable: true,
            align: 'right',
            cell: (c) =>
                c.last_activity_at ? (
                    <span className="data text-muted-foreground">
                        {c.last_activity_at.slice(0, 16).replace('T', ' ')}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <ConsoleLayout
            title="Customers"
            description="One record per person. The same person enquiring twice keeps one customer and two leads."
        >
            <Head title="Customers" />
            <DataTable
                page={customers}
                columns={columns}
                filters={filters}
                url="/customers"
                searchPlaceholder="Search name, mobile or email…"
                emptyTitle="No customers match"
                emptyHint="Customers are created automatically as leads arrive. Try clearing the filters."
                toolbar={
                    <FilterRow>
                        <FilterSelect
                            ctx={ctx}
                            name="member"
                            label="All members"
                            options={members.map((m) => ({ value: String(m.id), label: m.name }))}
                        />

                        <FilterSelect
                            ctx={ctx}
                            name="leads"
                            label="All customers"
                            options={[
                                { value: 'with', label: 'With leads' },
                                { value: 'without', label: 'Without leads' },
                            ]}
                        />

                        <DateRangeFilter ctx={ctx} />

                        <ResetFilters ctx={ctx} keys={FILTER_KEYS} />

                        <span className="ml-auto shrink-0">
                            <ExportLink ctx={ctx} path="/customers/export" />
                        </span>
                    </FilterRow>
                }
            />
        </ConsoleLayout>
    );
}
