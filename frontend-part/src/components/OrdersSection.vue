<template>
  <section class="w-full space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap justify-between items-center gap-4">
      <div>
        <h2 class="text-2xl font-extrabold text-white">
          إدارة الطلبات
        </h2>

        <p class="text-sm text-slate-400 mt-1">
          عرض جميع الطلبات وإدارة حالتها
        </p>
      </div>

      <button
        @click="loadOrders(pagination.current_page)"
        :disabled="orderStore.loading"
        class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white rounded-xl text-sm font-bold transition-all"
      >
        {{ orderStore.loading ? 'جاري التحديث...' : 'تحديث الطلبات' }}
      </button>
    </div>

    <!-- Admin protection in UI -->
    <div
      v-if="!authStore.isAdmin"
      class="bg-rose-950/30 border border-rose-800 text-rose-300 rounded-xl p-4 text-sm"
    >
      ليس لديك صلاحية الوصول إلى إدارة الطلبات.
    </div>

    <template v-else>

      <!-- Statistics -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">

        <div class="bg-slate-800/60 border border-slate-700 rounded-2xl p-4">
          <p class="text-xs text-slate-400 mb-2">إجمالي الطلبات</p>
          <p class="text-2xl font-extrabold text-white">
            {{ pagination.total }}
          </p>
        </div>

        <div class="bg-slate-800/60 border border-slate-700 rounded-2xl p-4">
          <p class="text-xs text-slate-400 mb-2">قيد الانتظار</p>
          <p class="text-2xl font-extrabold text-amber-400">
            {{ pendingCount }}
          </p>
        </div>

        <div class="bg-slate-800/60 border border-slate-700 rounded-2xl p-4 col-span-2 md:col-span-1">
          <p class="text-xs text-slate-400 mb-2">طلبات الصفحة الحالية</p>
          <p class="text-2xl font-extrabold text-purple-400">
            {{ orders.length }}
          </p>
        </div>

      </div>

      <!-- Error -->
      <div
        v-if="orderStore.error"
        class="bg-rose-950/30 border border-rose-800 text-rose-300 rounded-xl p-4 text-sm"
      >
        {{ orderStore.error }}
      </div>

      <!-- Success -->
      <div
        v-if="feedback"
        class="bg-emerald-950/30 border border-emerald-800 text-emerald-300 rounded-xl p-4 text-sm"
      >
        {{ feedback }}
      </div>

      <!-- Loading -->
      <div
        v-if="orderStore.loading && !orders.length"
        class="text-center py-12 text-slate-400"
      >
        جاري تحميل الطلبات...
      </div>

      <!-- Empty -->
      <div
        v-else-if="!orders.length && !orderStore.loading"
        class="text-center py-12 bg-slate-800/40 border border-slate-800 rounded-2xl"
      >
        <p class="text-slate-300 font-bold">
          لا توجد طلبات حالياً
        </p>

        <p class="text-sm text-slate-500 mt-2">
          ستظهر الطلبات الجديدة هنا تلقائياً بعد تحديث القائمة.
        </p>
      </div>

      <!-- Orders List -->
      <div v-else class="space-y-4">

        <article
          v-for="order in orders"
          :key="order.id"
          class="bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden"
        >

          <!-- Order Header -->
          <div class="flex flex-wrap justify-between items-center gap-3 p-5 border-b border-slate-700">

            <div>
              <p class="text-white font-extrabold">
                الطلب #{{ order.id }}
              </p>

              <p class="text-xs text-slate-400 mt-1">
                العميل: {{ order.user?.name ?? order.user?.email ?? `#${order.user_id}` }}
              </p>

              <p
                v-if="order.created_at"
                class="text-xs text-slate-500 mt-1"
              >
                {{ formatDate(order.created_at) }}
              </p>
            </div>

            <span
              class="text-xs font-bold px-3 py-1.5 rounded-full border"
              :class="statusClass(order.status)"
            >
              {{ ORDER_STATUSES[order.status] || order.status }}
            </span>

          </div>

          <!-- Order Items -->
          <div class="p-5 space-y-3">

            <h3 class="text-xs font-bold text-slate-400">
              المنتجات المطلوبة
            </h3>

            <div
              v-if="order.items?.length"
              class="space-y-2"
            >
              <div
                v-for="(item, index) in order.items"
                :key="item.id ?? index"
                class="flex justify-between items-center gap-3 bg-slate-900/50 rounded-xl p-3"
              >
                <div>
                  <p class="text-sm text-slate-200 font-semibold">
                    {{ item.product?.name ?? item.name ?? `منتج #${item.product_id}` }}
                  </p>

                  <p class="text-xs text-slate-500 mt-1">
                    الكمية: {{ item.quantity }}
                  </p>
                </div>

                <p
                  v-if="item.unit_price != null"
                  class="text-sm text-slate-300 font-bold"
                >
                  {{ formatMoney(Number(item.unit_price) * Number(item.quantity)) }}
                </p>
              </div>
            </div>

            <p v-else class="text-sm text-slate-500">
              لا توجد تفاصيل للمنتجات في استجابة الطلب.
            </p>

            <!-- Total -->
            <div class="flex justify-between items-center pt-3 border-t border-slate-700">
              <span class="text-sm text-slate-400">
                إجمالي الطلب
              </span>

              <span class="text-lg font-extrabold text-white">
                {{ formatMoney(order.total_amount) }}
              </span>
            </div>

          </div>

          <!-- Update Status -->
          <div class="p-5 bg-slate-900/40 border-t border-slate-700">

            <label class="block text-xs text-slate-400 font-bold mb-2">
              تغيير حالة الطلب
            </label>

            <div class="flex flex-wrap gap-3">

              <select
                v-model="statusDrafts[order.id]"
                class="flex-1 min-w-[160px] bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500"
              >
                <option
                  v-for="(label, key) in ORDER_STATUSES"
                  :key="key"
                  :value="key"
                >
                  {{ label }}
                </option>
              </select>

              <button
                @click="saveStatus(order)"
                :disabled="
                  updatingOrderId === order.id ||
                  statusDrafts[order.id] === order.status
                "
                class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-bold transition-all"
              >
                {{
                  updatingOrderId === order.id
                    ? 'جاري الحفظ...'
                    : 'حفظ الحالة'
                }}
              </button>

            </div>

          </div>

        </article>

      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.last_page > 1"
        class="flex justify-between items-center gap-3 bg-slate-800/50 border border-slate-700 rounded-xl p-4"
      >

        <button
          @click="loadOrders(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1 || orderStore.loading"
          class="px-4 py-2 bg-slate-700 hover:bg-slate-600 disabled:opacity-30 text-white rounded-lg text-xs font-bold"
        >
          السابق
        </button>

        <span class="text-xs text-slate-400">
          صفحة {{ pagination.current_page }} من {{ pagination.last_page }}
        </span>

        <button
          @click="loadOrders(pagination.current_page + 1)"
          :disabled="
            pagination.current_page >= pagination.last_page ||
            orderStore.loading
          "
          class="px-4 py-2 bg-slate-700 hover:bg-slate-600 disabled:opacity-30 text-white rounded-lg text-xs font-bold"
        >
          التالي
        </button>

      </div>

    </template>

  </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'

import {
  useOrderStore,
  ORDER_STATUSES
} from '../stores/orders'

import { useAuthStore } from '../stores/auth'

const orderStore = useOrderStore()
const authStore = useAuthStore()

const feedback = ref('')
const updatingOrderId = ref(null)

const statusDrafts = reactive({})

const orders = computed(() => orderStore.orders)
const pagination = computed(() => orderStore.pagination)

const pendingCount = computed(() => {
  return orders.value.filter(order => order.status === 'PENDING').length
})

// جلب الطلبات
const loadOrders = async (page = 1) => {
  feedback.value = ''

  const result = await orderStore.fetchOrders(page)

  if (result) {
    result.forEach(order => {
      statusDrafts[order.id] = order.status
    })
  }
}

// حفظ حالة الطلب
const saveStatus = async (order) => {
  const newStatus = statusDrafts[order.id]

  if (!newStatus || newStatus === order.status) return

  updatingOrderId.value = order.id
  feedback.value = ''

  const success = await orderStore.updateStatus(
    order.id,
    newStatus
  )

  if (success) {
    feedback.value = `تم تحديث حالة الطلب #${order.id} بنجاح`
  } else {
    statusDrafts[order.id] = order.status
  }

  updatingOrderId.value = null
}

// تنسيق الحالة
const statusClass = (status) => {
  const classes = {
    PENDING: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
    CONFIRMED: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
    SHIPPED: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
    DELIVERED: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
    CANCELLED: 'bg-rose-500/10 text-rose-400 border-rose-500/20'
  }

  return classes[status] || 'bg-slate-500/10 text-slate-400 border-slate-500/20'
}

// تنسيق التاريخ
const formatDate = (date) => {
  return new Date(date).toLocaleString('ar', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// تنسيق المبلغ
const formatMoney = (amount) => {
  return Number(amount ?? 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

onMounted(() => {
  if (authStore.isAdmin) {
    loadOrders()
  }
})
</script>