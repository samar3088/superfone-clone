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
import Settings from './pages/Settings'
import Integrations from './pages/Integrations'
import ComingSoon from './pages/ComingSoon'

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
            <Route path="/settings" element={<Settings />} />
            <Route path="/integrations" element={<Integrations />} />
            <Route path="/soon/:title" element={<ComingSoon />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>
)
