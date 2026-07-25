import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import { Button, Field, Modal, inputClass } from '@/components/ui-kit';

/**
 * Reconnect Facebook by replacing the stored access token.
 *
 * Not an OAuth round-trip: that needs the Meta app secret and a registered
 * HTTPS redirect URI, neither of which this app has yet. Pasting a token from
 * Meta's Graph API Explorer or a Business Manager System User does the same job
 * today, and the server proves the token works against Graph before saving it,
 * so a bad paste fails here rather than silently stopping lead syncing.
 */
export function FacebookReconnect({
    account,
    onClose,
}: {
    /** Whoever the current token belongs to, if it still works. */
    account?: string | null;
    onClose: () => void;
}) {
    const form = useForm({ token: '' });

    function submit(e: FormEvent) {
        e.preventDefault();

        form.put('/settings/facebook-token', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    }

    return (
        <Modal
            open
            onClose={onClose}
            title="Reconnect Facebook"
            description="Replace the access token this workspace uses to read leads."
        >
            <form onSubmit={submit} className="space-y-5">
                <div className="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm">
                    <p className="text-muted-foreground">Currently connected as</p>
                    <p className="mt-0.5 font-semibold">{account || 'Not connected'}</p>
                </div>

                <Field
                    label="New access token"
                    required
                    error={form.errors.token}
                    hint="From Meta → Graph API Explorer, or a Business Manager System User token. It is checked against Facebook before it is saved, then encrypted."
                >
                    <input
                        type="password"
                        autoComplete="off"
                        spellCheck={false}
                        className={inputClass}
                        placeholder="EAA…"
                        value={form.data.token}
                        onChange={(e) => form.setData('token', e.target.value)}
                    />
                </Field>

                <p className="rounded-lg border-l-2 border-amber-500 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    A personal token expires in about 60 days and lead syncing stops
                    when it does. Use a System User token for anything long-lived.
                </p>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing || form.data.token.length < 50}>
                        {form.processing ? 'Checking with Facebook…' : 'Reconnect'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
