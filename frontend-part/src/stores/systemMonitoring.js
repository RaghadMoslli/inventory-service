
import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'

export const useSystemMonitoringStore = defineStore('systemMonitoring', {
  state: () => ({
    status: null,
    loading: false,
    error: null,
    lastUpdated: null,
  }),

  actions: {
    async fetchStatus() {
      this.loading = true
      this.error = null

      try {
        const { data } = await api.get('/admin/system-status')

        this.status = data.data ?? data
        this.lastUpdated = new Date()

        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      } finally {
        this.loading = false
      }
    },
  },
})
