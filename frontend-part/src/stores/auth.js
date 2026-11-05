import { defineStore } from 'pinia'
import api from '../api/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin', // تحقق إذا كان الدور admin
  },
  actions: {
    async login(credentials) {
      try {
        const response = await api.post('/auth/login', credentials)
        this.token = response.data.token || response.data.access_token
        this.user = response.data.user
        localStorage.setItem('token', this.token)
        localStorage.setItem('user', JSON.stringify(this.user))
        return true
      } catch (error) {
        throw error.response?.data || error.message
      }
    },
    async register(payload) {
      try {
        const response = await api.post('/auth/register', payload)
        return response.data
      } catch (error) {
        throw error.response?.data || error.message
      }
    },
    async logout() {
      try {
        await api.post('/auth/logout')
      } catch (e) {
      }
      this.token = null
      this.user = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    }
  }
})