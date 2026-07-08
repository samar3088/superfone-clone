import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { buyNumberLink } from '../components/Layout'
import { Badge, inputClass } from '../components/ui'

function Stepper({ value, onChange }) {
  return (
    <div className="flex items-center rounded-lg border border-gray-300">
      <button type="button" onClick={() => onChange(Math.max(0, value - 1))} className="px-3 py-1.5 text-lg text-gray-500 hover:bg-gray-50">−</button>
      <span className="w-8 text-center font-semibold">{value}</span>
      <button type="button" onClick={() => onChange(value + 1)} className="px-3 py-1.5 text-lg text-gray-500 hover:bg-gray-50">+</button>
    </div>
  )
}

export default function Teams() {
  const { user, refreshUser } = useAuth()
  const [orgs, setOrgs] = useState([])
  const [selected, setSelected] = useState('')
  const [addonOrg, setAddonOrg] = useState(null) // org whose addon modal is open
  const [addons, setAddons] = useState([])
  const [cart, setCart] = useState({}) // addon_id -> qty
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState(null)

  const load = useCallback(() => {
    client.get('/orgs').then((res) => setOrgs(res.data))
  }, [])

  useEffect(() => {
    load()
    client.get('/addons').then((res) => setAddons(res.data))
  }, [load])

  const visible = selected ? orgs.filter((o) => String(o.id) === selected) : orgs

  const renew = async (org) => {
    if (!confirm(`Renew "${org.name}" (${org.plan?.name} — ₹${Number(org.plan?.price).toLocaleString('en-IN')}) from your wallet?`)) return
    setBusy(true)
    try {
      const { data } = await client.post(`/orgs/${org.id}/renew`)
      setNotice({ type: 'ok', text: data.message })
      load()
      refreshUser()
    } catch (err) {
      setNotice({ type: 'err', text: err.response?.data?.message || 'Renewal failed.' })
    } finally {
      setBusy(false)
    }
  }

  const openAddons = (org) => {
    setAddonOrg(org)
    setCart({})
  }

  const total = addons.reduce((sum, a) => sum + (cart[a.id] || 0) * Number(a.price), 0)

  const purchase = async () => {
    const items = Object.entries(cart)
      .filter(([, qty]) => qty > 0)
      .map(([addon_id, quantity]) => ({ addon_id: Number(addon_id), quantity }))
    if (!items.length) return
    setBusy(true)
    try {
      const { data } = await client.post('/addons/purchase', { customer_id: addonOrg.id, items })
      setNotice({ type: 'ok', text: data.message })
      setAddonOrg(null)
      load()
      refreshUser()
    } catch (err) {
      setNotice({ type: 'err', text: err.response?.data?.message || 'Purchase failed.' })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="flex items-center gap-3 text-3xl font-bold">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-xl">🏢</span>
            Teams <span className="text-lg font-medium text-gray-400">{orgs.length}</span>
          </h1>
          <p className="mt-1 text-gray-500">Manage your organizations, members, and subscriptions.</p>
        </div>
        <div className="flex items-center gap-3">
          <span className="font-semibold">Select Orgs:</span>
          <select value={selected} onChange={(e) => setSelected(e.target.value)} className={`${inputClass} w-72`}>
            <option value="">All organizations</option>
            {orgs.map((o) => (
              <option key={o.id} value={o.id}>{o.number}, {o.name}</option>
            ))}
          </select>
          <a
            href={buyNumberLink(user, orgs[0]?.name)}
            target="_blank"
            rel="noreferrer"
            className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
          >
            Buy a new Number
          </a>
        </div>
      </div>

      {notice && (
        <div className={`mt-4 rounded-lg px-4 py-3 text-sm font-medium ${notice.type === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
          {notice.text}
          <button onClick={() => setNotice(null)} className="float-right font-bold">×</button>
        </div>
      )}

      <div className="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wide text-gray-500">
              <th className="px-5 py-3.5">#</th>
              <th className="px-5 py-3.5">Team</th>
              <th className="px-5 py-3.5">Status</th>
              <th className="px-5 py-3.5">Number</th>
              <th className="px-5 py-3.5">Staff</th>
              <th className="px-5 py-3.5">Leads</th>
              <th className="px-5 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {visible.map((org, i) => (
              <tr key={org.id} className="border-b border-gray-50 last:border-0">
                <td className="px-5 py-4 text-gray-400">{i + 1}</td>
                <td className="max-w-xs truncate px-5 py-4 font-bold">{org.name}</td>
                <td className="px-5 py-4"><Badge value={org.status} /></td>
                <td className="px-5 py-4">{org.number || '—'}</td>
                <td className="px-5 py-4">
                  <strong>{org.staff_count}</strong> <span className="text-gray-400">/ {org.staff_limit}</span>
                </td>
                <td className="px-5 py-4">{org.leads_count}/{org.leads_limit}</td>
                <td className="px-5 py-4">
                  <div className="flex justify-end gap-2">
                    <button
                      onClick={() => renew(org)}
                      disabled={busy}
                      className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                      Renew
                    </button>
                    <button
                      onClick={() => openAddons(org)}
                      className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                    >
                      Addon Purchase
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {visible.length === 0 && (
              <tr><td colSpan={7} className="px-5 py-12 text-center text-gray-400">No teams found.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {/* ── Addon Purchase modal ── */}
      {addonOrg && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onClick={() => setAddonOrg(null)}>
          <div className="flex max-h-[85vh] w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-start justify-between p-5 pb-0">
              <h3 className="pr-4 text-lg font-bold">Buy Addons for {addonOrg.name} org</h3>
              <button onClick={() => setAddonOrg(null)} className="rounded-lg bg-blue-600 px-3 py-1 font-bold text-white">X</button>
            </div>
            <div className="mx-5 mt-3 rounded bg-yellow-300 px-4 py-2.5 text-sm font-medium">
              ⓘ Final price will be calculated based on the remaining days of your current plan at checkout.
            </div>
            <div className="flex items-center justify-between px-5 pt-4">
              <div>
                <div className="text-xs font-bold uppercase tracking-wide text-gray-500">Current plan</div>
                <div className="font-bold">{addonOrg.plan?.name || '—'} {addonOrg.plan ? `— ₹${Number(addonOrg.plan.price).toLocaleString('en-IN')}` : ''}</div>
              </div>
              <span className="rounded-lg bg-green-500 px-3 py-1 text-sm font-bold text-white">
                {addonOrg.status === 'active' ? 'ACTIVE' : addonOrg.status.toUpperCase()}
              </span>
            </div>
            <h4 className="px-5 pt-4 font-bold">Add-ons</h4>
            <div className="scroll-thin min-h-0 flex-1 space-y-3 overflow-y-auto p-5 pt-3">
              {addons.map((a) => (
                <div key={a.id} className="flex items-center justify-between rounded-2xl border border-gray-200 p-4">
                  <div className="flex items-center gap-3">
                    <span className="text-2xl">{a.icon}</span>
                    <div>
                      <div className="font-semibold">{a.name}</div>
                      <div className="text-lg font-bold">₹{Number(a.price).toLocaleString('en-IN')} <span className="text-sm font-normal text-gray-400">{a.unit}</span></div>
                    </div>
                  </div>
                  {a.quantity_based ? (
                    <Stepper value={cart[a.id] || 0} onChange={(v) => setCart({ ...cart, [a.id]: v })} />
                  ) : (
                    <button
                      onClick={() => setCart({ ...cart, [a.id]: cart[a.id] ? 0 : 1 })}
                      className={`rounded-full px-5 py-1.5 text-sm font-semibold ${cart[a.id] ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'}`}
                    >
                      {cart[a.id] ? 'Added ✓' : 'Add'}
                    </button>
                  )}
                </div>
              ))}
            </div>
            <div className="flex items-center justify-between border-t border-gray-100 p-5">
              <div>
                <div className="text-sm text-gray-500">Total</div>
                <div className="text-xl font-extrabold">₹{total.toLocaleString('en-IN')}</div>
                <div className="text-xs text-gray-400">Wallet: ₹{Number(user?.wallet_balance ?? 0).toLocaleString('en-IN')}</div>
              </div>
              <button
                onClick={purchase}
                disabled={busy || total === 0}
                className="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
              >
                {busy ? 'Processing…' : 'Purchase'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
