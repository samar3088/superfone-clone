import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const FILTERS = [
  { key: '', label: 'All' },
  { key: 'pending', label: 'Pending' },
  { key: 'today', label: 'Today' },
  { key: 'overdue', label: 'Overdue' },
  { key: 'done', label: 'Done' },
]

const EMPTY = {
  customer_id: '',
  contact_id: '',
  title: '',
  notes: '',
  due_at: '',
  status: 'pending',
}

// Format an ISO date into the value a datetime-local input expects.
function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export default function Reminders() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [filter, setFilter] = useState('pending')
  const [search, setSearch] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/reminders', { params: { filter, search, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [filter, search, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '', due_at: toLocalInput(new Date().toISOString()) })
    setErrors({})
  }

  const openEdit = (r) => {
    setEditing(r.id)
    setForm({
      customer_id: r.customer_id,
      contact_id: r.contact_id || '',
      title: r.title,
      notes: r.notes || '',
      due_at: toLocalInput(r.due_at),
      status: r.status,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = { ...form, contact_id: form.contact_id || null }
    try {
      if (editing === 'new') {
        await client.post('/reminders', payload)
      } else {
        await client.put(`/reminders/${editing}`, payload)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const toggle = async (r) => {
    await client.patch(`/reminders/${r.id}/toggle`)
    load()
  }

  const remove = async (r) => {
    if (!confirm(`Delete reminder "${r.title}"?`)) return
    await client.delete(`/reminders/${r.id}`)
    load()
  }

  const fmt = (iso) =>
    new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

  const isOverdue = (r) => r.status === 'pending' && new Date(r.due_at) < new Date()

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          {FILTERS.map((f) => (
            <button
              key={f.key}
              onClick={() => { setPageNum(1); setFilter(f.key) }}
              className={`rounded-lg px-3.5 py-1.5 text-sm font-semibold transition ${
                filter === f.key ? 'bg-brand text-white' : 'bg-white text-gray-600 hover:bg-gray-100'
              }`}
            >
              {f.label}
            </button>
          ))}
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search…"
            className={`${inputClass} ml-2 w-48`}
          />
        </div>
        <Button onClick={openCreate}>+ Add Reminder</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="divide-y divide-gray-100">
          {page?.data?.map((r) => (
            <div key={r.id} className="flex items-center gap-4 px-5 py-3.5">
              <input
                type="checkbox"
                checked={r.status === 'done'}
                onChange={() => toggle(r)}
                className="h-4 w-4 accent-brand"
              />
              <div className="min-w-0 flex-1">
                <div className={`font-semibold ${r.status === 'done' ? 'text-gray-400 line-through' : ''}`}>
                  {r.title}
                </div>
                <div className="text-xs text-gray-400">
                  {r.customer?.business_name}
                  {r.contact?.name ? ` · ${r.contact.name}` : ''}
                  {r.notes ? ` · ${r.notes}` : ''}
                </div>
              </div>
              <div className={`whitespace-nowrap text-sm ${isOverdue(r) ? 'font-semibold text-red-600' : 'text-gray-500'}`}>
                {isOverdue(r) ? '⚠ ' : ''}{fmt(r.due_at)}
              </div>
              <div className="flex gap-2">
                <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(r)}>Edit</Button>
                <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(r)}>Delete</Button>
              </div>
            </div>
          ))}
          {page?.data?.length === 0 && (
            <div className="px-5 py-12 text-center text-gray-400">No reminders found.</div>
          )}
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Reminder' : 'Edit Reminder'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <Field label="Title">
              <input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
              {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title[0]}</p>}
            </Field>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Business">
                <select className={inputClass} value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })}>
                  <option value="">Select business…</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>{c.business_name}</option>
                  ))}
                </select>
                {errors.customer_id && <p className="mt-1 text-xs text-red-600">{errors.customer_id[0]}</p>}
              </Field>
              <Field label="Due at">
                <input
                  type="datetime-local"
                  className={inputClass}
                  value={form.due_at}
                  onChange={(e) => setForm({ ...form, due_at: e.target.value })}
                />
                {errors.due_at && <p className="mt-1 text-xs text-red-600">{errors.due_at[0]}</p>}
              </Field>
            </div>
            <Field label="Notes">
              <textarea
                className={`${inputClass} h-20 resize-none`}
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
              />
            </Field>
            <Field label="Status">
              <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="pending">Pending</option>
                <option value="done">Done</option>
              </select>
            </Field>
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
