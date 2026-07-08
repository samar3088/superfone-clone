import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import './index.css'
import { AuthProvider, useAuth } from './auth/AuthContext'
import Layout from './components/Layout'
import Login from './pages/Login'
import Home from './pages/Home'
import Teams from './pages/Teams'
import TeamMembers from './pages/TeamMembers'
import LiveCalls from './pages/LiveCalls'
import Wallet from './pages/Wallet'
import ComingSoon from './pages/ComingSoon'
import Integrations from './pages/Integrations'
import Settings from './pages/Settings'
import Dashboard from './pages/Dashboard'
import Contacts from './pages/Contacts'
import Leads from './pages/Leads'
import Reminders from './pages/Reminders'
import VirtualNumbers from './pages/VirtualNumbers'
import CallLogs from './pages/CallLogs'
import Plans from './pages/Plans'
import Payments from './pages/Payments'
import Analytics from './pages/Analytics'
import Inbox from './pages/Inbox'
import Templates from './pages/Templates'
import Campaigns from './pages/Campaigns'
import AiAgents from './pages/AiAgents'
import Ivr from './pages/Ivr'
import CallerTunes from './pages/CallerTunes'

function Protected({ children }) {
  const { user, loading } = useAuth()
  if (loading) {
    return <div className="grid h-screen place-items-center text-gray-400">Loading…</div>
  }
  return user ? children : <Navigate to="/login" replace />
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            element={
              <Protected>
                <Layout />
              </Protected>
            }
          >
            <Route path="/" element={<Home />} />
            <Route path="/teams" element={<Teams />} />
            <Route path="/team-members" element={<TeamMembers />} />
            <Route path="/live-calls" element={<LiveCalls />} />
            <Route path="/wallet" element={<Wallet />} />
            <Route path="/call-logs" element={<CallLogs />} />
            <Route path="/analytics" element={<Analytics />} />
            <Route path="/insights" element={<Dashboard />} />
            <Route path="/reminders" element={<Reminders />} />
            <Route path="/contacts" element={<Contacts />} />
            <Route path="/leads" element={<Leads />} />
            <Route path="/inbox" element={<Inbox />} />
            <Route path="/templates" element={<Templates />} />
            <Route path="/campaigns" element={<Campaigns />} />
            <Route path="/ai-receptionist" element={<AiAgents initialType="receptionist" title="AI Receptionist" />} />
            <Route path="/ai-sales-agent" element={<AiAgents initialType="sales" title="AI Sales Agent" />} />
            <Route path="/caller-tunes" element={<CallerTunes />} />
            <Route path="/ivr" element={<Ivr />} />
            <Route path="/numbers" element={<VirtualNumbers />} />
            <Route path="/plans" element={<Plans />} />
            <Route path="/payments" element={<Payments />} />
            <Route path="/ai-google-business" element={<ComingSoon title="AI Google Business Reviews" icon="⭐" />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/integrations" element={<Integrations />} />
            <Route path="/automations" element={<ComingSoon title="Automations" icon="🤖" />} />
            <Route path="/notes" element={<ComingSoon title="Notes" icon="🗒️" />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>
)
