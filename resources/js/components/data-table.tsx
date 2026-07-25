import { router } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface Column<T> {
    key: string;
    header: string;
    sortable?: boolean;
    align?: 'left' | 'right';
    cell: (row: T) => ReactNode;
}

interface Props<T> {
    page: Paginated<T>;
    columns: Column<T>[];
    filters: Record<string, string | undefined>;
    /** Route the table reloads from — all state travels in the query string. */
    url: string;
    searchPlaceholder?: string;
    toolbar?: ReactNode;
    /**
     * Set when the toolbar carries its own search box, so the two do not
     * compete — the built-in one applies as you type, a filter form applies on
     * submit, and having both would fight over the same query key.
     */
    ownSearch?: boolean;
    emptyTitle?: string;
    emptyHint?: string;
}

/**
 * Server-side table: search, sort and paging are query-string state that the
 * server resolves in SQL. Nothing is filtered in the browser, so the component
 * behaves the same at 10 rows or 10 million.
 */
export function DataTable<T extends { id: number }>({
    page,
    columns,
    filters,
    url,
    searchPlaceholder = 'Search…',
    toolbar,
    ownSearch = false,
    emptyTitle = 'Nothing to show yet',
    emptyHint,
}: Props<T>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    // Debounced so a query fires once the user stops typing, not per keystroke.
    useEffect(() => {
        if (ownSearch || firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            reload({ search: search || undefined, page: undefined });
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const reload = (params: Record<string, string | number | undefined>) => {
        router.get(url, { ...filters, ...params }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleSort = (key: string) => {
        const nextDirection = filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc';
        reload({ sort: key, direction: nextDirection, page: undefined });
    };

    return (
        <div className="space-y-4">
            {ownSearch ? (
                toolbar
            ) : (
            <div className="flex items-center gap-2 overflow-x-auto pb-0.5">
                <div className="relative w-56 shrink-0">
                    <svg
                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        aria-hidden
                    >
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" strokeLinecap="round" />
                    </svg>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={searchPlaceholder}
                        aria-label={searchPlaceholder}
                        className="h-9 w-full rounded-lg border border-input bg-card pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                    />
                </div>
                {toolbar}
            </div>
            )}

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border">
                                {columns.map((col) => (
                                    <th
                                        key={col.key}
                                        scope="col"
                                        className={`px-4 py-3 font-semibold ${col.align === 'right' ? 'text-right' : 'text-left'}`}
                                    >
                                        {col.sortable ? (
                                            <button
                                                onClick={() => toggleSort(col.key)}
                                                className="eyebrow inline-flex items-center gap-1 transition hover:text-foreground"
                                            >
                                                {col.header}
                                                <SortGlyph
                                                    active={filters.sort === col.key}
                                                    direction={filters.direction}
                                                />
                                            </button>
                                        ) : (
                                            <span className="eyebrow">{col.header}</span>
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((row) => (
                                <tr key={row.id} className="border-b border-border/60 transition last:border-0 hover:bg-muted/50">
                                    {columns.map((col) => (
                                        <td
                                            key={col.key}
                                            className={`px-4 py-3.5 ${col.align === 'right' ? 'text-right' : ''}`}
                                        >
                                            {col.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))}

                            {page.data.length === 0 && (
                                <tr>
                                    <td colSpan={columns.length} className="px-4 py-16 text-center">
                                        <p className="font-display text-base font-semibold">{emptyTitle}</p>
                                        {emptyHint && (
                                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">{emptyHint}</p>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {page.total > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            Showing <span className="tabular font-medium text-foreground">{page.from}</span>–
                            <span className="tabular font-medium text-foreground">{page.to}</span> of{' '}
                            <span className="tabular font-medium text-foreground">{page.total}</span>
                        </p>
                        <div className="flex items-center gap-2">
                            <PageButton
                                disabled={page.current_page <= 1}
                                onClick={() => reload({ page: page.current_page - 1 })}
                            >
                                Previous
                            </PageButton>
                            <span className="tabular px-1 text-sm text-muted-foreground">
                                {page.current_page} / {page.last_page}
                            </span>
                            <PageButton
                                disabled={page.current_page >= page.last_page}
                                onClick={() => reload({ page: page.current_page + 1 })}
                            >
                                Next
                            </PageButton>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function PageButton({
    children,
    disabled,
    onClick,
}: {
    children: ReactNode;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className="rounded-lg border border-border px-3 py-1.5 text-sm font-medium transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
        >
            {children}
        </button>
    );
}

function SortGlyph({ active, direction }: { active: boolean; direction?: string }) {
    return (
        <svg className={`size-3 ${active ? 'text-primary' : 'opacity-35'}`} viewBox="0 0 12 12" aria-hidden>
            <path d="M6 1.5 9 5H3z" fill={active && direction === 'asc' ? 'currentColor' : 'currentColor'} opacity={active && direction === 'desc' ? 0.25 : 1} />
            <path d="M6 10.5 3 7h6z" fill="currentColor" opacity={active && direction === 'asc' ? 0.25 : 1} />
        </svg>
    );
}
