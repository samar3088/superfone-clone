import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const EMPTY = {
  customer_id: '',
  template_id: '',
  name: '',
  segment: 'all',
  status: 'draft',
  scheduled_at: '',
}

export default function Campaigns() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [templates, setTemplates] = useState([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/campaigns', { params: { search, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
    client.get('/templates', { params: { status: 'approved' } }).then((res) => setTemplates(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '' })
    setErrors({})
  }

  const openEdit = (c) => {
    setEditing(c.id)
    setForm({
      customer_id: c.customer_id,
      template_id: c.template_id || '',
      name: c.name,
      segment: c.segment,
      status: c.status,
      scheduled_at: c.scheduled_at ? c.scheduled_at.slice(0, 16) : '',
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = { ...form, template_id: form.template_id || null, scheduled_at: form.scheduled_at || null }
    try {
      if (editing === 'new') {
        await client.post('/campaigns', payload)
      } else {
        await client.put(`/campaigns/${editing}`, payload)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const send = async (c) => {
    if (!confirm(`Send "${c.name}" to its audience now?`)) return
    await client.post(`/campaigns/${c.id}/send`)
    load()
  }

  const remove = async (c) => {
    if (!confirm(`Delete campaign "${c.name}"?`)) return
    await client.delete(`/campaigns/${c.id}`)
    load()
  }

  const pct = (num, den) => (den > 0 ? Math.round((num / den) * 100) : 0)

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search campaigns…"
            className={`${inputClass} w-56`}
          />
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-40`}>
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="sending">Sending</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New Campaign</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Campaign</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Template</th>
                <th className="px-5 py-3">Audience</th>
                <th className="px-5 py-3">Delivery</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((c) => (
                <tr key={c.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3 font-semibold">{c.name}</td>
                  <td className="px-5 py-3 text-gray-600">{c.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{c.template?.name || '—'}</td>
                  <td className="px-5 py-3">
                    <span className="capitalize text-gray-600">{c.segment}</span>
                    <span className="text-gray-400"> · {c.recipients}</span>
                  </td>
                  <td className="px-5 py-3 text-gray-500">
                    {c.status === 'completed' ? (
                      <span>
                        {c.delivered} del · <span className="text-emerald-600">{pct(c.read, c.delivered)}% read</span>
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="px-5 py-3"><Badge value={c.status} /></td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      {c.status !== 'completed' && (
                        <Button variant="light" className="px-3 py-1.5" onClick={() => send(c)}>Send</Button>
                      )}
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(c)}>Edit</Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(c)}>Delete</Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-gray-400">No campaigns found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'New Campaign' : 'Edit Campaign'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <Field label="Campaign Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
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
              <Field label="Template">
                <select className={inputClass} value={form.template_id} onChange={(e) => setForm({ ...form, template_id: e.target.value })}>
                  <option value="">No template</option>
                  {templates.map((t) => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Audience segment">
                <select className={inputClass} value={form.segment} onChange={(e) => setForm({ ...form, segment: e.target.value })}>
                  <option value="all">All contacts</option>
                  <option value="customer">Customers</option>
                  <option value="prospect">Prospects</option>
                  <option value="vendor">Vendors</option>
                  <option value="other">Other</option>
                </select>
              </Field>
              <Field label="Status">
                <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="draft">Draft</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="sending">Sending</option>
                  <option value="completed">Completed</option>
                </select>
              </Field>
            </div>
            <Field label="Schedule for (optional)">
              <input
                type="datetime-local"
                className={inputClass}
                value={form.scheduled_at}
                onChange={(e) => setForm({ ...form, scheduled_at: e.target.value })}
              />
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
