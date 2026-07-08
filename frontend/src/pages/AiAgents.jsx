import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, StatCard, inputClass } from '../components/ui'

const TYPES = {
  sales: { label: 'Sales', icon: '💼' },
  receptionist: { label: 'Receptionist', icon: '🛎️' },
  whatsapp: { label: 'WhatsApp', icon: '💬' },
  payment: { label: 'Payment', icon: '💰' },
  manager: { label: 'Manager', icon: '📋' },
}

const CHANNELS = { call: 'Call', whatsapp: 'WhatsApp', both: 'Call + WhatsApp' }

const EMPTY = {
  customer_id: '',
  name: '',
  type: 'receptionist',
  channel: 'call',
  status: 'draft',
  persona: '',
}

export default function AiAgents({ initialType = '', title }) {
  const [agents, setAgents] = useState([])
  const [summary, setSummary] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [type, setType] = useState(initialType)
  const [status, setStatus] = useState('')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client.get('/ai-agents', { params: { search, type, status } }).then((res) => {
      setAgents(res.data.agents)
      setSummary(res.data.summary)
    })
  }, [search, type, status])

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

  const openEdit = (a) => {
    setEditing(a.id)
    setForm({
      customer_id: a.customer_id,
      name: a.name,
      type: a.type,
      channel: a.channel,
      status: a.status,
      persona: a.persona || '',
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/ai-agents', form)
      } else {
        await client.put(`/ai-agents/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const toggle = async (a) => {
    await client.patch(`/ai-agents/${a.id}/toggle`)
    load()
  }

  const remove = async (a) => {
    if (!confirm(`Delete "${a.name}"?`)) return
    await client.delete(`/ai-agents/${a.id}`)
    load()
  }

  return (
    <div>
      {title && <h1 className="mb-6 text-3xl font-bold">{title}</h1>}
      {summary && (
        <div className="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
          <StatCard label="Total Agents" value={summary.total} sub="configured" icon="🤖" />
          <StatCard label="Active" value={summary.active} sub="running now" icon="⚡" />
          <StatCard label="Interactions" value={summary.handled} sub="handled" icon="💬" />
          <StatCard label="Avg Resolution" value={`${summary.avg_resolution}%`} sub="across agents" icon="🎯" />
        </div>
      )}

      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search agents…"
            className={`${inputClass} w-52`}
          />
          <select value={type} onChange={(e) => setType(e.target.value)} className={`${inputClass} w-40`}>
            <option value="">All types</option>
            {Object.entries(TYPES).map(([k, v]) => (
              <option key={k} value={k}>{v.label}</option>
            ))}
          </select>
          <select value={status} onChange={(e) => setStatus(e.target.value)} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New Agent</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {agents.map((a) => (
          <div key={a.id} className="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-indigo-50 text-xl">
                  {TYPES[a.type]?.icon}
                </span>
                <div>
                  <div className="font-bold">{a.name}</div>
                  <div className="text-xs text-gray-400">{TYPES[a.type]?.label} · {CHANNELS[a.channel]}</div>
                </div>
              </div>
              <Badge value={a.status} />
            </div>

            <p className="mt-3 flex-1 text-sm text-gray-500">{a.persona}</p>

            <div className="mt-3 text-xs text-gray-400">{a.customer?.business_name}</div>

            {a.status === 'active' && (
              <div className="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-3 text-center">
                <div>
                  <div className="text-lg font-bold">{a.handled}</div>
                  <div className="text-[11px] text-gray-400">handled</div>
                </div>
                <div>
                  <div className="text-lg font-bold text-emerald-600">{a.resolution_rate}%</div>
                  <div className="text-[11px] text-gray-400">resolved</div>
                </div>
              </div>
            )}

            <div className="mt-4 flex gap-2">
              {a.status !== 'draft' && (
                <Button
                  variant={a.status === 'active' ? 'light' : 'primary'}
                  className="flex-1 justify-center"
                  onClick={() => toggle(a)}
                >
                  {a.status === 'active' ? '⏸ Pause' : '▶ Activate'}
                </Button>
              )}
              <Button variant="light" className="px-3" onClick={() => openEdit(a)}>Edit</Button>
              <Button variant="danger" className="px-3" onClick={() => remove(a)}>✕</Button>
            </div>
          </div>
        ))}
        {agents.length === 0 && (
          <div className="col-span-full rounded-2xl border border-gray-100 bg-white py-12 text-center text-gray-400">
            No AI agents found.
          </div>
        )}
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'New AI Agent' : 'Edit AI Agent'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Name">
                <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
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
              <Field label="Type">
                <select className={inputClass} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                  {Object.entries(TYPES).map(([k, v]) => (
                    <option key={k} value={k}>{v.label}</option>
                  ))}
                </select>
              </Field>
              <Field label="Channel">
                <select className={inputClass} value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
                  <option value="call">Call</option>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="both">Call + WhatsApp</option>
                </select>
              </Field>
            </div>
            <Field label="Persona / instructions">
              <textarea
                className={`${inputClass} h-24 resize-none`}
                value={form.persona}
                onChange={(e) => setForm({ ...form, persona: e.target.value })}
                placeholder="Describe how this agent should behave…"
              />
            </Field>
            <Field label="Status">
              <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
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
