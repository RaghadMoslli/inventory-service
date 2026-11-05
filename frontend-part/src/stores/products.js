import { defineStore } from 'pinia'
import api from '../api/axios'

export const useProductStore = defineStore('products', {
  state: () => ({
    products: [],
    currentProduct: null,
    loading: false,
    error: null,
    validationErrors: {},
    meta: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1
    }
  }),
  
  getters: {
    // يمكنك إضافة getters إضافية عند الحاجة، مثل تصفية المنتجات المتوفرة فقط
    availableProducts: (state) => state.products.filter(p => p.available_quantity > 0)
  },

  actions: {
    // جلب قائمة المنتجات مع دعم الفلترة والـ Pagination
    async fetchProducts(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/products', { params })
        // استجابة لاراول للـ Pagination تحتوي غالباً على data و meta
        this.products = response.data.data || response.data
        if (response.data.meta) {
          this.meta = response.data.meta
        }
        return true
      } catch (error) {
        this.error = error.response?.data || error.message
        throw this.error
      } finally {
        this.loading = false
      }
    },

    // جلب تفاصيل منتج محدد مع الـ available_quantity الواردة من gRPC
    async fetchProductDetails(id) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/products/${id}`)
        this.currentProduct = response.data.data || response.data
        return this.currentProduct
      } catch (error) {
        this.error = error.response?.data || error.message
        throw this.error
      } finally {
        this.loading = false
      }
    },

    // إضافة منتج جديد (خاص بالـ Admin) مع الـ initial_stock
    async createProduct(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/products', payload, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        return response.data
      } catch (error) {
        throw error.response?.data || error.message
      } finally {
        this.loading = false
      }
    }
  }
})