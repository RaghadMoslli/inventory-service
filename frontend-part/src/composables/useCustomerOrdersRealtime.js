
import { ref, watch, onActivated, onDeactivated, onBeforeUnmount } from 'vue'
import { useOrderStore } from '../stores/orders'
import { useUiStore } from '../stores/ui'
import { connectionState, getEcho } from '../plugins/echo'

export function useCustomerOrdersRealtime() {
  const orders = useOrderStore()
  const ui = useUiStore()

  const active = ref(false)
  const subscribedIds = new Set()

  function syncSubscriptions() {
    if (!active.value) return

    const echo = getEcho()
    if (!echo) return

    const currentIds = new Set(orders.orders.map(order => String(order.id)))

    // إلغاء الاشتراكات الخاصة بالطلبات التي لم تعد ظاهرة
    for (const id of subscribedIds) {
      if (!currentIds.has(id)) {
        echo.leave(`orders.${id}`)
        subscribedIds.delete(id)
      }
    }

    // الاشتراك بالطلبات الحالية
    for (const id of currentIds) {
      if (subscribedIds.has(id)) continue

      echo.private(`orders.${id}`)
        .listen('.order.status.changed', payload => {
          const orderId = payload.order_id ?? id

          orders.applyOrder({
            id: orderId,
            status: payload.new_status,
            updated_at: payload.updated_at,
          })

          // تحديث سجل الحالات بعد وصول التغيير
          orders.fetchOrderHistory(orderId)
        })
        .error(() => {
          ui.error(`تعذّر الاتصال بقناة تتبّع الطلب #${id}`)
        })

      subscribedIds.add(id)
    }
  }

  function activate() {
    active.value = true
    syncSubscriptions()
  }

  function deactivate() {
    active.value = false

    const echo = getEcho()

    if (echo) {
      for (const id of subscribedIds) {
        echo.leave(`orders.${id}`)
      }
    }

    subscribedIds.clear()
  }

  // مراقبة الطلبات عند تحميل صفحة جديدة أو تغيير الصفحة
  watch(
    () => orders.orders.map(order => String(order.id)).join(','),
    () => syncSubscriptions()
  )

  // عند عودة الاتصال نحدّث القائمة وسجلات الحالات
  watch(connectionState, async (now, previous) => {
    if (!active.value || now !== 'connected' || previous === 'connected') return

    await orders.fetchOrders(orders.pagination.current_page || 1, {
      silent: true,
    })

    for (const order of orders.orders) {
      orders.fetchOrderHistory(order.id)
    }

    syncSubscriptions()
  })

  onActivated(activate)
  onDeactivated(deactivate)
  onBeforeUnmount(deactivate)
}
