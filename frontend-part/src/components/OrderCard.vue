<script setup>
import { ref, computed, watch } from 'vue'
import Icon from './Icon.vue'
import StatusBadge from './StatusBadge.vue'
import { useProductStore } from '../stores/products'
import { ORDER_STATUSES, STATUS_FLOW, CANCELLABLE_STATUSES } from '../utils/constants'
import { formatMoney, formatDateTime, imageUrl } from '../utils/format'

const props = defineProps({ order: { type: Object, required: true }, isAdmin: Boolean, busy: Boolean })
const emit = defineEmits(['cancel', 'save-status'])

const products = useProductStore()
const draft = ref(props.order.status)
watch(() => props.order.status, (s) => { draft.value = s })

const productOf = (id) => products.byId[id]
const stepIndex = computed(() => STATUS_FLOW.indexOf(props.order.status))
const progressWidth = computed(() => `${(Math.max(stepIndex.value, 0) / (STATUS_FLOW.length - 1)) * 75}%`)
const cancellable = computed(() => CANCELLABLE_STATUSES.includes(props.order.status))
const itemsCount = computed(() => (props.order.items || []).reduce((sum, i) => sum + Number(i.quantity), 0))
</script>

<template>
  <article class="card overflow-hidden">
    <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-800">
      <div>
        <p class="text-white font-extrabold">طلب #{{ order.id }}</p>
        <p class="text-xs text-slate-500 mt-1">
          {{ formatDateTime(order.created_at) }}
          <template v-if="isAdmin"> — {{ order.user?.name ?? `العميل #${order.user_id}` }}</template>
        </p>
      </div>
      <StatusBadge :status="order.status" />
    </header>

    <div class="p-5 space-y-5">
      <!-- مراحل الطلب -->
      <div v-if="order.status !== 'CANCELLED'" class="relative pt-1">
        <div class="absolute top-[1.0625rem] h-0.5 bg-slate-800 inset-x-[12.5%]"></div>
        <div class="absolute top-[1.0625rem] h-0.5 bg-purple-500 transition-all duration-500" :style="{ width: progressWidth, insetInlineStart: '12.5%' }"></div>
        <ol class="relative grid grid-cols-4">
          <li v-for="(s, i) in STATUS_FLOW" :key="s" class="flex flex-col items-center gap-1.5">
            <span class="relative z-10 w-6 h-6 rounded-full grid place-items-center text-white transition" :class="i <= stepIndex ? 'bg-purple-500' : 'bg-slate-800 text-slate-600'">
              <Icon v-if="i <= stepIndex" name="check" size="w-3 h-3" />
            </span>
            <span class="text-[11px] text-center" :class="i === stepIndex ? 'text-white font-bold' : 'text-slate-500'">{{ ORDER_STATUSES[s] }}</span>
          </li>
        </ol>
      </div>
      <p v-else class="rounded-xl bg-rose-950/30 border border-rose-900/60 text-rose-300 text-xs px-4 py-3">تم إلغاء هذا الطلب وإرجاع الكمية المحجوزة إلى المخزون.</p>

      <!-- المنتجات -->
      <ul class="space-y-2">
        <li v-for="item in order.items" :key="item.id" class="flex items-center gap-3 rounded-xl bg-slate-950/40 border border-slate-800/70 p-3">
          <span class="w-12 h-12 shrink-0 rounded-lg bg-slate-800 overflow-hidden grid place-items-center text-slate-600">
            <img v-if="imageUrl(productOf(item.product_id)?.image_path)" :src="imageUrl(productOf(item.product_id).image_path)" alt="" class="w-full h-full object-cover" />
            <Icon v-else name="cube" size="w-5 h-5" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-sm font-semibold text-slate-100 truncate">{{ productOf(item.product_id)?.name ?? `منتج #${item.product_id}` }}</span>
            <span class="block text-xs text-slate-500 mt-0.5">{{ item.quantity }} × {{ formatMoney(item.unit_price) }}</span>
          </span>
          <span class="text-sm font-bold text-slate-200">{{ formatMoney(Number(item.unit_price) * Number(item.quantity)) }}</span>
        </li>
      </ul>

      <div class="flex items-center justify-between pt-1">
        <span class="text-sm text-slate-400">الإجمالي ({{ itemsCount }} قطعة)</span>
        <span class="text-xl font-extrabold text-white">{{ formatMoney(order.total_amount) }}</span>
      </div>
    </div>

    <!-- إجراءات الأدمن -->
    <footer v-if="isAdmin" class="px-5 py-4 bg-slate-950/40 border-t border-slate-800 flex flex-wrap gap-3 items-center">
      <label class="text-xs font-bold text-slate-400">الحالة</label>
      <select v-model="draft" class="input !w-auto flex-1 min-w-[10rem] !py-2.5">
        <option v-for="(label, key) in ORDER_STATUSES" :key="key" :value="key">{{ label }}</option>
      </select>
      <button class="btn btn-primary" :disabled="busy || draft === order.status" @click="emit('save-status', order, draft)">
        {{ busy ? 'جاري الحفظ...' : 'حفظ الحالة' }}
      </button>
    </footer>

    <!-- إجراءات العميل -->
    <footer v-else-if="cancellable" class="px-5 py-4 bg-slate-950/40 border-t border-slate-800 flex justify-end">
      <button class="btn btn-danger-soft" :disabled="busy" @click="emit('cancel', order)">
        <Icon name="x" size="w-4 h-4" />{{ busy ? 'جاري الإلغاء...' : 'إلغاء الطلب' }}
      </button>
    </footer>
  </article>
</template>
