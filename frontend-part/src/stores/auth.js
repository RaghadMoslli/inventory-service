import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'
import { useOrderStore } from './orders'
import { useProductStore } from './products'
import { useNotificationStore } from './notifications'

const LOCK_KEY = 'login_lock_until'

function readUser() {
  try { return JSON.parse(localStorage.getItem('user')) } catch { return null }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: readUser(),
    token: localStorage.getItem('token') || null,
    lockUntil: Number(localStorage.getItem(LOCK_KEY)) || 0,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin',
  },
  actions: {
    setSession(token, user) {
      this.token = token
      this.user = user
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
    },
    clearSession() {
      this.token = null
      this.user = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      useOrderStore().$reset()
      useProductStore().$reset()
      useNotificationStore().$reset()
    },
    setLock(timestamp) {
      this.lockUntil = timestamp
      if (timestamp) localStorage.setItem(LOCK_KEY, String(timestamp))
      else localStorage.removeItem(LOCK_KEY)
    },
    async login(credentials) {
      try {
        const { data } = await api.post('/auth/login', credentials)
        this.setSession(data.access_token || data.token, data.user)
        this.setLock(0)
        return data.user
      } catch (error) {
        const parsed = parseApiError(error)
        if (parsed.status === 429) this.setLock(Date.now() + parsed.retryAfter * 1000)
        throw parsed
      }
    },
    async register(payload) {
      try {
        const { data } = await api.post('/auth/register', payload)
        return data
      } catch (error) {
        throw parseApiError(error)
      }
    },
    async fetchMe() {
      try {
        const { data } = await api.get('/me')
        this.user = data
        localStorage.setItem('user', JSON.stringify(data))
      } catch { /* 401 يعالجه الـ interceptor */ }
    },
    async logout() {
      try { await api.post('/auth/logout') } catch { /* نكمل التنظيف المحلي */ }
      this.clearSession()
    },
  },
})