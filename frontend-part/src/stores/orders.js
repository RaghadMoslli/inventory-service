import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'
import { useProductStore } from './products'
export { ORDER_STATUSES } from '../utils/constants'

let listRequest = null
let listKey = ''

export const useOrderStore = defineStore('orders', {
  state: () => ({
    orders: [],
    pagination: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
    currentOrder: null,
    loaded: false,
    loading: false,
    creating: false,
    error: null,
  }),

  getters: {
    pendingCount: (state) => state.orders.filter((o) => o.status === 'PENDING').length,
  },

  actions: {
    applyOrder(order) {
      if (!order?.id) return
      const index = this.orders.findIndex((o) => o.id === order.id)
      if (index !== -1) this.orders[index] = { ...this.orders[index], ...order }
      if (this.currentOrder?.id === order.id) this.currentOrder = { ...this.currentOrder, ...order }
    },

   
    async fetchOrders(page = 1, { silent = false, perPage = 10 } = {}) {
      const key = `${page}:${perPage}`
      if (listRequest && listKey === key) return listRequest
      if (!silent || !this.loaded) this.loading = true
      listKey = key

      listRequest = (async () => {
        try {
          const { data: body } = await api.get('/orders', { params: { page, per_page: perPage } })
          const paged = body.data ?? body
          const rows = Array.isArray(paged) ? paged : paged.data ?? []
          this.orders = rows
          this.pagination = {
            current_page: paged.current_page ?? 1,
            last_page: paged.last_page ?? 1,
            total: paged.total ?? rows.length,
            per_page: paged.per_page ?? perPage,
          }
          this.loaded = true
          this.error = null
          useProductStore().resolveProducts(rows.flatMap((o) => (o.items || []).map((i) => i.product_id)))
          return rows
        } catch (error) {
          const message = parseApiError(error).message
          if (!silent || !this.orders.length) this.error = message
          return null
        } finally {
          this.loading = false
          listRequest = null
        }
      })()
      return listRequest
    },

    async fetchOrder(id) {
      try {
        const { data } = await api.get(`/orders/${id}`)
        this.currentOrder = data.data ?? data
        return this.currentOrder
      } catch (error) {
        this.error = parseApiError(error).message
        return null
      }
    },

    // يقبل (productId, quantity) أو مصفوفة items كاملة
    async createOrder(productIdOrItems, quantity) {
      this.creating = true
      this.error = null
      const items = Array.isArray(productIdOrItems)
        ? productIdOrItems
        : [{ product_id: Number(productIdOrItems), quantity: Number(quantity) }]
      try {
        const { data } = await api.post('/orders', { items })
        const order = data.data ?? data
        this.currentOrder = order
        if (this.loaded && this.pagination.current_page === 1) {
          this.orders = [order, ...this.orders.filter((o) => o.id !== order.id)]
          this.pagination.total += 1
        }
        return order
      } catch (error) {
        this.error = parseApiError(error).message
        return null
      } finally {
        this.creating = false
      }
    },

    async cancelOrder(id) {
      this.error = null
      try {
        const { data } = await api.patch(`/orders/${id}/cancel`)
        this.applyOrder(data.data ?? { id, status: 'CANCELLED' })
        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      }
    },

    // PATCH /orders/{id}/status  (للأدمن فقط)
    async updateStatus(id, status) {
      this.error = null
      try {
        const { data } = await api.patch(`/orders/${id}/status`, { status })
        this.applyOrder(data.data ?? { id, status })
        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      }
    },
  },
})
