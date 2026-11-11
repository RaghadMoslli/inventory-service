import { defineStore } from 'pinia'
import api from '../api/axios'

const currentUser = () => { try { return JSON.parse(localStorage.getItem('user')) } catch { return null } }
const storageKey = () => `cart_${currentUser()?.id ?? 'guest'}`
function read() {
  try {
    const v = JSON.parse(localStorage.getItem(storageKey()))
    return Array.isArray(v) ? v : []
  } catch { return [] }
}

export const useCartStore = defineStore('cart', {
  state: () => ({ items: read(), refreshing: false }),

  getters: {
    count: (s) => s.items.reduce((n, i) => n + i.quantity, 0),
    total: (s) => s.items.reduce((n, i) => n + i.quantity * Number(i.price), 0),
    hasUnavailable: (s) => s.items.some((i) => i.max <= 0),
    canCheckout: (s) => s.items.length > 0 && !s.items.some((i) => i.max <= 0),
  },

  actions: {
    load() { this.items = read() }, // عند تسجيل الدخول: سلة هذا المستخدم
    persist() { localStorage.setItem(storageKey(), JSON.stringify(this.items)) },
    quantityOf(id) { return this.items.find((i) => i.product_id === id)?.quantity ?? 0 },

    add(product, qty = 1) {
      if (currentUser()?.role === 'admin') return 0 // الأدمن لا يطلب
      const max = Number(product.available_quantity ?? 0)
      if (max <= 0) return 0
      const item = this.items.find((i) => i.product_id === product.id)
      if (item) {
        item.quantity = Math.min(max, item.quantity + qty)
        item.max = max
        item.price = product.price
      } else {
        this.items.push({
          product_id: product.id,
          name: product.name,
          price: product.price,
          image_path: product.image_path,
          category: product.category,
          max,
          quantity: Math.min(max, qty),
        })
      }
      this.persist()
      return this.quantityOf(product.id)
    },

    setQty(id, qty) {
      const item = this.items.find((i) => i.product_id === id)
      if (!item) return
      const n = Number.isFinite(qty) ? Math.floor(qty) : 1
      item.quantity = Math.max(1, Math.min(Math.max(item.max, 1), n))
      this.persist()
    },

    remove(id) {
      this.items = this.items.filter((i) => i.product_id !== id)
      this.persist()
    },

    clear() {
      this.items = []
      this.persist()
    },

    // تحديث السعر والكمية المتاحة لكل منتج في السلة من الباك
    async refresh() {
      if (!this.items.length) return
      this.refreshing = true
      await Promise.all(
        this.items.map(async (it) => {
          try {
            const { data } = await api.get(`/products/${it.product_id}`)
            const p = data.data ?? data
            it.name = p.name
            it.price = p.price
            it.image_path = p.image_path
            it.max = Number(p.available_quantity ?? 0)
            if (it.max > 0 && it.quantity > it.max) it.quantity = it.max
          } catch (e) {
            if (e.response?.status === 404) it.max = 0 // المنتج حُذف
          }
        })
      )
      this.persist()
      this.refreshing = false
    },
  },
})