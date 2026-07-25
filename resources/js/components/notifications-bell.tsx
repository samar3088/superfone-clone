import { Link } from '@inertiajs/react';

/**
 * Unread-lead indicator. The count is shared from the server on every page
 * load, so it stays accurate without polling.
 */
export function NotificationsBell({ count }: { count: number }) {
    const label =
        count === 0
            ? 'No new leads'
            : `${count} new ${count === 1 ? 'lead' : 'leads'}`;

    return (
        <Link
            href="/leads"
            title={label}
            aria-label={label}
            className="relative rounded-lg p-2 transition hover:bg-muted"
        >
            <svg
                className="size-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                aria-hidden
            >
                <path
                    d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <path d="M10.3 19.5a2 2 0 0 0 3.4 0" strokeLinecap="round" />
            </svg>

            {count > 0 && (
                <span
                    className="absolute -right-0.5 -top-0.5 grid min-w-[18px] place-items-center rounded-full px-1 text-[10px] font-bold leading-[18px] text-white"
                    style={{ background: 'var(--bad)' }}
                >
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}
