import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, StatCard, inputClass, inr } from '../components/ui'

const METHOD_LABEL = {
  card: 'Card',
  upi: 'UPI',
  netbanking: 'Net Banking',
  wallet: 'Wallet',
  cash: 'Cash',
}

const EMPTY = {
  customer_id: '',
  plan_id: '',
  amount: '',
  method: 'upi',
  status: 'pending',
}

export default function Payments() {
  const [page, setPage] = useState(null)
  const [summary, setSummary] = useState(null)
  const [customers, setCustomers] = useState([])
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
      .get('/payments', { params: { search, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, status, pageNum])

  const loadSummary = useCallback(() => {
    client.get('/payments/summary').then((res) => setSummary(res.data))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    loadSummary()
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
    client.get('/plans').then((res) => setPlans(res.data))
  }, [loadSummary])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '' })
    setErrors({})
  }

  const openEdit = (p) => {
    setEditing(p.id)
    setForm({
      customer_id: p.customer_id,
      plan_id: p.plan_id || '',
      amount: p.amount,
      method: p.method,
      status: p.status,
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
        await client.post('/payments', payload)
      } else {
        await client.put(`/payments/${editing}`, payload)
      }
      setEditing(null)
      load()
      loadSummary()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const markPaid = async (p) => {
    await client.patch(`/payments/${p.id}/paid`)
    load()
    loadSummary()
  }

  const remove = async (p) => {
    if (!confirm(`Delete invoice ${p.invoice_no}?`)) return
    await client.delete(`/payments/${p.id}`)
    load()
    loadSummary()
  }

  const fmt = (iso) => (iso ? new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—')

  return (
    <div>
      {summary && (
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Collected" value={inr(summary.collected)} sub="all-time paid" icon="✅" />
          <StatCard label="Outstanding" value={inr(summary.outstanding)} sub="pending invoices" icon="⏳" />
          <StatCard label="This Month" value={inr(summary.this_month)} sub="collected" icon="📅" />
          <StatCard label="Failed" value={summary.failed} sub="payments" icon="⚠️" />
        </div>
      )}

      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search invoice or business…"
            className={`${inputClass} w-64`}
          />
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-40`}>
            <option value="">All statuses</option>
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New Invoice</Button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Invoice</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Plan</th>
                <th className="px-5 py-3">Amount</th>
                <th className="px-5 py-3">Method</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3">Paid On</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((p) => (
                <tr key={p.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3 font-semibold">{p.invoice_no}</td>
                  <td className="px-5 py-3 text-gray-600">{p.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{p.plan?.name || '—'}</td>
                  <td className="px-5 py-3 font-semibold">{inr(p.amount)}</td>
                  <td className="px-5 py-3 text-gray-500">{METHOD_LABEL[p.method]}</td>
                  <td className="px-5 py-3"><Badge value={p.status} /></td>
                  <td className="px-5 py-3 text-gray-400">{fmt(p.paid_at)}</td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end gap-2">
                      {p.status !== 'paid' && (
                        <Button variant="light" className="px-3 py-1.5" onClick={() => markPaid(p)}>Mark paid</Button>
                      )}
                      <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(p)}>Edit</Button>
                      <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(p)}>Delete</Button>
                    </div>
                  </td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-5 py-12 text-center text-gray-400">No payments found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'New Invoice' : 'Edit Invoice'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
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
              <Field label="Plan">
                <select
                  className={inputClass}
                  value={form.plan_id}
                  onChange={(e) => {
                    const plan = plans.find((pl) => String(pl.id) === e.target.value)
                    setForm({ ...form, plan_id: e.target.value, amount: plan ? plan.price : form.amount })
                  }}
                >
                  <option value="">No plan</option>
                  {plans.map((pl) => (
                    <option key={pl.id} value={pl.id}>{pl.name} — {inr(pl.price)}</option>
                  ))}
                </select>
              </Field>
              <Field label="Amount (₹)">
                <input type="number" className={inputClass} value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
                {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount[0]}</p>}
              </Field>
              <Field label="Method">
                <select className={inputClass} value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>
                  <option value="upi">UPI</option>
                  <option value="card">Card</option>
                  <option value="netbanking">Net Banking</option>
                  <option value="wallet">Wallet</option>
                  <option value="cash">Cash</option>
                </select>
              </Field>
            </div>
            <Field label="Status">
              <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="failed">Failed</option>
                <option value="refunded">Refunded</option>
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
