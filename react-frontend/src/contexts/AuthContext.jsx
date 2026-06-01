import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import * as authService from '../services/auth'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('df_token')
    if (token) {
      authService.getCurrentUser()
        .then(res => setUser(res))
        .catch(() => {
          localStorage.removeItem('df_token')
          localStorage.removeItem('df_user')
        })
        .finally(() => setLoading(false))
    } else {
      setLoading(false)
    }
  }, [])

  const login = useCallback(async (email, password) => {
    const res = await authService.login(email, password)
    localStorage.setItem('df_token', res.token)
    localStorage.setItem('df_user', JSON.stringify(res.user))
    setUser(res.user)
    return res
  }, [])

  const register = useCallback(async (userData) => {
    const res = await authService.register(userData)
    localStorage.setItem('df_token', res.token)
    localStorage.setItem('df_user', JSON.stringify(res.user))
    setUser(res.user)
    return res
  }, [])

  const logout = useCallback(() => {
    authService.logout()
    localStorage.removeItem('df_token')
    localStorage.removeItem('df_user')
    setUser(null)
  }, [])

  const hasRole = useCallback((roles) => {
    if (!user) return false
    if (typeof roles === 'string') return user.role === roles
    return roles.includes(user.role)
  }, [user])

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, hasRole }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
