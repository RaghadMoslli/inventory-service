import { defineStore } from 'pinia'
import api from '../api/axios'
import { parseApiError } from '../utils/apiError'
import { clearEtags } from '../utils/etagCache'

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

function toFormData(payload) {
  if (payload instanceof FormData) return payload
  const form = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    if (value === undefined || value === null || value === '') continue
    form.append(key, value)
  }
  return form
}

const clean = (params) =>
  Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))

let listRequest = null
let listKey = ''

export const useProductStore = defineStore('products', {
  state: () => ({
    products: [],
    byId: {}, 
    currentProduct: null,
    loaded: false,
    loading: false,
    saving: false,
    detailLoading: false,
    detailError: null,
    error: null,
    validationErrors: {},
    meta: { current_page: 1, per_page: 12, total: 0, last_page: 1 },
  }),

  actions: {
    // silent = تحديث بالخلفية: لا يمسح القائمة الحالية ولا يظهر مؤشر تحميل
    async fetchProducts(params = {}, { silent = false } = {}) {
      const key = JSON.stringify(params)
      if (listRequest && listKey === key) return listRequest
      if (!silent || !this.loaded) this.loading = true
      listKey = key

      listRequest = (async () => {
        try {
          const response = await api.get('/products', { params: clean(params) })
          // 304: content unchanged — keep current list, do not wipe state
          if (response.status === 304) {
            this.loaded = true
            this.error = null
            return true
          }
          const body = response.data
          const rows = Array.isArray(body) ? body : body.data ?? []
          this.products = rows
          rows.forEach((p) => { this.byId[p.id] = p })
          this.meta = {
            current_page: body.current_page ?? 1,
            per_page: body.per_page ?? this.meta.per_page,
            total: body.total ?? rows.length,
            last_page: body.last_page ?? 1,
          }
          this.loaded = true
          this.error = null
          return true
        } catch (error) {
          const message = parseApiError(error).message
          if (!silent || !this.products.length) this.error = message
          return false
        } finally {
          this.loading = false
          listRequest = null
        }
      })()
      return listRequest
    },

    async fetchProductDetails(id, { silent = false } = {}) {
      if (!silent) this.detailLoading = true
      this.detailError = null
      try {
        const response = await api.get(`/products/${id}`)
        if (response.status === 304) {
          return this.byId[id] ?? this.currentProduct
        }
        const product = response.data.data ?? response.data
        this.byId[product.id] = product
        this.currentProduct = product
        return product
      } catch (error) {
        this.detailError = parseApiError(error).message
        return null
      } finally {
        this.detailLoading = false
      }
    },

    // جلب أسماء/صور المنتجات الموجودة داخل الطلبات (الباك يرجع product_id فقط)
    async resolveProducts(ids) {
      const missing = [...new Set(ids)].filter((id) => !this.byId[id])
      await Promise.allSettled(
        missing.map(async (id) => {
          try {
            const { data } = await api.get(`/products/${id}`)
            this.byId[id] = data.data ?? data
          } catch (error) {
            if (error.response?.status === 404) this.byId[id] = { id, name: null, deleted: true }
          }
        })
      )
    },

    async createProduct(payload) {
      this.saving = true
      this.error = null
      this.validationErrors = {}

      if (!(payload instanceof FormData)) {
        const errors = validateProductPayload(payload)
        if (Object.keys(errors).length) {
          this.validationErrors = errors
          this.saving = false
          throw { status: 422, message: 'يرجى تصحيح الحقول المظللة', fields: errors }
        }
      }

      try {
        const { data } = await api.post('/products', toFormData(payload))
        const product = data.data ?? data
        this.byId[product.id] = product
        clearEtags()
        return product
      } catch (error) {
        const parsed = parseApiError(error)
        this.error = parsed.message
        if (parsed.status === 422) this.validationErrors = parsed.fields
        throw parsed
           } finally {
        this.saving = false
      }
    },

    // تعديل منتج (أدمن). يرجع { product, stockSynced }
    async updateProduct(id, payload) {
      const errors = validateProductPayload(payload)
      if (Object.keys(errors).length) {
        throw { status: 422, message: 'يرجى تصحيح الحقول المظللة', fields: errors }
      }

      const fields = {
        name: payload.name,
        description: payload.description ?? '',
        price: Number(payload.price),
        category: payload.category,
        initial_stock: Number(payload.initial_stock),
      }

      try {
        if (payload.image instanceof File) {
          // رفع صورة جديدة: multipart مع _method لأن PHP لا يقرأ multipart مع PATCH
          const form = toFormData({ ...fields, image: payload.image })
          form.append('_method', 'PATCH')
          await api.post(`/products/${id}`, form)
        } else {
          await api.patch(`/products/${id}`, fields)
        }
      } catch (error) {
        throw parseApiError(error)
      }

      clearEtags()

      // رد الـ PATCH لا يحتوي الكمية، فنجلبها من الباك (يقرأها من Inventory Service)
      const fresh = await this.fetchProductDetails(id, { silent: true })
      const stockSynced = !fresh || Number(fresh.available_quantity) === fields.initial_stock
      return { product: fresh, stockSynced }
    },

    // حذف منتج (أدمن)
    async deleteProduct(id) {
      try {
        const { data } = await api.delete(`/products/${id}`)
        this.products = this.products.filter((p) => p.id !== id)
        delete this.byId[id]
        this.meta.total = Math.max(0, this.meta.total - 1)
        clearEtags()
        return data?.message || 'تم حذف المنتج بنجاح'
      } catch (error) {
        throw parseApiError(error)
      }
    },
  },
})
    
