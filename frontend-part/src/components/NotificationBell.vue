<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import Icon from './Icon.vue'
import { useNotificationStore } from '../stores/notifications'
import { localizeNotification, timeAgo } from '../utils/format'

const store = useNotificationStore()
const router = useRouter()
const open = ref(false)
const root = ref(null)

const meta = {
  order_created: { icon: 'orders', tone: 'bg-purple-500/15 text-purple-300' },
  order_status_changed: { icon: 'refresh', tone: 'bg-sky-500/15 text-sky-300' },
}
const metaOf = (type) => meta[type] || { icon: 'bell', tone: 'bg-slate-700/60 text-slate-300' }

async function onItemClick(item) {
  store.markAsRead(item.id)
  open.value = false
  router.push({ name: 'Orders' })
}

const onDocClick = (e) => { if (open.value && root.value && !root.value.contains(e.target)) open.value = false }
const onKey = (e) => { if (e.key === 'Escape') open.value = false }
onMounted(() => { document.addEventListener('click', onDocClick); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { document.removeEventListener('click', onDocClick); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div ref="root" class="relative">
    <button
      class="relative w-11 h-11 grid place-items-center rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 border border-transparent hover:border-slate-700 transition"
      :aria-expanded="open"
      aria-label="الإشعارات"
      @click="open = !open"
    >
      <Icon name="bell" size="w-6 h-6" />
      <span
        v-if="store.unreadCount > 0"
        class="absolute -top-1 -end-1 min-w-[1.25rem] h-5 px-1.5 grid place-items-center text-[11px] font-bold text-white bg-gradient-to-r from-rose-500 to-red-600 rounded-full shadow-md shadow-red-500/40"
      >{{ store.unreadCount > 9 ? '9+' : store.unreadCount }}</span>
    </button>

    <Transition name="pop">
      <div v-if="open" class="absolute end-0 mt-3 w-[min(24rem,calc(100vw-2rem))] rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl ring-1 ring-white/5 overflow-hidden z-50">
        <div class="flex items-center justify-between px-4 py-3 bg-slate-950/60 border-b border-slate-800">
          <span class="font-bold text-sm text-white">الإشعارات</span>
          <button v-if="store.unreadCount" class="text-xs font-bold text-purple-300 hover:text-purple-200" @click="store.markAllAsRead()">تحديد الكل كمقروء</button>
        </div>

        <div class="max-h-96 overflow-y-auto custom-scrollbar divide-y divide-slate-800/60">
          <div v-if="store.loading && !store.notifications.length" class="p-8 text-center text-sm text-slate-400">جاري التحميل...</div>

          <div v-else-if="store.error && !store.notifications.length" class="p-6 text-center text-sm text-rose-300">
            {{ store.error }}
            <button class="block mx-auto mt-2 text-xs font-bold text-purple-300" @click="store.fetchNotifications()">إعادة المحاولة</button>
          </div>

          <button
            v-for="item in store.notifications"
            :key="item.id"
            class="w-full text-start flex gap-3 px-4 py-3.5 transition hover:bg-slate-800/60"
            :class="!item.read_at ? 'bg-purple-950/20' : ''"
            @click="onItemClick(item)"
          >
            <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center" :class="metaOf(item.type).tone">
              <Icon :name="metaOf(item.type).icon" size="w-4 h-4" />
            </span>
            <span class="flex-1 min-w-0">
              <span class="block text-sm leading-6" :class="!item.read_at ? 'text-white font-semibold' : 'text-slate-400'">{{ localizeNotification(item.message) }}</span>
              <span class="block text-[11px] text-slate-500 mt-0.5">{{ timeAgo(item.created_at) }}</span>
            </span>
            <span v-if="!item.read_at" class="w-2 h-2 mt-2 rounded-full bg-purple-500 shrink-0"></span>
          </button>

          <div v-if="store.loaded && !store.notifications.length" class="p-10 text-center text-sm text-slate-500">
            <Icon name="inbox" size="w-8 h-8 mx-auto mb-2 text-slate-600" />
            لا توجد إشعارات حالياً
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>
