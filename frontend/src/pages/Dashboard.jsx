import { useEffect, useState } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
} from 'chart.js'
import { Line } from 'react-chartjs-2'
import { Link } from 'react-router-dom'
import client from '../api/client'
import { Badge, StatCard, Panel, inr, duration } from '../components/ui'

const viewAll = (to) => (
  <Link to={to} className="text-sm font-semibold text-brand hover:underline">
    View all →
  </Link>
)

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

export default function Dashboard() {
  const [data, setData] = useState(null)

  useEffect(() => {
    client.get('/dashboard').then((res) => setData(res.data))
  }, [])

  if (!data) {
    return <div className="text-gray-400">Loading dashboard…</div>
  }

  const { stats, call_trend, recent_calls, recent_customers } = data

  const chartData = {
    labels: call_trend.map((d) => d.date),
    datasets: [
      {
        data: call_trend.map((d) => d.count),
        borderColor: '#6c5ce7',
        backgroundColor: 'rgba(108,92,231,0.12)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#6c5ce7',
        pointRadius: 3,
      },
    ],
  }

  const chartOptions = {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f6' } },
      x: { grid: { display: false } },
    },
    maintainAspectRatio: false,
  }

  return (
    <div>
      <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total Customers"
          value={stats.customers}
          sub={`${stats.active_customers} active`}
          icon="🏢"
          to="/teams"
        />
        <StatCard
          label="Numbers Assigned"
          value={stats.numbers_assigned}
          sub={`${stats.numbers_available} available`}
          icon="📱"
          to="/numbers"
        />
        <StatCard
          label="Calls Today"
          value={stats.calls_today}
          sub={`${stats.missed_today} missed`}
          icon="📞"
          to="/call-logs"
        />
        <StatCard label="MRR" value={inr(stats.mrr)} sub="from active plans" icon="💳" to="/analytics" />
      </div>

      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <StatCard label="Open Leads" value={stats.open_leads} sub={inr(stats.pipeline_value)} icon="🎯" to="/leads" />
        <StatCard label="Pending Tasks" value={stats.pending_reminders} sub="reminders" icon="⏰" to="/reminders" />
        <StatCard label="Open Chats" value={stats.open_chats} sub="WhatsApp" icon="💬" to="/inbox" />
        <StatCard label="AI Agents" value={stats.active_agents} sub="active" icon="🤖" to="/ai-receptionist" />
        <StatCard label="Collected" value={inr(stats.collected_month)} sub="this month" icon="🧾" to="/payments" />
        <StatCard label="Plans" value={stats.plans} sub="offered" icon="💳" to="/plans" />
      </div>

      <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Panel title="Calls — last 7 days" padded>
            <div style={{ height: 260 }}>
              <Line data={chartData} options={chartOptions} />
            </div>
          </Panel>
        </div>
        <Panel title="Recent Customers" action={viewAll('/contacts')}>
          <div className="divide-y divide-gray-100">
            {recent_customers.map((c) => (
              <div key={c.id} className="flex items-center justify-between px-5 py-3">
                <div>
                  <div className="text-sm font-semibold">{c.business_name}</div>
                  <div className="text-xs text-gray-400">{c.plan?.name || 'No plan'}</div>
                </div>
                <Badge value={c.status} />
              </div>
            ))}
          </div>
        </Panel>
      </div>

      <Panel title="Recent Calls" action={viewAll('/call-logs')}>
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th className="px-5 py-3">Caller</th>
                <th className="px-5 py-3">Business</th>
                <th className="px-5 py-3">Number</th>
                <th className="px-5 py-3">Direction</th>
                <th className="px-5 py-3">Duration</th>
                <th className="px-5 py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {recent_calls.map((call) => (
                <tr key={call.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                  <td className="px-5 py-3 font-medium">{call.caller}</td>
                  <td className="px-5 py-3 text-gray-500">{call.customer?.business_name || '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{call.virtual_number}</td>
                  <td className="px-5 py-3"><Badge value={call.direction} /></td>
                  <td className="px-5 py-3 text-gray-500">{duration(call.duration_sec)}</td>
                  <td className="px-5 py-3"><Badge value={call.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </div>
  )
}
