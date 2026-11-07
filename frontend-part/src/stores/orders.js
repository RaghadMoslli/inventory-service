
import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'

export const ORDER_STATUSES = {
  PENDING: 'قيد الانتظار',
  CONFIRMED: 'مؤكد',
  SHIPPED: 'تم الشحن',
  DELIVERED: 'تم التسليم',
  CANCELLED: 'ملغي'
}

export const useOrderStore = defineStore('orders', {
  state: () => ({
    currentOrder: null,
    loading: false,
    error: null
  }),

  actions: {
    // إنشاء طلب جديد
    async createOrder(productId, quantity) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/orders', {
          items: [
            {
              product_id: Number(productId),
              quantity: Number(quantity)
            }
          ]
        })

        this.currentOrder = response.data.data ?? response.data
        return this.currentOrder
      } catch (error) {
        this.error = parseApiError(error).message
        return null
      } finally {
        this.loading = false
      }
    },

    // جلب تفاصيل طلب
    async fetchOrder(id) {
      this.loading = true
      this.error = null
      this.currentOrder = null

      try {
        const response = await api.get(`/orders/${id}`)
        this.currentOrder = response.data.data ?? response.data
        return this.currentOrder
      } catch (error) {
        this.error = parseApiError(error).message
        return null
      } finally {
        this.loading = false
      }
    },

    // تغيير حالة الطلب
    async updateStatus(id, status) {
      this.error = null
    
      try {
        const response = await api.patch(`/orders/${id}/status`, {
          status
        })
    
        const updated = response.data.data ?? response.data
    
        this.currentOrder = {
          ...this.currentOrder,
          ...(updated && typeof updated === 'object' ? updated : {}),
          status
        }
    
        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      }
    }
  }
})
