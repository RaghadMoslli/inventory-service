<script setup>
import { reactive, watch, computed } from 'vue'
import Icon from './Icon.vue'
import StatusBadge from './StatusBadge.vue'
import { useProductStore } from '../stores/products'
import { ORDER_STATUSES } from '../utils/constants'
import { formatMoney, formatDateTime, imageUrl } from '../utils/format'

const props = defineProps({ orders: { type: Array, required: true }, busyId: { type: [Number, String], default: null } })
const emit = defineEmits(['save-status'])

const products = useProductStore()
const open = reactive({})
const drafts = reactive({})

const productOf = (id) => products.byId[id]
const nameOf = (item) => productOf(item.product_id)?.name ?? `منتج #${item.product_id}`
const draftOf = (o) => drafts[o.id] ?? o.status
const dirty = (o) => draftOf(o) !== o.status
const countOf = (o) => (o.items || []).reduce((sum, i) => sum + Number(i.quantity), 0)
const toggle = (id) => { open[id] = !open[id] }

// أي تغيير فعلي بالحالات (حفظ أو تحديث تلقائي) يلغي المسودات القديمة
const signature = computed(() => props.orders.map((o) => `${o.id}:${o.status}`).join('|'))
watch(signature, () => { for (const k of Object.keys(drafts)) delete drafts[k] })
</script>

<template>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-sm min-w-[56rem]">
        <thead class="bg-slate-950/60 text-slate-400 text-xs">
          <tr>
            <th class="w-10 p-4"></th>
            <th class="p-4 text-start font-bold">رقم الطلب</th>
            <th class="p-4 text-start font-bold">العميل</th>
            <th class="p-4 text-start font-bold">التاريخ</th>
            <th class="p-4 text-start font-bold">المنتجات</th>
            <th class="p-4 text-start font-bold">الإجمالي</th>
            <th class="p-4 text-start font-bold">الحالة</th>
            <th class="p-4 text-start font-bold">تغيير الحالة</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-800/70">
          <template v-for="o in orders" :key="o.id">
            <tr class="hover:bg-slate-800/30 transition cursor-pointer" @click="toggle(o.id)">
              <td class="p-4 text-slate-500">
                <Icon name="plus" size="w-4 h-4 transition-transform" :class="open[o.id] ? 'rotate-45 text-purple-300' : ''" />
              </td>
              <td class="p-4 font-extrabold text-white">#{{ o.id }}</td>
              <td class="p-4 text-slate-300">{{ o.user?.name ?? `العميل #${o.user_id}` }}</td>
              <td class="p-4 text-slate-400 whitespace-nowrap">{{ formatDateTime(o.created_at) }}</td>
              <td class="p-4">
                <div class="flex items-center gap-2">
                  <span class="flex -space-x-2 rtl:space-x-reverse">
                    <span
                      v-for="item in (o.items || []).slice(0, 3)"
                      :key="item.id"
                      class="w-8 h-8 rounded-lg bg-slate-800 border-2 border-slate-900 overflow-hidden grid place-items-center text-slate-600"
                    >
                      <img v-if="imageUrl(productOf(item.product_id)?.image_path)" :src="imageUrl(productOf(item.product_id).image_path)" alt="" class="w-full h-full object-cover" />
                      <Icon v-else name="cube" size="w-4 h-4" />
                                   </span>
                  </span>
                  <span class="text-xs text-slate-300 max-w-[14rem] truncate" :title="(o.items || []).map(nameOf).join('، ')">
                    {{ (o.items || []).map(nameOf).join('، ') }} <span class="text-slate-500">({{ countOf(o) }} قطعة)</span>
                  </span>
                </div>
              </td>
              <td class="p-4 font-bold text-emerald-400 whitespace-nowrap">{{ formatMoney(o.total_amount) }}</td>
              <td class="p-4"><StatusBadge :status="o.status" /></td>
              <td class="p-4" @click.stop>
                <div class="flex items-center gap-2">
                  <select :value="draftOf(o)" class="input !w-auto !py-2 !px-3 text-xs" :disabled="busyId === o.id" @change="drafts[o.id] = $event.target.value">
                    <option v-for="(label, key) in ORDER_STATUSES" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <button class="btn btn-primary !px-3 !py-2 text-xs" :disabled="busyId === o.id || !dirty(o)" @click="emit('save-status', o, draftOf(o))">
                    {{ busyId === o.id ? '...' : 'حفظ' }}
                  </button>
                </div>
              </td>
            </tr>

            <!-- تفاصيل المنتجات (تتوسع عند الضغط على الصف) -->
            <tr v-if="open[o.id]" class="bg-slate-950/40">
              <td></td>
              <td colspan="7" class="p-4">
                <ul class="grid sm:grid-cols-2 gap-2">
                  <li v-for="item in o.items" :key="item.id" class="flex items-center gap-3 rounded-xl bg-slate-900/70 border border-slate-800 p-3">
                    <span class="w-11 h-11 shrink-0 rounded-lg bg-slate-800 overflow-hidden grid place-items-center text-slate-600">
                      <img v-if="imageUrl(productOf(item.product_id)?.image_path)" :src="imageUrl(productOf(item.product_id).image_path)" alt="" class="w-full h-full object-cover" />
                      <Icon v-else name="cube" size="w-5 h-5" />
                    </span>
                    <span class="flex-1 min-w-0">
                      <span class="block text-sm font-semibold text-slate-100 truncate">{{ nameOf(item) }}</span>
                      <span class="block text-xs text-slate-500 mt-0.5">{{ item.quantity }} × {{ formatMoney(item.unit_price) }}</span>
                    </span>
                    <span class="text-sm font-bold text-slate-200">{{ formatMoney(Number(item.unit_price) * Number(item.quantity)) }}</span>
                  </li>
                </ul>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
