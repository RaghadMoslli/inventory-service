<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import Icon from './Icon.vue'
import { useProductStore } from '../stores/products'
import { useOrderStore } from '../stores/orders'
import { imageUrl, formatMoney } from '../utils/format'
import { categoryLabel } from '../utils/constants'
import { useAuthStore } from '../stores/auth'
const authStore = useAuthStore()
const props = defineProps({ product: { type: Object, default: null } })
const emit = defineEmits(['close', 'ordered'])

const router = useRouter()
const products = useProductStore()
const orders = useOrderStore()

const detail = ref(null)
const loading = ref(false)
const loadError = ref('')
const qty = ref(1)
const orderError = ref('')
const created = ref(null)
const broken = ref(false)

const shown = computed(() => detail.value ?? props.product)
const available = computed(() => Number(shown.value?.available_quantity ?? 0))
const src = computed(() => imageUrl(shown.value?.image_path))
const validQty = computed(() => Number.isInteger(qty.value) && qty.value >= 1 && qty.value <= available.value)
const canOrder = computed(() => !created.value && available.value > 0 && validQty.value)
const total = computed(() => Number(shown.value?.price ?? 0) * (validQty.value ? qty.value : 0))

let seq = 0
async function loadDetail(silent = false) {
  const id = props.product?.id
  if (!id) return
  const mine = ++seq
  if (!silent) loading.value = true
  loadError.value = ''
  const result = await products.fetchProductDetails(id, { silent: true })
  if (mine !== seq) return
  if (result) detail.value = result
  else loadError.value = products.detailError
  loading.value = false
}

const onKey = (e) => { if (e.key === 'Escape') emit('close') }

watch(
  () => props.product,
  (p) => {
    detail.value = null
    qty.value = 1
    orderError.value = ''
    created.value = null
    broken.value = false
    if (p) {
      loadDetail()
      document.body.style.overflow = 'hidden'
      window.addEventListener('keydown', onKey)
    } else {
      seq++
      document.body.style.overflow = ''
      window.removeEventListener('keydown', onKey)
    }
  },
  { immediate: true }
)
onBeforeUnmount(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKey)
})

function step(delta) {
  const next = (Number(qty.value) || 1) + delta
  qty.value = Math.min(Math.max(next, 1), Math.max(available.value, 1))
}

async function submit() {
  if (!canOrder.value || orders.creating) return
  orderError.value = ''
  const order = await orders.createOrder(shown.value.id, qty.value)
  if (order) {
    created.value = order
    emit('ordered', order)
  } else {
    orderError.value = orders.error
    loadDetail(true) // تحديث الكمية المتوفرة بعد فشل الطلب
  }
}

function goOrders() {
  emit('close')
  router.push({ name: 'Orders' })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="product" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/70 backdrop-blur-sm sm:p-4" @click.self="emit('close')">
        <div class="w-full max-w-lg max-h-[92vh] overflow-y-auto custom-scrollbar bg-slate-900 border border-slate-700 rounded-t-3xl sm:rounded-3xl shadow-2xl" role="dialog" aria-modal="true">
          <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 bg-slate-900/95 backdrop-blur border-b border-slate-800">
            <h3 class="text-lg font-extrabold text-white">تفاصيل المنتج</h3>
            <button class="w-9 h-9 grid place-items-center rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300" aria-label="إغلاق" @click="emit('close')">
              <Icon name="x" size="w-4 h-4" />
            </button>
          </div>

          <div class="p-5 space-y-5">
            <div class="aspect-[16/10] rounded-2xl bg-slate-800 overflow-hidden grid place-items-center">
              <img v-if="src && !broken" :src="src" :alt="shown.name" class="w-full h-full object-cover" @error="broken = true" />
              <div v-else class="text-slate-600 flex flex-col items-center gap-1"><Icon name="photo" size="w-10 h-10" /><span class="text-xs">لا توجد صورة</span></div>
            </div>

            <div>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-300 border border-purple-500/20">{{ categoryLabel(shown.category) }}</span>
              <h4 class="text-xl font-extrabold text-white mt-3">{{ shown.name }}</h4>
              <p class="text-sm text-slate-300 leading-7 mt-2">{{ shown.description || 'لا يوجد وصف لهذا المنتج.' }}</p>
            </div>

            <dl class="grid grid-cols-2 gap-3">
              <div class="rounded-2xl bg-slate-800/60 border border-slate-800 p-4">
                <dt class="text-xs text-slate-400">السعر</dt>
                <dd class="text-lg font-extrabold text-emerald-400 mt-1">{{ formatMoney(shown.price) }}</dd>
              </div>
              <div class="rounded-2xl bg-slate-800/60 border border-slate-800 p-4">
                <dt class="text-xs text-slate-400">الكمية المتوفرة</dt>
                <dd class="text-lg font-extrabold mt-1" :class="available > 0 ? 'text-indigo-300' : 'text-rose-300'">
                  <span v-if="loading" class="inline-block w-8 h-5 skeleton align-middle"></span>
                  <template v-else>{{ available > 0 ? available : 'نفد' }}</template>
                </dd>
              </div>
            </dl>

            <p v-if="loadError" class="p-3 rounded-xl bg-amber-950/30 border border-amber-800/60 text-amber-300 text-xs">
              تعذّر تحديث الكمية: {{ loadError }}
              <button class="font-bold underline ms-1" @click="loadDetail()">إعادة المحاولة</button>
            </p>

            <!-- نجاح الطلب -->
            <div v-if="created" class="rounded-2xl bg-emerald-950/30 border border-emerald-800/60 p-4 text-emerald-200">
              <p class="font-bold flex items-center gap-2"><Icon name="check" size="w-5 h-5" />تم إنشاء الطلب #{{ created.id }} بنجاح</p>
              <p class="text-xs text-emerald-300/80 mt-1">الإجمالي: {{ formatMoney(created.total_amount) }} — يمكنك متابعة حالته من صفحة طلباتي.</p>
              <div class="flex gap-3 mt-4">
                <button class="btn btn-primary flex-1 text-xs" @click="goOrders">عرض طلباتي</button>
                <button class="btn btn-ghost flex-1 text-xs" @click="emit('close')">متابعة التسوق</button>
              </div>
            </div>

        <!-- إذا لم يكن أدمن (أي زبون)، نعرض له حقل الكمية وزر الطلب -->
<div v-if="!authStore.isAdmin">
  <!-- نجاح الطلب -->
  <div v-if="created" class="rounded-2xl bg-emerald-950/30 border border-emerald-800/60 p-4 text-emerald-200">
    <p class="font-bold flex items-center gap-2"><Icon name="check" size="w-5 h-5" />تم إنشاء الطلب #{{ created.id }} بنجاح</p>
    <p class="text-xs text-emerald-300/80 mt-1">الإجمالي: {{ formatMoney(created.total_amount) }} — يمكنك متابعة حالته من صفحة طلباتي.</p>
    <div class="flex gap-3 mt-4">
      <button class="btn btn-primary flex-1 text-xs" @click="goOrders">عرض طلباتي</button>
      <button class="btn btn-ghost flex-1 text-xs" @click="emit('close')">متابعة التسوق</button>
    </div>
  </div>

  <!-- نموذج الطلب -->
  <div v-else class="space-y-4">
    <div>
      <label class="label">الكمية المطلوبة</label>
      <div class="flex items-center gap-3">
        <button class="w-11 h-11 rounded-xl bg-slate-800 border border-slate-700 grid place-items-center hover:bg-slate-700 disabled:opacity-40" :disabled="qty <= 1 || available < 1" @click="step(-1)"><Icon name="minus" size="w-4 h-4" /></button>
        <input v-model.number="qty" type="number" min="1" :max="available" :disabled="available < 1" class="input text-center !w-24" />
        <button class="w-11 h-11 rounded-xl bg-slate-800 border border-slate-700 grid place-items-center hover:bg-slate-700 disabled:opacity-40" :disabled="qty >= available" @click="step(1)"><Icon name="plus" size="w-4 h-4" /></button>
        <span class="ms-auto text-sm text-slate-400">الإجمالي <b class="text-white text-base">{{ formatMoney(total) }}</b></span>
      </div>
      <p v-if="available > 0 && !validQty" class="field-error">الكمية يجب أن تكون بين 1 و {{ available }}</p>
    </div>

    <p v-if="orderError" class="p-3 rounded-xl bg-rose-950/30 border border-rose-800 text-rose-300 text-xs">{{ orderError }}</p>

    <button class="btn btn-primary w-full py-3" :disabled="!canOrder || orders.creating" @click="submit">
      {{ orders.creating ? 'جاري إنشاء الطلب...' : available < 1 ? 'المنتج غير متوفر حالياً' : 'تأكيد الطلب' }}
    </button>
  </div>
</div>

<!-- إذا كان المستخدم أدمن، نظهر له رسالة توضيحية بدل نموذج الطلب -->
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
