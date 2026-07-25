import { Head, Link } from '@inertiajs/react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import { Button } from '@/components/ui-kit';
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

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<Customer>;
    filters: Record<string, string | undefined>;
}) {
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
            actions={
                <a href="/customers/export">
                    <Button variant="ghost">Export CSV</Button>
                </a>
            }
        >
            <Head title="Customers" />
            <DataTable
                page={customers}
                columns={columns}
                filters={filters}
                url="/customers"
                searchPlaceholder="Search name, mobile or email…"
                emptyTitle="No customers yet"
                emptyHint="Customers are created automatically as leads arrive."
            />
        </ConsoleLayout>
    );
}
