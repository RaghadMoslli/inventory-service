<script setup>
import { ref, computed, reactive } from 'vue'
import Icon from './Icon.vue'
import OrderCard from './OrderCard.vue'
import OrdersTable from './OrdersTable.vue'
import EmptyState from './EmptyState.vue'
import PaginationBar from './PaginationBar.vue'
import ConfirmModal from './ConfirmModal.vue'
import { useOrderStore } from '../stores/orders'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { useAutoRefresh } from '../composables/useAutoRefresh'
import { ORDER_STATUSES } from '../utils/constants'
import { formatTime } from '../utils/format'

const orders = useOrderStore()
const auth = useAuthStore()
const ui = useUiStore()

const filter = ref('ALL')
const busyId = ref(null)
const lastUpdated = ref(null)
const confirm = reactive({ open: false, order: null, status: null })

const visible = computed(() => (filter.value === 'ALL' ? orders.orders : orders.orders.filter((o) => o.status === filter.value)))

async function load(page = orders.pagination.current_page || 1, silent = true) {
  const result = await orders.fetchOrders(page, { silent })
  if (result) lastUpdated.value = new Date()
}

// تحميل فور فتح الصفحة، ثم تحديث كل 15 ثانية وعند العودة للتبويب
useAutoRefresh(() => load(), 15000)

function askCancel(order) {
  confirm.order = order
  confirm.status = 'CANCELLED'
  confirm.open = true
}
function requestStatus(order, status) {
  if (status === 'CANCELLED') askCancel(order)
  else run(order, status)
}

async function run(order, status) {
  busyId.value = order.id
  const ok = status === 'CANCELLED' ? await orders.cancelOrder(order.id) : await orders.updateStatus(order.id, status)
  busyId.value = null
  if (ok) ui.success(status === 'CANCELLED' ? `تم إلغاء الطلب #${order.id} وإرجاع الكمية للمخزون` : `تم تحديث حالة الطلب #${order.id} إلى «${ORDER_STATUSES[status]}»`)
  else ui.error(orders.error)
}

async function confirmRun() {
  const { order, status } = confirm
  confirm.open = false
  await run(order, status)
}
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white">{{ auth.isAdmin ? 'إدارة الطلبات' : 'طلباتي' }}</h2>
        <p class="text-sm text-slate-400 mt-1">
          {{ auth.isAdmin ? 'جميع طلبات العملاء وإدارة حالتها.' : 'تابع حالة طلباتك لحظة بلحظة.' }}
          <span v-if="orders.pagination.total" class="text-slate-500">({{ orders.pagination.total }} طلب)</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="lastUpdated" class="text-[11px] text-slate-500">آخر تحديث {{ formatTime(lastUpdated) }}</span>
        <button class="btn btn-ghost" :disabled="orders.loading" @click="load(orders.pagination.current_page, false)">
          <Icon name="refresh" size="w-4 h-4" :class="orders.loading ? 'animate-spin' : ''" />تحديث
        </button>
      </div>
    </div>

    <div v-if="orders.orders.length" class="flex flex-wrap gap-2">
      <button class="chip" :class="{ 'chip-active': filter === 'ALL' }" @click="filter = 'ALL'">الكل</button>
      <button v-for="(label, key) in ORDER_STATUSES" :key="key" class="chip" :class="{ 'chip-active': filter === key }" @click="filter = key">{{ label }}</button>
    </div>

    <!-- تحميل أولي -->
    <div v-if="orders.loading && !orders.loaded" class="space-y-4">
      <div v-for="n in 3" :key="n" class="skeleton h-56"></div>
    </div>

    <!-- خطأ بدون بيانات -->
    <EmptyState v-else-if="orders.error && !orders.orders.length" icon="alert" title="تعذّر تحميل الطلبات" :text="orders.error">
      <button class="btn btn-primary" @click="load(1, false)">إعادة المحاولة</button>
    </EmptyState>

    <EmptyState v-else-if="!orders.orders.length" icon="orders" :title="auth.isAdmin ? 'لا توجد طلبات بعد' : 'لم تقم بأي طلب بعد'" :text="auth.isAdmin ? 'ستظهر الطلبات الجديدة هنا تلقائياً.' : 'تصفح المنتجات واطلب ما يعجبك، وستجد طلباتك هنا.'">
      <router-link v-if="!auth.isAdmin" to="/" class="btn btn-primary">تصفح المنتجات</router-link>
    </EmptyState>

    <template v-else>
      <p v-if="orders.error" class="rounded-xl bg-amber-950/30 border border-amber-800/60 text-amber-300 text-xs px-4 py-3">تعذّر تحديث القائمة، تُعرض آخر بيانات محمّلة. {{ orders.error }}</p>

      <!-- الأدمن: جدول على الشاشات الكبيرة، وبطاقات على الجوال -->
      <template v-if="auth.isAdmin">
        <div class="hidden md:block">
          <OrdersTable v-if="visible.length" :orders="visible" :busy-id="busyId" @save-status="requestStatus" />
        </div>
        <div class="md:hidden space-y-4">
          <OrderCard v-for="o in visible" :key="o.id" :order="o" is-admin :busy="busyId === o.id" @save-status="requestStatus" />
        </div>
      </template>

      <!-- العميل: بطاقات -->
      <div v-else class="space-y-4">
        <OrderCard v-for="o in visible" :key="o.id" :order="o" :busy="busyId === o.id" @cancel="askCancel" />
      </div>
      <p v-if="!visible.length" class="text-center text-sm text-slate-500 py-10">لا توجد طلبات بهذه الحالة في هذه الصفحة.</p>

      <PaginationBar :current="orders.pagination.current_page" :last="orders.pagination.last_page" :disabled="orders.loading" @change="(p) => load(p, false)" />
    </template>

    <ConfirmModal
      :open="confirm.open"
      title="إلغاء الطلب"
      :message="`سيتم إلغاء الطلب #${confirm.order?.id ?? ''} وإرجاع الكمية المحجوزة إلى المخزون. لا يمكن التراجع عن هذه العملية.`"
      confirm-text="نعم، إلغاء الطلب"
      danger
      @confirm="confirmRun"
      @close="confirm.open = false"
    />
  </section>
</template>
