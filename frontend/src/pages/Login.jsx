import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('admin@superfone.test')
  const [password, setPassword] = useState('admin123')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    setBusy(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid email or password.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div
      className="grid min-h-screen place-items-center p-5"
      style={{
        background:
          'radial-gradient(1200px 600px at 70% -10%, #2a2350, #0f1424 60%)',
      }}
    >
      <div className="w-full max-w-sm rounded-3xl bg-white p-9 shadow-2xl">
        <div className="mb-1 flex items-center gap-3">
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-brand to-indigo-400 text-sm font-extrabold text-white">
            SF
          </span>
          <span className="text-lg font-bold">Superfone</span>
        </div>
        <h1 className="mt-5 text-2xl font-bold">Welcome back</h1>
        <p className="mb-6 text-sm text-gray-500">Sign in to the admin dashboard</p>

        {error && (
          <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {error}
          </div>
        )}

        <form onSubmit={submit}>
          <label className="mb-4 block">
            <span className="mb-1.5 block text-sm font-semibold text-gray-700">Email</span>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/15"
              required
              autoFocus
            />
          </label>
          <label className="mb-5 block">
            <span className="mb-1.5 block text-sm font-semibold text-gray-700">Password</span>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/15"
              required
            />
          </label>
          <button
            type="submit"
            disabled={busy}
            className="w-full rounded-lg bg-brand py-3 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:opacity-60"
          >
            {busy ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p className="mt-5 text-center text-xs text-gray-400">
          Demo: admin@superfone.test / admin123
        </p>
      </div>
    </div>
  )
}
