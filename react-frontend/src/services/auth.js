import api from './api'

export async function login(email, password) {
  const { data } = await api.post('/auth/login', { email, password })
  return data
}

export async function register(userData) {
  const { data } = await api.post('/auth/register', userData)
  return data
}

export async function logout() {
  try {
    await api.post('/auth/logout')
  } catch {
    // ignore
  }
}

export async function getCurrentUser() {
  const { data } = await api.get('/auth/me')
  return data
}

export async function updateProfile(profileData) {
  const { data } = await api.put('/auth/profile', profileData)
  return data
}
