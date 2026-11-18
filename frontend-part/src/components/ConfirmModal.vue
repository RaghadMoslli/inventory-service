<script setup>
defineProps({
  open: Boolean,
  title: String,
  message: String,
  confirmText: { type: String, default: 'تأكيد' },
  danger: Boolean,
  loading: Boolean,
})
const emit = defineEmits(['confirm', 'close'])
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-[150] grid place-items-center p-4 bg-black/70 backdrop-blur-sm" @click.self="emit('close')" @keydown.esc="emit('close')">
        <div class="w-full max-w-sm rounded-3xl bg-slate-900 border border-slate-700 p-6 shadow-2xl" role="alertdialog" aria-modal="true">
          <h3 class="text-lg font-extrabold text-white">{{ title }}</h3>
          <p class="text-sm text-slate-400 mt-2 leading-7">{{ message }}</p>
          <div class="flex gap-3 mt-6">
            <button class="btn flex-1" :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="loading" @click="emit('confirm')">
              {{ loading ? 'جاري التنفيذ...' : confirmText }}
            </button>
            <button class="btn btn-ghost flex-1" :disabled="loading" @click="emit('close')">تراجع</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
