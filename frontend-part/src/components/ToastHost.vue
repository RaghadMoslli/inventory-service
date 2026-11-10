<script setup>
import { useUiStore } from '../stores/ui'
import Icon from './Icon.vue'
const ui = useUiStore()
const styles = {
  success: 'border-emerald-500/40 bg-emerald-950/90 text-emerald-200',
  error: 'border-rose-500/40 bg-rose-950/90 text-rose-200',
  info: 'border-purple-500/40 bg-slate-900/95 text-slate-100',
}
const icons = { success: 'check', error: 'alert', info: 'bell' }
</script>

<template>
  <div class="fixed bottom-4 start-4 z-[200] flex flex-col gap-2 w-[min(22rem,calc(100vw-2rem))]" aria-live="polite">
    <TransitionGroup name="toast">
      <button
        v-for="t in ui.toasts"
        :key="t.id"
        type="button"
        class="flex items-start gap-3 text-start rounded-2xl border px-4 py-3 text-sm shadow-2xl backdrop-blur-xl"
        :class="styles[t.type]"
        @click="ui.dismiss(t.id)"
      >
        <Icon :name="icons[t.type]" size="w-5 h-5 mt-0.5 shrink-0" />
        <span class="leading-6">{{ t.message }}</span>
      </button>
    </TransitionGroup>
  </div>
</template>
