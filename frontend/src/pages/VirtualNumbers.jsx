import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const EMPTY = { number: '', customer_id: '', type: 'mobile', status: 'available' }

export default function VirtualNumbers() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/virtual-numbers', { params: { search, type, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, type, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    // Pull a large page of customers for the assignment dropdown.
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm(EMPTY)
    setErrors({})
  }

  const openEdit = (n) => {
    setEditing(n.id)
    setForm({
      number: n.number,
      customer_id: n.customer_id || '',
      type: n.type,
      status: n.status,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = { ...form, customer_id: form.customer_id || null }
    try {
      if (editing === 'new') {
        await client.post('/virtual-numbers', payload)
      } else {
        await client.put(`/virtual-numbers/${editing}`, payload)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const remove = async (n) => {
    if (!confirm(`Delete number ${n.number}?`)) return
    await client.delete(`/virtual-numbers/${n.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => {
              setPageNum(1)
              setSearch(e.target.value)
            }}
            placeholder="Search number…"
            className={`${inputClass} w-56`}
          />
          <select value={type} onChange={(e) => { setPageNum(1); setType(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All types</option>
            <option value="mobile">Mobile</option>
            <option value="landline">Landline</option>
            <option value="tollfree">Toll-free</option>
          </select>
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="available">Available</option>
            <option value="assigned">Assigned</option>
            <option value="porting">Porting</option>
            <option value="released">Released</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ Add Number</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Number</th>
                <th className="px-5 py-3">Type</th>
                <th className="px-5 py-3">Assigned To</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((n) => (
                <tr key={n.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3 font-semibold">{n.number}</td>
                  <td className="px-5 py-3"><Badge value={n.type} /></td>
                  <td className="px-5 py-3 text-gray-600">{n.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3"><Badge value={n.status} /></td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(n)}>Edit</Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(n)}>Delete</Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-5 py-12 text-center text-gray-400">No numbers found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Number' : 'Edit Number'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <Field label="Number">
              <input
                className={inputClass}
                value={form.number}
                onChange={(e) => setForm({ ...form, number: e.target.value })}
              />
              {errors.number && <p className="mt-1 text-xs text-red-600">{errors.number[0]}</p>}
            </Field>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Type">
                <select className={inputClass} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                  <option value="mobile">Mobile</option>
                  <option value="landline">Landline</option>
                  <option value="tollfree">Toll-free</option>
                </select>
              </Field>
              <Field label="Status">
                <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="available">Available</option>
                  <option value="assigned">Assigned</option>
                  <option value="porting">Porting</option>
                  <option value="released">Released</option>
                </select>
              </Field>
            </div>
            <Field label="Assign to Customer">
              <select
                className={inputClass}
                value={form.customer_id}
                onChange={(e) => setForm({ ...form, customer_id: e.target.value })}
              >
                <option value="">Unassigned</option>
                {customers.map((c) => (
                  <option key={c.id} value={c.id}>{c.business_name}</option>
                ))}
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
