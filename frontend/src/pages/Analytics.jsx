import { useEffect, useState } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import { Line, Doughnut, Bar } from 'react-chartjs-2'
import client from '../api/client'
import { Panel, StatCard, inr } from '../components/ui'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Tooltip,
  Legend,
  Filler
)

const PALETTE = ['#6c5ce7', '#00b894', '#fdcb6e', '#e17055', '#0984e3', '#a29bfe']

export default function Analytics() {
  const [data, setData] = useState(null)

  useEffect(() => {
    client.get('/analytics').then((res) => setData(res.data))
  }, [])

  if (!data) return <div className="text-gray-400">Loading analytics…</div>

  const { revenue_trend, calls_by_direction, lead_funnel, customers_by_status, top_agents, totals } = data

  const revenueChart = {
    labels: revenue_trend.map((d) => d.label),
    datasets: [
      {
        data: revenue_trend.map((d) => d.value),
        borderColor: '#6c5ce7',
        backgroundColor: 'rgba(108,92,231,0.12)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#6c5ce7',
      },
    ],
  }

  const callsChart = {
    labels: ['Inbound', 'Outbound', 'Missed'],
    datasets: [
      {
        data: [calls_by_direction.inbound, calls_by_direction.outbound, calls_by_direction.missed],
        backgroundColor: ['#00b894', '#0984e3', '#e17055'],
        borderWidth: 0,
      },
    ],
  }

  const statusChart = {
    labels: ['Active', 'Trial', 'Suspended', 'Churned'],
    datasets: [
      {
        data: [
          customers_by_status.active,
          customers_by_status.trial,
          customers_by_status.suspended,
          customers_by_status.churned,
        ],
        backgroundColor: ['#00b894', '#0984e3', '#e17055', '#b2bec3'],
        borderWidth: 0,
      },
    ],
  }

  const funnelChart = {
    labels: ['New', 'Contacted', 'Qualified', 'Won', 'Lost'],
    datasets: [
      {
        data: [lead_funnel.new, lead_funnel.contacted, lead_funnel.qualified, lead_funnel.won, lead_funnel.lost],
        backgroundColor: PALETTE,
        borderRadius: 6,
      },
    ],
  }

  const agentChart = {
    labels: top_agents.map((a) => a.name),
    datasets: [
      {
        data: top_agents.map((a) => a.calls),
        backgroundColor: '#6c5ce7',
        borderRadius: 6,
      },
    ],
  }

  const noLegend = { plugins: { legend: { display: false } }, maintainAspectRatio: false }
  const withLegend = {
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } },
    maintainAspectRatio: false,
  }
  const moneyAxis = {
    ...noLegend,
    scales: {
      y: { beginAtZero: true, grid: { color: '#eef0f6' }, ticks: { callback: (v) => '₹' + v / 1000 + 'k' } },
      x: { grid: { display: false } },
    },
  }
  const countAxis = {
    ...noLegend,
    scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f6' } }, x: { grid: { display: false } } },
  }

  return (
    <div>
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Collected" value={inr(totals.collected)} sub="all-time" icon="💰" />
        <StatCard label="Outstanding" value={inr(totals.outstanding)} sub="pending" icon="⏳" />
        <StatCard label="Won Pipeline" value={inr(totals.won_value)} sub="closed leads" icon="🎯" />
        <StatCard label="Lead Conversion" value={`${totals.conversion_rate}%`} sub="won / total" icon="📈" />
      </div>

      <div className="mb-5">
        <Panel title="Revenue — last 6 months" padded>
          <div style={{ height: 280 }}>
            <Line data={revenueChart} options={moneyAxis} />
          </div>
        </Panel>
      </div>

      <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <Panel title="Calls by direction" padded>
          <div style={{ height: 260 }}>
            <Doughnut data={callsChart} options={withLegend} />
          </div>
        </Panel>
        <Panel title="Customers by status" padded>
          <div style={{ height: 260 }}>
            <Doughnut data={statusChart} options={withLegend} />
          </div>
        </Panel>
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <Panel title="Lead funnel" padded>
          <div style={{ height: 260 }}>
            <Bar data={funnelChart} options={countAxis} />
          </div>
        </Panel>
        <Panel title="Top agents by calls" padded>
          <div style={{ height: 260 }}>
            <Bar data={agentChart} options={countAxis} />
          </div>
        </Panel>
      </div>
    </div>
  )
}
