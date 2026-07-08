import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const EMPTY = {
  customer_id: '',
  name: '',
  phone: '',
  email: '',
  company: '',
  tag: 'prospect',
  notes: '',
}

export default function Contacts() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [tag, setTag] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/contacts', { params: { search, tag, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, tag, pageNum])

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

  const openEdit = (c) => {
    setEditing(c.id)
    setForm({
      customer_id: c.customer_id,
      name: c.name,
      phone: c.phone,
      email: c.email || '',
      company: c.company || '',
      tag: c.tag,
      notes: c.notes || '',
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/contacts', form)
      } else {
        await client.put(`/contacts/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const remove = async (c) => {
    if (!confirm(`Delete contact "${c.name}"?`)) return
    await client.delete(`/contacts/${c.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search contacts…"
            className={`${inputClass} w-64`}
          />
          <select value={tag} onChange={(e) => { setPageNum(1); setTag(e.target.value) }} className={`${inputClass} w-40`}>
            <option value="">All tags</option>
            <option value="customer">Customer</option>
            <option value="prospect">Prospect</option>
            <option value="vendor">Vendor</option>
            <option value="other">Other</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ Add Contact</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Name</th>
                <th className="px-5 py-3">Contact</th>
                <th className="px-5 py-3">Company</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Tag</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((c) => (
                <tr key={c.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3">
                    <div className="font-semibold">{c.name}</div>
                    {c.notes && <div className="max-w-xs truncate text-xs text-gray-400">{c.notes}</div>}
                  </td>
                  <td className="px-5 py-3 text-gray-500">
                    <div>{c.phone}</div>
                    <div className="text-xs">{c.email}</div>
                  </td>
                  <td className="px-5 py-3 text-gray-600">{c.company || '—'}</td>
                  <td className="px-5 py-3 text-gray-600">{c.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3"><Badge value={c.tag} /></td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(c)}>Edit</Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(c)}>Delete</Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-12 text-center text-gray-400">No contacts found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Contact' : 'Edit Contact'} onClose={() => setEditing(null)}>
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
                {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
              </Field>
              <Field label="Company">
                <input className={inputClass} value={form.company} onChange={(e) => setForm({ ...form, company: e.target.value })} />
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
              <Field label="Tag">
                <select className={inputClass} value={form.tag} onChange={(e) => setForm({ ...form, tag: e.target.value })}>
                  <option value="customer">Customer</option>
                  <option value="prospect">Prospect</option>
                  <option value="vendor">Vendor</option>
                  <option value="other">Other</option>
                </select>
              </Field>
            </div>
            <Field label="Notes">
              <textarea
                className={`${inputClass} h-20 resize-none`}
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
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
