import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Column, DataTable, Paginated } from '@/components/data-table';
import { FilterForm, Filters, FilterSearch, FilterSelect } from '@/components/table-filters';
import { Button, Field, Modal, Pill, inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Member {
    id: number;
    name: string;
    email: string;
    mobile: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    roles: { id: number; name: string }[];
}

interface Props {
    members: Paginated<Member>;
    filters: Filters;
    roles: string[];
}

/** Everything the Reset button clears. */
const FILTER_KEYS = ['search', 'status', 'role'];

export default function TeamIndex({ members, filters, roles }: Props) {
    const { auth } = usePage<{ auth: { user: { id: number; is_owner: boolean } } }>().props;
    const [editing, setEditing] = useState<Member | null>(null);
    const [creating, setCreating] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState<Member | null>(null);

    const columns: Column<Member>[] = [
        {
            key: 'name',
            header: 'Member',
            sortable: true,
            cell: (m) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 flex-shrink-0 place-items-center rounded-full bg-accent text-xs font-bold text-accent-foreground">
                        {m.name
                            .split(' ')
                            .slice(0, 2)
                            .map((p) => p[0])
                            .join('')
                            .toUpperCase()}
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-semibold">{m.name}</p>
                        <p className="truncate text-xs text-muted-foreground">{m.email}</p>
                    </div>
                </div>
            ),
        },
        { key: 'mobile', header: 'Mobile', sortable: true, cell: (m) => <span className="data">{m.mobile}</span> },
        {
            key: 'role',
            header: 'Role',
            cell: (m) => <span className="capitalize">{m.roles[0]?.name ?? '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (m) => <Pill tone={m.is_active ? 'good' : 'neutral'}>{m.is_active ? 'Active' : 'Inactive'}</Pill>,
        },
        {
            key: 'last_login_at',
            header: 'Last sign-in',
            sortable: true,
            cell: (m) =>
                m.last_login_at ? (
                    <span className="data text-muted-foreground">{m.last_login_at.slice(0, 16).replace('T', ' ')}</span>
                ) : (
                    <span className="text-muted-foreground">Never</span>
                ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cell: (m) => {
                const isOwnerAccount = m.roles.some((r) => r.name === 'owner');
                return (
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" className="px-2.5 py-1.5" onClick={() => setEditing(m)}>
                            Edit
                        </Button>
                        <Button
                            variant="danger"
                            className="px-2.5 py-1.5"
                            disabled={isOwnerAccount || m.id === auth.user.id}
                            title={isOwnerAccount ? 'Owner accounts cannot be deleted' : undefined}
                            onClick={() => setConfirmDelete(m)}
                        >
                            Delete
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <ConsoleLayout
            title="Team Members"
            description="Add staff, set what they can reach, and keep the roster current."
            actions={
                auth.user.is_owner && <Button onClick={() => setCreating(true)}>＋ Add member</Button>
            }
        >
            <Head title="Team Members" />

            <DataTable
                page={members}
                columns={columns}
                filters={filters}
                url="/team"
                emptyTitle="No team members match"
                emptyHint="Try a different search, or add your first member."
                ownSearch
                toolbar={
                    <FilterForm
                        url="/team"
                        filters={filters}
                        keys={FILTER_KEYS}
                        exportPath={auth.user.is_owner ? '/team/export' : undefined}
                    >
                        <FilterSearch placeholder="Search name, email or mobile…" />

                        <FilterSelect
                            name="status"
                            label="All statuses"
                            options={[
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ]}
                        />

                        <FilterSelect
                            name="role"
                            label="All roles"
                            className="capitalize"
                            options={roles.map((r) => ({ value: r, label: r }))}
                        />
                    </FilterForm>
                }
            />

            {creating && <MemberForm roles={roles} onClose={() => setCreating(false)} />}
            {editing && <MemberForm roles={roles} member={editing} onClose={() => setEditing(null)} />}

            <Modal
                open={!!confirmDelete}
                onClose={() => setConfirmDelete(null)}
                title={`Remove ${confirmDelete?.name}?`}
                description="They will lose access immediately. The record is kept for your activity history."
            >
                <div className="flex justify-end gap-3">
                    <Button variant="ghost" onClick={() => setConfirmDelete(null)}>
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        onClick={() =>
                            router.delete(`/team/${confirmDelete!.id}`, {
                                preserveScroll: true,
                                onFinish: () => setConfirmDelete(null),
                            })
                        }
                    >
                        Remove member
                    </Button>
                </div>
            </Modal>
        </ConsoleLayout>
    );
}

function MemberForm({ member, roles, onClose }: { member?: Member; roles: string[]; onClose: () => void }) {
    const isEdit = !!member;

    const form = useForm({
        name: member?.name ?? '',
        email: member?.email ?? '',
        mobile: member?.mobile ?? '',
        role: member?.roles[0]?.name ?? 'member',
        is_active: member?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (isEdit) {
            form.patch(`/team/${member!.id}`, options);
        } else {
            form.post('/team', options);
        }
    };

    return (
        <Modal
            open
            onClose={onClose}
            title={isEdit ? 'Edit team member' : 'Add team member'}
            description={
                isEdit
                    ? 'Update their details or change what they can reach.'
                    : 'They will sign in with their mobile number and a one-time code.'
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Field label="Full name" required error={form.errors.name}>
                    <input
                        className={inputClass}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        autoFocus
                    />
                </Field>

                <Field label="Email" required error={form.errors.email}>
                    <input
                        type="email"
                        className={inputClass}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>

                <Field
                    label="Mobile number"
                    required
                    error={form.errors.mobile}
                    hint="This is how they sign in."
                >
                    <div className="flex items-stretch overflow-hidden rounded-lg border border-input bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                        <span className="data grid place-items-center border-r border-input bg-muted px-3 text-muted-foreground">
                            +91
                        </span>
                        <input
                            type="tel"
                            inputMode="numeric"
                            maxLength={10}
                            className="data w-full bg-transparent px-3.5 py-2.5 text-sm outline-none"
                            value={form.data.mobile}
                            onChange={(e) => form.setData('mobile', e.target.value.replace(/\D/g, ''))}
                        />
                    </div>
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Role" required error={form.errors.role}>
                        <select
                            className={`${inputClass} capitalize`}
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value)}
                        >
                            {roles.map((r) => (
                                <option key={r} value={r} className="capitalize">
                                    {r}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Status">
                        <select
                            className={inputClass}
                            value={form.data.is_active ? '1' : '0'}
                            onChange={(e) => form.setData('is_active', e.target.value === '1')}
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </Field>
                </div>

                <div className="flex justify-end gap-3 pt-1">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Add member'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
