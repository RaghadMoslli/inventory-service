import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'

// تحقق مبدئي من نفس شروط الباك (حسب التوثيق) قبل إرسال الطلب
export function validateProductPayload(p = {}) {
  const errors = {}
  if (!p.name || !String(p.name).trim()) errors.name = 'اسم المنتج مطلوب'
  else if (String(p.name).length > 255) errors.name = 'الاسم يجب ألا يتجاوز 255 حرفاً'

  if (p.price === '' || p.price === null || p.price === undefined || Number.isNaN(Number(p.price)))
    errors.price = 'السعر مطلوب'
  else if (Number(p.price) < 0) errors.price = 'السعر يجب ألا يكون سالباً'

  if (!p.category || !String(p.category).trim()) errors.category = 'التصنيف مطلوب'

  const stock = Number(p.initial_stock)
  if (p.initial_stock === '' || p.initial_stock === null || p.initial_stock === undefined)
    errors.initial_stock = 'الكمية الابتدائية مطلوبة'
  else if (!Number.isInteger(stock) || stock < 0)
    errors.initial_stock = 'الكمية يجب أن تكون عدداً صحيحاً لا يقل عن 0'

  if (p.image instanceof File) {
    if (!p.image.type.startsWith('image/')) errors.image = 'الملف يجب أن يكون صورة'
    else if (p.image.size > 2048 * 1024) errors.image = 'حجم الصورة يجب ألا يتجاوز 2 ميغابايت'
  }
  return errors
}

// نبني FormData ونرسل فقط الحقول الموجودة (description و image اختياريان)
function toFormData(payload) {
  if (payload instanceof FormData) return payload
  const form = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    if (value === undefined || value === null || value === '') continue
    form.append(key, value)
  }
  return form
}

export const useProductStore = defineStore('products', {
  state: () => ({
    products: [],
    currentProduct: null,
    loading: false,
    error: null, // نص جاهز للعرض
    validationErrors: {},
    meta: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1
    }
  }),

  getters: {
    availableProducts: (state) => state.products.filter((p) => p.available_quantity > 0)
  },

  actions: {
    // جلب قائمة المنتجات مع الفلترة والـ Pagination
    async fetchProducts(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/products', { params })
        const body = response.data

        this.products = Array.isArray(body) ? body : body.data ?? []

        // لاراول Resource: الـ pagination داخل meta — بدون Resource: الحقول بالمستوى الأعلى
        const m = body.meta ?? (body.last_page ? body : null)
        this.meta = m
          ? {
              current_page: m.current_page ?? 1,
              per_page: m.per_page ?? this.meta.per_page,
              total: m.total ?? this.products.length,
              last_page: m.last_page ?? 1
            }
          : { ...this.meta, current_page: 1, last_page: 1, total: this.products.length }

        return true
      } catch (error) {
        this.error = parseApiError(error).message
        return false
      } finally {
        this.loading = false
      }
    },

    // تفاصيل منتج (مع available_quantity القادمة من gRPC)
    async fetchProductDetails(id) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/products/${id}`)
        this.currentProduct = response.data.data ?? response.data
        return this.currentProduct
      } catch (error) {
        this.error = parseApiError(error).message
        return null
      } finally {
        this.loading = false
      }
    },

    // إضافة منتج (Admin) — multipart/form-data
    async createProduct(payload) {
      this.loading = true
      this.error = null
      this.validationErrors = {}

      if (!(payload instanceof FormData)) {
        const errors = validateProductPayload(payload)
        if (Object.keys(errors).length) {
          this.validationErrors = errors
          this.loading = false
          throw { errors }
        }
      }

      try {
        // بدون تحديد Content-Type يدوياً: المتصفح بيضيف الـ boundary تلقائياً
        const response = await api.post('/products', toFormData(payload))
        return response.data.data ?? response.data
      } catch (error) {
        const parsed = parseApiError(error)
        this.error = parsed.message
        if (parsed.status === 422) this.validationErrors = parsed.fields
        throw error.response?.data || error.message
      } finally {
        this.loading = false
      }
    }
  }
})
