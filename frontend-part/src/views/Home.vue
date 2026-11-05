<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 selection:bg-purple-500 selection:text-white" dir="rtl">
    
    <header class="sticky top-0 z-40 backdrop-blur-xl bg-slate-900/80 border-b border-slate-800 shadow-lg shadow-slate-950/20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex justify-between items-center">
        
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </div>
          <h1 class="text-xl font-black bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent tracking-wide">
            منصة التجارة الإلكترونية
          </h1>
        </div>
        
        <div class="flex items-center gap-4 sm:gap-6">
          
          <div class="relative">
            <button 
              @click="toggleNotifications"
              class="relative p-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl focus:outline-none transition-all duration-200 border border-transparent hover:border-slate-700"
              title="الإشعارات"
            >
              <svg class="w-6 h-6 transition-transform hover:scale-105" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
   
              <span 
                v-if="notifStore.unreadCount > 0"
                class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-bold leading-none text-white bg-gradient-to-r from-rose-500 to-red-600 rounded-full shadow-md shadow-red-500/40 animate-pulse"
              >
                {{ notifStore.unreadCount }}
              </span>
            </button>
   
            <div 
              v-if="showNotifications" 
              class="absolute left-0 mt-3 w-80 sm:w-96 bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 z-50 overflow-hidden backdrop-blur-2xl ring-1 ring-white/10"
            >
              <div class="p-4 bg-slate-950/50 border-b border-slate-800 flex justify-between items-center">
                <span class="font-bold text-sm text-white flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                  الإشعارات
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-400 font-medium border border-purple-500/20">
                  {{ notifStore.unreadCount }} غير مقروء
                </span>
              </div>
   
              <div class="max-h-80 overflow-y-auto divide-y divide-slate-800/60 custom-scrollbar">
                <div v-if="notifStore.loading && !notifStore.notifications.length" class="p-8 text-center text-sm text-slate-400 flex flex-col items-center gap-2">
                  <div class="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
                  جاري التحميل...
                </div>
   
                <div 
                  v-for="item in notifStore.notifications" 
                  :key="item.id"
                  @click="handleReadNotification(item)"
                  :class="[
                    'p-4 text-sm cursor-pointer transition-all duration-200 hover:bg-slate-800/60 relative',
                    (!item.read_at && !item.is_read) ? 'bg-purple-950/20 font-semibold border-r-2 border-purple-500' : 'text-slate-400'
                  ]"
                >
                  <div class="flex justify-between items-start mb-1.5">
                    <p class="text-slate-200 text-xs font-bold tracking-wide">{{ item.title || 'إشعار جديد' }}</p>
                    <span class="text-[10px] text-slate-500 bg-slate-800 px-2 py-0.5 rounded-md">
                      {{ formatDate(item.created_at) }}
                    </span>
                  </div>
                  <p class="text-xs text-slate-400 leading-relaxed">{{ item.message || item.data?.message || item.content }}</p>
                </div>
   
                <div v-if="!notifStore.notifications.length && !notifStore.loading" class="p-8 text-center text-xs text-slate-500 flex flex-col items-center gap-2">
                  <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                  </svg>
                  لا توجد إشعارات حالياً
                </div>
              </div>
            </div>
          </div>
   
          <div class="hidden md:flex items-center gap-3 pl-3 border-l border-slate-800">
            <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-purple-400 text-sm shadow-inner">
              {{ (authStore.user?.name || 'م')[0] }}
            </div>
            <span class="text-slate-300 font-medium text-sm">
              مرحباً، {{ authStore.user?.name || 'مستخدم' }}
            </span>
          </div>
  
          <button 
            @click="handleLogout" 
            class="bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white px-4 py-2 rounded-xl text-sm font-medium shadow-lg shadow-red-600/20 hover:shadow-red-600/40 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition-all duration-200 flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>تسجيل الخروج</span>
          </button>
        </div>
      </div>
    </header>
 
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

      <div class="mb-6 inline-flex gap-1 p-1 bg-slate-800/60 border border-slate-800 rounded-2xl">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-5 py-2 rounded-xl text-xs font-bold transition-all',
            activeTab === tab.key ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <template v-if="activeTab === 'products'">

      <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-slate-800/40 backdrop-blur-xl border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold border border-emerald-500/20 mb-3">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            حالة الاتصال: نشط وآمن
          </div>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">استعرض المنتجات المتوفرة</h2>
          <p class="text-slate-400 text-sm mt-1">تصفح أحدث المنتجات مع الكميات المتاحة مباشرة من المستودع.</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button 
            @click="filterByCategory('')"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-bold transition-all',
              selectedCategory === '' ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700'
            ]"
          >
            الكل
          </button>
          <button 
            v-for="cat in categories" 
            :key="cat"
            @click="filterByCategory(cat)"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-bold transition-all',
              selectedCategory === cat ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700'
            ]"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <div v-if="productStore.loading && !productStore.products.length" class="flex flex-col items-center justify-center py-20 gap-3">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-slate-400 text-sm">جاري جلب المنتجات...</p>
      </div>

      <div v-else-if="productStore.error" class="bg-rose-950/30 border border-rose-800 text-rose-300 p-6 rounded-2xl text-center mb-8">
        <p class="font-bold text-base mb-1">حدث خطأ أثناء تحميل المنتجات</p>
        <p class="text-xs text-rose-400">{{ productStore.error }}</p>
      </div>

      <div v-else-if="productStore.products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <div 
          v-for="product in productStore.products" 
          :key="product.id"
          class="bg-slate-800/60 backdrop-blur-xl border border-slate-800 rounded-2xl overflow-hidden shadow-lg hover:border-slate-700 transition-all duration-300 flex flex-col group"
        >
          <div class="h-48 bg-slate-900/60 relative overflow-hidden flex items-center justify-center border-b border-slate-800">
            <img 
              v-if="product.image_path" 
              :src="product.image_path" 
              :alt="product.name" 
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            >
            <div v-else class="text-slate-600 flex flex-col items-center gap-1">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span class="text-[10px]">بدون صورة</span>
            </div>
            
            <span class="absolute top-3 right-3 text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-900/80 text-purple-300 backdrop-blur-md border border-slate-700">
              {{ product.category }}
            </span>
          </div>

          <div class="p-5 flex-1 flex flex-col justify-between">
            <div>
              <h3 class="font-bold text-white text-base mb-1.5 group-hover:text-purple-400 transition-colors line-clamp-1">
                {{ product.name }}
              </h3>
              <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed mb-4">
                {{ product.description }}
              </p>
            </div>

            <div>
              <div class="flex items-center justify-between mb-4 pt-3 border-t border-slate-800/80">
                <span class="text-emerald-400 font-extrabold text-lg">
                  {{ product.price }} <span class="text-xs font-normal text-slate-400">$</span>
                </span>
                
                <span 
                  :class="[
                    'text-[11px] font-bold px-2.5 py-1 rounded-full border',
                    (product.available_quantity > 0) 
                      ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' 
                      : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                  ]"
                >
                  {{ product.available_quantity > 0 ? `المتوفر: ${product.available_quantity}` : 'نفد المنتج' }}
                </span>
              </div>

              <button
  @click="openProductDetails(product)"
  :disabled="product.available_quantity === 0"
  class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:from-slate-800 disabled:to-slate-800 disabled:text-slate-600 disabled:cursor-not-allowed text-white py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-purple-600/20 transition-all flex items-center justify-center gap-2"
>
  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      stroke-width="2"
      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
    />
  </svg>
  عرض التفاصيل والطلب
</button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-20 bg-slate-800/30 rounded-3xl border border-slate-800">
        <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
        <p class="text-slate-400 font-medium text-sm">لا توجد منتجات مطابقة للعرض حالياً.</p>
      </div>

      <div v-if="productStore.meta.last_page > 1" class="flex justify-center items-center gap-3 mt-10">
        <button 
          @click="changePage(productStore.meta.current_page - 1)"
          :disabled="productStore.meta.current_page === 1"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition-all"
        >
          السابق
        </button>
        <span class="text-xs text-slate-400 font-medium">
          صفحة {{ productStore.meta.current_page }} من {{ productStore.meta.last_page }}
        </span>
        <button 
          @click="changePage(productStore.meta.current_page + 1)"
          :disabled="productStore.meta.current_page === productStore.meta.last_page"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition-all"
        >
          التالي
        </button>
      </div>

      </template>

      <OrdersSection v-else />

      <!-- Product Details & Create Order Modal -->
<div
  v-if="showProductModal"
  class="fixed inset-0 z-[100] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
  @click.self="closeProductModal"
>
  <div
    class="w-full max-w-lg bg-slate-900 border border-slate-700 rounded-3xl shadow-2xl overflow-hidden"
    dir="rtl"
  >
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-5 border-b border-slate-800">
      <h3 class="text-lg font-extrabold text-white">
        تفاصيل المنتج
      </h3>

      <button
        @click="closeProductModal"
        class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300"
        aria-label="إغلاق"
      >
        ✕
      </button>
    </div>

    <!-- Loading -->
    <div
      v-if="productStore.loading && !productStore.currentProduct"
      class="p-10 text-center text-slate-400"
    >
      <div class="w-8 h-8 mx-auto mb-3 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
      جاري تحميل التفاصيل...
    </div>

    <!-- Error -->
    <div
      v-else-if="productStore.error && !productStore.currentProduct"
      class="p-6 text-center text-rose-300 text-sm"
    >
      {{ productStore.error }}
    </div>

    <!-- Product Details -->
    <div v-else-if="productStore.currentProduct" class="p-5">
      <div class="h-52 mb-5 bg-slate-800 rounded-2xl overflow-hidden flex items-center justify-center">
        <img
          v-if="productStore.currentProduct.image_path"
          :src="productStore.currentProduct.image_path"
          :alt="productStore.currentProduct.name"
          class="w-full h-full object-cover"
        />

        <span v-else class="text-slate-500 text-sm">
          لا توجد صورة للمنتج
        </span>
      </div>

      <div class="space-y-3">
        <div>
          <h4 class="text-xl font-extrabold text-white">
            {{ productStore.currentProduct.name }}
          </h4>

          <p class="text-xs text-purple-400 mt-1">
            {{ productStore.currentProduct.category }}
          </p>
        </div>

        <p class="text-sm text-slate-300 leading-7">
          {{ productStore.currentProduct.description || 'لا يوجد وصف لهذا المنتج.' }}
        </p>

        <div class="flex justify-between items-center py-3 border-y border-slate-800">
          <span class="text-slate-400 text-sm">السعر</span>
          <span class="text-emerald-400 font-extrabold text-lg">
            {{ productStore.currentProduct.price }} $
          </span>
        </div>

        <div class="flex justify-between items-center">
          <span class="text-slate-400 text-sm">الكمية المتوفرة</span>
          <span class="text-indigo-400 font-bold text-sm">
            {{ productStore.currentProduct.available_quantity ?? selectedProduct?.available_quantity ?? 0 }}
          </span>
        </div>

        <!-- Quantity -->
        <div class="pt-2">
          <label class="block text-sm text-slate-300 mb-2">
            الكمية المطلوبة
          </label>

          <input
            v-model.number="orderQuantity"
            type="number"
            min="1"
            :max="productStore.currentProduct.available_quantity ?? selectedProduct?.available_quantity ?? 1"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>

        <!-- Order Error -->
        <div
          v-if="orderStore.error"
          class="p-3 rounded-xl bg-rose-950/30 border border-rose-800 text-rose-300 text-xs"
        >
          {{ orderStore.error }}
        </div>

        <!-- Success -->
        <div
          v-if="orderSuccess"
          class="p-3 rounded-xl bg-emerald-950/30 border border-emerald-800 text-emerald-300 text-sm"
        >
          <p class="font-bold">تم إنشاء الطلب بنجاح!</p>
          <p v-if="createdOrder?.id" class="mt-1">
            رقم الطلب: #{{ createdOrder.id }}
          </p>
        </div>

        <!-- Submit -->
        <button
          @click="submitOrder"
          :disabled="orderStore.loading || !canSubmitOrder || !!orderSuccess"
          class="w-full mt-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white py-3 rounded-xl font-bold text-sm transition-all"
        >
          {{ orderStore.loading ? 'جاري إنشاء الطلب...' : orderSuccess ? 'تم إنشاء الطلب' : 'تأكيد الطلب' }}
        </button>
      </div>
    </div>
  </div>
</div>

    </main>
  </div>
</template>
 
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import { useProductStore } from '../stores/products'
import OrdersSection from '../components/OrdersSection.vue'
import { useRouter } from 'vue-router'
import { useOrderStore } from '../stores/orders'
 
const authStore = useAuthStore()
const notifStore = useNotificationStore()
const productStore = useProductStore()
const orderStore = useOrderStore()

const showProductModal = ref(false)
const selectedProduct = ref(null)
const orderQuantity = ref(1)
const orderSuccess = ref(false)
const createdOrder = ref(null)

const canSubmitOrder = computed(() => {
  const product = productStore.currentProduct
  if (!product) return false

  const available = Number(
    product.available_quantity ?? selectedProduct.value?.available_quantity ?? 0
  )

  return (
    Number.isInteger(orderQuantity.value) &&
    orderQuantity.value >= 1 &&
    orderQuantity.value <= available
  )
})
const router = useRouter()
 
const showNotifications = ref(false)
const activeTab = ref('products')
const tabs = computed(() => {
  const items = [
    { key: 'products', label: 'المنتجات' }
  ]

  if (authStore.isAdmin) {
    items.push({ key: 'orders', label: 'إدارة الطلبات' })
  }

  return items
})
const selectedCategory = ref('')
const categories = ['Books', 'Electronics', 'Home', 'Clothing']
let pollingTimer = null
 
const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
}
 
const handleReadNotification = async (item) => {
  if (!item.read_at && !item.is_read) {
    await notifStore.markAsRead(item.id)
  }
}

const openProductDetails = async (product) => {
  selectedProduct.value = product
  orderQuantity.value = 1
  orderSuccess.value = false
  createdOrder.value = null
  showProductModal.value = true

  await productStore.fetchProductDetails(product.id)
}

const closeProductModal = () => {
  showProductModal.value = false
  selectedProduct.value = null
  orderQuantity.value = 1
  orderSuccess.value = false
  createdOrder.value = null
  productStore.currentProduct = null
  orderStore.error = null
}

const submitOrder = async () => {
  if (!canSubmitOrder.value) return

  orderSuccess.value = false
  createdOrder.value = null

  const product = productStore.currentProduct

  const result = await orderStore.createOrder(
    product.id,
    orderQuantity.value
  )

  if (result) {
    createdOrder.value = result
    orderSuccess.value = true

    // تحديث قائمة المنتجات بعد إنشاء الطلب
    await productStore.fetchProducts({
      page: productStore.meta.current_page,
      category: selectedCategory.value || undefined
    })
  }
}
 
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
}

const filterByCategory = async (category) => {
  selectedCategory.value = category
  await productStore.fetchProducts({ page: 1, category: category || undefined })
}

const changePage = async (page) => {
  if (page >= 1 && page <= productStore.meta.last_page) {
    await productStore.fetchProducts({ page, category: selectedCategory.value || undefined })
  }
}
 
const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
 
onMounted(() => {
  notifStore.fetchNotifications()
  productStore.fetchProducts({ page: 1 })
  
  pollingTimer = setInterval(() => {
    notifStore.fetchNotifications()
  }, 30000)
})
 
onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer)
})
</script>