<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Icon from '../components/Icon.vue'
import StatusBadge from '../components/StatusBadge.vue'
import PaginationBar from '../components/PaginationBar.vue'
import { useAdminOrdersStore } from '../stores/adminOrders'
import { useUiStore } from '../stores/ui'
import { connectionLabel, connectionState, getEcho } from '../plugins/echo'
import { formatDateTime, formatMoney } from '../utils/format'

const store = useAdminOrdersStore()
const ui = useUiStore()

const filters = [
  { key: 'all', label: 'الكل' },
  { key: 'pending', label: 'بانتظار القرار' },
  { key: 'approved', label: 'تمت الموافقة' },
  { key: 'rejected', label: 'مرفوض / ملغي' },
]

const conn = computed(() => connectionLabel(connectionState.value))
const toneClass = computed(
  () => ({ ok: 'text-emerald-300 bg-emerald-950/40 border-emerald-800/50', warn: 'text-amber-300 bg-amber-950/40 border-amber-800/50', bad: 'text-rose-300 bg-rose-950/40 border-rose-800/50' }[conn.value.tone]),
)
const dotClass = computed(() => ({ ok: 'bg-emerald-400', warn: 'bg-amber-400 animate-pulse', bad: 'bg-rose-400' }[conn.value.tone]))

const productsText = (o) =>
  (o.items || []).map((i) => `${i.product_name ?? `منتج #${i.product_id}`} ×${i.quantity}`).join('، ')

// ---------- الاشتراك بالقناة الخاصة admin.orders ----------
function subscribe() {
  const echo = getEcho()
  if (!echo) return
  echo
    .private('admin.orders')
    .listen('.order.created', (payload) => store.onCreated(payload))
    .listen('.order.updated', (payload) => store.onUpdated(payload))
    .error(() => ui.error('تعذّر الاشتراك بالقناة اللحظية: لا تملك صلاحية المسؤول'))
}

function unsubscribe() {
  getEcho()?.leave('admin.orders')
}

// بعد أي انقطاع واتصال جديد: نعيد تحميل الجدول حتى لا نفقد ما حصل أثناء الانقطاع
let wasDown = false
watch(connectionState, (now) => {
  if (now === 'connected') {
    if (wasDown && store.loaded) store.fetchOrders(store.meta.current_page, { silent: true })
    wasDown = false
  } else if (now !== 'connecting' && now !== 'initialized') {
    wasDown = true
  }
})

onMounted(async () => {
  subscribe() // نشترك أولاً ثم نحمّل، حتى لا يضيع طلب يصل بين الخطوتين
  await store.fetchOrders(1)
})
onBeforeUnmount(unsubscribe)
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white">لوحة الطلبات اللحظية</h2>
        <p class="text-sm text-slate-400 mt-1">الطلبات الجديدة وقرارات الموافقة تظهر هنا فوراً بدون تحديث الصفحة.</p>
      </div>

      <span class="inline-flex items-center gap-2 text-xs font-bold px-3 py-1.5 rounded-full border" :class="toneClass" data-testid="ws-status">
        <span class="w-2 h-2 rounded-full" :class="dotClass"></span>
        WebSocket: {{ conn.text }}
      </span>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="f in filters"
        :key="f.key"
        class="px-4 py-2 rounded-xl text-sm font-bold border transition"
        :class="store.filter === f.key ? 'bg-purple-600 border-purple-500 text-white' : 'bg-slate-800/80 border-slate-700 text-slate-300 hover:bg-slate-700'"
        @click="store.setFilter(f.key)"
      >{{ f.label }}</button>

      <span class="text-xs text-slate-500 ms-2">الإجمالي: {{ store.meta.total }}</span>

      <button class="btn btn-ghost !px-3 ms-auto" :disabled="store.loading" @click="store.fetchOrders(store.meta.current_page)">
        <Icon name="refresh" size="w-4 h-4" /> تحديث
      </button>
    </div>

    <p v-if="store.error" class="rounded-xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-sm p-3">{{ store.error }}</p>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-sm min-w-[52rem]">
          <thead class="bg-slate-950/60 text-slate-400 text-xs">
            <tr>
              <th class="p-4 text-start font-bold">رقم الطلب</th>
              <th class="p-4 text-start font-bold">العميل</th>
              <th class="p-4 text-start font-bold">المنتجات</th>
              <th class="p-4 text-start font-bold">الإجمالي</th>
              <th class="p-4 text-start font-bold">التاريخ</th>
              <th class="p-4 text-start font-bold">الحالة</th>
              <th class="p-4 text-start font-bold">القرار</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/70">
            <tr
              v-for="o in store.orders"
              :key="o.order_id"
              class="hover:bg-slate-800/30 transition"
              :class="{ 'row-fresh': store.fresh[o.order_id] }"
              :data-order-id="o.order_id"
            >
              <td class="p-4 font-extrabold text-white">#{{ o.order_id }}</td>
              <td class="p-4 text-slate-300">{{ o.customer_name ?? '—' }}</td>
              <td class="p-4 text-slate-400 max-w-[16rem] truncate" :title="productsText(o)">{{ productsText(o) || '—' }}</td>
              <td class="p-4 text-slate-200 whitespace-nowrap">{{ formatMoney(o.total) }}</td>
              <td class="p-4 text-slate-400 whitespace-nowrap">{{ formatDateTime(o.created_at) }}</td>
              <td class="p-4"><StatusBadge :status="o.status" /></td>
              <td class="p-4">
                <div v-if="o.status === 'PENDING'" class="flex items-center gap-2">
                  <button class="btn btn-primary !py-1.5 !px-3 text-xs" :disabled="store.isBusy(o.order_id)" @click="store.decide(o.order_id, 'approved')">
                    <Icon name="check" size="w-3.5 h-3.5" />
                    {{ store.busy[o.order_id] === 'approved' ? '...' : 'موافقة' }}
                  </button>
                  <button class="btn btn-danger-soft !py-1.5 !px-3 text-xs" :disabled="store.isBusy(o.order_id)" @click="store.decide(o.order_id, 'rejected')">
                    <Icon name="x" size="w-3.5 h-3.5" />
                    {{ store.busy[o.order_id] === 'rejected' ? '...' : 'رفض' }}
                  </button>
                </div>
                <span v-else class="text-xs text-slate-500">تم اتخاذ القرار</span>
              </td>
            </tr>
            <tr v-if="!store.orders.length">
              <td colspan="7" class="p-10 text-center text-slate-500">
                {{ store.loading ? 'جارٍ التحميل...' : 'لا توجد طلبات ضمن هذا الفلتر' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <PaginationBar :current="store.meta.current_page" :last="store.meta.last_page" :disabled="store.loading" @change="store.fetchOrders($event)" />
  </section>
</template>

<style scoped>
.row-fresh {
  animation: row-flash 2.5s ease-out;
}
@keyframes row-flash {
  0% { background-color: rgb(168 85 247 / 0.35); }
  100% { background-color: transparent; }
}
</style>
