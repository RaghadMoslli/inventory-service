<script setup>
import { computed } from 'vue'
const props = defineProps({ current: Number, last: Number, disabled: Boolean })
const emit = defineEmits(['change'])
const pages = computed(() => {
  const start = Math.max(1, Math.min(props.current - 2, props.last - 4))
  const end = Math.min(props.last, start + 4)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})
</script>

<template>
  <nav v-if="last > 1" class="flex items-center justify-center gap-2 mt-8" aria-label="الصفحات">
    <button class="btn btn-ghost !px-3 !py-2 text-xs" :disabled="disabled || current <= 1" @click="emit('change', current - 1)">السابق</button>
    <button
      v-for="p in pages"
      :key="p"
      class="w-9 h-9 rounded-xl text-xs font-bold border transition"
      :class="p === current ? 'bg-purple-600 border-purple-500 text-white' : 'bg-slate-800/80 border-slate-700 text-slate-300 hover:bg-slate-700'"
      :disabled="disabled"
      @click="p !== current && emit('change', p)"
    >{{ p }}</button>
    <button class="btn btn-ghost !px-3 !py-2 text-xs" :disabled="disabled || current >= last" @click="emit('change', current + 1)">التالي</button>
  </nav>
</template>
