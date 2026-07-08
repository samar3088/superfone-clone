import { useEffect, useRef, useState, useCallback } from 'react'
import client from '../api/client'
import { inputClass } from '../components/ui'

function elapsed(fromIso) {
  const s = Math.max(0, Math.floor((Date.now() - new Date(fromIso).getTime()) / 1000))
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

export default function LiveCalls() {
  const [state, setState] = useState({ active: [], recent: [], sticky_ring_seconds: 15 })
  const [orgs, setOrgs] = useState([])
  const [membersByOrg, setMembersByOrg] = useState({})
  const [simOrg, setSimOrg] = useState('')
  const [caller, setCaller] = useState('9876512340')
  const [busy, setBusy] = useState(false)
  const [, forceTick] = useState(0)
  const escalatingRef = useRef(new Set())

  const load = useCallback(() => {
    client.get('/live-calls').then((res) => setState(res.data))
  }, [])

  useEffect(() => {
    load()
    client.get('/orgs').then((res) => {
      setOrgs(res.data)
      setSimOrg(String(res.data[0]?.id || ''))
      res.data.forEach((org) => {
        client.get('/team-members', { params: { customer_id: org.id } }).then((r) =>
          setMembersByOrg((prev) => ({ ...prev, [org.id]: r.data.data }))
        )
      })
    })
    const poll = setInterval(load, 3000)
    const tick = setInterval(() => forceTick((n) => n + 1), 1000)
    return () => { clearInterval(poll); clearInterval(tick) }
  }, [load])

  // Auto-escalate: if the sticky agent hasn't answered within the ring window,
  // open the call to everyone (exactly what a provider timeout would do).
  useEffect(() => {
    state.active.forEach((call) => {
      if (call.status !== 'ringing' || call.ring_all || !call.sticky_agent_id) return
      const ringingFor = (Date.now() - new Date(call.started_at).getTime()) / 1000
      if (ringingFor > state.sticky_ring_seconds && !escalatingRef.current.has(call.id)) {
        escalatingRef.current.add(call.id)
        client.post(`/live-calls/${call.id}/escalate`).then(load).catch(() => {})
      }
    })
  })

  const simulate = async (e) => {
    e.preventDefault()
    const org = orgs.find((o) => String(o.id) === simOrg)
    if (!org?.number || !caller) return
    setBusy(true)
    try {
      await client.post('/telephony/webhook/simulated', { to: org.number, caller })
      load()
    } finally {
      setBusy(false)
    }
  }

  const answer = async (call, memberId) => {
    await client.post(`/live-calls/${call.id}/answer`, { team_member_id: memberId })
    load()
  }

  const hangup = async (call) => {
    await client.post(`/live-calls/${call.id}/hangup`)
    load()
  }

  const stickyLeft = (call) =>
    Math.max(0, state.sticky_ring_seconds - Math.floor((Date.now() - new Date(call.started_at).getTime()) / 1000))

  return (
    <div className="mx-auto max-w-6xl">
      <h1 className="flex items-center gap-3 text-3xl font-bold">
        Live Call Dashboard
        <span className="h-3 w-3 animate-pulse rounded-full bg-green-500" />
      </h1>
      <p className="mt-1 text-gray-500">
        Incoming calls ring the agent who last spoke with the caller. If they don't pick up in{' '}
        {state.sticky_ring_seconds}s, the call opens to the whole team.
      </p>

      {/* Simulate — same webhook a real provider (Exotel/Twilio) would call */}
      <form onSubmit={simulate} className="mt-6 flex flex-wrap items-end gap-3 rounded-2xl border border-dashed border-blue-300 bg-blue-50/40 p-5">
        <div>
          <span className="mb-1.5 block text-sm font-semibold">Virtual number (team)</span>
          <select value={simOrg} onChange={(e) => setSimOrg(e.target.value)} className={`${inputClass} w-80`}>
            {orgs.map((o) => (
              <option key={o.id} value={o.id}>{o.number} — {o.name}</option>
            ))}
          </select>
        </div>
        <div>
          <span className="mb-1.5 block text-sm font-semibold">Caller number</span>
          <input value={caller} onChange={(e) => setCaller(e.target.value)} className={`${inputClass} w-52`} />
        </div>
        <button type="submit" disabled={busy} className="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-50">
          📲 Simulate incoming call
        </button>
        <span className="text-xs text-gray-400">Posts to /api/telephony/webhook/simulated — a real provider plugs into the same endpoint.</span>
      </form>

      {/* Active calls */}
      <h2 className="mt-8 text-lg font-bold">Active calls ({state.active.length})</h2>
      <div className="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {state.active.map((call) => {
          const members = (membersByOrg[call.customer_id] || []).filter((m) => m.status === 'active')
          return (
            <div key={call.id} className={`rounded-2xl border-2 bg-white p-5 shadow-sm ${call.status === 'ringing' ? 'border-amber-300' : 'border-green-300'}`}>
              <div className="flex items-start justify-between">
                <div>
                  <div className="text-xl font-bold">{call.caller}</div>
                  <div className="text-sm text-gray-500">→ {call.virtual_number} · {call.customer?.business_name}</div>
                </div>
                {call.status === 'ringing' ? (
                  <span className="animate-pulse rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-600">📳 Ringing {elapsed(call.started_at)}</span>
                ) : (
                  <span className="rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-700">🟢 Live {elapsed(call.answered_at)}</span>
                )}
              </div>

              {call.status === 'ringing' && !call.ring_all && call.sticky_agent && (
                <div className="mt-4 rounded-xl bg-indigo-50 p-3">
                  <div className="text-sm">
                    <strong>{call.sticky_agent.name}</strong> spoke with this caller last time — ringing them first
                    <span className="ml-2 font-mono font-bold text-indigo-600">{stickyLeft(call)}s</span>
                  </div>
                  <div className="mt-2 flex gap-2">
                    <button onClick={() => answer(call, call.sticky_agent.id)} className="rounded-lg bg-green-600 px-4 py-1.5 text-sm font-bold text-white hover:bg-green-700">
                      ✅ {call.sticky_agent.name} answers
                    </button>
                    <button
                      onClick={() => client.post(`/live-calls/${call.id}/escalate`).then(load)}
                      className="rounded-lg border border-gray-300 px-4 py-1.5 text-sm font-semibold hover:bg-gray-50"
                    >
                      Ring everyone now
                    </button>
                  </div>
                </div>
              )}

              {call.status === 'ringing' && call.ring_all && (
                <div className="mt-4">
                  <div className="text-sm font-semibold text-gray-600">
                    {call.sticky_agent ? `${call.sticky_agent.name} didn't pick up — ` : 'New caller — '}ringing the whole team. Anyone can answer:
                  </div>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {members.map((m) => (
                      <button key={m.id} onClick={() => answer(call, m.id)} className="rounded-lg bg-green-600 px-3.5 py-1.5 text-sm font-bold text-white hover:bg-green-700">
                        {m.name}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {call.status === 'in_progress' && (
                <div className="mt-4 flex items-center justify-between rounded-xl bg-green-50 p-3">
                  <span className="text-sm">On call with <strong>{call.answered_by?.name}</strong></span>
                  <button onClick={() => hangup(call)} className="rounded-lg bg-red-500 px-4 py-1.5 text-sm font-bold text-white hover:bg-red-600">
                    ⏹ Hang up
                  </button>
                </div>
              )}

              {call.status === 'ringing' && (
                <button onClick={() => hangup(call)} className="mt-3 text-xs text-gray-400 underline hover:text-red-500">
                  Caller hung up (mark missed)
                </button>
              )}
            </div>
          )
        })}
        {state.active.length === 0 && (
          <div className="col-span-full rounded-2xl border border-gray-100 bg-white py-12 text-center text-gray-400">
            No active calls. Simulate one above to see the sticky routing in action.
          </div>
        )}
      </div>

      {/* Recent */}
      <h2 className="mt-8 text-lg font-bold">Recent calls</h2>
      <div className="mt-3 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
              <th className="px-5 py-3">Caller</th>
              <th className="px-5 py-3">Team</th>
              <th className="px-5 py-3">Handled by</th>
              <th className="px-5 py-3">Result</th>
              <th className="px-5 py-3">When</th>
            </tr>
          </thead>
          <tbody>
            {state.recent.map((c) => (
              <tr key={c.id} className="border-b border-gray-50 last:border-0">
                <td className="px-5 py-3 font-semibold">{c.caller}</td>
                <td className="px-5 py-3 text-gray-500">{c.customer?.business_name}</td>
                <td className="px-5 py-3">{c.answered_by?.name || '—'}</td>
                <td className="px-5 py-3">
                  <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${c.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>
                    {c.status}
                  </span>
                </td>
                <td className="px-5 py-3 text-gray-400">{new Date(c.started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}</td>
              </tr>
            ))}
            {state.recent.length === 0 && (
              <tr><td colSpan={5} className="px-5 py-10 text-center text-gray-400">No calls yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
