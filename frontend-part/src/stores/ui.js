import { defineStore } from 'pinia'

let seq = 0

export const useUiStore = defineStore('ui', {
  state: () => ({ toasts: [] }),
  actions: {
    push(type, message, ttl = 4500) {
      const id = ++seq
      this.toasts.push({ id, type, message })
      setTimeout(() => this.dismiss(id), ttl)
    },
    dismiss(id) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
    success(message) { this.push('success', message) },
    error(message) { this.push('error', message, 6000) },
    info(message) { this.push('info', message) },
  },
})