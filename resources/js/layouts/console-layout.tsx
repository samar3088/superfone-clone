import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

import { NotificationsBell } from '@/components/notifications-bell';

interface AuthUser {
    id: number;
    name: string;
    email: string;
    mobile: string;
    initials: string;
    is_owner: boolean;
}

interface PageProps {
    auth: { user: AuthUser | null; permissions: string[] };
    notifications?: { unread_leads: number };
    flash?: { success?: string; error?: string };
    [key: string]: unknown;
}

const COLLAPSE_KEY = 'vvt.sidebar.collapsed';

/**
 * Application shell: a collapsible sidebar of admin menus beside a warm
 * paper canvas. The collapsed state persists so it survives navigation.
 */
export default function ConsoleLayout({
    children,
    title,
    description,
    actions,
}: PropsWithChildren<{ title?: string; description?: string; actions?: ReactNode }>) {
    const { auth, notifications, flash } = usePage<PageProps>().props;
    const [collapsed, setCollapsed] = useState(false);
    const [hydrated, setHydrated] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    // Restore the saved preference once, on mount.
    useEffect(() => {
        setCollapsed(localStorage.getItem(COLLAPSE_KEY) === '1');
        setHydrated(true);
    }, []);

    // Persist as a side effect, never inside the state updater — React may
    // invoke an updater more than once, which would desync the stored value.
    useEffect(() => {
        if (!hydrated) return;
        localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
    }, [collapsed, hydrated]);

    const toggleCollapse = () => setCollapsed((prev) => !prev);

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
    const isActive = (href: string) => currentPath === href || currentPath.startsWith(href + '/');

    const nav = [
        { label: 'Dashboard', href: '/dashboard', icon: IconGrid },
        { label: 'Leads', href: '/leads', icon: IconSpark },
        { label: 'Team Members', href: '/team', icon: IconUsers },
        { label: 'Activity Log', href: '/activity', icon: IconPulse },
        ...(auth.user?.is_owner ? [{ label: 'Settings', href: '/settings', icon: IconCog }] : []),
    ];

    return (
        <div className="min-h-svh bg-background">
            {/* ── Sidebar ── */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex flex-col bg-rail text-rail-foreground transition-[width,transform] duration-200 ${
                    collapsed ? 'w-[72px]' : 'w-[248px]'
                } ${mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}
            >
                <div className={`flex h-16 items-center gap-2.5 ${collapsed ? 'justify-center px-3' : 'px-5'}`}>
                    <Link href="/dashboard" className="flex items-center gap-2.5" title="VVT">
                        <span className="grid size-9 flex-shrink-0 place-items-center rounded-lg bg-primary font-display text-base font-bold text-primary-foreground">
                            V
                        </span>
                        {!collapsed && (
                            <span className="font-display text-lg font-bold tracking-tight">VVT</span>
                        )}
                    </Link>
                </div>

                <nav className="flex-1 space-y-1 px-3 py-3">
                    {nav.map((item) => {
                        const Icon = item.icon;
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                title={collapsed ? item.label : undefined}
                                onClick={() => setMobileOpen(false)}
                                className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                                    active ? 'bg-primary/15 text-primary' : 'opacity-70 hover:bg-white/5 hover:opacity-100'
                                } ${collapsed ? 'justify-center' : ''}`}
                            >
                                <Icon className="size-[18px] flex-shrink-0" />
                                {!collapsed && <span className="truncate">{item.label}</span>}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-white/10 p-3">
                    <Link
                        href="/profile"
                        title={collapsed ? auth.user?.name : undefined}
                        className={`flex items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-white/5 ${
                            collapsed ? 'justify-center' : ''
                        }`}
                    >
                        <span className="grid size-8 flex-shrink-0 place-items-center rounded-full bg-primary/20 text-xs font-bold text-primary">
                            {auth.user?.initials}
                        </span>
                        {!collapsed && (
                            <span className="min-w-0 flex-1 truncate text-[13px] font-semibold">{auth.user?.name}</span>
                        )}
                    </Link>

                    <button
                        onClick={toggleCollapse}
                        className={`mt-1 hidden w-full items-center gap-3 rounded-lg px-3 py-2 text-sm opacity-60 transition hover:bg-white/5 hover:opacity-100 lg:flex ${
                            collapsed ? 'justify-center' : ''
                        }`}
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        <IconChevron className={`size-[18px] transition-transform ${collapsed ? 'rotate-180' : ''}`} />
                        {!collapsed && <span>Collapse</span>}
                    </button>
                </div>
            </aside>

            {mobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-hidden
                />
            )}

            {/* ── Content column ── */}
            <div className={`transition-[padding] duration-200 ${collapsed ? 'lg:pl-[72px]' : 'lg:pl-[248px]'}`}>
                <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/85 px-4 backdrop-blur sm:px-6">
                    <button
                        onClick={() => setMobileOpen(true)}
                        className="rounded-lg p-2 hover:bg-muted lg:hidden"
                        aria-label="Open menu"
                    >
                        <IconMenu className="size-5" />
                    </button>

                    <div className="ml-auto flex items-center gap-2">
                        <NotificationsBell count={notifications?.unread_leads ?? 0} />
                        <button
                            onClick={() => router.post('/logout')}
                            className="rounded-lg border border-border px-3 py-1.5 text-sm font-medium transition hover:bg-muted"
                        >
                            Sign out
                        </button>
                    </div>
                </header>

                <main className="mx-auto max-w-[1320px] px-4 py-8 sm:px-6">
                    {(title || actions) && (
                        <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                {title && <h1 className="font-display text-2xl font-bold sm:text-[28px]">{title}</h1>}
                                {description && (
                                    <p className="mt-1.5 text-[15px] text-muted-foreground">{description}</p>
                                )}
                            </div>
                            {actions && <div className="flex items-center gap-2.5">{actions}</div>}
                        </div>
                    )}

                    {flash?.success && <Banner tone="good">{flash.success}</Banner>}
                    {flash?.error && <Banner tone="bad">{flash.error}</Banner>}

                    {children}
                </main>
            </div>
        </div>
    );
}

function Banner({ tone, children }: { tone: 'good' | 'bad'; children: ReactNode }) {
    return (
        <div
            className="mb-5 rounded-lg px-4 py-3 text-sm font-medium"
            style={{
                background: `var(--${tone}-soft)`,
                color: `var(--${tone})`,
                border: `1px solid color-mix(in srgb, var(--${tone}) 22%, transparent)`,
            }}
        >
            {children}
        </div>
    );
}

/* ── icons (inline so there is no runtime icon dependency) ── */
type IconProps = { className?: string };

const IconGrid = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <rect x="3" y="3" width="7" height="7" rx="1.5" />
        <rect x="14" y="3" width="7" height="7" rx="1.5" />
        <rect x="3" y="14" width="7" height="7" rx="1.5" />
        <rect x="14" y="14" width="7" height="7" rx="1.5" />
    </svg>
);

const IconUsers = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20" />
        <circle cx="9" cy="7" r="3.2" />
        <path d="M22 20v-1.5a4 4 0 0 0-3-3.85M16 3.6a4 4 0 0 1 0 7.75" />
    </svg>
);

const IconPulse = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d="M3 12h4l3-8 4 16 3-8h4" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
);

const IconSpark = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8" strokeLinecap="round" />
        <circle cx="12" cy="12" r="2.6" />
    </svg>
);

const IconCog = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
        <circle cx="12" cy="12" r="3.2" />
        <path d="M19.4 15a1.6 1.6 0 0 0 .32 1.77l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.6 1.6 0 0 0 15 19.4a1.6 1.6 0 0 0-1 1.47V21a2 2 0 1 1-4 0v-.09A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.77.32l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.6 1.6 0 0 0 4.6 15a1.6 1.6 0 0 0-1.47-1H3a2 2 0 1 1 0-4h.09A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.32-1.77l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.6 1.6 0 0 0 9 4.6h.09A1.6 1.6 0 0 0 10 3.13V3a2 2 0 1 1 4 0v.09A1.6 1.6 0 0 0 15 4.6a1.6 1.6 0 0 0 1.77-.32l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.6 1.6 0 0 0 19.4 9v.09a1.6 1.6 0 0 0 1.47 1H21a2 2 0 1 1 0 4h-.09a1.6 1.6 0 0 0-1.47 1z" />
    </svg>
);

const IconChevron = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M15 18l-6-6 6-6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
);

const IconMenu = ({ className }: IconProps) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M4 6h16M4 12h16M4 18h16" strokeLinecap="round" />
    </svg>
);
