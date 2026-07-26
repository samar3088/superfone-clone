import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import { AdvancedFilters, FilterGroup } from '@/components/advanced-filters';
import { Column, DataTable, Paginated } from '@/components/data-table';
import { Button } from '@/components/ui-kit';
import CreateContact, { ContactOptions } from './create-contact';
import {
    DateRangeFilter,
    FilterForm,
    Filters,
    FilterSearch,
    FilterSelect,
    MultiSelect,
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
    options,
}: {
    customers: Paginated<Customer>;
    filters: Filters;
    members: { id: number; name: string }[];
    options: ContactOptions;
}) {
    const [creating, setCreating] = useState(false);
    const [moreFilters, setMoreFilters] = useState(false);

    /*
     | Assigned to and the search box stay in the always-visible row, because
     | they are the two people reach for constantly. The rest live behind
     | "Add more filters" rather than growing the row until it scrolls.
     */
    const filterGroups: FilterGroup[] = [
        {
            name: 'team',
            label: 'Team name',
            options: options.teams.map((t) => ({ value: String(t.id), label: t.name })),
        },
        {
            name: 'tags',
            label: 'Tags',
            options: options.tags.map((t) => ({
                value: String(t.id),
                label: t.name,
                swatch: t.color,
                emoji: t.emoji,
            })),
        },
        {
            name: 'stage',
            label: 'Lead stages',
            options: options.stages.map((s) => ({
                value: String(s.id),
                label: s.name,
                emoji: s.emoji,
            })),
        },
        {
            name: 'group',
            label: 'Lead groups',
            options: options.groups.map((g) => ({ value: String(g.id), label: g.name })),
        },
        {
            name: 'creator',
            label: 'Created by',
            options: options.creators.map((c) => ({ value: String(c.id), label: c.name })),
        },
        { name: 'created', label: 'Created date', kind: 'dates', fromName: 'date_from', toName: 'date_to' },
    ];

    // Shown on the button so the count survives a page load.
    const advancedCount = filterGroups.filter((g) =>
        g.kind === 'dates'
            ? filters[g.fromName!] || filters[g.toName!]
            : filters[g.name],
    ).length;

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
            actions={<Button onClick={() => setCreating(true)}>＋ Create contact</Button>}
        >
            <Head title="Customers" />
            <DataTable
                page={customers}
                columns={columns}
                filters={filters}
                url="/customers"
                emptyTitle="No customers match"
                emptyHint="Customers are created automatically as leads arrive. Try resetting the filters."
                ownSearch
                toolbar={
                    <FilterForm
                        url="/customers"
                        filters={filters}
                        keys={FILTER_KEYS}
                        exportPath="/customers/export"
                    >
                        <FilterSearch placeholder="Search name, mobile or email…" />

                        <MultiSelect
                            name="member"
                            label="All members"
                            searchPlaceholder="Search members…"
                            options={members.map((m) => ({ value: String(m.id), label: m.name }))}
                        />

                        <FilterSelect
                            name="leads"
                            label="All customers"
                            options={[
                                { value: 'with', label: 'With leads' },
                                { value: 'without', label: 'Without leads' },
                            ]}
                        />

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
                }
            />

            {moreFilters && (
                <AdvancedFilters
                    url="/customers"
                    filters={filters}
                    groups={filterGroups}
                    onClose={() => setMoreFilters(false)}
                />
            )}

            {creating && (
                <CreateContact
                    options={options}
                    members={members}
                    onClose={() => setCreating(false)}
                />
            )}
        </ConsoleLayout>
    );
}
