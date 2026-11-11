<script setup>
import Icon from './Icon.vue'
defineProps({ icon: { type: String, default: 'inbox' }, title: String, text: String })
</script>

<template>
  <div class="card text-center py-16 px-6">
    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-slate-800/80 grid place-items-center text-slate-500">
      <Icon :name="icon" size="w-7 h-7" />
    </div>
    <p class="text-white font-bold">{{ title }}</p>
    <p v-if="text" class="text-slate-400 text-sm mt-1.5">{{ text }}</p>
    <div class="mt-5 flex justify-center"><slot /></div>
  </div>
</template>
