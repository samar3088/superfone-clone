import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const ACTIONS = ['forward', 'voicemail', 'submenu', 'hangup', 'repeat']
const EMPTY = { customer_id: '', name: 'Main Menu', greeting: '', status: 'inactive', options: [] }
const EMPTY_OPT = { key_press: '', label: '', action: 'forward', destination: '' }

export default function Ivr() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/ivr-flows', { params: { search, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '', options: [{ ...EMPTY_OPT, key_press: '1' }] })
    setErrors({})
  }

  const openEdit = (f) => {
    setEditing(f.id)
    setForm({
      customer_id: f.customer_id,
      name: f.name,
      greeting: f.greeting || '',
      status: f.status,
      options: f.options.map((o) => ({
        key_press: o.key_press,
        label: o.label,
        action: o.action,
        destination: o.destination || '',
      })),
    })
    setErrors({})
  }

  const setOpt = (i, patch) => {
    const options = form.options.map((o, idx) => (idx === i ? { ...o, ...patch } : o))
    setForm({ ...form, options })
  }
  const addOpt = () => setForm({ ...form, options: [...form.options, { ...EMPTY_OPT }] })
  const removeOpt = (i) => setForm({ ...form, options: form.options.filter((_, idx) => idx !== i) })

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = {
      ...form,
      options: form.options.map((o) => ({ ...o, destination: o.destination || null })),
    }
    try {
      if (editing === 'new') {
        await client.post('/ivr-flows', payload)
      } else {
        await client.put(`/ivr-flows/${editing}`, payload)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const toggle = async (f) => {
    await client.patch(`/ivr-flows/${f.id}/toggle`)
    load()
  }

  const remove = async (f) => {
    if (!confirm(`Delete IVR flow "${f.name}"?`)) return
    await client.delete(`/ivr-flows/${f.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search IVR flows…"
            className={`${inputClass} w-56`}
          />
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New IVR Flow</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {page?.data?.map((f) => (
          <div key={f.id} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="font-bold">{f.name}</h3>
                <div className="text-xs text-gray-400">{f.customer?.business_name}</div>
              </div>
              <Badge value={f.status} />
            </div>
            {f.greeting && <p className="mt-2 text-sm italic text-gray-500">“{f.greeting}”</p>}
            <div className="mt-3 space-y-1.5">
              {f.options.map((o) => (
                <div key={o.id} className="flex items-center gap-3 text-sm">
                  <span className="grid h-7 w-7 flex-shrink-0 place-items-center rounded-lg bg-gray-100 font-bold">{o.key_press}</span>
                  <span className="font-medium">{o.label}</span>
                  <span className="text-xs text-gray-400">
                    → {o.action}{o.destination ? ` · ${o.destination}` : ''}
                  </span>
                </div>
              ))}
              {f.options.length === 0 && <div className="text-sm text-gray-400">No options configured.</div>}
            </div>
            <div className="mt-4 flex gap-2">
              <Button
                variant={f.status === 'active' ? 'light' : 'primary'}
                className="flex-1 justify-center"
                onClick={() => toggle(f)}
              >
                {f.status === 'active' ? 'Deactivate' : 'Activate'}
              </Button>
              <Button variant="light" className="px-3" onClick={() => openEdit(f)}>Edit</Button>
              <Button variant="danger" className="px-3" onClick={() => remove(f)}>✕</Button>
            </div>
          </div>
        ))}
        {page?.data?.length === 0 && (
          <div className="col-span-full rounded-2xl border border-gray-100 bg-white py-12 text-center text-gray-400">
            No IVR flows found.
          </div>
        )}
      </div>

      <Pagination meta={page} onPage={setPageNum} />

      {editing && (
        <Modal title={editing === 'new' ? 'New IVR Flow' : 'Edit IVR Flow'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Flow Name">
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
            </div>
            <Field label="Greeting">
              <textarea
                className={`${inputClass} h-16 resize-none`}
                value={form.greeting}
                onChange={(e) => setForm({ ...form, greeting: e.target.value })}
                placeholder="Thank you for calling…"
              />
            </Field>

            <div className="mb-2 flex items-center justify-between">
              <span className="text-sm font-semibold text-gray-700">Keypad options</span>
              <button type="button" onClick={addOpt} className="text-sm font-semibold text-brand hover:underline">+ Add option</button>
            </div>
            <div className="mb-4 space-y-2">
              {form.options.map((o, i) => (
                <div key={i} className="flex items-center gap-2">
                  <input
                    className={`${inputClass} w-12 text-center`}
                    value={o.key_press}
                    maxLength={2}
                    placeholder="1"
                    onChange={(e) => setOpt(i, { key_press: e.target.value })}
                  />
                  <input
                    className={`${inputClass} flex-1`}
                    value={o.label}
                    placeholder="Label (e.g. Sales)"
                    onChange={(e) => setOpt(i, { label: e.target.value })}
                  />
                  <select className={`${inputClass} w-32`} value={o.action} onChange={(e) => setOpt(i, { action: e.target.value })}>
                    {ACTIONS.map((a) => (
                      <option key={a} value={a}>{a}</option>
                    ))}
                  </select>
                  <input
                    className={`${inputClass} w-32`}
                    value={o.destination}
                    placeholder="Destination"
                    onChange={(e) => setOpt(i, { destination: e.target.value })}
                  />
                  <button type="button" onClick={() => removeOpt(i)} className="px-2 text-lg text-red-400 hover:text-red-600">×</button>
                </div>
              ))}
            </div>

            <Field label="Status">
              <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="inactive">Inactive</option>
                <option value="active">Active</option>
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
