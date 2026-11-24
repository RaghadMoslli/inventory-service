<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import Icon from '../components/Icon.vue'
import { useEmailStore } from '../stores/emails'

const emails = useEmailStore()
const status = ref('')
let timer = null

async function load() {
  await emails.fetchLogs({ status: status.value })
}

async function retry(id) {
  await emails.retry(id)
}

onMounted(() => {
  load()
  timer = setInterval(load, 3000)
})
onBeforeUnmount(() => clearInterval(timer))
watch(status, () => load())

const statusClass = (s) => ({
  queued: 'text-amber-300 bg-amber-950/40 border-amber-800/50',
  sent: 'text-emerald-300 bg-emerald-950/40 border-emerald-800/50',
  failed: 'text-orange-300 bg-orange-950/40 border-orange-800/50',
  dead: 'text-rose-300 bg-rose-950/40 border-rose-800/50',
}[s] || 'text-slate-300 bg-slate-800 border-slate-700')
</script>

<template>
  <section class="space-y-6">
    <div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white">مراقبة إيميلات الطلبات</h2>
      <p class="text-sm text-slate-400 mt-1">متابعة طابور الإيميلات، الإرسال، والرسائل الميتة (DLQ).</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="key in ['queued','sent','failed','dead']" :key="key" class="card p-4">
        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ key }}</p>
        <p class="text-2xl font-extrabold text-white mt-1">{{ emails.stats[key] ?? 0 }}</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <select v-model="status" class="input !w-auto">
        <option value="">كل الحالات</option>
        <option value="queued">queued</option>
        <option value="sent">sent</option>
        <option value="failed">failed</option>
        <option value="dead">dead</option>
      </select>
      <button class="btn btn-secondary" @click="load" :disabled="emails.loading">
        <Icon name="refresh" size="w-4 h-4" /> تحديث
      </button>
    </div>

    <p v-if="emails.error" class="rounded-xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-sm p-3">{{ emails.error }}</p>

    <div class="card overflow-x-auto">
      <table class="w-full text-sm text-right">
        <thead class="text-slate-400 border-b border-slate-800">
          <tr>
            <th class="p-3 font-medium">#</th>
            <th class="p-3 font-medium">الطلب</th>
            <th class="p-3 font-medium">إلى</th>
            <th class="p-3 font-medium">الحالة</th>
            <th class="p-3 font-medium">محاولات</th>
            <th class="p-3 font-medium">خطأ</th>
            <th class="p-3 font-medium">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in emails.logs" :key="log.id" class="border-b border-slate-800/70">
            <td class="p-3 text-slate-300">{{ log.id }}</td>
            <td class="p-3 text-white font-bold">#{{ log.order_id }}</td>
            <td class="p-3 text-slate-300">{{ log.to }}</td>
            <td class="p-3">
              <span class="inline-flex px-2 py-1 rounded-lg border text-xs font-bold" :class="statusClass(log.status)">{{ log.status }}</span>
            </td>
            <td class="p-3 text-slate-300">{{ log.attempts }}</td>
            <td class="p-3 text-slate-400 max-w-[14rem] truncate" :title="log.error || ''">{{ log.error || '—' }}</td>
            <td class="p-3">
              <button
                v-if="log.status === 'dead'"
                class="btn btn-primary !py-1.5 !px-3 text-xs"
                @click="retry(log.id)"
              >إعادة إرسال</button>
              <span v-else class="text-slate-600">—</span>
            </td>
          </tr>
          <tr v-if="!emails.logs.length">
            <td colspan="7" class="p-6 text-center text-slate-500">لا توجد سجلات بعد</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
