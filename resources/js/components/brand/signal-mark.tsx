/**
 * Ambient brand graphic for the login rail: concentric signal arcs radiating
 * from a handset point — the product's own vocabulary rather than a generic
 * gradient blob. Purely decorative, so it is hidden from assistive tech and
 * holds still when the visitor prefers reduced motion.
 */
export function SignalMark() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden">
            {/* soft teal wash anchoring the arcs */}
            <div
                className="absolute -right-24 top-1/4 size-[36rem] rounded-full opacity-25 blur-3xl"
                style={{ background: 'radial-gradient(circle, var(--primary), transparent 65%)' }}
            />

            <svg
                className="absolute -right-32 top-1/2 size-[42rem] -translate-y-1/2 opacity-[0.18]"
                viewBox="0 0 400 400"
                fill="none"
            >
                {[60, 110, 160, 210, 260].map((r, i) => (
                    <circle
                        key={r}
                        cx="200"
                        cy="200"
                        r={r}
                        stroke="var(--primary)"
                        strokeWidth="1.25"
                        strokeDasharray="4 10"
                        className="origin-center motion-safe:animate-[spin_var(--dur)_linear_infinite]"
                        style={{ ['--dur' as string]: `${70 + i * 26}s` }}
                    />
                ))}
                <circle cx="200" cy="200" r="7" fill="var(--primary)" />
            </svg>

            {/* fine grid, kept at a whisper so type stays dominant */}
            <div
                className="absolute inset-0 opacity-[0.05]"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px)',
                    backgroundSize: '56px 56px',
                }}
            />
        </div>
    );
}
