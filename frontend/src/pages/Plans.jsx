import { useEffect, useState } from 'react'
import client from '../api/client'
import { Button, Field, Modal, inputClass, inr } from '../components/ui'

const EMPTY = {
  name: '',
  price: '',
  validity_days: 30,
  users_included: 1,
  features: '',
  is_active: true,
}

export default function Plans() {
  const [plans, setPlans] = useState([])
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = () => client.get('/plans').then((res) => setPlans(res.data))

  useEffect(() => {
    load()
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm(EMPTY)
    setErrors({})
  }

  const openEdit = (p) => {
    setEditing(p.id)
    setForm({
      name: p.name,
      price: p.price,
      validity_days: p.validity_days,
      users_included: p.users_included,
      features: p.features || '',
      is_active: p.is_active,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/plans', form)
      } else {
        await client.put(`/plans/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const remove = async (p) => {
    if (!confirm(`Delete plan "${p.name}"?`)) return
    await client.delete(`/plans/${p.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <p className="text-sm text-gray-500">Subscription plans offered to customers.</p>
        <Button onClick={openCreate}>+ Add Plan</Button>
      </div>

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {plans.map((p) => (
          <div key={p.id} className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-lg font-bold">{p.name}</h3>
                {!p.is_active && <span className="text-xs text-gray-400">Inactive</span>}
              </div>
              <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-brand">
                {p.customers_count} customers
              </span>
            </div>
            <div className="mt-4 text-3xl font-extrabold">
              {inr(p.price)}
              <span className="text-sm font-medium text-gray-400"> / {p.validity_days}d</span>
            </div>
            <ul className="mt-4 space-y-1.5 text-sm text-gray-600">
              <li>👥 {p.users_included} user{p.users_included > 1 ? 's' : ''} included</li>
              {p.features && <li>✨ {p.features}</li>}
            </ul>
            <div className="mt-5 flex gap-2">
              <Button variant="light" className="flex-1 justify-center" onClick={() => openEdit(p)}>
                Edit
              </Button>
              <Button variant="danger" className="px-3" onClick={() => remove(p)}>
                Delete
              </Button>
            </div>
          </div>
        ))}
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add Plan' : 'Edit Plan'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <Field label="Plan Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
            </Field>
            <div className="grid grid-cols-3 gap-x-4">
              <Field label="Price (₹)">
                <input type="number" className={inputClass} value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
                {errors.price && <p className="mt-1 text-xs text-red-600">{errors.price[0]}</p>}
              </Field>
              <Field label="Validity (days)">
                <input type="number" className={inputClass} value={form.validity_days} onChange={(e) => setForm({ ...form, validity_days: e.target.value })} />
              </Field>
              <Field label="Users">
                <input type="number" className={inputClass} value={form.users_included} onChange={(e) => setForm({ ...form, users_included: e.target.value })} />
              </Field>
            </div>
            <Field label="Features">
              <input className={inputClass} value={form.features} onChange={(e) => setForm({ ...form, features: e.target.value })} />
            </Field>
            <label className="mb-4 flex items-center gap-2 text-sm font-medium text-gray-700">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              />
              Active
            </label>
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
