
<script setup>
import { onMounted, onBeforeUnmount, onActivated, onDeactivated } from 'vue'
import { useSystemMonitoringStore } from '../stores/systemMonitoring'
import Icon from '../components/Icon.vue'

const monitoring = useSystemMonitoringStore()

let timer = null

function startPolling() {
  if (timer) return

  monitoring.fetchStatus()
  timer = setInterval(() => {
    if (!document.hidden) monitoring.fetchStatus()
  }, 5000)
}

function stopPolling() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

onMounted(startPolling)
onActivated(startPolling)
onDeactivated(stopPolling)
onBeforeUnmount(stopPolling)

const statusClass = (status) => {
  if (status === 'connected') return 'text-emerald-300 bg-emerald-950/40 border-emerald-800/50'
  if (status === 'unavailable') return 'text-rose-300 bg-rose-950/40 border-rose-800/50'
  return 'text-amber-300 bg-amber-950/40 border-amber-800/50'
}

const statusLabel = (status) => {
  if (status === 'connected') return 'متصل'
  if (status === 'unavailable') return 'غير متاح'
  return 'غير معروف'
}

const services = [
  { key: 'database', name: 'PostgreSQL', description: 'قاعدة البيانات' },
  { key: 'redis', name: 'Redis', description: 'التخزين المؤقت' },
  { key: 'rabbitmq', name: 'RabbitMQ', description: 'إدارة طوابير المهام' },
]
</script>

<template>
  <section class="space-y-6" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white">مراقبة حالة النظام</h2>
        <p class="text-sm text-slate-400 mt-1">
          متابعة اتصال الخدمات الأساسية وحالة طوابير المهام.
        </p>
      </div>

      <button
        class="btn btn-secondary"
        @click="monitoring.fetchStatus()"
        :disabled="monitoring.loading"
      >
        <Icon name="refresh" size="w-4 h-4" />
        {{ monitoring.loading ? 'جارٍ التحديث...' : 'تحديث الآن' }}
      </button>
    </div>

    <p
      v-if="monitoring.error"
      class="rounded-xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-sm p-3"
    >
      {{ monitoring.error }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div v-for="service in services" :key="service.key" class="card p-5">
        <p class="text-sm text-slate-400">{{ service.description }}</p>
        <div class="flex items-center justify-between gap-3 mt-3">
          <div>
            <h3 class="text-lg font-extrabold text-white">{{ service.name }}</h3>
            <span
              class="inline-flex mt-2 px-3 py-1 rounded-lg border text-xs font-bold"
              :class="statusClass(monitoring.status?.[service.key]?.status)"
            >
              {{ statusLabel(monitoring.status?.[service.key]?.status) }}
            </span>
          </div>
          <span
            class="w-3 h-3 rounded-full"
            :class="monitoring.status?.[service.key]?.status === 'connected'
              ? 'bg-emerald-400'
              : monitoring.status?.[service.key]?.status === 'unavailable'
                ? 'bg-rose-400'
                : 'bg-amber-400'"
          ></span>
        </div>
      </div>
    </div>

    <div class="card p-5 space-y-4">
      <div>
        <h3 class="text-lg font-extrabold text-white">إحصائيات RabbitMQ</h3>
        <p class="text-sm text-slate-400 mt-1">تفاصيل الطوابير والرسائل الحالية.</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-right">
          <thead class="text-slate-400 border-b border-slate-800">
            <tr>
              <th class="p-3 font-medium">اسم الطابور</th>
              <th class="p-3 font-medium">إجمالي الرسائل</th>
              <th class="p-3 font-medium">جاهزة</th>
              <th class="p-3 font-medium">قيد المعالجة</th>
              <th class="p-3 font-medium">Consumers</th>
              <th class="p-3 font-medium">الحالة</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="queue in monitoring.status?.rabbitmq?.queues || []"
              :key="queue.name"
              class="border-b border-slate-800/70"
            >
              <td class="p-3 text-white font-bold">{{ queue.name }}</td>
              <td class="p-3 text-slate-300">{{ queue.messages }}</td>
              <td class="p-3 text-slate-300">{{ queue.messages_ready }}</td>
              <td class="p-3 text-slate-300">{{ queue.messages_unacknowledged }}</td>
              <td class="p-3 text-slate-300">{{ queue.consumers }}</td>
              <td class="p-3">
                <span
                  class="inline-flex px-2 py-1 rounded-lg border text-xs font-bold"
                  :class="statusClass(queue.state === 'running' ? 'connected' : 'unavailable')"
                >
                  {{ queue.state === 'running' ? 'تعمل' : 'متوقفة' }}
                </span>
              </td>
            </tr>

            <tr v-if="monitoring.status && !monitoring.status.rabbitmq?.queues?.length">
              <td colspan="6" class="p-5 text-center text-slate-500">
                لا توجد طوابير متاحة.
              </td>
            </tr>

            <tr v-if="!monitoring.status">
              <td colspan="6" class="p-5 text-center text-slate-500">
                {{ monitoring.loading ? 'جارٍ تحميل البيانات...' : 'لا توجد بيانات حالياً.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p v-if="monitoring.lastUpdated" class="text-xs text-slate-500">
      آخر تحديث:
      {{ monitoring.lastUpdated.toLocaleTimeString('ar') }}
      — التحديث التلقائي كل 5 ثوانٍ.
    </p>
  </section>
</template>
