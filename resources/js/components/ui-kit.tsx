import { ReactNode, useEffect } from 'react';

/** Status pill — colour is never the only signal; the label always shows. */
export function Pill({ tone, children }: { tone: 'good' | 'warn' | 'bad' | 'neutral'; children: ReactNode }) {
    const style =
        tone === 'neutral'
            ? { background: 'var(--muted)', color: 'var(--muted-foreground)' }
            : { background: `var(--${tone}-soft)`, color: `var(--${tone})` };

    return (
        <span
            className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
            style={style}
        >
            <span className="size-1.5 rounded-full bg-current" aria-hidden />
            {children}
        </span>
    );
}

export function Button({
    children,
    variant = 'primary',
    className = '',
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' | 'danger' }) {
    const styles = {
        primary: 'bg-primary text-primary-foreground hover:opacity-90',
        ghost: 'border border-border hover:bg-muted',
        danger: 'border border-transparent hover:opacity-90',
    }[variant];

    return (
        <button
            className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-40 ${styles} ${className}`}
            style={variant === 'danger' ? { background: 'var(--bad-soft)', color: 'var(--bad)' } : undefined}
            {...props}
        >
            {children}
        </button>
    );
}

export function Field({
    label,
    error,
    hint,
    required,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <label className="block space-y-1.5">
            <span className="text-sm font-semibold">
                {label}
                {required && <span style={{ color: 'var(--bad)' }}> *</span>}
            </span>
            {children}
            {hint && !error && <span className="block text-xs text-muted-foreground">{hint}</span>}
            {error && (
                <span className="block text-xs font-medium" style={{ color: 'var(--bad)' }}>
                    {error}
                </span>
            )}
        </label>
    );
}

export const inputClass =
    'w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';

/** Accessible modal: Escape closes, backdrop click closes, body scroll locks. */
export function Modal({
    open,
    onClose,
    title,
    description,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children: ReactNode;
}) {
    useEffect(() => {
        if (!open) return;

        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-[60] grid place-items-center bg-black/45 p-4 backdrop-blur-[2px]"
            onClick={onClose}
            role="presentation"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className="max-h-[88vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-border bg-card p-6 shadow-2xl"
            >
                <div className="mb-5">
                    <h2 className="font-display text-xl font-bold">{title}</h2>
                    {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
                </div>
                {children}
            </div>
        </div>
    );
}
