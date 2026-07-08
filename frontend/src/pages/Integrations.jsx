import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { inputClass } from '../components/ui'

// Provider catalog — key, label, badge look. Tabs show the starred subset.
const PROVIDERS = [
  { key: 'facebook', label: 'Facebook', badge: 'f', bg: '#1877f2', tab: true },
  { key: 'indiamart', label: 'IndiaMart', badge: 'M', bg: '#b91c1c', tab: true },
  { key: 'webhook', label: 'Custom Webhook', badge: '⛓', bg: '#7c3aed', tabLabel: 'Webhook', tab: true },
  { key: 'housing', label: 'Housing.com', badge: '⌂', bg: '#f59e0b', tab: true },
  { key: '99acres', label: '99acres', badge: '99', bg: '#2563eb', tabLabel: '99Acres', tab: true },
  { key: 'magicbricks', label: 'MagicBricks', badge: 'mb', bg: '#dc2626', tab: true },
  { key: 'ninjaforms', label: 'Ninja Forms', badge: 'N', bg: '#111827' },
  { key: 'wix', label: 'Wix', badge: 'wix', bg: '#111827' },
  { key: 'email', label: 'Email Leads', badge: '✉', bg: '#6b7280' },
  { key: 'razorpay', label: 'Razorpay', badge: 'R', bg: '#0e4bad' },
  { key: 'forminator', label: 'Forminator', badge: 'F', bg: '#374151' },
  { key: 'shopify', label: 'Shopify', badge: '🛍', bg: '#16a34a' },
  { key: 'woocommerce', label: 'Woo commerce', badge: 'W', bg: '#7f54b3' },
  { key: 'googlesheet', label: 'Google sheet', badge: '▦', bg: '#16a34a' },
  { key: 'tradeindia', label: 'TradeIndia', badge: 'ti', bg: '#ea580c' },
]

const byKey = Object.fromEntries(PROVIDERS.map((p) => [p.key, p]))

function ProviderBadge({ provider, size = 'h-10 w-10 text-lg' }) {
  const p = byKey[provider] || { badge: '?', bg: '#9ca3af' }
  return (
    <span
      className={`grid ${size} flex-shrink-0 place-items-center rounded-full font-bold text-white`}
      style={{ background: p.bg }}
    >
      {p.badge}
    </span>
  )
}

function timeAgo(iso) {
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000)
  if (days < 1) return 'today'
  if (days < 30) return `${days} day${days > 1 ? 's' : ''} ago`
  const months = Math.floor(days / 30)
  if (months < 12) return `${months} month${months > 1 ? 's' : ''} ago`
  const years = Math.floor(months / 12)
  return `${years} year${years > 1 ? 's' : ''} ago`
}

export default function Integrations() {
  const [list, setList] = useState([])
  const [tab, setTab] = useState('')
  const [orgs, setOrgs] = useState([])

  // Modal state machine: null | 'providers' | 'fb-1' | 'fb-2' | 'generic'
  const [view, setView] = useState(null)
  const [orgId, setOrgId] = useState('')
  const [provider, setProvider] = useState(null)
  const [name, setName] = useState('')
  const [fb, setFb] = useState(null) // { account, pages }
  const [pageId, setPageId] = useState('')
  const [forms, setForms] = useState([])
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    client.get('/integrations', { params: { provider: tab || undefined } }).then((res) => setList(res.data))
  }, [tab])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/orgs').then((res) => {
      setOrgs(res.data)
      setOrgId(String(res.data[0]?.id || ''))
    })
  }, [])

  const openCreate = () => {
    setView('providers')
    setProvider(null)
    setName('')
    setPageId('')
  }

  const pickProvider = (p) => {
    setProvider(p)
    setName('')
    if (p.key === 'facebook') {
      setView('fb-1')
      client.get('/integrations/facebook/pages').then((res) => setFb(res.data))
    } else {
      setView('generic')
    }
  }

  const openFbStep2 = () => {
    if (!name.trim()) return
    setView('fb-2')
    if (fb?.pages?.length) selectPage(fb.pages[0].id)
  }

  const selectPage = (id) => {
    setPageId(id)
    setForms([])
    client.get(`/integrations/facebook/pages/${id}/forms`).then((res) => setForms(res.data))
  }

  const createIntegration = async (formName = null) => {
    setBusy(true)
    try {
      await client.post('/integrations', {
        customer_id: orgId,
        provider: provider.key,
        name: name.trim(),
        page_name: provider.key === 'facebook' ? fb?.pages?.find((p) => p.id === pageId)?.name : null,
        form_name: formName,
      })
      setView(null)
      setTab('')
      load()
    } finally {
      setBusy(false)
    }
  }

  const remove = async (integration) => {
    if (!confirm(`Remove integration "${integration.name}"?`)) return
    await client.delete(`/integrations/${integration.id}`)
    load()
  }

  const toggle = async (integration) => {
    await client.patch(`/integrations/${integration.id}/toggle`)
    load()
  }

  const tabs = [{ key: '', label: 'All' }, ...PROVIDERS.filter((p) => p.tab).map((p) => ({ key: p.key, label: p.tabLabel || p.label, provider: p.key }))]

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-3xl font-bold">Integrations</h1>
          <p className="mt-2 text-gray-600">
            You can connect and sync your leads from different platforms effortlessly in realtime. Stay organised and never miss a lead.
          </p>
        </div>
        <button onClick={openCreate} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
          Create new Integration
        </button>
      </div>

      {/* Provider tabs */}
      <div className="mt-6 flex gap-1 overflow-x-auto rounded-t-xl border border-gray-100 bg-white px-2">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-semibold transition ${
              tab === t.key ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            {t.provider && <ProviderBadge provider={t.provider} size="h-5 w-5 text-[10px]" />}
            {t.label}
          </button>
        ))}
      </div>

      {/* Integration cards */}
      <div className="mt-4 space-y-4">
        {list.map((it) => (
          <div key={it.id} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <ProviderBadge provider={it.provider} />
                <span className="text-lg font-bold">{it.name}</span>
              </div>
              <div className="flex items-center gap-4">
                <button title="View" className="text-gray-400 hover:text-gray-600">👁</button>
                <button onClick={() => remove(it)} title="Edit / remove" className="text-gray-400 hover:text-gray-600">✏️</button>
                <button onClick={() => toggle(it)} className={`flex items-center gap-1.5 text-xs font-bold ${it.status === 'active' ? 'text-green-600' : 'text-gray-400'}`}>
                  <span className={`h-2 w-2 rounded-full ${it.status === 'active' ? 'bg-green-500' : 'bg-gray-300'}`} />
                  {it.status.toUpperCase()}
                </button>
              </div>
            </div>

            <div className="mt-2 flex items-start justify-between">
              <div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                  ✓ Complete
                </span>
                {(it.page_name || it.form_name) && (
                  <div className="mt-2.5 text-sm text-gray-600">
                    {it.page_name && <>Page: {it.page_name}</>}
                    {it.page_name && it.form_name && <span className="mx-2 text-gray-300">|</span>}
                    {it.form_name && <>Form: {it.form_name}</>}
                  </div>
                )}
                {it.connected_account && (
                  <div className="mt-2 flex items-center gap-2 text-sm font-medium text-blue-700">
                    <ProviderBadge provider={it.provider} size="h-4 w-4 text-[9px]" />
                    {it.connected_account}
                  </div>
                )}
                <div className="mt-2 text-sm text-gray-400">Created {timeAgo(it.created_at)}</div>
              </div>
              <div className="text-right">
                <div className="font-bold">{it.customer?.business_name}</div>
                <div className="text-sm text-gray-400">Created by <span className="font-semibold text-gray-600">{it.created_by}</span></div>
              </div>
            </div>
          </div>
        ))}
        {list.length === 0 && (
          <div className="rounded-2xl border border-gray-100 bg-white py-16 text-center text-gray-400">
            No integrations{tab ? ` for ${byKey[tab]?.label}` : ''} yet. Create one to start syncing leads.
          </div>
        )}
      </div>

      {/* ── Create modal ── */}
      {view && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onClick={() => setView(null)}>
          <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto scroll-thin rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>

            {view === 'providers' && (
              <>
                <h3 className="text-xl font-bold">Create a new integration</h3>
                <p className="mt-1 text-sm text-gray-500">Pick a provider to start connecting your leads.</p>
                <div className="mt-5">
                  <span className="mb-1.5 block font-bold">Select Org:</span>
                  <select value={orgId} onChange={(e) => setOrgId(e.target.value)} className={inputClass}>
                    {orgs.map((o) => (
                      <option key={o.id} value={o.id}>{o.number}, {o.name}</option>
                    ))}
                  </select>
                </div>
                <div className="mt-5">
                  <span className="mb-2.5 block font-bold">Select Provider</span>
                  <div className="flex flex-wrap gap-3">
                    {PROVIDERS.map((p) => (
                      <button
                        key={p.key}
                        onClick={() => pickProvider(p)}
                        className="flex items-center gap-2.5 rounded-full border border-gray-200 px-4 py-2 text-sm font-semibold transition hover:border-blue-400 hover:bg-blue-50"
                      >
                        <ProviderBadge provider={p.key} size="h-6 w-6 text-[11px]" />
                        {p.label}
                      </button>
                    ))}
                  </div>
                </div>
              </>
            )}

            {(view === 'fb-1' || view === 'fb-2') && (
              <>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <button onClick={() => setView(view === 'fb-2' ? 'fb-1' : 'providers')} className="text-xl text-gray-500 hover:text-gray-800">←</button>
                    <h3 className="text-xl font-bold">Facebook</h3>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-blue-700">{fb?.account?.name || '…'}</span>
                    <button
                      onClick={() => alert('Reconnecting requires the Meta App (App ID + Secret) — on the client checklist.')}
                      className="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                      Reconnect
                    </button>
                  </div>
                </div>

                {/* Step indicator */}
                <div className="mt-5 flex items-center gap-3 text-sm">
                  <span className={`grid h-6 w-6 place-items-center rounded-full text-xs font-bold ${view === 'fb-1' ? 'bg-blue-600 text-white' : 'bg-blue-600 text-white'}`}>
                    {view === 'fb-2' ? '✓' : '1'}
                  </span>
                  <span className={view === 'fb-1' ? 'font-bold' : 'text-gray-500'}>Enter name</span>
                  <span className="h-px w-10 bg-gray-200" />
                  <span className={`grid h-6 w-6 place-items-center rounded-full text-xs font-bold ${view === 'fb-2' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-400'}`}>2</span>
                  <span className={view === 'fb-2' ? 'font-bold' : 'text-gray-400'}>Select page and form</span>
                </div>

                {view === 'fb-1' && (
                  <div className="mt-6">
                    <span className="mb-1.5 block text-sm font-semibold text-gray-600">Name</span>
                    <input
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className={`${inputClass} bg-gray-50`}
                      placeholder="e.g. Wedding leads"
                      autoFocus
                    />
                    <button
                      onClick={openFbStep2}
                      disabled={!name.trim()}
                      className="mt-4 w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                      Next
                    </button>
                  </div>
                )}

                {view === 'fb-2' && (
                  <div className="mt-6">
                    <span className="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Select page</span>
                    <div className="flex flex-wrap gap-3">
                      {fb?.pages?.map((p) => (
                        <button
                          key={p.id}
                          onClick={() => selectPage(p.id)}
                          className={`flex min-w-56 items-center gap-3 rounded-xl border-2 p-3.5 text-left transition ${
                            pageId === p.id ? 'border-blue-400 bg-blue-50/60' : 'border-gray-200 hover:border-gray-300'
                          }`}
                        >
                          <span className="grid h-9 w-9 place-items-center rounded-full font-bold text-white" style={{ background: p.color }}>
                            {p.initial}
                          </span>
                          <span className={`font-semibold ${pageId === p.id ? 'text-blue-700' : ''}`}>{p.name}</span>
                        </button>
                      ))}
                    </div>

                    <span className="mb-2 mt-6 block text-xs font-bold uppercase tracking-wide text-gray-500">Select form</span>
                    <div className="max-h-72 overflow-y-auto scroll-thin rounded-xl border border-gray-100">
                      <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-blue-50/60">
                          <tr className="text-left text-[13px] font-bold text-gray-700">
                            <th className="px-4 py-3">Name</th>
                            <th className="px-4 py-3 text-right">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          {forms.map((f) => (
                            <tr key={f.id} className="border-t border-gray-50">
                              <td className="px-4 py-3.5 font-medium">{f.name}</td>
                              <td className="px-4 py-3.5 text-right">
                                <button
                                  onClick={() => createIntegration(f.name)}
                                  disabled={busy}
                                  className="rounded-lg bg-blue-600 px-5 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                  Select
                                </button>
                              </td>
                            </tr>
                          ))}
                          {forms.length === 0 && (
                            <tr><td colSpan={2} className="px-4 py-8 text-center text-gray-400">Loading forms from the connected account…</td></tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </>
            )}

            {view === 'generic' && provider && (
              <>
                <div className="flex items-center gap-3">
                  <button onClick={() => setView('providers')} className="text-xl text-gray-500 hover:text-gray-800">←</button>
                  <ProviderBadge provider={provider.key} size="h-8 w-8 text-sm" />
                  <h3 className="text-xl font-bold">{provider.label}</h3>
                </div>
                <div className="mt-6">
                  <span className="mb-1.5 block text-sm font-semibold text-gray-600">Name</span>
                  <input value={name} onChange={(e) => setName(e.target.value)} className={`${inputClass} bg-gray-50`} placeholder="Integration name" autoFocus />
                  {provider.key === 'webhook' && (
                    <p className="mt-3 rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-600">
                      POST http://localhost:8000/api/telephony/webhook/leads — send your leads here after creating.
                    </p>
                  )}
                  <button
                    onClick={() => createIntegration()}
                    disabled={!name.trim() || busy}
                    className="mt-4 w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                  >
                    {busy ? 'Creating…' : 'Create integration'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
