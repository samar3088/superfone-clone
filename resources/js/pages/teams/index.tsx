import { Head } from '@inertiajs/react';

import { Button, Pill } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Team {
    id: number;
    name: string;
    status: string;
    virtual_number: string | null;
    number_provider: string | null;
    has_number: boolean;
    staff_limit: number | null;
    lead_limit: number | null;
    plan_renews_at: string | null;
    staff_count: number;
    leads_count: number;
}

/** Nothing here is wired to a payment gateway yet, so nothing pretends to be. */
const PENDING = 'Available once a billing provider is connected';

export default function TeamsIndex({ teams }: { teams: Team[] }) {
    return (
        <ConsoleLayout
            title="Teams"
            description="Your organisation, its number and its plan."
            actions={
                <Button disabled title="Available once a telephony provider is connected">
                    Buy a new number
                </Button>
            }
        >
            <Head title="Teams" />

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="table-head">
                            <tr className="border-b border-border">
                                {['#', 'Team', 'Status', 'Number', 'Staff', 'Leads', ''].map((h, i) => (
                                    <th
                                        key={h || i}
                                        scope="col"
                                        className={`px-4 py-3 ${i >= 4 ? 'text-right' : 'text-left'}`}
                                    >
                                        <span className="eyebrow">{h}</span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {teams.map((t, i) => (
                                <tr key={t.id} className="border-b border-border/60 last:border-0">
                                    <td className="tabular px-4 py-4 text-muted-foreground">{i + 1}</td>

                                    <td className="px-4 py-4">
                                        <p className="font-semibold">{t.name}</p>
                                        {t.plan_renews_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Renews {t.plan_renews_at}
                                            </p>
                                        )}
                                    </td>

                                    <td className="px-4 py-4">
                                        <Pill tone={t.status === 'active' ? 'good' : 'neutral'}>
                                            {t.status === 'active' ? 'Active' : t.status}
                                        </Pill>
                                    </td>

                                    <td className="px-4 py-4">
                                        {t.has_number ? (
                                            <span className="data font-medium">{t.virtual_number}</span>
                                        ) : (
                                            /*
                                                Said plainly rather than shown as a dash: no number is a
                                                state worth explaining, not a missing value.
                                            */
                                            <span className="text-muted-foreground">
                                                Not assigned yet
                                            </span>
                                        )}
                                    </td>

                                    <td className="px-4 py-4 text-right">
                                        <Usage used={t.staff_count} limit={t.staff_limit} />
                                    </td>

                                    <td className="px-4 py-4 text-right">
                                        <Usage used={t.leads_count} limit={t.lead_limit} />
                                    </td>

                                    <td className="px-4 py-4">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" className="px-3 py-1.5" disabled title={PENDING}>
                                                Renew plan
                                            </Button>
                                            <Button variant="ghost" className="px-3 py-1.5" disabled title={PENDING}>
                                                Buy add-on
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {teams.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-16 text-center">
                                        <p className="font-display text-base font-semibold">No organisation yet</p>
                                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                            Run the seeder to create one.
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-4 max-w-2xl rounded-lg border-l-2 border-amber-500 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                The virtual number fills in automatically once telephony is connected.
                Plans and add-ons need a billing provider before those buttons do anything.
            </p>
        </ConsoleLayout>
    );
}

/** "9 / 10" when a plan caps it, just "9" when nothing does. */
function Usage({ used, limit }: { used: number; limit: number | null }) {
    const nearingLimit = limit !== null && used / limit >= 0.9;

    return (
        <span className="tabular">
            <span className={`font-semibold ${nearingLimit ? 'text-[var(--bad)]' : ''}`}>
                {used.toLocaleString()}
            </span>
            {limit !== null && (
                <span className="text-muted-foreground"> / {limit.toLocaleString()}</span>
            )}
        </span>
    );
}
