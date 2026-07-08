import { createContext, useContext, useEffect, useState } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (!token) {
      setLoading(false)
      return
    }
    client
      .get('/me')
      .then((res) => setUser(res.data))
      .catch(() => localStorage.removeItem('token'))
      .finally(() => setLoading(false))
  }, [])

  const login = async (email, password) => {
    const { data } = await client.post('/login', { email, password })
    localStorage.setItem('token', data.token)
    setUser(data.user)
  }

  const logout = async () => {
    try {
      await client.post('/logout')
    } catch {
      // ignore network errors on logout
    }
    localStorage.removeItem('token')
    setUser(null)
  }

  // Re-fetch the user (e.g. after a wallet purchase changes the balance).
  const refreshUser = async () => {
    try {
      const { data } = await client.get('/me')
      setUser(data)
    } catch {
      // keep the stale user on network errors
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
