import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import { AdvancedFilters, FilterGroup } from '@/components/advanced-filters';
import { Column, DataTable, Paginated } from '@/components/data-table';
import { ColumnChoice, TableSettings } from '@/components/table-settings';
import { Button } from '@/components/ui-kit';
import { useColumns } from '@/hooks/use-columns';
import CreateContact, { ContactOptions } from './create-contact';
import DownloadContacts from './download-contacts';
import Duplicates from './duplicates';
import ImportContacts from './import-contacts';
import NotesModal from './notes';
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
    business_name: string | null;
    additional_info: string | null;
    leads_count: number;
    notes_count: number;
    last_activity_at: string | null;
    created_at: string;
    tags: { id: number; name: string; color: string; emoji: string | null }[];
    team: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    latest_lead: {
        source: string | null;
        deal_value: string | null;
        stage: { id: number; name: string; emoji: string | null } | null;
        assignee: { id: number; name: string } | null;
    } | null;
}

/** Every column the chooser offers. Name is locked — a row needs an identity. */
const COLUMN_CHOICES: ColumnChoice[] = [
    { key: 'name', label: 'Name', locked: true },
    { key: 'city', label: 'City' },
    { key: 'additional_info', label: 'Additional Info' },
    { key: 'email', label: 'Email' },
    { key: 'tags', label: 'Tags' },
    { key: 'stage', label: 'Lead stage' },
    { key: 'deal_value', label: 'Deal Value' },
    { key: 'source', label: 'Source' },
    { key: 'owner', label: 'Lead Owner' },
    { key: 'team', label: 'Team Name' },
    { key: 'business_name', label: 'Business Name' },
    { key: 'leads', label: 'Leads' },
    { key: 'notes', label: 'Notes' },
    { key: 'created_at', label: 'Date created' },
    { key: 'last_activity_at', label: 'Last activity' },
];

const DEFAULT_COLUMNS = ['name', 'additional_info', 'email', 'tags', 'stage', 'source', 'owner', 'leads', 'notes'];

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
    const [importing, setImporting] = useState(false);
    const [tableSettings, setTableSettings] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [notesFor, setNotesFor] = useState<Customer | null>(null);
    const [downloading, setDownloading] = useState(false);
    const [dedupe, setDedupe] = useState(false);

    const [visibleColumns, setVisibleColumns] = useColumns('customers.columns', DEFAULT_COLUMNS);

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

    /*
     | Every column the table can show. Which ones actually render is decided
     | by the chooser — defined here in one list so the chooser and the table
     | cannot disagree about what exists.
     */
    const allColumns: Record<string, Column<Customer>> = {
        name: {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (c) => (
                <div className="min-w-0">
                    <Link href={`/customers/${c.id}`} className="font-semibold hover:text-primary">
                        {c.name}
                    </Link>
                    <p className="data text-xs text-muted-foreground">{c.mobile}</p>
                </div>
            ),
        },
        city: { key: 'city', header: 'City', cell: (c) => c.city ?? <Dash /> },
        additional_info: {
            key: 'additional_info',
            header: 'Additional Info',
            cell: (c) => c.additional_info
                ? <span className="line-clamp-1" title={c.additional_info}>{c.additional_info}</span>
                : <Dash />,
        },
        email: { key: 'email', header: 'Email', cell: (c) => c.email ?? <Dash /> },
        tags: {
            key: 'tags',
            header: 'Tags',
            cell: (c) => c.tags.length === 0 ? <Dash /> : (
                <span className="flex flex-wrap gap-1">
                    {c.tags.map((t) => (
                        <span
                            key={t.id}
                            className="rounded-md px-1.5 py-0.5 text-xs font-semibold"
                            style={{
                                background: `color-mix(in srgb, ${t.color} 14%, transparent)`,
                                color: t.color,
                            }}
                        >
                            {t.emoji} {t.name}
                        </span>
                    ))}
                </span>
            ),
        },
        stage: {
            key: 'stage',
            header: 'Lead stage',
            cell: (c) => c.latest_lead?.stage
                ? <span>{c.latest_lead.stage.emoji} {c.latest_lead.stage.name}</span>
                : <Dash />,
        },
        deal_value: {
            key: 'deal_value',
            header: 'Deal Value',
            align: 'right',
            cell: (c) => c.latest_lead?.deal_value
                ? <span className="tabular">{Number(c.latest_lead.deal_value).toLocaleString()}</span>
                : <Dash />,
        },
        source: { key: 'source', header: 'Source', cell: (c) => c.latest_lead?.source ?? <Dash /> },
        owner: {
            key: 'owner',
            header: 'Lead Owner',
            cell: (c) => c.latest_lead?.assignee?.name ?? <Dash />,
        },
        team: { key: 'team', header: 'Team Name', cell: (c) => c.team?.name ?? <Dash /> },
        business_name: { key: 'business_name', header: 'Business Name', cell: (c) => c.business_name ?? <Dash /> },
        leads: {
            key: 'leads',
            header: 'Leads',
            align: 'right',
            cell: (c) => <span className="tabular font-medium">{c.leads_count}</span>,
        },
        notes: {
            key: 'notes',
            header: 'Notes',
            cell: (c) => (
                <button
                    type="button"
                    onClick={() => setNotesFor(c)}
                    title={`Notes on ${c.name}`}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-border px-2 py-1 text-xs font-semibold transition hover:bg-muted"
                >
                    <svg viewBox="0 0 24 24" className="size-3.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="M4 5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" />
                        <path d="M14 3v6h6M8 13h8M8 17h5" />
                    </svg>
                    <span className="tabular">{c.notes_count || 'Add'}</span>
                </button>
            ),
        },
        created_at: {
            key: 'created_at',
            header: 'Date created',
            sortable: true,
            cell: (c) => <span className="data text-muted-foreground">{c.created_at.slice(0, 10)}</span>,
        },
        last_activity_at: {
            key: 'last_activity_at',
            header: 'Last activity',
            sortable: true,
            align: 'right',
            cell: (c) => c.last_activity_at
                ? <span className="data text-muted-foreground">{c.last_activity_at.slice(0, 16).replace('T', ' ')}</span>
                : <Dash />,
        },
    };

    // Rendered in the chooser's order, so the table always reads the same way.
    const columns = COLUMN_CHOICES
        .filter((c) => visibleColumns.includes(c.key))
        .map((c) => allColumns[c.key])
        .filter(Boolean);

    return (
        <ConsoleLayout
            title="Customers"
            description="One record per person. The same person enquiring twice keeps one customer and two leads."
            actions={
                <div className="flex items-center gap-2">
                    <Button onClick={() => setCreating(true)}>＋ Create contact</Button>

                    <div className="relative">
                        <button
                            type="button"
                            aria-label="More actions"
                            aria-expanded={menuOpen}
                            onClick={() => setMenuOpen((o) => !o)}
                            className="grid size-10 place-items-center rounded-lg border border-border text-lg transition hover:bg-muted"
                        >
                            ⋯
                        </button>

                        {menuOpen && (
                            <>
                                {/* Catches the click that closes the menu, so it
                                    shuts on any outside press. */}
                                <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />

                                <div className="absolute right-0 top-12 z-50 w-52 overflow-hidden rounded-lg border border-border bg-card py-1 shadow-xl">
                                    <MenuItem onClick={() => { setMenuOpen(false); setImporting(true); }}>
                                        Import CSV
                                    </MenuItem>
                                    <MenuItem onClick={() => { setMenuOpen(false); setDownloading(true); }}>
                                        Download
                                    </MenuItem>
                                    <MenuItem onClick={() => { setMenuOpen(false); setDedupe(true); }}>
                                        Delete duplicates
                                    </MenuItem>
                                    <MenuItem onClick={() => { setMenuOpen(false); setTableSettings(true); }}>
                                        Table settings
                                    </MenuItem>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            }
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
                    /*
                     | No exportPath: Export is now the Download dialog, where
                     | the columns can be chosen. Two buttons producing two
                     | different files from the same rows would be a trap.
                     */
                    <FilterForm
                        url="/customers"
                        filters={filters}
                        keys={FILTER_KEYS}
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

                        <button
                            type="button"
                            onClick={() => setDownloading(true)}
                            className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition hover:opacity-85"
                            style={{ background: 'var(--info-soft)', color: 'var(--info)' }}
                        >
                            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            Download
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

            {importing && (
                <ImportContacts
                    options={options}
                    members={members}
                    onClose={() => setImporting(false)}
                />
            )}

            {notesFor && <NotesModal customer={notesFor} onClose={() => setNotesFor(null)} />}

            {dedupe && <Duplicates onClose={() => setDedupe(false)} />}

            {downloading && (
                <DownloadContacts
                    filters={filters}
                    columns={options.downloadColumns}
                    defaults={options.downloadDefaults}
                    onClose={() => setDownloading(false)}
                />
            )}

            {tableSettings && (
                <TableSettings
                    columns={COLUMN_CHOICES}
                    visible={visibleColumns}
                    onSave={setVisibleColumns}
                    onClose={() => setTableSettings(false)}
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

/** An empty cell, said the same way everywhere. */
function Dash() {
    return <span className="text-muted-foreground">—</span>;
}

function MenuItem({ onClick, children }: { onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-muted"
        >
            {children}
        </button>
    );
}
