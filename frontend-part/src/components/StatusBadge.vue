<script setup>
import { ORDER_STATUSES } from '../utils/constants'
defineProps({ status: { type: String, required: true } })
const classes = {
  PENDING: 'bg-amber-500/10 text-amber-300 border-amber-500/25',
  CONFIRMED: 'bg-sky-500/10 text-sky-300 border-sky-500/25',
  SHIPPED: 'bg-purple-500/10 text-purple-300 border-purple-500/25',
  DELIVERED: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/25',
  CANCELLED: 'bg-rose-500/10 text-rose-300 border-rose-500/25',
}
</script>

<template>
  <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full border" :class="classes[status] || 'bg-slate-500/10 text-slate-300 border-slate-500/25'">
    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
    {{ ORDER_STATUSES[status] || status }}
  </span>
</template>
