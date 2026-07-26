import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

import { Button, Modal } from '@/components/ui-kit';

/**
 * Bulk contact import.
 *
 * The sample is generated from the same column map the parser reads, so it can
 * never describe a format the importer no longer accepts — and it is offered
 * before the file picker, because the format is the thing people get wrong.
 */
export default function ImportContacts({ onClose }: { onClose: () => void }) {
    const { props } = usePage<{ importErrors?: string[] }>();
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/customers/import', {
            preserveScroll: true,
            forceFormData: true,
            // Inertia hands back the fresh page, so the rejected rows can be
            // read off it — the modal stays open when there are any, and
            // closes when the file went in cleanly.
            onSuccess: (page) => {
                const rejected = (page.props as { importErrors?: string[] }).importErrors ?? [];

                if (rejected.length === 0) {
                    onClose();
                }
            },
        });
    };

    const errors = props.importErrors ?? [];

    return (
        <Modal
            open
            onClose={onClose}
            title="Import contacts"
            description="Bring in a list from a spreadsheet."
        >
            <form onSubmit={submit} className="space-y-5">
                <ol className="space-y-4 text-sm">
                    <li className="flex gap-3">
                        <Step n={1} />
                        <div className="min-w-0">
                            <p className="font-medium">Start from the sample</p>
                            <p className="mt-0.5 text-muted-foreground">
                                It has the right headings and two filled-in rows to copy.
                            </p>
                            <a
                                href="/customers/import/sample"
                                className="mt-2 inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition hover:opacity-85"
                                style={{ background: 'var(--info-soft)', color: 'var(--info)' }}
                            >
                                <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Download sample
                            </a>
                        </div>
                    </li>

                    <li className="flex gap-3">
                        <Step n={2} />
                        <div className="min-w-0">
                            <p className="font-medium">Fill it in, then save as CSV</p>
                            <p className="mt-0.5 text-muted-foreground">
                                In Excel: File → Save As → CSV UTF-8. Only <b>Name</b> and{' '}
                                <b>Phone</b> are required; leave anything else blank.
                            </p>
                        </div>
                    </li>

                    <li className="flex gap-3">
                        <Step n={3} />
                        <div className="w-full min-w-0">
                            <p className="font-medium">Upload it</p>
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                className="mt-2 w-full rounded-lg border border-input bg-card p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            />
                            {form.errors.file && (
                                <p className="mt-1.5 text-sm" style={{ color: 'var(--bad)' }}>{form.errors.file}</p>
                            )}
                            {form.progress && (
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    Uploading… {form.progress.percentage}%
                                </p>
                            )}
                        </div>
                    </li>
                </ol>

                <p className="rounded-lg border-l-2 border-amber-500 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    A number already on file is matched to that contact rather than
                    added twice — the import uses the same duplicate check as the
                    Create Contact form. Up to 2,000 rows at a time.
                </p>

                {errors.length > 0 && (
                    <div className="max-h-40 overflow-y-auto rounded-lg border border-border p-3 text-sm">
                        <p className="mb-1.5 font-semibold" style={{ color: 'var(--bad)' }}>
                            Rows that could not be imported
                        </p>
                        <ul className="space-y-1 text-muted-foreground">
                            {errors.map((e, i) => <li key={i}>{e}</li>)}
                        </ul>
                    </div>
                )}

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <Button type="button" variant="ghost" onClick={onClose}>Close</Button>
                    <Button type="submit" disabled={!form.data.file || form.processing}>
                        {form.processing ? 'Importing…' : 'Import'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function Step({ n }: { n: number }) {
    return (
        <span className="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-bold text-accent-foreground">
            {n}
        </span>
    );
}
