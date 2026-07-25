import { router } from '@inertiajs/react';
import { ReactNode } from 'react';

/**
 * Filter controls for the server-side tables.
 *
 * Every control writes straight to the query string, which is the same state
 * DataTable reads and the export links carry — so what you see, what you share
 * as a URL, and what lands in the CSV are always the same set of rows.
 */

export type Filters = Record<string, string | undefined>;

interface Ctx {
    filters: Filters;
    url: string;
}

/** Push one or more filter values, always returning to page 1. */
function apply(ctx: Ctx, changes: Filters) {
    router.get(
        ctx.url,
        { ...ctx.filters, ...changes, page: undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const control =
    'h-9 rounded-lg border border-input bg-card px-2.5 text-sm outline-none transition ' +
    'focus:border-primary focus:ring-2 focus:ring-primary/15';

export function FilterSelect({
    ctx,
    name,
    label,
    options,
    className = '',
}: {
    ctx: Ctx;
    name: string;
    /** Shown as the "no choice made" option, e.g. "All members". */
    label: string;
    options: { value: string; label: string }[];
    className?: string;
}) {
    return (
        <select
            aria-label={label}
            value={ctx.filters[name] ?? ''}
            onChange={(e) => apply(ctx, { [name]: e.target.value || undefined })}
            className={`${control} ${ctx.filters[name] ? 'border-primary/60 font-medium text-primary' : ''} ${className}`}
        >
            <option value="">{label}</option>
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}

/**
 * Two native date inputs. Native rather than a custom picker because they are
 * keyboard accessible, localised by the browser, and need no library.
 */
export function DateRangeFilter({
    ctx,
    fromName = 'date_from',
    toName = 'date_to',
}: {
    ctx: Ctx;
    fromName?: string;
    toName?: string;
}) {
    const from = ctx.filters[fromName] ?? '';
    const to = ctx.filters[toName] ?? '';

    return (
        <div className="flex items-center gap-1.5">
            <input
                type="date"
                aria-label="From date"
                value={from}
                // A range that ends before it starts returns nothing and looks
                // like a bug, so each input bounds the other.
                max={to || undefined}
                onChange={(e) => apply(ctx, { [fromName]: e.target.value || undefined })}
                className={`${control} ${from ? 'border-primary/60 text-primary' : ''}`}
            />
            <span className="text-xs text-muted-foreground">to</span>
            <input
                type="date"
                aria-label="To date"
                value={to}
                min={from || undefined}
                onChange={(e) => apply(ctx, { [toName]: e.target.value || undefined })}
                className={`${control} ${to ? 'border-primary/60 text-primary' : ''}`}
            />
        </div>
    );
}

/** Only rendered when something is actually filtered, so it is never dead furniture. */
export function ResetFilters({ ctx, keys }: { ctx: Ctx; keys: string[] }) {
    const active = keys.filter((k) => ctx.filters[k]);

    if (active.length === 0) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() =>
                apply(ctx, Object.fromEntries(keys.map((k) => [k, undefined])) as Filters)
            }
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
        >
            <svg viewBox="0 0 24 24" className="size-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
                <path d="M18 6 6 18M6 6l12 12" strokeLinecap="round" />
            </svg>
            Reset
            <span className="tabular rounded-full bg-muted px-1.5 text-xs">{active.length}</span>
        </button>
    );
}

/**
 * Export carries the current filters, so the file matches the screen.
 * A plain anchor, not a router visit — this is a file download.
 */
export function ExportLink({ ctx, path, children = 'Export CSV' }: { ctx: Ctx; path: string; children?: ReactNode }) {
    const params = new URLSearchParams(
        Object.entries(ctx.filters).filter(([, v]) => v) as [string, string][],
    );
    params.delete('page');

    return (
        <a
            href={`${path}${params.toString() ? `?${params}` : ''}`}
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium transition hover:bg-muted"
        >
            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            {children}
        </a>
    );
}

/**
 * Keeps the whole set on one line and lets it scroll sideways on a narrow
 * screen rather than wrapping into a second row.
 */
export function FilterRow({ children }: { children: ReactNode }) {
    return <div className="flex items-center gap-2 overflow-x-auto pb-0.5">{children}</div>;
}
