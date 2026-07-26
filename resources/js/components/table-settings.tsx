import { Button, Modal } from '@/components/ui-kit';
import { useState } from 'react';

export interface ColumnChoice {
    key: string;
    label: string;
    /** Always shown and not offered — without it a row has nothing to identify it. */
    locked?: boolean;
}

/**
 * Column chooser.
 *
 * Nothing is applied until Save, so unticking six columns is one change of
 * mind rather than six redraws of the table underneath the dialog.
 */
export function TableSettings({
    columns,
    visible,
    onSave,
    onClose,
}: {
    columns: ColumnChoice[];
    visible: string[];
    onSave: (next: string[]) => void;
    onClose: () => void;
}) {
    const [draft, setDraft] = useState<string[]>(visible);

    const toggle = (key: string) =>
        setDraft((d) => (d.includes(key) ? d.filter((k) => k !== key) : [...d, key]));

    const optional = columns.filter((c) => !c.locked);
    const locked = columns.filter((c) => c.locked).map((c) => c.key);

    return (
        <Modal
            open
            onClose={onClose}
            title="Table settings"
            description="Choose which columns to display."
        >
            <div className="mb-3 flex gap-4 border-b border-border pb-3 text-sm font-semibold">
                <button
                    type="button"
                    onClick={() => setDraft([...locked, ...optional.map((c) => c.key)])}
                    className="text-primary underline-offset-4 hover:underline"
                >
                    Select all
                </button>
                <button
                    type="button"
                    onClick={() => setDraft(locked)}
                    className="text-muted-foreground underline-offset-4 hover:underline"
                >
                    Uncheck all
                </button>
            </div>

            <div className="max-h-80 space-y-0.5 overflow-y-auto">
                {columns.map((c) => (
                    <label
                        key={c.key}
                        className={`flex items-center gap-3 rounded-lg px-2 py-2 text-sm ${
                            c.locked ? 'opacity-60' : 'cursor-pointer hover:bg-muted'
                        }`}
                        title={c.locked ? 'Always shown' : undefined}
                    >
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={c.locked || draft.includes(c.key)}
                            disabled={c.locked}
                            onChange={() => toggle(c.key)}
                        />
                        <span className="font-medium">{c.label}</span>
                    </label>
                ))}
            </div>

            <div className="mt-5 flex justify-end gap-3 border-t border-border pt-4">
                <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                <Button
                    type="button"
                    onClick={() => {
                        onSave([...new Set([...locked, ...draft])]);
                        onClose();
                    }}
                >
                    Save
                </Button>
            </div>
        </Modal>
    );
}
