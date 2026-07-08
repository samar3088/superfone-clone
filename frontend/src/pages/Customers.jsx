import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const EMPTY = {
  business_name: '',
  owner_name: '',
  email: '',
  phone: '',
  city: '',
  plan_id: '',
  status: 'trial',
}

export default function Customers() {
  const [page, setPage] = useState(null)
  const [plans, setPlans] = useState([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/customers', { params: { search, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/plans').then((res) => setPlans(res.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm(EMPTY)
    setErrors({})
  }

  const openEdit = (c) => {
    setEditing(c.id)
    setForm({
      business_name: c.business_name,
      owner_name: c.owner_name,
      email: c.email,
      phone: c.phone,
      city: c.city || '',
      plan_id: c.plan_id || '',
      status: c.status,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = { ...form, plan_id: form.plan_id || null }
    try {
      if (editing === 'new') {
        await client.post('/customers', payload)
      } else {
        await client.put(`/customers/${editing}`, payload)
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
    if (!confirm(`Delete "${c.business_name}"?`)) return
    await client.delete(`/customers/${c.id}`)
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
            placeholder="Search customers…"
            className={`${inputClass} w-64`}
          />
          <select
            value={status}
            onChange={(e) => {
              setPageNum(1)
              setStatus(e.target.value)
            }}
            className={`${inputClass} w-40`}
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="trial">Trial</option>
            <option value="suspended">Suspended</option>
            <option value="churned">Churned</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ Add Customer</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Owner</th>
                <th className="px-5 py-3">Contact</th>
                <th className="px-5 py-3">Plan</th>
                <th className="px-5 py-3">Numbers</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((c) => (
                <tr key={c.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3">
                    <div className="font-semibold">{c.business_name}</div>
                    <div className="text-xs text-gray-400">{c.city}</div>
                  </td>
                  <td className="px-5 py-3 text-gray-600">{c.owner_name}</td>
                  <td className="px-5 py-3 text-gray-500">
                    <div>{c.phone}</div>
                    <div className="text-xs">{c.email}</div>
                  </td>
                  <td className="px-5 py-3 text-gray-600">{c.plan?.name || '—'}</td>
                  <td className="px-5 py-3 text-gray-600">{c.numbers_count}</td>
                  <td className="px-5 py-3"><Badge value={c.status} /></td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(c)}>
                        Edit
                      </Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(c)}>
                        Delete
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-gray-400">
                    No customers found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'Add Customer' : 'Edit Customer'}
          onClose={() => setEditing(null)}
        >
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Business Name">
                <input
                  className={inputClass}
                  value={form.business_name}
                  onChange={(e) => setForm({ ...form, business_name: e.target.value })}
                />
                {errors.business_name && <p className="mt-1 text-xs text-red-600">{errors.business_name[0]}</p>}
              </Field>
              <Field label="Owner Name">
                <input
                  className={inputClass}
                  value={form.owner_name}
                  onChange={(e) => setForm({ ...form, owner_name: e.target.value })}
                />
                {errors.owner_name && <p className="mt-1 text-xs text-red-600">{errors.owner_name[0]}</p>}
              </Field>
              <Field label="Email">
                <input
                  className={inputClass}
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                />
                {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
              </Field>
              <Field label="Phone">
                <input
                  className={inputClass}
                  value={form.phone}
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                />
                {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone[0]}</p>}
              </Field>
              <Field label="City">
                <input
                  className={inputClass}
                  value={form.city}
                  onChange={(e) => setForm({ ...form, city: e.target.value })}
                />
              </Field>
              <Field label="Plan">
                <select
                  className={inputClass}
                  value={form.plan_id}
                  onChange={(e) => setForm({ ...form, plan_id: e.target.value })}
                >
                  <option value="">No plan</option>
                  {plans.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
            <Field label="Status">
              <select
                className={inputClass}
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
              >
                <option value="active">Active</option>
                <option value="trial">Trial</option>
                <option value="suspended">Suspended</option>
                <option value="churned">Churned</option>
              </select>
            </Field>
            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="light" onClick={() => setEditing(null)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
