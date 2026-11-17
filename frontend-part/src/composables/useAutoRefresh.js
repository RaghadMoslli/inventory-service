import { onActivated, onBeforeUnmount, onDeactivated } from 'vue'


export function useAutoRefresh(task, interval = 20000) {
  let timer = null
  const tick = () => { if (!document.hidden) task() }
  const stop = () => {
    clearInterval(timer)
    timer = null
    document.removeEventListener('visibilitychange', tick)
    window.removeEventListener('focus', tick)
  }
  const start = () => {
    stop()
    task()
    timer = setInterval(tick, interval)
    document.addEventListener('visibilitychange', tick)
    window.addEventListener('focus', tick)
  }
  onActivated(start)
  onDeactivated(stop)
  onBeforeUnmount(stop)
}
