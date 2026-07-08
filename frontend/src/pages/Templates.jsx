import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Button, Field, Modal, Pagination, inputClass } from '../components/ui'

const CAT_BADGE = {
  marketing: 'bg-purple-100 text-purple-700',
  utility: 'bg-blue-100 text-blue-700',
  authentication: 'bg-amber-100 text-amber-700',
}

const EMPTY = { name: '', category: 'utility', body: '', status: 'pending' }

export default function Templates() {
  const [page, setPage] = useState(null)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    client
      .get('/templates', { params: { search, category, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, category, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  const openCreate = () => {
    setEditing('new')
    setForm(EMPTY)
    setErrors({})
  }

  const openEdit = (t) => {
    setEditing(t.id)
    setForm({ name: t.name, category: t.category, body: t.body, status: t.status })
    setErrors({})
  }

  const save = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      if (editing === 'new') {
        await client.post('/templates', form)
      } else {
        await client.put(`/templates/${editing}`, form)
      }
      setEditing(null)
      load()
    } catch (err) {
      setErrors(err.response?.data?.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const remove = async (t) => {
    if (!confirm(`Delete template "${t.name}"?`)) return
    await client.delete(`/templates/${t.id}`)
    load()
  }

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            value={search}
            onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
            placeholder="Search templates…"
            className={`${inputClass} w-56`}
          />
          <select value={category} onChange={(e) => { setPageNum(1); setCategory(e.target.value) }} className={`${inputClass} w-40`}>
            <option value="">All categories</option>
            <option value="marketing">Marketing</option>
            <option value="utility">Utility</option>
            <option value="authentication">Authentication</option>
          </select>
          <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
            <option value="">All statuses</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <Button onClick={openCreate}>+ New Template</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {page?.data?.map((t) => (
          <div key={t.id} className="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="mb-2 flex items-start justify-between gap-2">
              <h3 className="font-bold">{t.name}</h3>
              <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${CAT_BADGE[t.category]}`}>
                {t.category}
              </span>
            </div>
            <p className="flex-1 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm text-gray-600">{t.body}</p>
            <div className="mt-3 flex items-center justify-between">
              <Badge value={t.status} />
              <span className="text-xs text-gray-400">{t.campaigns_count} campaign{t.campaigns_count === 1 ? '' : 's'}</span>
            </div>
            <div className="mt-4 flex gap-2">
              <Button variant="light" className="flex-1 justify-center" onClick={() => openEdit(t)}>Edit</Button>
              <Button variant="danger" className="px-3" onClick={() => remove(t)}>Delete</Button>
            </div>
          </div>
        ))}
        {page?.data?.length === 0 && (
          <div className="col-span-full rounded-2xl border border-gray-100 bg-white py-12 text-center text-gray-400">
            No templates found.
          </div>
        )}
      </div>

      <div className="mt-4 overflow-hidden rounded-2xl">
        <Pagination meta={page} onPage={setPageNum} />
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'New Template' : 'Edit Template'} onClose={() => setEditing(null)}>
          <form onSubmit={save}>
            <div className="grid grid-cols-2 gap-x-4">
              <Field label="Name">
                <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
              </Field>
              <Field label="Category">
                <select className={inputClass} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                  <option value="utility">Utility</option>
                  <option value="marketing">Marketing</option>
                  <option value="authentication">Authentication</option>
                </select>
              </Field>
            </div>
            <Field label="Body (use {{1}}, {{2}} for variables)">
              <textarea
                className={`${inputClass} h-28 resize-none`}
                value={form.body}
                onChange={(e) => setForm({ ...form, body: e.target.value })}
              />
              {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body[0]}</p>}
            </Field>
            <Field label="Status">
              <select className={inputClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
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
