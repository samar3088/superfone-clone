import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass, duration } from '../components/ui'

const GENRE_ICON = { default: '🎵', corporate: '🏢', festive: '🎉', custom: '🎙️' }
const EMPTY = { customer_id: '', name: '', genre: 'default', duration_sec: 30, status: 'inactive' }

export default function CallerTunes() {
  const [page, setPage] = useState(null)
  const [customers, setCustomers] = useState([])
  const [search, setSearch] = useState('')
  const [genre, setGenre] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/caller-tunes', { params: { search, genre, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, genre, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    client.get('/customers', { params: { page: 1 } }).then((res) => setCustomers(res.data.data))
  }, [])

  const openCreate = () => {
    setEditing('new')
    setForm({ ...EMPTY, customer_id: customers[0]?.id || '' })
    setErrors({})
  }

  const openEdit = (t) => {
    setEditing(t.id)
    setForm({
      customer_id: t.customer_id,
      name: t.name,
      genre: t.genre,
      duration_sec: t.duration_sec,
      status: t.status,
    })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/caller-tunes', form)
      } else {
        await client.put(`/caller-tunes/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const activate = async (t) => {
    await client.patch(`/caller-tunes/${t.id}/activate`)
    load()
  }

  const remove = async (t) => {
    if (!confirm(`Delete caller tune "${t.name}"?`)) return
    await client.delete(`/caller-tunes/${t.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search tunes…"
            className={`${inputClass} w-52`}
          />
          <select value={genre} onChange={(e) => { setPageNum(1); setGenre(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All genres</option>
            <option value="default">Default</option>
            <option value="corporate">Corporate</option>
            <option value="festive">Festive</option>
            <option value="custom">Custom</option>
          </select>
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New Tune</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {page?.data?.map((t) => (
          <div key={t.id} className="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <span className="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-indigo-50 text-2xl">
              {GENRE_ICON[t.genre]}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="truncate font-bold">{t.name}</span>
                {t.status === 'active' && <Badge value="active" />}
              </div>
              <div className="text-xs capitalize text-gray-400">{t.genre} · {duration(t.duration_sec)} · {t.customer?.business_name}</div>
              <div className="mt-3 flex gap-2">
                {t.status !== 'active' && (
                  <Button variant="primary" className="px-3 py-1.5" onClick={() => activate(t)}>▶ Set active</Button>
                )}
                <Button variant="light" className="px-3 py-1.5" onClick={() => openEdit(t)}>Edit</Button>
                <Button variant="danger" className="px-3 py-1.5" onClick={() => remove(t)}>Delete</Button>
              </div>
            </div>
          </div>
        ))}
        {page?.data?.length === 0 && (
          <div className="col-span-full rounded-2xl border border-gray-100 bg-white py-12 text-center text-gray-400">
            No caller tunes found.
          </div>
        )}
      </div>

      <Pagination meta={page} onPage={setPageNum} />

      {editing && (
        <Modal title={editing === 'new' ? 'New Caller Tune' : 'Edit Caller Tune'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <Field label="Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
            </Field>
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
              <Field label="Genre">
                <select className={inputClass} value={form.genre} onChange={(e) => setForm({ ...form, genre: e.target.value })}>
                  <option value="default">Default</option>
                  <option value="corporate">Corporate</option>
                  <option value="festive">Festive</option>
                  <option value="custom">Custom</option>
                </select>
              </Field>
              <Field label="Duration (seconds)">
                <input type="number" className={inputClass} value={form.duration_sec} onChange={(e) => setForm({ ...form, duration_sec: e.target.value })} />
                {errors.duration_sec && <p className="mt-1 text-xs text-red-600">{errors.duration_sec[0]}</p>}
              </Field>
              <Field label="Status">
                <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="inactive">Inactive</option>
                  <option value="active">Active</option>
                </select>
              </Field>
            </div>
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
