import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'

export const useEmailStore = defineStore('emails', {
  state: () => ({
    logs: [],
    stats: { queued: 0, sent: 0, failed: 0, dead: 0 },
    pagination: null,
    loading: false,
    error: null,
    byOrderId: {},
  }),
  actions: {
    async fetchOrderEmailStatus(orderId) {
      try {
        const { data } = await api.get(`/orders/${orderId}/email-status`)
        const payload = data.data ?? data
        this.byOrderId[orderId] = payload
        return payload
      } catch (error) {
        const parsed = parseApiError(error)
        this.byOrderId[orderId] = { status: 'unknown', error: parsed.message }
        return null
      }
    },
    async fetchLogs({ status = '', page = 1 } = {}) {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.get('/admin/email-logs', {
          params: { status: status || undefined, page, per_page: 20 },
        })
        this.logs = data.data?.data ?? data.data ?? []
        this.pagination = data.data ?? null
        this.stats = data.stats ?? this.stats
        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      } finally {
        this.loading = false
      }
    },
    async retry(id) {
      this.error = null
      try {
        await api.post(`/admin/email-logs/${id}/retry`)
        await this.fetchLogs()
        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      }
    },
  },
})
