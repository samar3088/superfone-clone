import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'
import { Badge, Pagination, inputClass, duration } from '../components/ui'

export default function CallLogs() {
  const [page, setPage] = useState(null)
  const [search, setSearch] = useState('')
  const [direction, setDirection] = useState('')
  const [status, setStatus] = useState('')
  const [pageNum, setPageNum] = useState(1)

  const load = useCallback(() => {
    client
      .get('/call-logs', { params: { search, direction, status, page: pageNum } })
      .then((res) => setPage(res.data))
  }, [search, direction, status, pageNum])

  useEffect(() => {
    load()
  }, [load])

  const fmt = (iso) =>
    new Date(iso).toLocaleString('en-IN', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })

  return (
    <div>
      <div className="mb-5 flex flex-wrap gap-3">
        <input
          value={search}
          onChange={(e) => { setPageNum(1); setSearch(e.target.value) }}
          placeholder="Search caller or number…"
          className={`${inputClass} w-64`}
        />
        <select value={direction} onChange={(e) => { setPageNum(1); setDirection(e.target.value) }} className={`${inputClass} w-36`}>
          <option value="">All directions</option>
          <option value="inbound">Inbound</option>
          <option value="outbound">Outbound</option>
          <option value="missed">Missed</option>
        </select>
        <select value={status} onChange={(e) => { setPageNum(1); setStatus(e.target.value) }} className={`${inputClass} w-36`}>
          <option value="">All statuses</option>
          <option value="completed">Completed</option>
          <option value="missed">Missed</option>
          <option value="rejected">Rejected</option>
          <option value="voicemail">Voicemail</option>
        </select>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Caller</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Agent</th>
                <th className="px-5 py-3">Virtual Number</th>
                <th className="px-5 py-3">Direction</th>
                <th className="px-5 py-3">Duration</th>
                <th className="px-5 py-3">Recording</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3">When</th>
              </tr>
            </thead>
            <tbody>
              {page?.data?.map((call) => (
                <tr key={call.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3 font-medium">{call.caller}</td>
                  <td className="px-5 py-3 text-gray-500">{call.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{call.agent?.name || '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{call.virtual_number}</td>
                  <td className="px-5 py-3"><Badge value={call.direction} /></td>
                  <td className="px-5 py-3 text-gray-500">{duration(call.duration_sec)}</td>
                  <td className="px-5 py-3">
                    {call.recording_url ? (
                      <a
                        href={call.recording_url}
                        target="_blank"
                        rel="noreferrer"
                        title="Play recording"
                        className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-brand hover:bg-indigo-100"
                      >
                        ▶ Play
                      </a>
                    ) : (
                      <span className="text-gray-300">—</span>
                    )}
                  </td>
                  <td className="px-5 py-3"><Badge value={call.status} /></td>
                  <td className="px-5 py-3 text-gray-400">{fmt(call.called_at)}</td>
                </tr>
              ))}
              {page?.data?.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-5 py-12 text-center text-gray-400">No call logs found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <Pagination meta={page} onPage={setPageNum} />
      </div>
    </div>
  )
}
