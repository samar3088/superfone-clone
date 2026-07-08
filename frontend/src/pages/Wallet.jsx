import { useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { inr } from '../components/ui'

export default function Wallet() {
  const { user } = useAuth()
  const [purchases, setPurchases] = useState([])
  const [payments, setPayments] = useState([])

  useEffect(() => {
    client.get('/addon-purchases').then((res) => setPurchases(res.data))
    client.get('/payments', { params: { page: 1 } }).then((res) => setPayments(res.data.data.slice(0, 8)))
  }, [])

  const fmt = (iso) => new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })

  return (
    <div className="mx-auto max-w-5xl">
      <h1 className="text-3xl font-bold">Wallet</h1>

      <div className="mt-6 flex items-center justify-between rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-white shadow-lg">
        <div>
          <div className="text-sm opacity-80">Available balance</div>
          <div className="mt-1 text-4xl font-extrabold">₹{Number(user?.wallet_balance ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 1 })}</div>
        </div>
        <a
          href="https://api.whatsapp.com/send?phone=919429690909&text=I%20want%20to%20recharge%20my%20Superfone%20wallet"
          target="_blank"
          rel="noreferrer"
          className="rounded-lg bg-white px-5 py-2.5 font-bold text-blue-600 hover:bg-blue-50"
        >
          + Add money
        </a>
      </div>

      <h2 className="mt-8 text-lg font-bold">Add-on purchases</h2>
      <div className="mt-3 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
              <th className="px-5 py-3">Add-on</th>
              <th className="px-5 py-3">Team</th>
              <th className="px-5 py-3">Qty</th>
              <th className="px-5 py-3">Amount</th>
              <th className="px-5 py-3">Date</th>
            </tr>
          </thead>
          <tbody>
            {purchases.map((p) => (
              <tr key={p.id} className="border-b border-gray-50 last:border-0">
                <td className="px-5 py-3 font-semibold">{p.addon?.icon} {p.addon?.name}</td>
                <td className="px-5 py-3 text-gray-500">{p.customer?.business_name}</td>
                <td className="px-5 py-3">{p.quantity}</td>
                <td className="px-5 py-3 font-semibold">{inr(p.amount)}</td>
                <td className="px-5 py-3 text-gray-400">{fmt(p.created_at)}</td>
              </tr>
            ))}
            {purchases.length === 0 && (
              <tr><td colSpan={5} className="px-5 py-10 text-center text-gray-400">No add-on purchases yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <h2 className="mt-8 text-lg font-bold">Recent plan payments</h2>
      <div className="mt-3 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
              <th className="px-5 py-3">Invoice</th>
              <th className="px-5 py-3">Team</th>
              <th className="px-5 py-3">Amount</th>
              <th className="px-5 py-3">Status</th>
              <th className="px-5 py-3">Paid on</th>
            </tr>
          </thead>
          <tbody>
            {payments.map((p) => (
              <tr key={p.id} className="border-b border-gray-50 last:border-0">
                <td className="px-5 py-3 font-semibold">{p.invoice_no}</td>
                <td className="px-5 py-3 text-gray-500">{p.customer?.business_name}</td>
                <td className="px-5 py-3 font-semibold">{inr(p.amount)}</td>
                <td className="px-5 py-3 capitalize">{p.status}</td>
                <td className="px-5 py-3 text-gray-400">{p.paid_at ? fmt(p.paid_at) : '—'}</td>
              </tr>
            ))}
            {payments.length === 0 && (
              <tr><td colSpan={5} className="px-5 py-10 text-center text-gray-400">No payments yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
