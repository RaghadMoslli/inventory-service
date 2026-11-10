<script setup>
import { ref, computed } from 'vue'
import Icon from '../components/Icon.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductModal from '../components/ProductModal.vue'
import EmptyState from '../components/EmptyState.vue'
import PaginationBar from '../components/PaginationBar.vue'
import { useProductStore } from '../stores/products'
import { useOrderStore } from '../stores/orders'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useAutoRefresh } from '../composables/useAutoRefresh'
import { BASE_CATEGORIES, categoryLabel } from '../utils/constants'

const PER_PAGE = 12
const products = useProductStore()
const orders = useOrderStore()
const auth = useAuthStore()
const ui = useUiStore()

const selectedCategory = ref('')
const selectedProduct = ref(null)

// التصنيفات الأساسية + أي تصنيف جديد أضافه الأدمن
const categories = computed(() => {
  const found = Object.values(products.byId).map((p) => p.category).filter(Boolean)
  return [...new Set([...BASE_CATEGORIES, ...found])]
})

function load(page = products.meta.current_page || 1, silent = true) {
  return products.fetchProducts({ page, per_page: PER_PAGE, category: selectedCategory.value }, { silent })
}

// تُجلب القائمة عند فتح الصفحة وعند العودة إليها من أي تبويب، وتتحدث دورياً بصمت
useAutoRefresh(() => load(), 30000)

function filterBy(category) {
  selectedCategory.value = category
  load(1, false)
}

function onOrdered() {
  ui.success('تم إنشاء طلبك بنجاح')
  load() // تحديث الكميات المتوفرة
  orders.fetchOrders(1, { silent: true }) // تحديث قائمة الطلبات
}
</script>

<template>
  <div class="space-y-8">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white">المنتجات</h2>
        <p class="text-sm text-slate-400 mt-1">
          تصفّح المنتجات مع الكميات المتاحة مباشرة من المستودع.
          <span v-if="products.meta.total" class="text-slate-500">({{ products.meta.total }} منتج)</span>
        </p>
      </div>
      <div class="flex gap-3">
        <button class="btn btn-ghost" :disabled="products.loading" @click="load(products.meta.current_page, false)">
          <Icon name="refresh" size="w-4 h-4" :class="products.loading ? 'animate-spin' : ''" />تحديث
        </button>
        <router-link v-if="auth.isAdmin" to="/admin/products/create" class="btn btn-primary">
          <Icon name="plus" size="w-4 h-4" />إضافة منتج
        </router-link>
      </div>
    </section>

    <div class="flex flex-wrap gap-2">
      <button class="chip" :class="{ 'chip-active': selectedCategory === '' }" @click="filterBy('')">الكل</button>
      <button v-for="c in categories" :key="c" class="chip" :class="{ 'chip-active': selectedCategory === c }" @click="filterBy(c)">{{ categoryLabel(c) }}</button>
    </div>

    <!-- تحميل أولي -->
    <div v-if="products.loading && !products.products.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="n in 8" :key="n" class="skeleton h-80"></div>
    </div>

    <EmptyState v-else-if="products.error && !products.products.length" icon="alert" title="تعذّر تحميل المنتجات" :text="products.error">
      <button class="btn btn-primary" @click="load(1, false)">إعادة المحاولة</button>
    </EmptyState>

    <EmptyState v-else-if="!products.products.length" icon="inbox" title="لا توجد منتجات" text="لا توجد منتجات مطابقة للعرض حالياً.">
      <button v-if="selectedCategory" class="btn btn-ghost" @click="filterBy('')">عرض كل المنتجات</button>
    </EmptyState>

    <template v-else>
      <p v-if="products.error" class="rounded-xl bg-amber-950/30 border border-amber-800/60 text-amber-300 text-xs px-4 py-3">تعذّر تحديث القائمة، تُعرض آخر بيانات محمّلة.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 transition-opacity" :class="{ 'opacity-60': products.loading }">
        <ProductCard v-for="p in products.products" :key="p.id" :product="p" @open="selectedProduct = $event" />
      </div>
      <PaginationBar :current="products.meta.current_page" :last="products.meta.last_page" :disabled="products.loading" @change="(p) => load(p, false)" />
    </template>

    <ProductModal :product="selectedProduct" @close="selectedProduct = null" @ordered="onOrdered" />
  </div>
</template>
