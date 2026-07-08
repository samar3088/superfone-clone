import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { inputClass } from '../components/ui'

const EMPTY = { first: '', last: '', phone: '', customer_id: '', role: 'member' }

export default function TeamMembers() {
  const [members, setMembers] = useState([])
  const [total, setTotal] = useState(0)
  const [orgs, setOrgs] = useState([])
  const [selectedOrg, setSelectedOrg] = useState('')
  const [adding, setAdding] = useState(false)
  const [editing, setEditing] = useState(null) // member being edited
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    client
      .get('/team-members', { params: { customer_id: selectedOrg || undefined, page: 1 } })
      .then((res) => {
        // API paginates; pull the full first page (10) + total.
        setMembers(res.data.data)
        setTotal(res.data.total)
      })
  }, [selectedOrg])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/orgs').then((res) => setOrgs(res.data))
  }, [])

  const openAdd = () => {
    setForm({ ...EMPTY, customer_id: selectedOrg || orgs[0]?.id || '' })
    setErrors({})
    setEditing(null)
    setAdding(true)
  }

  const openEdit = (m) => {
    const [first, ...rest] = (m.name || '').split(' ')
    setForm({
      first,
      last: rest.join(' '),
      phone: m.phone || '',
      customer_id: m.customer_id,
      role: m.role,
    })
    setErrors({})
    setEditing(m)
    setAdding(true)
  }

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setErrors({})
    const payload = {
      customer_id: form.customer_id,
      name: `${form.first} ${form.last}`.trim(),
      email: `${(form.first + '.' + form.last).toLowerCase().replace(/[^a-z.]/g, '') || 'staff'}@team.in`,
      phone: form.phone,
      role: form.role,
      status: editing ? editing.status : 'active',
    }
    try {
      if (editing) {
        await client.put(`/team-members/${editing.id}`, payload)
      } else {
        await client.post('/team-members', payload)
      }
      setAdding(false)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setBusy(false)
    }
  }

  const remove = async (m) => {
    if (!confirm(`Remove "${m.name}" from the team?`)) return
    await client.delete(`/team-members/${m.id}`)
    load()
  }

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="flex items-center gap-3 text-3xl font-bold">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-xl">👥</span>
            Team Members <span className="text-lg font-medium text-gray-400">{total}</span>
          </h1>
          <p className="mt-1 text-gray-500">Manage staff access, presence, and roles across your teams.</p>
        </div>
        <div className="flex items-center gap-3">
          <span className="font-semibold">Select Orgs:</span>
          <select value={selectedOrg} onChange={(e) => setSelectedOrg(e.target.value)} className={`${inputClass} w-72`}>
            <option value="">All organizations</option>
            {orgs.map((o) => (
              <option key={o.id} value={o.id}>{o.number}, {o.name}</option>
            ))}
          </select>
          <button onClick={load} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            ⟳ Refresh
          </button>
          <button onClick={openAdd} className="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
            👤+ Add staff
          </button>
        </div>
      </div>

      <div className="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 bg-blue-50/40 text-left text-[13px] font-bold text-gray-700">
              <th className="px-5 py-3.5">Name</th>
              <th className="px-5 py-3.5">Role</th>
              <th className="px-5 py-3.5">State</th>
              <th className="px-5 py-3.5">Status</th>
              <th className="px-5 py-3.5">Actions</th>
            </tr>
          </thead>
          <tbody>
            {members.map((m) => (
              <tr key={m.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/40">
                <td className="px-5 py-4">
                  <div className="font-semibold text-blue-600 underline decoration-blue-200 underline-offset-2">{m.name}</div>
                  <div className="mt-0.5 text-gray-500">{m.phone}</div>
                </td>
                <td className="px-5 py-4 capitalize">{m.role}</td>
                <td className="px-5 py-4">
                  <span className={`inline-flex rounded border px-2.5 py-0.5 text-xs font-semibold capitalize ${
                    m.status === 'active'
                      ? 'border-green-200 bg-green-50 text-green-600'
                      : 'border-gray-200 bg-gray-50 text-gray-500'
                  }`}>
                    {m.status}
                  </span>
                </td>
                <td className="px-5 py-4 text-gray-400">-</td>
                <td className="px-5 py-4">
                  <div className="flex items-center gap-4">
                    <button onClick={() => openEdit(m)} title="Edit" className="text-gray-400 hover:text-gray-600">✏️</button>
                    {m.role !== 'owner' && (
                      <button onClick={() => remove(m)} title="Remove" className="text-red-300 hover:text-red-500">🗑️</button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {members.length === 0 && (
              <tr><td colSpan={5} className="px-5 py-12 text-center text-gray-400">No team members found.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {/* ── Add / Edit staff modal ── */}
      {adding && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onClick={() => setAdding(false)}>
          <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-xl font-bold">{editing ? 'Edit staff' : 'Add staff'}</h3>
            <form onSubmit={submit} className="mt-5">
              <div className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <label className="block">
                  <span className="mb-1.5 block text-sm font-semibold"><span className="text-red-500">*</span> First Name</span>
                  <input className={inputClass} placeholder="Enter First Name" value={form.first} onChange={(e) => setForm({ ...form, first: e.target.value })} required />
                </label>
                <label className="block">
                  <span className="mb-1.5 block text-sm font-semibold"><span className="text-red-500">*</span> Last Name</span>
                  <input className={inputClass} placeholder="Enter Last Name" value={form.last} onChange={(e) => setForm({ ...form, last: e.target.value })} />
                </label>
                <label className="block">
                  <span className="mb-1.5 block text-sm font-semibold"><span className="text-red-500">*</span> Phone Number</span>
                  <div className="flex">
                    <span className="flex items-center rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 px-3 text-sm text-gray-500">+91</span>
                    <input
                      className={`${inputClass} rounded-l-none`}
                      placeholder="Enter Phone Number"
                      value={form.phone}
                      onChange={(e) => setForm({ ...form, phone: e.target.value.replace(/\D/g, '').slice(0, 10) })}
                      required
                    />
                  </div>
                  {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone[0]}</p>}
                </label>
                <label className="block">
                  <span className="mb-1.5 block text-sm font-semibold"><span className="text-red-500">*</span> Team</span>
                  <select className={inputClass} value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })} required>
                    <option value="">Select team…</option>
                    {orgs.map((o) => (
                      <option key={o.id} value={o.id}>{o.name} {o.number}</option>
                    ))}
                  </select>
                  {errors.customer_id && <p className="mt-1 text-xs text-red-600">{errors.customer_id[0]}</p>}
                </label>
                <label className="block">
                  <span className="mb-1.5 block text-sm font-semibold"><span className="text-red-500">*</span> Role</span>
                  <select className={inputClass} value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                    <option value="owner">Owner</option>
                  </select>
                </label>
              </div>
              <div className="mt-6 flex justify-end gap-3">
                <button type="button" onClick={() => setAdding(false)} className="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-bold hover:bg-gray-50">
                  CANCEL
                </button>
                <button type="submit" disabled={busy} className="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                  {busy ? 'SAVING…' : 'SUBMIT'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
