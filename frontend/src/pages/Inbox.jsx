import { useEffect, useRef, useState, useCallback } from 'react'
import client from '../api/client'
import { inputClass } from '../components/ui'

const TICK = { sent: '✓', delivered: '✓✓', read: '✓✓' }

export default function Inbox() {
  const [list, setList] = useState([])
  const [search, setSearch] = useState('')
  const [activeId, setActiveId] = useState(null)
  const [thread, setThread] = useState(null)
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const endRef = useRef(null)

  const loadList = useCallback(() => {
    client.get('/conversations', { params: { search } }).then((res) => {
      setList(res.data)
      if (!activeId && res.data.length) setActiveId(res.data[0].id)
    })
  }, [search, activeId])

  useEffect(() => {
    loadList()
  }, [loadList])

  const loadThread = useCallback((id) => {
    client.get(`/conversations/${id}`).then((res) => setThread(res.data))
  }, [])

  useEffect(() => {
    if (activeId) loadThread(activeId)
  }, [activeId, loadThread])

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [thread])

  const send = async (e) => {
    e.preventDefault()
    if (!draft.trim() || !activeId) return
    setSending(true)
    try {
      await client.post(`/conversations/${activeId}/reply`, { body: draft })
      setDraft('')
      await loadThread(activeId)
      loadList()
    } finally {
      setSending(false)
    }
  }

  const toggleClose = async () => {
    await client.patch(`/conversations/${activeId}/close`)
    loadThread(activeId)
    loadList()
  }

  const time = (iso) => new Date(iso).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })

  return (
    <div className="flex h-[calc(100vh-140px)] overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      {/* Conversation list */}
      <div className="flex w-80 flex-shrink-0 flex-col border-r border-gray-100">
        <div className="border-b border-gray-100 p-3">
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search chats…"
            className={inputClass}
          />
        </div>
        <div className="flex-1 overflow-y-auto scroll-thin">
          {list.map((c) => (
            <button
              key={c.id}
              onClick={() => setActiveId(c.id)}
              className={`flex w-full items-center gap-3 border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50 ${
                activeId === c.id ? 'bg-indigo-50/60' : ''
              }`}
            >
              <span className="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 font-bold text-white">
                {c.name?.[0]?.toUpperCase()}
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between">
                  <span className="truncate font-semibold">{c.name}</span>
                  {c.last_message_at && (
                    <span className="ml-2 flex-shrink-0 text-[11px] text-gray-400">{time(c.last_message_at)}</span>
                  )}
                </div>
                <div className="flex items-center justify-between">
                  <span className="truncate text-xs text-gray-400">{c.preview || c.phone}</span>
                  {c.unread > 0 && (
                    <span className="ml-2 grid h-5 min-w-5 flex-shrink-0 place-items-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-bold text-white">
                      {c.unread}
                    </span>
                  )}
                </div>
              </div>
            </button>
          ))}
          {list.length === 0 && <div className="p-6 text-center text-sm text-gray-400">No chats.</div>}
        </div>
      </div>

      {/* Thread */}
      <div className="flex min-w-0 flex-1 flex-col">
        {thread ? (
          <>
            <div className="flex items-center justify-between border-b border-gray-100 px-5 py-3">
              <div>
                <div className="font-bold">{thread.conversation.name}</div>
                <div className="text-xs text-gray-400">
                  {thread.conversation.phone} · {thread.conversation.customer?.business_name}
                </div>
              </div>
              <button
                onClick={toggleClose}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${
                  thread.conversation.status === 'open'
                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                }`}
              >
                {thread.conversation.status === 'open' ? 'Close chat' : 'Reopen'}
              </button>
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto scroll-thin bg-[#f5f3ef] p-5">
              {thread.messages.map((m) => (
                <div key={m.id} className={`flex ${m.direction === 'outbound' ? 'justify-end' : 'justify-start'}`}>
                  <div
                    className={`max-w-[70%] rounded-2xl px-3.5 py-2 text-sm shadow-sm ${
                      m.direction === 'outbound'
                        ? 'rounded-br-sm bg-[#d9fdd3] text-gray-800'
                        : 'rounded-bl-sm bg-white text-gray-800'
                    }`}
                  >
                    <div>{m.body}</div>
                    <div className="mt-1 flex items-center justify-end gap-1 text-[10px] text-gray-400">
                      {time(m.sent_at)}
                      {m.direction === 'outbound' && (
                        <span className={m.status === 'read' ? 'text-sky-500' : ''}>{TICK[m.status] || ''}</span>
                      )}
                    </div>
                  </div>
                </div>
              ))}
              <div ref={endRef} />
            </div>

            <form onSubmit={send} className="flex items-center gap-2 border-t border-gray-100 p-3">
              <input
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                placeholder="Type a message…"
                className={inputClass}
              />
              <button
                type="submit"
                disabled={sending || !draft.trim()}
                className="rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50"
              >
                Send
              </button>
            </form>
          </>
        ) : (
          <div className="grid flex-1 place-items-center text-gray-400">Select a conversation</div>
        )}
      </div>
    </div>
  )
}
