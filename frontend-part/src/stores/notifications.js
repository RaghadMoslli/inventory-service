import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'
import { localizeNotification } from '../utils/format'
import { useUiStore } from './ui'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    knownIds: [],
    loaded: false,
    loading: false,
    error: null,
  }),

  getters: {
    unreadCount: (state) => state.notifications.filter((n) => !n.read_at).length,
  },

  actions: {
    async fetchNotifications({ silent = false } = {}) {
      if (!silent || !this.loaded) this.loading = true
      try {
        const { data } = await api.get('/notifications')
        const list = Array.isArray(data) ? data : data.data ?? []

        if (this.loaded) {
          list
            .filter((n) => !n.read_at && !this.knownIds.includes(n.id))
            .slice(0, 3)
            .forEach((n) => useUiStore().info(localizeNotification(n.message)))
        }
        this.notifications = list
        this.knownIds = list.map((n) => n.id)
        this.loaded = true
        this.error = null
      } catch (error) {
        if (!this.notifications.length) this.error = parseApiError(error).message
      } finally {
        this.loading = false
      }
    },

    async markAsRead(id) {
      const target = this.notifications.find((n) => n.id === id)
      if (!target || target.read_at) return
      const previous = target.read_at
      target.read_at = new Date().toISOString() // تحديث فوري
      try {
        await api.patch(`/notifications/${id}/read`)
      } catch {
        target.read_at = previous
      }
    },

    async markAllAsRead() {
      const unread = this.notifications.filter((n) => !n.read_at)
      await Promise.allSettled(unread.map((n) => this.markAsRead(n.id)))
    },
  },
})
