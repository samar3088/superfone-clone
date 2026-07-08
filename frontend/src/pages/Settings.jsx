import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Toggle, inputClass } from '../components/ui'

const TABS = ['Business Management', 'Call Settings', 'CRM Settings', 'Custom Fields', 'Priority Order']

const TAG_COLORS = [
  '#4338ca', '#f87171', '#10b981', '#60a5fa', '#38bdf8', '#84cc16', '#a78bfa', '#eab308',
  '#e879f9', '#15803d', '#be185d', '#f472b6', '#ef4444', '#f97316', '#111827', '#9ca3af',
]

const TYPE_OPTIONS = ['NONE', 'INITIAL', 'FINAL_POSITIVE', 'FINAL_NEGATIVE']

// Soft chip background from a hex color.
const chip = (color) => ({ background: `${color}22`, color })

export default function Settings() {
  const [tab, setTab] = useState(TABS[0])
  const [orgs, setOrgs] = useState([])
  const [orgId, setOrgId] = useState('')

  useEffect(() => {
    client.get('/orgs').then((res) => {
      setOrgs(res.data)
      setOrgId(String(res.data[0]?.id || ''))
    })
  }, [])

  if (!orgId) return <div className="text-gray-400">Loading…</div>

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex overflow-x-auto rounded-xl border border-gray-100 bg-white">
          {TABS.map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`whitespace-nowrap border-b-2 px-5 py-3.5 text-[15px] font-semibold transition ${
                tab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              {t}
            </button>
          ))}
        </div>
        <select value={orgId} onChange={(e) => setOrgId(e.target.value)} className={`${inputClass} w-72`}>
          {orgs.map((o) => (
            <option key={o.id} value={o.id}>{o.number}, {o.name}</option>
          ))}
        </select>
      </div>

      <div className="mt-5">
        {tab === 'Business Management' && <TagsTab orgId={orgId} />}
        {tab === 'Call Settings' && <CallSettingsTab orgId={orgId} />}
        {tab === 'CRM Settings' && <CrmTab orgId={orgId} />}
        {tab === 'Custom Fields' && <CustomFieldsTab orgId={orgId} />}
        {tab === 'Priority Order' && <PriorityTab orgId={orgId} />}
      </div>
    </div>
  )
}

/* ── Business Management → Tags ─────────────────────────── */
function TagsTab({ orgId }) {
  const [tags, setTags] = useState({ visible: [], hidden: [] })
  const [creating, setCreating] = useState(false)
  const [name, setName] = useState('')
  const [color, setColor] = useState(TAG_COLORS[0])
  const [deleting, setDeleting] = useState(null)

  const load = useCallback(() => {
    client.get('/settings/tags', { params: { customer_id: orgId } }).then((res) => setTags(res.data))
  }, [orgId])

  useEffect(() => { load() }, [load])

  const add = async () => {
    if (!name.trim()) return
    await client.post('/settings/tags', { customer_id: orgId, name: name.trim(), color })
    setCreating(false); setName(''); setColor(TAG_COLORS[0])
    load()
  }

  const toggleHidden = async (t) => {
    await client.patch(`/settings/tags/${t.id}`, { hidden: !t.hidden })
    load()
  }

  const confirmDelete = async () => {
    await client.delete(`/settings/tags/${deleting.id}`)
    setDeleting(null)
    load()
  }

  const TagTable = ({ list, hidden }) => (
    <table className="w-full text-sm">
      <thead>
        <tr className="text-left text-xs font-bold uppercase tracking-wide text-gray-400">
          <th className="px-5 py-3">Tag ID</th>
          <th className="px-5 py-3">Tag</th>
          <th className="px-5 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        {list.map((t) => (
          <tr key={t.id} className="border-t border-gray-50">
            <td className="px-5 py-3.5 text-gray-600">{2197300 + t.id}</td>
            <td className="px-5 py-3.5">
              <span className="rounded-md px-2.5 py-1 text-sm font-medium" style={chip(t.color)}>{t.name}</span>
            </td>
            <td className="px-5 py-3.5">
              <div className="flex justify-end gap-4">
                <button onClick={() => toggleHidden(t)} title={hidden ? 'Unhide' : 'Hide'} className="text-gray-400 hover:text-gray-600">
                  {hidden ? '👁' : '🙈'}
                </button>
                <button title="Edit" className="text-gray-400 hover:text-gray-600">✏️</button>
                <button onClick={() => setDeleting(t)} title="Delete" className="text-red-300 hover:text-red-500">🗑️</button>
              </div>
            </td>
          </tr>
        ))}
        {list.length === 0 && (
          <tr><td colSpan={3} className="px-5 py-8 text-center text-gray-300">No tags.</td></tr>
        )}
      </tbody>
    </table>
  )

  return (
    <>
      <div className="mb-4 inline-flex rounded-lg border border-gray-100 bg-white px-4 py-2 text-sm font-semibold text-blue-600">Tags</div>

      <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div className="flex items-start justify-between">
          <div>
            <h3 className="font-bold tracking-wide">VISIBLE TAGS</h3>
            <p className="text-sm text-gray-400">are available for selection when tags are added to contacts</p>
          </div>
          <button onClick={() => setCreating(true)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ Create</button>
        </div>
        <div className="mt-3"><TagTable list={tags.visible} hidden={false} /></div>
      </div>

      <div className="mt-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 className="font-bold tracking-wide">HIDDEN TAGS</h3>
        <p className="text-sm text-gray-400">will not show during selection. Contacts with tags already added will continue to display them</p>
        <div className="mt-3"><TagTable list={tags.hidden} hidden={true} /></div>
      </div>

      {creating && (
        <Modal onClose={() => setCreating(false)}>
          <h3 className="text-xl font-bold">Create new tag</h3>
          <div className="mt-4">
            <span className="mb-1.5 block font-semibold">Tag name</span>
            <input className={`${inputClass} bg-gray-50`} placeholder="Enter Tag name" value={name} onChange={(e) => setName(e.target.value)} autoFocus />
          </div>
          <div className="mt-5 grid grid-cols-8 gap-3">
            {TAG_COLORS.map((c) => (
              <button
                key={c}
                onClick={() => setColor(c)}
                className={`mx-auto h-9 w-9 rounded-full transition ${color === c ? 'ring-2 ring-gray-700 ring-offset-2' : ''}`}
                style={{ background: c }}
              />
            ))}
          </div>
          <div className="mt-5 flex items-center justify-between rounded-xl border border-gray-100 p-4">
            <span className="font-bold">Preview:</span>
            <span className="rounded-md px-3 py-1 font-medium" style={chip(color)}>{name || 'Tag name'}</span>
          </div>
          <div className="mt-5 flex justify-end gap-3">
            <button onClick={() => setCreating(false)} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Cancel</button>
            <button onClick={add} className="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Add</button>
          </div>
        </Modal>
      )}

      {deleting && (
        <Modal onClose={() => setDeleting(null)}>
          <h3 className="text-xl font-bold">Delete Tag "{deleting.name.replace(/^\p{Emoji}+\s*/u, '')}"</h3>
          <p className="mt-2 text-gray-500">Are you sure?</p>
          <div className="mt-5 flex justify-end gap-3">
            <button onClick={() => setDeleting(null)} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Cancel</button>
            <button onClick={confirmDelete} className="rounded-lg bg-red-500 px-5 py-2 font-semibold text-white hover:bg-red-600">Delete</button>
          </div>
        </Modal>
      )}
    </>
  )
}

/* ── Call Settings ──────────────────────────────────────── */
function CallSettingsTab({ orgId }) {
  const [sub, setSub] = useState('Ringing Pattern')
  const [data, setData] = useState(null)
  const [members, setMembers] = useState([])
  const [hours, setHours] = useState('open')
  const [groups, setGroups] = useState([[]])
  const [fallbackOpen, setFallbackOpen] = useState(true)

  const load = useCallback(() => {
    client.get('/settings/call', { params: { customer_id: orgId } }).then((res) => {
      setData(res.data)
      const ro = res.data.ring_orders[hours] || {}
      const gs = Object.keys(ro).sort().map((k) => ro[k].map((r) => r.team_member_id))
      setGroups(gs.length ? gs : [[]])
    })
    client.get('/team-members', { params: { customer_id: orgId } }).then((res) => setMembers(res.data.data))
  }, [orgId, hours])

  useEffect(() => { load() }, [load])

  const patch = async (key, value) => {
    await client.patch('/settings/call', { customer_id: orgId, [key]: value })
    load()
  }

  const saveRingOrder = async () => {
    await client.post('/settings/ring-order', {
      customer_id: orgId,
      hours,
      groups: groups.filter((g) => g.length),
    })
    load()
  }

  const memberName = (id) => members.find((m) => m.id === id)?.name || `#${id}`
  const s = data?.settings

  return (
    <>
      <div className="mb-4 flex gap-2">
        {['Recording', 'Ringing Pattern'].map((x) => (
          <button key={x} onClick={() => setSub(x)} className={`rounded-lg border px-4 py-2 text-sm font-semibold ${sub === x ? 'border-blue-200 bg-white text-blue-600' : 'border-gray-100 bg-white text-gray-500'}`}>
            {x}
          </button>
        ))}
      </div>

      {sub === 'Recording' && s && (
        <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-bold">Call Recording</h3>
              <p className="text-gray-500">Automatically record every incoming and outgoing call</p>
            </div>
            <Toggle checked={s.recording_enabled} onChange={() => patch('recording_enabled', !s.recording_enabled)} />
          </div>
        </div>
      )}

      {sub === 'Ringing Pattern' && s && (
        <div className="space-y-5">
          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold">Sticky agent</h3>
                <p className="text-gray-500">Calls from customer always ring assigned agent first</p>
              </div>
              <Toggle checked={s.sticky_agent} onChange={() => patch('sticky_agent', !s.sticky_agent)} />
            </div>
            {s.sticky_agent && (
              <div className="mt-4">
                <button onClick={() => setFallbackOpen(!fallbackOpen)} className="flex items-center gap-2 font-semibold text-gray-700">
                  ✔️ In case sticky agent does not pick up <span className={`transition ${fallbackOpen ? 'rotate-180' : ''}`}>⌄</span>
                </button>
                {fallbackOpen && (
                  <div className="mt-2 flex gap-4 pl-7">
                    {[['ringing_order', 'Ring as per ringing order'], ['end_call', 'End the call']].map(([v, label]) => (
                      <button
                        key={v}
                        onClick={() => patch('sticky_fallback', v)}
                        className={`text-sm font-medium ${s.sticky_fallback === v ? 'text-blue-600 underline underline-offset-4' : 'text-gray-400 hover:text-gray-600'}`}
                      >
                        {label}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold">IVR</h3>
                <p className="text-gray-500">If you turn on IVR, ringing order turns off. Click Help to request changes to your IVR</p>
              </div>
              <Toggle checked={s.ivr_enabled} onChange={() => patch('ivr_enabled', !s.ivr_enabled)} />
            </div>
          </div>

          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold">Configure Ringing Order</h3>
                <p className="text-gray-500">Arrange ringing order of team members for open and closed hours</p>
              </div>
              <button onClick={saveRingOrder} className="font-bold text-blue-600 hover:underline">SAVE ›</button>
            </div>

            <div className="mt-4 flex justify-center gap-8 border-b border-gray-100 pb-2">
              {['open', 'closed'].map((h) => (
                <button key={h} onClick={() => setHours(h)} className={`pb-1 text-lg font-semibold capitalize ${hours === h ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-400'}`}>
                  {h} Hours
                </button>
              ))}
            </div>

            <div className="mt-4 space-y-4">
              {groups.map((group, gi) => (
                <div key={gi} className="flex items-start gap-4">
                  <span className="mt-1 grid h-8 w-8 flex-shrink-0 place-items-center rounded-full bg-gray-100 font-bold text-gray-500">{gi + 1}</span>
                  <div className="flex-1">
                    <div className="mb-1 text-sm font-semibold text-gray-500">{gi === 0 ? 'Ring first' : `Ring ${gi === 1 ? 'second' : 'next'}`}</div>
                    <div className="flex flex-wrap items-center gap-2">
                      {group.map((id) => (
                        <span key={id} className="flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700">
                          {memberName(id)}
                          <button onClick={() => setGroups(groups.map((g, i) => (i === gi ? g.filter((x) => x !== id) : g)))} className="text-blue-400 hover:text-blue-700">×</button>
                        </span>
                      ))}
                      <select
                        value=""
                        onChange={(e) => {
                          const id = Number(e.target.value)
                          if (id) setGroups(groups.map((g, i) => (i === gi ? [...g, id] : g)))
                        }}
                        className="rounded-lg border border-dashed border-gray-300 px-3 py-1.5 text-sm text-gray-500"
                      >
                        <option value="">+ Add member</option>
                        {members.filter((m) => !groups.flat().includes(m.id)).map((m) => (
                          <option key={m.id} value={m.id}>{m.name}</option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>
              ))}
              <button onClick={() => setGroups([...groups, []])} className="pl-12 text-sm font-semibold text-blue-600 hover:underline">+ Add ring group</button>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 className="text-2xl font-bold">Frequently asked questions</h3>
            {[
              ['What is Ringing Order?', 'It decides who gets the call first, second, and third when someone calls your business number.'],
              ['Can I ring multiple team members in parallel or a few people first & others later?', 'Yes, simply drag and add multiple members in the same ringing group and calls will ring in parallel to those members. Add others below that as you want.'],
              ["Open Hours vs Closed Hours—what's the difference?", 'You can set different ringing orders for working hours (Open Hours) and after-hours (Closed Hours).'],
              ['How do I set Business Hours for Open & Closed Hours?', 'Tap Manage in the Ringing Order section and set your Business Hours. Superfone will automatically use the Open Hours or Closed Hours ringing order based on those timings.'],
              ['What is Sticky Agent?', 'If a customer calls again, Superfone tries to ring the assigned team member first and if no answer, it then rings others based on your IVR or Ringing Order Setting.'],
            ].map(([q, a]) => (
              <div key={q} className="mt-4">
                <div className="font-bold">{q}</div>
                <p className="text-gray-600">{a}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </>
  )
}

/* ── CRM Settings ───────────────────────────────────────── */
function CrmTab({ orgId }) {
  const [sub, setSub] = useState('Lead Stage')
  const [stages, setStages] = useState([])
  const [groups, setGroups] = useState([])
  const [modal, setModal] = useState(null) // 'custom' | 'template-pick' | 'template-preview' | 'group'
  const [rows, setRows] = useState([{ name: '', type: 'NONE' }])
  const [categories, setCategories] = useState([])
  const [category, setCategory] = useState('')
  const [preview, setPreview] = useState([])

  const load = useCallback(() => {
    client.get('/settings/lead-stages', { params: { customer_id: orgId } }).then((res) => setStages(res.data))
    client.get('/settings/lead-groups', { params: { customer_id: orgId } }).then((res) => setGroups(res.data))
  }, [orgId])

  useEffect(() => { load() }, [load])

  const openTemplates = () => {
    setModal('template-pick')
    client.get('/settings/lead-stage-templates').then((res) => setCategories(res.data))
  }

  const loadPreview = () => {
    client.get('/settings/lead-stage-templates', { params: { category } }).then((res) => {
      setPreview(res.data)
      setModal('template-preview')
    })
  }

  const saveStages = async (stageRows) => {
    const valid = stageRows.filter((r) => r.name.trim())
    if (!valid.length) return
    await client.post('/settings/lead-stages', { customer_id: orgId, stages: valid })
    setModal(null)
    setRows([{ name: '', type: 'NONE' }])
    load()
  }

  const saveGroups = async () => {
    const valid = rows.filter((r) => r.name.trim()).map((r) => ({ name: r.name, type: r.type === 'NONE' ? 'DEFAULT' : r.type }))
    if (!valid.length) return
    await client.post('/settings/lead-groups', { customer_id: orgId, groups: valid })
    setModal(null)
    setRows([{ name: '', type: 'NONE' }])
    load()
  }

  const toggleStage = async (st) => {
    await client.patch(`/settings/lead-stages/${st.id}/toggle`)
    load()
  }

  const toggleGroup = async (g) => {
    await client.patch(`/settings/lead-groups/${g.id}/toggle`)
    load()
  }

  return (
    <>
      <div className="mb-4 flex gap-2">
        {['Lead Stage', 'Lead Group'].map((x) => (
          <button key={x} onClick={() => setSub(x)} className={`rounded-lg border px-4 py-2 text-sm font-semibold ${sub === x ? 'border-blue-200 bg-white text-blue-600' : 'border-gray-100 bg-white text-gray-500'}`}>
            {x}
          </button>
        ))}
      </div>

      {sub === 'Lead Stage' && (
        <>
          <div className="mb-4 flex justify-end gap-3">
            <button onClick={() => { setRows([{ name: '', type: 'NONE' }]); setModal('custom') }} className="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">+ Add custom lead stage</button>
            <button onClick={openTemplates} className="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">+ Add from template</button>
            <button className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Edit Lead Stages</button>
          </div>
          <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="bg-blue-50/40">
                <tr className="text-left text-[13px] font-bold text-gray-700">
                  <th className="px-5 py-3.5">Sequence</th>
                  <th className="px-5 py-3.5">ID</th>
                  <th className="px-5 py-3.5">Lead Stage</th>
                  <th className="px-5 py-3.5">Type</th>
                  <th className="px-5 py-3.5">Contacts Attached<br /><span className="font-normal">(Past 6 months)</span></th>
                  <th className="px-5 py-3.5">Status</th>
                </tr>
              </thead>
              <tbody>
                {stages.map((st, i) => (
                  <tr key={st.id} className="border-t border-gray-50">
                    <td className="px-5 py-4 text-gray-500">{String(i + 1).padStart(2, '0')}</td>
                    <td className="px-5 py-4 text-gray-500">{101384 + st.id}</td>
                    <td className="px-5 py-4 font-medium">{st.name}</td>
                    <td className="px-5 py-4 text-gray-600">{st.type === 'NONE' ? 'NONE' : st.type.replace('_', ' ')}</td>
                    <td className="px-5 py-4">{st.contacts_attached}</td>
                    <td className="px-5 py-4"><Toggle checked={st.active} onChange={() => toggleStage(st)} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {sub === 'Lead Group' && (
        <>
          <div className="mb-4 flex justify-end gap-3">
            <button onClick={() => { setRows([{ name: '', type: 'DEFAULT' }]); setModal('group') }} className="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">+ Add custom lead group</button>
            <button className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Edit Lead Groups</button>
          </div>
          <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="bg-blue-50/40">
                <tr className="text-left text-[13px] font-bold text-gray-700">
                  <th className="px-5 py-3.5">ID</th>
                  <th className="px-5 py-3.5">Lead Group</th>
                  <th className="px-5 py-3.5">Type ⓘ</th>
                  <th className="px-5 py-3.5">Status</th>
                </tr>
              </thead>
              <tbody>
                {groups.map((g) => (
                  <tr key={g.id} className="border-t border-gray-50">
                    <td className="px-5 py-4 text-gray-500">{18540 + g.id}</td>
                    <td className="px-5 py-4 font-medium">{g.name}</td>
                    <td className="px-5 py-4 text-gray-600">{g.type}</td>
                    <td className="px-5 py-4"><Toggle checked={g.active} onChange={() => toggleGroup(g)} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {(modal === 'custom' || modal === 'group') && (
        <Modal onClose={() => setModal(null)}>
          <h3 className="text-xl font-bold">{modal === 'custom' ? 'Custom lead stage' : 'Custom lead group'}</h3>
          <p className="mt-1 text-gray-500">Add one or more lead {modal === 'custom' ? 'stages' : 'groups'} and choose a type for each.</p>
          <div className="mt-4 space-y-3">
            {rows.map((r, i) => (
              <div key={i} className="flex gap-3 rounded-xl border border-gray-100 p-4">
                <div className="flex-1">
                  <span className="mb-1 block text-sm font-semibold text-gray-600">Lead {modal === 'custom' ? 'stage' : 'group'}</span>
                  <input
                    className={`${inputClass} bg-gray-50`}
                    placeholder={`Add lead ${modal === 'custom' ? 'stage' : 'group'}`}
                    value={r.name}
                    onChange={(e) => setRows(rows.map((x, xi) => (xi === i ? { ...x, name: e.target.value } : x)))}
                  />
                </div>
                <div className="w-44">
                  <span className="mb-1 block text-sm font-semibold text-gray-600">Type</span>
                  <select
                    className={inputClass}
                    value={r.type}
                    onChange={(e) => setRows(rows.map((x, xi) => (xi === i ? { ...x, type: e.target.value } : x)))}
                  >
                    {(modal === 'custom' ? TYPE_OPTIONS : ['DEFAULT', 'CUSTOM']).map((t) => (
                      <option key={t} value={t}>{t}</option>
                    ))}
                  </select>
                </div>
              </div>
            ))}
          </div>
          <button onClick={() => setRows([...rows, { name: '', type: modal === 'custom' ? 'NONE' : 'DEFAULT' }])} className="mt-3 text-sm font-semibold text-blue-600 hover:underline">
            + Add another lead {modal === 'custom' ? 'stage' : 'group'}
          </button>
          <div className="mt-5 flex justify-end gap-3">
            <button onClick={() => setModal(null)} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Cancel</button>
            <button onClick={() => (modal === 'custom' ? saveStages(rows) : saveGroups())} className="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Add</button>
          </div>
        </Modal>
      )}

      {modal === 'template-pick' && (
        <Modal onClose={() => setModal(null)}>
          <h3 className="text-xl font-bold">Lead stage templates</h3>
          <h2 className="mt-3 text-2xl font-bold">Pick a template</h2>
          <p className="text-gray-500">Select a suitable category for you business to view the template</p>
          <div className="mt-5 flex items-center gap-4">
            <span className="font-bold">Pick category</span>
            <select value={category} onChange={(e) => setCategory(e.target.value)} className={`${inputClass} w-64`}>
              <option value="">Select Type</option>
              {categories.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </div>
          <div className="mt-5 flex gap-3">
            <button onClick={() => setModal(null)} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Back</button>
            <button onClick={loadPreview} disabled={!category} className="rounded-lg bg-blue-500 px-6 py-2 font-semibold text-white hover:bg-blue-600 disabled:opacity-50">Next</button>
          </div>
        </Modal>
      )}

      {modal === 'template-preview' && (
        <Modal wide onClose={() => setModal(null)}>
          <h3 className="text-xl font-bold">Lead stage templates</h3>
          <h2 className="mt-3 text-2xl font-bold">Template Preview</h2>
          <p className="text-gray-500">{category}</p>
          <div className="mt-4 max-h-96 overflow-y-auto scroll-thin rounded-xl border border-gray-100">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-blue-50/60">
                <tr className="text-left text-[13px] font-bold text-gray-700">
                  <th className="px-4 py-3">Sequence</th>
                  <th className="px-4 py-3">Title</th>
                  <th className="px-4 py-3">Type</th>
                </tr>
              </thead>
              <tbody>
                {preview.map((p) => (
                  <tr key={p.sequence} className="border-t border-gray-50">
                    <td className="px-4 py-3.5 text-gray-500">⠿ {p.sequence + 14}</td>
                    <td className="px-4 py-3.5 font-medium">{p.name}</td>
                    <td className="px-4 py-3.5 text-gray-600">{p.type}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="mt-5 flex gap-3">
            <button onClick={() => setModal('template-pick')} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Back</button>
            <button onClick={() => saveStages(preview)} className="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Save</button>
          </div>
        </Modal>
      )}
    </>
  )
}

/* ── Custom Fields ──────────────────────────────────────── */
function CustomFieldsTab({ orgId }) {
  const [fields, setFields] = useState([])
  const [creating, setCreating] = useState(false)
  const [label, setLabel] = useState('')
  const [fieldType, setFieldType] = useState('')

  const load = useCallback(() => {
    client.get('/settings/custom-fields', { params: { customer_id: orgId } }).then((res) => setFields(res.data))
  }, [orgId])

  useEffect(() => { load() }, [load])

  const create = async () => {
    if (!label.trim() || !fieldType) return
    await client.post('/settings/custom-fields', { customer_id: orgId, label: label.trim(), field_type: fieldType })
    setCreating(false); setLabel(''); setFieldType('')
    load()
  }

  const remove = async (f) => {
    await client.delete(`/settings/custom-fields/${f.id}`)
    load()
  }

  return (
    <>
      <div className="mb-4 flex justify-end">
        <button onClick={() => setCreating(true)} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">+ Create Custom Field</button>
      </div>
      {fields.length === 0 ? (
        <div className="rounded-2xl border border-gray-100 bg-white py-20 text-center text-gray-400 shadow-sm">No data available</div>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="bg-blue-50/40">
              <tr className="text-left text-[13px] font-bold text-gray-700">
                <th className="px-5 py-3.5">Label</th>
                <th className="px-5 py-3.5">Field Type</th>
                <th className="px-5 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {fields.map((f) => (
                <tr key={f.id} className="border-t border-gray-50">
                  <td className="px-5 py-4 font-medium">{f.label}</td>
                  <td className="px-5 py-4 capitalize text-gray-600">{f.field_type}</td>
                  <td className="px-5 py-4 text-right">
                    <button onClick={() => remove(f)} className="text-red-300 hover:text-red-500">🗑️</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {creating && (
        <Modal onClose={() => setCreating(false)}>
          <h3 className="text-xl font-bold">Create a Custom Field</h3>
          <div className="mt-4">
            <span className="mb-1.5 block font-semibold">Label</span>
            <input className={`${inputClass} bg-gray-50`} value={label} onChange={(e) => setLabel(e.target.value)} autoFocus />
          </div>
          <div className="mt-4">
            <span className="mb-1.5 block font-semibold">Field Type</span>
            <select className={`${inputClass} w-72`} value={fieldType} onChange={(e) => setFieldType(e.target.value)}>
              <option value="">Select</option>
              {['text', 'number', 'date', 'dropdown', 'checkbox'].map((t) => (
                <option key={t} value={t}>{t[0].toUpperCase() + t.slice(1)}</option>
              ))}
            </select>
          </div>
          <div className="mt-5 flex justify-end gap-3">
            <button onClick={() => setCreating(false)} className="rounded-lg border border-gray-200 px-5 py-2 font-semibold hover:bg-gray-50">Cancel</button>
            <button onClick={create} className="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Create</button>
          </div>
        </Modal>
      )}
    </>
  )
}

/* ── Priority Order ─────────────────────────────────────── */
function PriorityTab({ orgId }) {
  const [data, setData] = useState(null)
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    client.get('/settings/priority', { params: { customer_id: orgId } }).then((res) => setData(res.data))
  }, [orgId])

  const toggle = (section, i) => {
    setData({
      ...data,
      [section]: data[section].map((f, fi) => (fi === i ? { ...f, selected: !f.selected } : f)),
    })
  }

  const save = async () => {
    await client.put('/settings/priority', { customer_id: orgId, ...data })
    setSaved(true)
    setTimeout(() => setSaved(false), 2000)
  }

  if (!data) return <div className="text-gray-400">Loading…</div>

  const Section = ({ title, section }) => (
    <div>
      <h3 className="mb-3 text-xl font-bold">{title}</h3>
      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-[13px] font-bold text-gray-500">
              <th className="w-10 px-4 py-3"></th>
              <th className="px-4 py-3">Select</th>
              <th className="px-4 py-3">Field Name</th>
              <th className="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody>
            {data[section].map((f, i) => (
              <tr key={f.name} className="border-t border-gray-50">
                <td className="px-4 py-4 text-gray-300">⠿</td>
                <td className="px-4 py-4">
                  <input type="checkbox" checked={!!f.selected} onChange={() => toggle(section, i)} className="h-4 w-4 accent-blue-600" />
                </td>
                <td className="px-4 py-4 font-medium">{f.name}</td>
                <td className="px-4 py-4">
                  <span className="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-600">Static</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )

  return (
    <>
      <div className="mb-4 flex justify-end">
        <button onClick={save} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
          {saved ? '✓ Saved' : 'Set Priority Fields'}
        </button>
      </div>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section title="Lead tracking fields" section="lead" />
        <Section title="Additional Fields" section="additional" />
      </div>
    </>
  )
}

/* ── Shared modal shell ─────────────────────────────────── */
function Modal({ children, onClose, wide }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onClick={onClose}>
      <div
        className={`max-h-[85vh] w-full ${wide ? 'max-w-3xl' : 'max-w-xl'} overflow-y-auto scroll-thin rounded-2xl bg-white p-6 shadow-xl`}
        onClick={(e) => e.stopPropagation()}
      >
        {children}
      </div>
    </div>
  )
}
