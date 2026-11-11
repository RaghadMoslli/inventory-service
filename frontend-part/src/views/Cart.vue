<script setup>
import { ref, onActivated } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '../components/Icon.vue'
import EmptyState from '../components/EmptyState.vue'
import { useCartStore } from '../stores/cart'
import { useOrderStore } from '../stores/orders'
import { useUiStore } from '../stores/ui'
import { imageUrl, formatMoney } from '../utils/format'

const router = useRouter()
const cart = useCartStore()
const orders = useOrderStore()
const ui = useUiStore()
const errorMsg = ref('')

onActivated(() => cart.refresh())
async function checkout() {
  if (!cart.canCheckout || orders.creating) return
  errorMsg.value = ''
  const kinds = cart.items.length
  // طلب واحد يحتوي كل المنتجات (Order واحد + Order Items متعددة)
  const order = await orders.createOrder(cart.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity })))
  if (order) {
    cart.clear()
    ui.success(`تم إنشاء الطلب #${order.id} ويحتوي ${kinds} منتج`)
    router.push({ name: 'Orders' })
  } else {
    errorMsg.value = orders.error
    cart.refresh() // لتحديث الكميات إذا كان السبب نقص المخزون
  }
}
</script>

<template>
  <section class="space-y-6">
    <div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white">سلة المشتريات</h2>
      <p class="text-sm text-slate-400 mt-1">راجع منتجاتك وكمياتها، ثم أكّد الطلب مرة واحدة لكل المنتجات.</p>
    </div>

    <EmptyState v-if="!cart.items.length" icon="inbox" title="السلة فارغة" text="أضف منتجات من صفحة المنتجات ثم عد هنا لتأكيد الطلب.">
      <router-link to="/" class="btn btn-primary">تصفح المنتجات</router-link>
    </EmptyState>

    <div v-else class="grid lg:grid-cols-[1fr_22rem] gap-6 items-start">
      <ul class="space-y-3">
        <li
          v-for="item in cart.items"
          :key="item.product_id"
          class="card p-4 flex flex-wrap items-center gap-4"
          :class="{ '!border-rose-800/70': item.max <= 0 }"
        >
          <span class="w-16 h-16 shrink-0 rounded-xl bg-slate-800 overflow-hidden grid place-items-center text-slate-600">
            <img v-if="imageUrl(item.image_path)" :src="imageUrl(item.image_path)" alt="" class="w-full h-full object-cover" />
            <Icon v-else name="cube" size="w-6 h-6" />
          </span>

          <div class="flex-1 min-w-[8rem]">
            <p class="font-bold text-white line-clamp-1">{{ item.name }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ formatMoney(item.price) }} للقطعة</p>
            <p v-if="item.max <= 0" class="text-xs font-bold text-rose-400 mt-1">غير متوفر حالياً، احذفه لمتابعة الطلب</p>
            <p v-else class="text-[11px] text-slate-500 mt-1">المتوفر: {{ item.max }}</p>
          </div>

          <div class="flex items-center gap-2">
            <button class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 grid place-items-center hover:bg-slate-700 disabled:opacity-40" :disabled="item.quantity <= 1 || item.max <= 0" aria-label="إنقاص" @click="cart.setQty(item.product_id, item.quantity - 1)">
              <Icon name="minus" size="w-4 h-4" />
            </button>
            <input
              type="number"
              min="1"
              :max="item.max"
              :value="item.quantity"
              :disabled="item.max <= 0"
              class="input text-center !w-16 !py-2"
              @change="cart.setQty(item.product_id, Number($event.target.value))"
            />
            <button class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 grid place-items-center hover:bg-slate-700 disabled:opacity-40" :disabled="item.quantity >= item.max" aria-label="زيادة" @click="cart.setQty(item.product_id, item.quantity + 1)">
              <Icon name="plus" size="w-4 h-4" />
            </button>
          </div>

          <span class="w-24 text-end font-extrabold text-emerald-400">{{ formatMoney(item.quantity * item.price) }}</span>

          <button class="btn btn-danger-soft !px-3 !py-2" aria-label="حذف من السلة" @click="cart.remove(item.product_id)">
            <Icon name="x" size="w-4 h-4" />
          </button>
        </li>
      </ul>

      <aside class="card p-5 lg:sticky lg:top-28 space-y-4">
        <h3 class="font-extrabold text-white">ملخص الطلب</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between text-slate-400"><span>عدد المنتجات</span><b class="text-slate-200">{{ cart.items.length }}</b></div>
          <div class="flex justify-between text-slate-400"><span>إجمالي القطع</span><b class="text-slate-200">{{ cart.count }}</b></div>
          <div class="flex justify-between items-center pt-3 border-t border-slate-800">
            <span class="text-slate-300">الإجمالي</span>
            <span class="text-xl font-extrabold text-white">{{ formatMoney(cart.total) }}</span>
          </div>
        </div>

        <p v-if="cart.hasUnavailable" class="rounded-xl bg-amber-950/30 border border-amber-800/60 text-amber-300 text-xs p-3">يوجد منتج غير متوفر في السلة، احذفه حتى تتمكن من التأكيد.</p>
        <p v-if="errorMsg" class="rounded-xl bg-rose-950/30 border border-rose-800 text-rose-300 text-xs p-3">{{ errorMsg }}</p>

        <button class="btn btn-primary w-full py-3" :disabled="!cart.canCheckout || orders.creating" @click="checkout">
          <Icon name="check" size="w-4 h-4" />{{ orders.creating ? 'جاري إنشاء الطلب...' : 'تأكيد الطلب' }}
        </button>
        <button class="btn btn-ghost w-full" :disabled="orders.creating" @click="cart.clear()">تفريغ السلة</button>
        <router-link to="/" class="block text-center text-xs text-slate-400 hover:text-white">متابعة التسوق</router-link>
      </aside>
    </div>
  </section>
</template>