import { onBeforeUnmount, onMounted, watch } from 'vue'
import { connectionState, getEcho } from '../plugins/echo'
import { useOrderStore } from '../stores/orders'
import { useUiStore } from '../stores/ui'
import { ORDER_STATUSES } from '../utils/constants'


export function useAdminOrdersRealtime() {
  const orders = useOrderStore()
  const ui = useUiStore()

  const reload = () => orders.fetchOrders(orders.pagination.current_page || 1, { silent: true })

  onMounted(() => {
    const echo = getEcho()
    if (!echo) return

    echo
      .private('admin.orders')
      .listen('.order.created', (p) => {
        ui.info(`طلب جديد #${p.order_id} من ${p.customer_name ?? 'عميل'}`)
        reload()
      })
      .listen('.order.updated', (p) => {
        const row = orders.orders.find((o) => o.id === p.order_id)
        if (row && row.status !== p.status) {
          ui.info(`الطلب #${p.order_id} أصبح «${ORDER_STATUSES[p.status] ?? p.status}»`)
        }
        orders.applyOrder({ id: p.order_id, status: p.status })
      })
      .error(() => ui.error('تعذّر الاشتراك بالتحديث اللحظي: لا تملك صلاحية المسؤول'))
  })

  let wasDown = false
  watch(connectionState, (now) => {
    if (now === 'connected') {
      if (wasDown && orders.loaded) reload()
      wasDown = false
    } else if (now !== 'connecting' && now !== 'initialized') {
      wasDown = true
    }
  })

  onBeforeUnmount(() => getEcho()?.leave('admin.orders'))
}
