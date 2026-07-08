import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { buyNumberLink } from '../components/Layout'
import { inputClass } from '../components/ui'

function fmtDur(sec) {
  if (!sec) return '0s'
  const m = Math.floor(sec / 60)
  const s = sec % 60
  if (m === 0) return `${s}s`
  return `${m}m ${s}s`
}

function Tile({ label, value }) {
  return (
    <div className="border-b border-r border-gray-100 px-6 py-5 last:border-r-0">
      <div className="text-sm text-gray-500">{label}</div>
      <div className="mt-1 text-2xl font-bold">{value}</div>
    </div>
  )
}

export default function Home() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [from, setFrom] = useState(new Date().toISOString().slice(0, 10))
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10))
  const [staffOpen, setStaffOpen] = useState(false)

  const load = useCallback(() => {
    client.get('/home', { params: { from, to } }).then((res) => setData(res.data))
  }, [from, to])

  useEffect(() => {
    load()
  }, [load])

  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'
  const emoji = hour < 12 ? '🌅' : hour < 17 ? '☀️' : '🌙'
  const today = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })

  const ci = data?.call_insights
  const cust = data?.customer_insights
  const sub = data?.subscription

  return (
    <div className="mx-auto max-w-6xl">
      <h1 className="text-4xl font-bold">
        {greeting}, {user?.name} {emoji}
      </h1>
      <p className="mt-1 text-gray-500">{today}</p>

      {/* Explore AI */}
      <div className="mt-8 flex items-center gap-2 text-xs font-bold tracking-widest text-gray-600">
        <span className="text-indigo-500">✦</span> EXPLORE AI
      </div>
      <div className="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Link to="/caller-tunes" className="group flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md">
          <span className="grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-xl">🎵</span>
          <div className="flex-1">
            <div className="font-bold">AI Caller Tune</div>
            <div className="text-sm text-gray-500">Personalize your ring and greet callers</div>
          </div>
          <span className="text-gray-300 transition group-hover:text-gray-500">↗</span>
        </Link>
        <Link to="/ai-receptionist" className="group flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md">
          <span className="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-xl">🎧</span>
          <div className="flex-1">
            <div className="font-bold">AI Receptionist</div>
            <div className="text-sm text-gray-500">Let AI answer and route your calls 24/7</div>
          </div>
          <span className="text-gray-300 transition group-hover:text-gray-500">↗</span>
        </Link>
      </div>

      {/* Call insights header */}
      <div className="mt-8 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">Call insights</h2>
          <p className="text-sm text-gray-400">
            {data?.range?.from} – {data?.range?.to}
            {data?.synced_at && <> · synced {new Date(data.synced_at).toLocaleString('en-IN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</>}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={`${inputClass} w-40`} />
          <span className="text-gray-400">–</span>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={`${inputClass} w-40`} />
          <button onClick={load} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            ⟳ Refresh
          </button>
          <Link to="/call-logs" className="text-sm font-semibold text-blue-600 hover:underline">
            Details ›
          </Link>
        </div>
      </div>

      {/* Call Insights tiles */}
      <div className="mt-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 className="mb-3 font-bold">Call Insights</h3>
        <div className="grid grid-cols-2 overflow-hidden rounded-xl border border-gray-100 lg:grid-cols-4">
          <Tile label="Outgoing Calls" value={ci?.outgoing ?? '–'} />
          <Tile label="Incoming Calls" value={ci?.incoming ?? '–'} />
          <Tile label="Connected Calls" value={ci?.connected ?? '–'} />
          <Tile label="Received Calls" value={ci?.received ?? '–'} />
          <Tile label="Missed Calls" value={ci?.missed ?? '–'} />
          <Tile label="Total Talk Time" value={fmtDur(ci?.talk_time_sec)} />
          <Tile label="Avg. Outgoing Call Duration" value={fmtDur(ci?.avg_outgoing_sec)} />
          <Tile label="Avg. Incoming Call Duration" value={fmtDur(ci?.avg_incoming_sec)} />
        </div>
      </div>

      {/* Customer Insights */}
      <div className="mt-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 className="mb-3 font-bold">Customer Insights</h3>
        <div className="grid grid-cols-2 overflow-hidden rounded-xl border border-gray-100">
          <Tile label="Unique Customers" value={cust?.unique_customers ?? '–'} />
          <Tile label="Missed Customers" value={cust?.missed_customers ?? '–'} />
        </div>
      </div>

      {/* Staff Level Insights (collapsible) */}
      <div className="mt-5 rounded-2xl border border-gray-100 bg-white shadow-sm">
        <button
          onClick={() => setStaffOpen(!staffOpen)}
          className="flex w-full items-center justify-between px-5 py-4 font-bold"
        >
          Staff Level Insights
          <span className={`text-gray-400 transition ${staffOpen ? 'rotate-180' : ''}`}>⌄</span>
        </button>
        {staffOpen && (
          <div className="border-t border-gray-100 px-5 pb-4">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                  <th className="py-2.5">Staff</th>
                  <th className="py-2.5">Calls</th>
                  <th className="py-2.5">Connected</th>
                  <th className="py-2.5">Missed</th>
                  <th className="py-2.5">Talk Time</th>
                </tr>
              </thead>
              <tbody>
                {data?.staff_insights?.map((s, i) => (
                  <tr key={i} className="border-t border-gray-50">
                    <td className="py-2.5 font-semibold">{s.name}</td>
                    <td className="py-2.5">{s.calls}</td>
                    <td className="py-2.5">{s.connected}</td>
                    <td className="py-2.5">{s.missed}</td>
                    <td className="py-2.5">{fmtDur(s.talk_time_sec)}</td>
                  </tr>
                ))}
                {(!data?.staff_insights || data.staff_insights.length === 0) && (
                  <tr><td colSpan={5} className="py-6 text-center text-gray-400">No staff activity in this range.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Subscription overview + all-set */}
      <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <div className="text-lg font-bold">{sub?.teams ?? '–'} Team{sub?.teams === 1 ? '' : 's'}</div>
              <div className="text-sm text-gray-400">Subscription overview</div>
            </div>
            <a
              href={buyNumberLink(user)}
              target="_blank"
              rel="noreferrer"
              className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
              Buy a new Number
            </a>
          </div>
          <div className="mt-4 grid grid-cols-3 overflow-hidden rounded-xl border border-gray-100">
            <div className="border-r border-gray-100 px-5 py-4">
              <div className="text-sm text-gray-500">Expiring soon</div>
              <div className="mt-1 text-2xl font-bold text-amber-500">{sub?.expiring_soon ?? 0}</div>
            </div>
            <div className="border-r border-gray-100 px-5 py-4">
              <div className="text-sm text-gray-500">Expired</div>
              <div className="mt-1 text-2xl font-bold text-red-500">{sub?.expired ?? 0}</div>
            </div>
            <div className="px-5 py-4">
              <div className="text-sm text-gray-500">On trial</div>
              <div className="mt-1 text-2xl font-bold text-blue-600">{sub?.on_trial ?? 0}</div>
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <span className="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-lg text-emerald-500">✓</span>
          <div className="mt-3 text-lg font-bold">You're all set</div>
          <p className="text-sm text-gray-500">No pending setup. Manage teams or invite your staff anytime.</p>
          <Link to="/teams" className="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">
            Manage teams ›
          </Link>
        </div>
      </div>
    </div>
  )
}
