<template>
  <section class="max-w-2xl">
    <div class="mb-6 bg-slate-800/40 border border-slate-800 p-6 rounded-3xl">
      <h2 class="text-2xl font-extrabold text-white mb-1">الطلبات</h2>
      <p class="text-slate-400 text-sm mb-4">أدخل رقم الطلب لعرضه أو تغيير حالته.</p>

      <form @submit.prevent="search" class="flex gap-2">
        <input
          v-model.trim="orderId"
          type="text"
          placeholder="رقم الطلب"
          class="flex-1 bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500"
        />
        <button
          type="submit"
          :disabled="!orderId || orderStore.loading"
          class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-bold transition-all"
        >
          {{ orderStore.loading ? 'جاري البحث...' : 'عرض' }}
        </button>
      </form>
    </div>

    <div v-if="orderStore.error" class="mb-4 text-xs text-rose-300 bg-rose-950/30 border border-rose-800 rounded-xl p-3">
      {{ orderStore.error }}
    </div>
    <div v-if="feedback" class="mb-4 text-xs text-emerald-300 bg-emerald-950/30 border border-emerald-800 rounded-xl p-3">
      {{ feedback }}
    </div>

    <div v-if="order" class="bg-slate-800/60 border border-slate-800 rounded-2xl p-5 space-y-4">
      <div class="flex justify-between items-center">
        <p class="font-bold text-white">طلب #{{ order.id }}</p>
        <span class="text-xs font-bold px-2.5 py-1 rounded-full border bg-indigo-500/10 text-indigo-400 border-indigo-500/20">
          {{ ORDER_STATUSES[order.status] || order.status }}
        </span>
      </div>

      <!-- عناصر الطلب إن وُجدت بالاستجابة -->
      <div v-if="items.length" class="divide-y divide-slate-800/60">
        <div v-for="(item, i) in items" :key="i" class="py-2 flex justify-between text-sm text-slate-300">
          <span>{{ item.product?.name ?? item.name ?? item.product_id }}</span>
          <span class="text-slate-400">× {{ item.quantity }}</span>
        </div>
      </div>

      <!-- تغيير الحالة -->
      <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-800">
        <select
          v-model="newStatus"
          class="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
        >
          <option v-for="(label, key) in ORDER_STATUSES" :key="key" :value="key">{{ label }}</option>
        </select>
        <button
          @click="saveStatus"
          :disabled="updating || newStatus === order.status"
          class="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-xs font-bold transition-all"
        >
          {{ updating ? 'جاري الحفظ...' : 'حفظ الحالة' }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useOrderStore, ORDER_STATUSES } from '../stores/orders'

const orderStore = useOrderStore()
const orderId = ref('')
const newStatus = ref('PENDING')
const updating = ref(false)
const feedback = ref('')

const order = computed(() => orderStore.currentOrder)
const items = computed(() => order.value?.items ?? order.value?.order_items ?? [])

watch(order, (o) => { if (o?.status) newStatus.value = o.status })

const search = async () => {
  feedback.value = ''
  await orderStore.fetchOrder(orderId.value)
}

const saveStatus = async () => {
  updating.value = true
  feedback.value = ''
  const ok = await orderStore.updateStatus(order.value.id ?? orderId.value, newStatus.value)
  if (ok) feedback.value = 'تم تحديث حالة الطلب'
  updating.value = false
}
</script>
