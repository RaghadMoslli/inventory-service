
import { onMounted, onActivated, onBeforeUnmount, onDeactivated } from 'vue'

export function useAutoRefresh(task, interval = 20000) {
  let timer = null
  let active = false

  const tick = () => {
    if (!document.hidden) task()
  }

  const stop = () => {
    active = false
    clearInterval(timer)
    timer = null
    document.removeEventListener('visibilitychange', tick)
    window.removeEventListener('focus', tick)
  }

  const start = () => {
    if (active) return

    active = true
    task()

    timer = setInterval(tick, interval)

    document.addEventListener('visibilitychange', tick)
    window.addEventListener('focus', tick)
  }

  onMounted(start)
  onActivated(start)
  onDeactivated(stop)
  onBeforeUnmount(stop)
}
