import axios from 'axios'

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/+$/, '')

export const API_ORIGIN = (() => {
  try { return new URL(BASE_URL).origin } catch { return 'http://localhost:8000' }
})()

const AUTH_PATHS = ['/auth/login', '/auth/register']

let onUnauthorized = null
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

const api = axios.create({
  baseURL: BASE_URL,
  timeout: 20000,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const url = error.config?.url || ''
    const isAuthCall = AUTH_PATHS.some((p) => url.endsWith(p))
   
    if (error.response?.status === 401 && !isAuthCall && localStorage.getItem('token') && onUnauthorized) {
      onUnauthorized()
    }
    return Promise.reject(error)
  }
)

export default api
