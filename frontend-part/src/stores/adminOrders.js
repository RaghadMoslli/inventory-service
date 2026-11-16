import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'
import { useUiStore } from './ui'

export const FILTER_TO_STATUS = { pending: 'PENDING', approved: 'CONFIRMED', rejected: 'CANCELLED' }

const DECISION_TO_STATUS = { approved: 'CONFIRMED', rejected: 'CANCELLED' }

const time = (v) => (v ? Date.parse(v) : 0)

export const useAdminOrdersStore = defineStore('adminOrders', {
  state: () => ({
    orders: [],
    filter: 'all', // all | pending | approved | rejected
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 20 },
    loading: false,
    loaded: false,
    error: null,
    busy: {}, // { [orderId]: الحالة المطلوبة } أثناء الإرسال
    fresh: {}, // { [orderId]: true } لتمييز الصف الجديد لحظة وصوله
  }),

  getters: {
    isBusy: (state) => (id) => !!state.busy[id],
  },

  actions: {
    matches(status) {
      return this.filter === 'all' || FILTER_TO_STATUS[this.filter] === status
    },

    async fetchOrders(page = 1, { silent = false } = {}) {
      if (!silent || !this.loaded) this.loading = true
      try {
        const params = { page, per_page: this.meta.per_page }
        if (this.filter !== 'all') params.status = this.filter
        const { data } = await api.get('/admin/orders', { params })
        this.orders = data.data ?? []
        this.meta = { ...this.meta, ...(data.meta ?? {}) }
        this.loaded = true
        this.error = null
      } catch (error) {
        if (!silent || !this.orders.length) this.error = parseApiError(error).message
      } finally {
        this.loading = false
      }
    },

    async setFilter(filter) {
      this.filter = filter
      await this.fetchOrders(1)
    },

    applyRow(row) {
      const index = this.orders.findIndex((o) => o.order_id === row.order_id)
      const exists = index !== -1
      const inPage = this.meta.current_page === 1

      if (exists) {
        const current = this.orders[index]
        if (time(row.updated_at) < time(current.updated_at)) return { isNew: false, statusChanged: false }
        const statusChanged = current.status !== row.status
        if (!this.matches(row.status)) {
          this.orders.splice(index, 1)
          this.meta.total = Math.max(0, this.meta.total - 1)
        } else {
          this.orders[index] = { ...current, ...row }
        }
        return { isNew: false, statusChanged }
      }

      if (inPage && this.matches(row.status)) {
        this.orders.unshift(row)
        this.meta.total += 1
        this.fresh[row.order_id] = true
        setTimeout(() => { delete this.fresh[row.order_id] }, 6000)
        return { isNew: true, statusChanged: false }
      }
      return { isNew: false, statusChanged: false }
    },

    onCreated(payload) {
      const existed = this.orders.some((o) => o.order_id === payload.order_id)
      this.applyRow(payload)
      if (!existed) useUiStore().info(`طلب جديد #${payload.order_id} من ${payload.customer_name ?? 'عميل'}`)
    },

    onUpdated(payload) {
      const { statusChanged } = this.applyRow(payload)
      if (statusChanged) useUiStore().info(`تم تحديث الطلب #${payload.order_id}`)
    },

    // تغيير الحالة (CONFIRMED | SHIPPED | DELIVERED | CANCELLED) عبر endpoint الموحّد
    async setStatus(id, status) {
      if (this.busy[id]) return false
      this.busy[id] = status
      const ui = useUiStore()
      try {
        const { data } = await api.patch(`/orders/${id}/status`, { status })
        this.applyRow(data.data)
        ui.success(`تم تحديث الطلب #${id}`)
        return true
      } catch (error) {
        const parsed = parseApiError(error)
        ui.error(parsed.message)
        // 422: الحالة تغيّرت (مسؤول آخر سبقنا)، فنزامن القائمة
        if (parsed.status === 422) this.fetchOrders(this.meta.current_page, { silent: true })
        return false
      } finally {
        delete this.busy[id]
      }
    },

    // يبقى للتوافق مع أزرار الموافقة/الرفض الحالية: approved | rejected
    async decide(id, decision) {
      const ok = await this.setStatus(id, DECISION_TO_STATUS[decision])
      return ok
    },

    $reset() {
      this.orders = []
      this.filter = 'all'
      this.meta = { current_page: 1, last_page: 1, total: 0, per_page: 20 }
      this.loading = false
      this.loaded = false
      this.error = null
      this.busy = {}
      this.fresh = {}
    },
  },
})