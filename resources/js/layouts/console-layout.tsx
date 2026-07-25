import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';

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
    flash?: { success?: string; error?: string };
    [key: string]: unknown;
}

/**
 * The application shell: a dark command rail across the top, then a warm
 * paper canvas beneath. Navigation lives horizontally because this console
 * has few sections — a vertical sidebar would be mostly empty space.
 */
export default function ConsoleLayout({
    children,
    title,
    description,
    actions,
}: PropsWithChildren<{ title?: string; description?: string; actions?: React.ReactNode }>) {
    const { auth, flash, url } = usePage<PageProps>().props as PageProps & { url?: string };
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : (url ?? '');
    const [menuOpen, setMenuOpen] = useState(false);

    const nav = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Team', href: '/team' },
        { label: 'Activity', href: '/activity' },
        ...(auth.user?.is_owner ? [{ label: 'Settings', href: '/settings' }] : []),
    ];

    const isActive = (href: string) => currentPath === href || currentPath.startsWith(href + '/');

    return (
        <div className="min-h-svh bg-background">
            {/* ── Command rail ── */}
            <header className="sticky top-0 z-40 bg-rail text-rail-foreground">
                <div className="mx-auto flex h-16 max-w-[1400px] items-center gap-6 px-4 sm:px-6">
                    <Link href="/dashboard" className="flex flex-shrink-0 items-center gap-2.5">
                        <span className="grid size-8 place-items-center rounded-lg bg-primary font-display text-base font-bold text-primary-foreground">
                            S
                        </span>
                        <span className="hidden font-display text-lg font-bold tracking-tight sm:block">Superfone</span>
                    </Link>

                    {/* Desktop nav */}
                    <nav className="hidden flex-1 items-center gap-1 md:flex">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`relative rounded-lg px-3.5 py-2 text-sm font-medium transition ${
                                    isActive(item.href)
                                        ? 'bg-white/10 text-white'
                                        : 'opacity-70 hover:bg-white/5 hover:opacity-100'
                                }`}
                            >
                                {item.label}
                                {isActive(item.href) && (
                                    <span className="absolute inset-x-3.5 -bottom-[9px] h-0.5 rounded-full bg-primary" />
                                )}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <Link
                            href="/profile"
                            className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition hover:bg-white/5"
                        >
                            <span className="grid size-8 place-items-center rounded-full bg-primary/20 text-xs font-bold text-primary">
                                {auth.user?.initials}
                            </span>
                            <span className="hidden text-left leading-tight sm:block">
                                <span className="block text-[13px] font-semibold">{auth.user?.name}</span>
                                <span className="block text-[11px] opacity-55">
                                    {auth.user?.is_owner ? 'Owner' : 'Member'}
                                </span>
                            </span>
                        </Link>

                        <button
                            onClick={() => router.post('/logout')}
                            className="hidden rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium transition hover:bg-white/5 sm:block"
                        >
                            Sign out
                        </button>

                        <button
                            onClick={() => setMenuOpen((o) => !o)}
                            className="rounded-lg p-2 hover:bg-white/5 md:hidden"
                            aria-label="Menu"
                            aria-expanded={menuOpen}
                        >
                            <span className="block h-0.5 w-5 bg-current" />
                            <span className="mt-1 block h-0.5 w-5 bg-current" />
                            <span className="mt-1 block h-0.5 w-5 bg-current" />
                        </button>
                    </div>
                </div>

                {menuOpen && (
                    <nav className="border-t border-white/10 px-4 pb-3 md:hidden">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`block rounded-lg px-3 py-2.5 text-sm font-medium ${
                                    isActive(item.href) ? 'bg-white/10' : 'opacity-70'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                        <button
                            onClick={() => router.post('/logout')}
                            className="mt-1 block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium opacity-70"
                        >
                            Sign out
                        </button>
                    </nav>
                )}
            </header>

            {/* ── Page ── */}
            <main className="mx-auto max-w-[1400px] px-4 py-8 sm:px-6">
                {(title || actions) && (
                    <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            {title && <h1 className="font-display text-2xl font-bold sm:text-[28px]">{title}</h1>}
                            {description && <p className="mt-1.5 text-[15px] text-muted-foreground">{description}</p>}
                        </div>
                        {actions && <div className="flex items-center gap-2.5">{actions}</div>}
                    </div>
                )}

                {flash?.success && (
                    <div
                        className="mb-5 rounded-lg px-4 py-3 text-sm font-medium"
                        style={{
                            background: 'var(--good-soft)',
                            color: 'var(--good)',
                            border: '1px solid color-mix(in srgb, var(--good) 22%, transparent)',
                        }}
                    >
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div
                        className="mb-5 rounded-lg px-4 py-3 text-sm font-medium"
                        style={{
                            background: 'var(--bad-soft)',
                            color: 'var(--bad)',
                            border: '1px solid color-mix(in srgb, var(--bad) 22%, transparent)',
                        }}
                    >
                        {flash.error}
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}
