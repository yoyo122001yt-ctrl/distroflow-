 import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('df_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  const lang = localStorage.getItem('lang') || 'en'
  config.headers['Accept-Language'] = lang
  return config
})

api.interceptors.response.use(
  (response) => {
    const body = response.data
    if (body && typeof body === 'object' && 'data' in body && 'message' in body) {
      const inner = body.data
      if (inner && typeof inner === 'object' && 'data' in inner && Array.isArray(inner.data)) {
        response.data = { results: inner.data, total: inner.total, page: inner.current_page, lastPage: inner.last_page }
      } else {
        response.data = inner
      }
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('df_token')
      localStorage.removeItem('df_user')
      window.location.href = '/login'
    }
    const message = error.response?.data?.detail || error.response?.data?.message || error.message || 'An error occurred'
    return Promise.reject(new Error(message))
  }
)

export default api
