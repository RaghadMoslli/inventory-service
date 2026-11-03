import { defineStore } from 'pinia'
import api from '../api/axios'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    loading: false,
    error: null,
  }),

  getters: {
    // حساب عدد الإشعارات غير المقروءة
    unreadCount: (state) => {
      return state.notifications.filter(n => !n.read_at && !n.is_read).length
    }
  },

  actions: {
    // جلب قائمة الإشعارات
    async fetchNotifications() {
      try {
        this.loading = true
        const response = await api.get('/notifications')
        // التعامل مع اختلاف شكل الـ Response (سواء data مباشرة أو داخل data.data)
        this.notifications = response.data.data || response.data || []
      } catch (err) {
        this.error = err.response?.data?.message || 'فشل جلب الإشعارات'
      } finally {
        this.loading = false
      }
    },

    // تحديد إشعار محدد كمقروء
    async markAsRead(notificationId) {
      try {
        await api.patch(`/notifications/${notificationId}/read`)
        
        // تحديث الحالة محلياً فوراً
        const target = this.notifications.find(n => n.id === notificationId)
        if (target) {
          target.read_at = new Date().toISOString()
          target.is_read = true
        }
      } catch (err) {
        console.error('خطأ أثناء تحديث حالة الإشعار:', err)
      }
    }
  }
})