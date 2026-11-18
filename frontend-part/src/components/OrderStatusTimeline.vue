
<script setup>
import { computed, onMounted } from 'vue'
import { useOrderStore } from '../stores/orders'

const props = defineProps({
  orderId: {
    type: [Number, String],
    required: true,
  },
})

const orders = useOrderStore()

const history = computed(() => orders.orderHistories?.[props.orderId] || [])
const loading = computed(() => orders.historyLoading?.[props.orderId] || false)

const statusLabels = {
  PENDING: 'قيد الانتظار',
  CONFIRMED: 'تم تأكيد الطلب',
  SHIPPED: 'تم شحن الطلب',
  DELIVERED: 'تم التسليم',
  CANCELLED: 'تم إلغاء الطلب',
}

function formatDate(value) {
  if (!value) return '—'

  return new Intl.DateTimeFormat('ar', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(() => {
  orders.fetchOrderHistory(props.orderId)
})
</script>

<template>
  <section class="mt-4 border-t pt-3">
    <h3 class="mb-3 text-sm font-semibold text-gray-800">
      سجل تتبّع الطلب
    </h3>

    <div v-if="loading && !history.length" class="text-sm text-gray-500">
      جارٍ تحميل سجل الطلب...
    </div>

    <div v-else-if="!history.length" class="text-sm text-gray-500">
      لا يوجد سجل حالات لهذا الطلب.
    </div>

    <ol v-else class="space-y-4">
      <li
        v-for="(entry, index) in history"
        :key="entry.id"
        class="relative flex gap-3"
      >
        <div class="flex flex-col items-center">
          <span
            class="mt-1 h-3 w-3 shrink-0 rounded-full"
            :class="index === 0 ? 'bg-blue-600' : 'bg-gray-400'"
          />
          <span
            v-if="index !== history.length - 1"
            class="mt-1 w-px flex-1 bg-gray-200"
          />
        </div>

        <div class="min-w-0 flex-1 pb-2">
          <p class="text-sm font-semibold text-gray-800">
            {{ statusLabels[entry.new_status] || entry.new_status }}
          </p>

          <p class="mt-1 text-xs text-gray-500">
            {{ formatDate(entry.created_at) }}
          </p>

          <p v-if="entry.changed_by" class="mt-1 text-xs text-gray-600">
            بواسطة: {{ entry.changed_by }}
          </p>

          <p v-if="entry.note" class="mt-1 text-xs text-gray-600">
            {{ entry.note }}
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>
