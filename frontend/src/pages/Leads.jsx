import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Button, Field, Modal, inputClass, inr } from '../components/ui'

const STAGES = [
  { key: 'new', label: 'New', color: 'border-t-gray-400' },
  { key: 'contacted', label: 'Contacted', color: 'border-t-blue-500' },
  { key: 'qualified', label: 'Qualified', color: 'border-t-amber-500' },
  { key: 'won', label: 'Won', color: 'border-t-green-500' },
  { key: 'lost', label: 'Lost', color: 'border-t-red-500' },
]

const EMPTY = {
  customer_id: '',
  name: '',
  phone: '',
  email: '',
  source: 'other',
  stage: 'new',
  value: 0,
  owner: '',
  notes: '',
}

export default function Leads() {
  const [data, setData] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client.get('/leads', { params: { search } }).then((res) => setData(res.data))
  }, [search])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '' })
    setErrors({})
  }

  const openEdit = (l) => {
    setEditing(l.id)
    setForm({
      customer_id: l.customer_id,
      name: l.name,
      phone: l.phone,
      email: l.email || '',
      source: l.source,
      stage: l.stage,
      value: l.value,
      owner: l.owner || '',
      notes: l.notes || '',
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/leads', form)
      } else {
        await client.put(`/leads/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const move = async (lead, stage) => {
    await client.patch(`/leads/${lead.id}/move`, { stage })
    load()
  }

  const remove = async (lead) => {
    if (!confirm(`Delete lead "${lead.name}"?`)) return
    await client.delete(`/leads/${lead.id}`)
    load()
  }

  const nextStage = (key) => {
    const idx = STAGES.findIndex((s) => s.key === key)
    return idx >= 0 && idx < 3 ? STAGES[idx + 1].key : null
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-4">
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search leads…"
            className={`${inputClass} w-64`}
          />
          {data && (
            <span className="text-sm text-gray-500">
              <strong>{data.total}</strong> leads · pipeline{' '}
              <strong className="text-brand">{inr(data.pipeline_value)}</strong>
            </span>
          )}
        </div>
        <Button onClick={openCreate}>+ Add Lead</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
        {STAGES.map((stage) => {
          const col = data?.board?.[stage.key]
          return (
            <div key={stage.key} className={`rounded-2xl border border-gray-100 border-t-4 bg-gray-50/60 ${stage.color}`}>
              <div className="flex items-center justify-between px-4 py-3">
                <span className="text-sm font-bold">{stage.label}</span>
                <span className="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-500">
                  {col?.count || 0}
                </span>
              </div>
              {col?.value > 0 && (
                <div className="px-4 pb-2 text-xs text-gray-400">{inr(col.value)}</div>
              )}
              <div className="flex flex-col gap-2 px-3 pb-3">
                {col?.leads?.map((lead) => (
                  <div key={lead.id} className="rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
                    <div className="flex items-start justify-between gap-2">
                      <button onClick={() => openEdit(lead)} className="text-left text-sm font-semibold hover:text-brand">
                        {lead.name}
                      </button>
                      <span className="whitespace-nowrap text-xs font-bold text-gray-700">{inr(lead.value)}</span>
                    </div>
                    <div className="mt-1 text-xs text-gray-400">{lead.customer?.business_name}</div>
                    <div className="mt-1 text-xs text-gray-400">{lead.phone}</div>
                    <div className="mt-2 flex items-center justify-between">
                      <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] capitalize text-gray-500">
                        {lead.source}
                      </span>
                      <div className="flex gap-1">
                        {nextStage(stage.key) && (
                          <button
                            onClick={() => move(lead, nextStage(stage.key))}
                            title="Advance stage"
                            className="rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-brand hover:bg-indigo-100"
                          >
                            →
                          </button>
                        )}
                        {stage.key !== 'lost' && stage.key !== 'won' && (
                          <button
                            onClick={() => move(lead, 'lost')}
                            title="Mark lost"
                            className="rounded bg-red-50 px-1.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-100"
                          >
                            ✕
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
                {col?.leads?.length === 0 && (
                  <div className="px-1 py-6 text-center text-xs text-gray-300">No leads</div>
                )}
              </div>
            </div>
          )
        })}
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Lead' : 'Edit Lead'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Name">
                <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
              </Field>
              <Field label="Phone">
                <input className={inputClass} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
                {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone[0]}</p>}
              </Field>
              <Field label="Email">
                <input className={inputClass} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
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
              <Field label="Source">
                <select className={inputClass} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>
                  <option value="referral">Referral</option>
                  <option value="website">Website</option>
                  <option value="ads">Ads</option>
                  <option value="walkin">Walk-in</option>
                  <option value="other">Other</option>
                </select>
              </Field>
              <Field label="Stage">
                <select className={inputClass} value={form.stage} onChange={(e) => setForm({ ...form, stage: e.target.value })}>
                  {STAGES.map((s) => (
                    <option key={s.key} value={s.key}>{s.label}</option>
                  ))}
                </select>
              </Field>
              <Field label="Value (₹)">
                <input type="number" className={inputClass} value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} />
              </Field>
              <Field label="Owner">
                <input className={inputClass} value={form.owner} onChange={(e) => setForm({ ...form, owner: e.target.value })} />
              </Field>
            </div>
            <Field label="Notes">
              <textarea
                className={`${inputClass} h-20 resize-none`}
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
              />
            </Field>
            <div className="mt-2 flex justify-between">
              {editing !== 'new' ? (
                <Button type="button" variant="danger" onClick={() => { setEditing(null); remove({ id: editing, name: form.name }) }}>
                  Delete
                </Button>
              ) : (
                <span />
              )}
              <div className="flex gap-2">
                <Button type="button" variant="light" onClick={() => setEditing(null)}>Cancel</Button>
                <Button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save'}</Button>
              </div>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
