import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, Panel, inputClass } from '../components/ui'

const ROLE_BADGE = {
  owner: 'bg-purple-100 text-purple-700',
  manager: 'bg-amber-100 text-amber-700',
  agent: 'bg-blue-100 text-blue-700',
}

const EMPTY = {
  customer_id: '',
  name: '',
  email: '',
  phone: '',
  role: 'agent',
  status: 'invited',
}

export default function Team() {
  const [page, setPage] = useState(null)
  const [leaders, setLeaders] = useState([])
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/team-members', { params: { search, role, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, role, status, pageNum])

  const loadLeaders = useCallback(() => {
    client.get('/team-members/leaderboard').then((res) => setLeaders(res.data))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    loadLeaders()
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [loadLeaders])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '' })
    setErrors({})
  }

  const openEdit = (m) => {
    setEditing(m.id)
    setForm({
      customer_id: m.customer_id,
      name: m.name,
      email: m.email,
      phone: m.phone || '',
      role: m.role,
      status: m.status,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/team-members', form)
      } else {
        await client.put(`/team-members/${editing}`, form)
      }
      setEditing(null)
      load()
      loadLeaders()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const remove = async (m) => {
    if (!confirm(`Remove "${m.name}" from the team?`)) return
    await client.delete(`/team-members/${m.id}`)
    load()
    loadLeaders()
  }

  const maxCalls = Math.max(1, ...leaders.map((l) => l.handled_calls_count))

  return (
    <div>
      {leaders.length > 0 && (
        <div className="mb-5">
          <Panel title="Top Agents — calls handled" padded>
            <div className="space-y-3">
              {leaders.map((l, i) => (
                <div key={l.id} className="flex items-center gap-3">
                  <span className="w-5 text-sm font-bold text-gray-400">{i + 1}</span>
                  <span className="grid h-8 w-8 flex-shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand to-indigo-400 text-xs font-bold text-white">
                    {l.name?.[0]?.toUpperCase()}
                  </span>
                  <div className="w-40 flex-shrink-0">
                    <div className="truncate text-sm font-semibold">{l.name}</div>
                    <div className="truncate text-xs text-gray-400">{l.customer?.business_name}</div>
                  </div>
                  <div className="flex-1">
                    <div className="h-2 rounded-full bg-gray-100">
                      <div
                        className="h-2 rounded-full bg-brand"
                        style={{ width: `${(l.handled_calls_count / maxCalls) * 100}%` }}
                      />
                    </div>
                  </div>
                  <span className="w-12 text-right text-sm font-bold">{l.handled_calls_count}</span>
                </div>
              ))}
            </div>
          </Panel>
        </div>
      )}

      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search team…"
            className={`${inputClass} w-56`}
          />
          <select value={role} onChange={(e) => { setPageNum(1); setRole(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All roles</option>
            <option value="owner">Owner</option>
            <option value="manager">Manager</option>
            <option value="agent">Agent</option>
          </select>
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="invited">Invited</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ Add Member</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Member</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Role</th>
                <th className="px-5 py-3">Calls</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((m) => (
                <tr key={m.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3">
                    <div className="font-semibold">{m.name}</div>
                    <div className="text-xs text-gray-400">{m.email}{m.phone ? ` · ${m.phone}` : ''}</div>
                  </td>
                  <td className="px-5 py-3 text-gray-600">{m.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3">
                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${ROLE_BADGE[m.role]}`}>
                      {m.role}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-gray-600">{m.handled_calls_count}</td>
                  <td className="px-5 py-3"><Badge value={m.status} /></td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(m)}>Edit</Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(m)}>Remove</Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-12 text-center text-gray-400">No team members found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Team Member' : 'Edit Team Member'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Name">
                <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
              </Field>
              <Field label="Phone">
                <input className={inputClass} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
              </Field>
              <Field label="Email">
                <input className={inputClass} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
                {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
              </Field>
              <Field label="Business">
                <select className={inputClass} value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })}>
                  <option value="">Select business…</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>{c.business_name}</option>
                  ))}
                </select>
                {errors.customer_id && <p className="mt-1 text-xs text-red-600">{errors.customer_id[0]}</p>}
              </Field>
              <Field label="Role">
                <select className={inputClass} value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                  <option value="owner">Owner</option>
                  <option value="manager">Manager</option>
                  <option value="agent">Agent</option>
                </select>
              </Field>
              <Field label="Status">
                <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="active">Active</option>
                  <option value="invited">Invited</option>
                  <option value="inactive">Inactive</option>
                </select>
              </Field>
            </div>
            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="light" onClick={() => setEditing(null)}>Cancel</Button>
              <Button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
