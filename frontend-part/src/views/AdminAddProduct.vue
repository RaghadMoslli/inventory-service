<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 py-10 px-4 sm:px-6 lg:px-8" dir="rtl">
    <div class="max-w-3xl mx-auto">
      
      <!-- رأس الصفحة -->
      <div class="mb-8 flex justify-between items-center bg-slate-800/50 border border-slate-800 p-6 rounded-3xl shadow-xl backdrop-blur-xl">
        <div>
          <button 
            @click="handleLogout" 
            class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>تسجيل الخروج</span>
          </button>
          <span class="px-3 py-1 rounded-full bg-purple-500/10 text-purple-400 text-xs font-semibold border border-purple-500/20">
            لوحة تحكم المشرف (Admin)
          </span>
          <h2 class="text-2xl font-extrabold text-white mt-2">إضافة منتج جديد</h2>
          <p class="text-slate-400 text-xs mt-1">أدخل تفاصيل المنتج والكمية الأولية للمستودع.</p>
        </div>
        <router-link 
          to="/" 
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold border border-slate-700 transition-all"
        >
          العودة للرئيسية
        </router-link>
      </div>

      <!-- نموذج الإضافة -->
      <form @submit.prevent="handleSubmit" class="bg-slate-800/40 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl space-y-6">
        
        <!-- اسم المنتج -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-2">اسم المنتج</label>
          <input 
            v-model="form.name" 
            type="text" 
            placeholder="مثال: كتاب البرمجة المتقدمة"
            class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
          />
          <p v-if="productStore.validationErrors.name" class="text-rose-400 text-xs mt-1">
            {{ productStore.validationErrors.name[0] }}
          </p>
        </div>

        <!-- التصنيف والسعر -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-2">التصنيف</label>
            <select 
              v-model="form.category" 
              class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
            >
              <option disabled value="">اختر التصنيف</option>
              <option value="Books">Books</option>
              <option value="Electronics">Electronics</option>
              <option value="Home">Home</option>
              <option value="Clothing">Clothing</option>
            </select>
            <p v-if="productStore.validationErrors.category" class="text-rose-400 text-xs mt-1">
              {{ productStore.validationErrors.category[0] }}
            </p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-2">السعر ($)</label>
            <input 
              v-model="form.price" 
              type="number" 
              step="0.01" 
              placeholder="0.00"
              class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
            />
            <p v-if="productStore.validationErrors.price" class="text-rose-400 text-xs mt-1">
              {{ productStore.validationErrors.price[0] }}
            </p>
          </div>
        </div>

        <!-- الكمية الأولية للمستودع (initial_stock) -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-2">الكمية الأولية في المستودع (initial_stock)</label>
          <input 
            v-model="form.initial_stock" 
            type="number" 
            min="1" 
            placeholder="مثال: 50"
            class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
          />
          <p v-if="productStore.validationErrors.initial_stock" class="text-rose-400 text-xs mt-1">
            {{ productStore.validationErrors.initial_stock[0] }}
          </p>
        </div>

        <!-- الوصف -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-2">وصف المنتج</label>
          <textarea 
            v-model="form.description" 
            rows="4" 
            placeholder="اكتب وصفاً تفصيلياً للمنتج..."
            class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition-colors"
          ></textarea>
          <p v-if="productStore.validationErrors.description" class="text-rose-400 text-xs mt-1">
            {{ productStore.validationErrors.description[0] }}
          </p>
        </div>

        <!-- رفع الصورة -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-2">صورة المنتج</label>
          <input 
            type="file" 
            @change="handleFileChange" 
            accept="image/jpeg,image/png,image/jpg,image/webp"
            class="w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white hover:file:bg-purple-500 cursor-pointer bg-slate-900/80 border border-slate-700 rounded-xl p-2"
          />
          <p v-if="productStore.validationErrors.image" class="text-rose-400 text-xs mt-1">
            {{ productStore.validationErrors.image[0] }}
          </p>
        </div>

        <!-- رسالة الخطأ العامة -->
        <div v-if="productStore.error && typeof productStore.error === 'string'" class="bg-rose-950/40 border border-rose-800 text-rose-300 p-4 rounded-xl text-xs">
          {{ productStore.error }}
        </div>

        <!-- زر الحفظ -->
        <button 
          type="submit" 
          :disabled="productStore.loading"
          class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-50 text-white py-3.5 rounded-xl text-sm font-bold shadow-lg shadow-purple-600/30 transition-all flex items-center justify-center gap-2"
        >
          <div v-if="productStore.loading" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <span>حفظ وإنشاء المنتج</span>
        </button>

      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { useProductStore } from '../stores/products'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const productStore = useProductStore()
const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  name: '',
  description: '',
  price: '',
  category: '',
  initial_stock: '',
  image: null
})

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    form.image = file
  }
}

const handleSubmit = async () => {
  const formData = new FormData()
  formData.append('name', form.name)
  formData.append('description', form.description)
  formData.append('price', form.price)
  formData.append('category', form.category)
  if (form.initial_stock) {
    formData.append('initial_stock', form.initial_stock)
  }
  if (form.image) {
    formData.append('image', form.image)
  }

  try {
    await productStore.createProduct(formData)
    alert('تم إضافة المنتج بنجاح وتحديد الكمية الأولية في المستودع!')
    router.push('/')
  } catch (err) {
    console.error('فشل في إنشاء المنتج:', err)
  }
}
</script>