import { useEffect, useState } from 'react'
import { NavLink, Outlet, Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import client from '../api/client'

const NAV = [
  { to: '/', label: 'Home', icon: '🏠', end: true },
  { to: '/teams', label: 'Teams', icon: '🏢' },
  { to: '/call-logs', label: 'Call History', icon: '📞' },
  { to: '/analytics', label: 'Reports', icon: '📊' },
  { to: '/insights', label: 'Insights', icon: '📈' },
  { to: '/reminders', label: 'To Dos', icon: '📝' },
  { to: '/contacts', label: 'Contacts', icon: '👤' },
  { to: '/ai-receptionist', label: 'AI Receptionist', icon: '✦', ai: true, isNew: true },
  { to: '/ai-sales-agent', label: 'AI Sales Agent', icon: '✦', ai: true, isNew: true },
  { to: '/caller-tunes', label: 'AI Caller Tune', icon: '✦', ai: true },
  { to: '/ai-google-business', label: 'AI Google Business Rev…', icon: '✦', ai: true },
  { to: '/inbox', label: 'Whatsapp', icon: '💬' },
  { to: '/campaigns', label: 'Broadcast', icon: '📣' },
  { to: '/wallet', label: 'Wallet', icon: '👛' },
  { to: '/settings', label: 'Settings', icon: '⚙️' },
  { to: '/integrations', label: 'Integrations', icon: '🧩' },
  { to: '/automations', label: 'Automations', icon: '🤖' },
  { to: '/team-members', label: 'Team Members', icon: '👥' },
  { to: '/notes', label: 'Notes', icon: '🗒️' },
  { to: '/templates', label: 'Quick Message', icon: '💭' },
]

// WhatsApp deep link used by the real product for buying numbers.
export function buyNumberLink(user, orgName) {
  const text =
    `I want to purchase additional superfone number\n(source: OD)\n` +
    `Org Name: ${orgName || '—'}\nPhone: +91${user?.phone || ''}\nSource: Web dashboard`
  return `https://api.whatsapp.com/send?phone=919429690909&text=${encodeURIComponent(text)}`
}

export default function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [org, setOrg] = useState(null)
  const orgName = org?.name || ''

  useEffect(() => {
    client.get('/orgs').then((res) => setOrg(res.data[0] || null)).catch(() => {})
  }, [])

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const balance = Number(user?.wallet_balance ?? 0).toLocaleString('en-IN', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
  })

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-white">
      {/* ── Topbar ── */}
      <header className="z-20 flex flex-shrink-0 items-center justify-between border-b border-gray-100 px-5 py-2.5 shadow-sm">
        <div className="flex min-w-0 items-center gap-5">
          <Link to="/" className="flex flex-shrink-0 items-center gap-2">
            <span className="grid h-8 w-8 place-items-center rounded-full bg-blue-600 text-lg text-white">⚡</span>
            <span className="text-2xl font-extrabold tracking-tight">Superfone</span>
          </Link>
          {org && (
            <span className="hidden truncate font-serif text-lg text-gray-800 xl:block">
              {org.name} <span className="whitespace-nowrap">{org.number}</span>
            </span>
          )}
        </div>

        <Link
          to="/live-calls"
          className="relative rounded-lg border-2 border-gray-800 px-5 py-1.5 text-[15px] font-semibold shadow-[3px_3px_0_#d1d5db] transition hover:bg-gray-50"
        >
          Live Call Dashboard
          <span className="absolute -right-1 -top-1 h-3 w-3 animate-pulse rounded-full bg-green-500" />
        </Link>

        <div className="flex items-center gap-4">
          <span className="text-xl">💼</span>
          <span className="rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-bold text-blue-600">
            AVL BAL : ₹{balance}
          </span>
          <div className="text-right leading-tight">
            <div className="font-bold">{user?.name}</div>
            <div className="text-sm text-gray-500">{user?.phone}</div>
          </div>
          <button
            onClick={handleLogout}
            className="rounded-lg border border-gray-200 px-4 py-1.5 text-sm font-semibold hover:bg-gray-50"
          >
            Logout
          </button>
        </div>
      </header>

      <div className="flex min-h-0 flex-1">
        {/* ── Sidebar ── */}
        <aside className="flex w-64 flex-shrink-0 flex-col border-r border-gray-100">
          <nav className="scroll-thin flex-1 overflow-y-auto px-3 py-3">
            {NAV.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `mb-0.5 flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-[15px] font-medium transition ${
                    isActive
                      ? 'bg-blue-50 text-blue-600'
                      : item.ai
                        ? 'text-amber-600 hover:bg-gray-50'
                        : 'text-gray-700 hover:bg-gray-50'
                  }`
                }
              >
                <span className="w-5 text-center">{item.icon}</span>
                <span className="truncate">{item.label}</span>
                {item.isNew && (
                  <span className="ml-auto rounded bg-blue-600 px-1.5 py-0.5 text-[9px] font-bold text-white">
                    NEW
                  </span>
                )}
              </NavLink>
            ))}
          </nav>

          <div className="flex-shrink-0 space-y-2 border-t border-gray-100 p-3">
            <button className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
              ▶ Watch tutorial
            </button>
            <a
              href={buyNumberLink(user, orgName)}
              target="_blank"
              rel="noreferrer"
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
              Buy a new Number
            </a>
            <a
              href="https://api.whatsapp.com/send?phone=919429690909&text=Hi%2C%20I%20need%20help"
              target="_blank"
              rel="noreferrer"
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
              📞 Contact Us
            </a>
            <a
              href="https://api.whatsapp.com/send?phone=919429690909&text=Hi"
              target="_blank"
              rel="noreferrer"
              className="block text-center text-sm font-bold underline"
            >
              Chat with live agent
            </a>
          </div>
        </aside>

        {/* ── Content ── */}
        <main className="scroll-thin min-w-0 flex-1 overflow-y-auto bg-gray-50/60 p-7">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
