<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import Icon from './Icon.vue'
import NotificationBell from './NotificationBell.vue'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationStore()
const router = useRouter()
const loggingOut = ref(false)

const links = computed(() => {
  const items = [
    { to: '/', label: 'المنتجات', icon: 'bag', exact: true },
    { to: '/orders', label: auth.isAdmin ? 'إدارة الطلبات' : 'طلباتي', icon: 'orders' },
  ]
  if (auth.isAdmin) items.push({ to: '/admin/products/create', label: 'إضافة منتج', icon: 'plus' })
  return items
})

async function logout() {
  loggingOut.value = true
  await auth.logout()
  router.replace({ name: 'Login' })
}

// الإشعارات تبقى محدّثة طوال الجلسة بغض النظر عن الصفحة المفتوحة
let timer = null
const poll = () => { if (!document.hidden) notifications.fetchNotifications({ silent: true }) }
onMounted(() => {
  poll()
  timer = setInterval(poll, 15000)
  window.addEventListener('focus', poll)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  window.removeEventListener('focus', poll)
})
</script>

<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-40 backdrop-blur-xl bg-slate-950/80 border-b border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-4">
        <router-link to="/" class="flex items-center gap-3 shrink-0">
          <span class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 grid place-items-center shadow-lg shadow-purple-500/30 text-white">
            <Icon name="bag" />
          </span>
          <span class="hidden sm:block text-lg font-black text-white">منصة التجارة الإلكترونية</span>
        </router-link>

        <nav class="hidden md:flex items-center gap-1">
          <router-link v-for="l in links" :key="l.to" :to="l.to" class="nav-link" active-class="nav-active" exact-active-class="nav-active">
            <Icon :name="l.icon" size="w-4 h-4" />{{ l.label }}
          </router-link>
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
          <NotificationBell />
          <div class="hidden lg:flex items-center gap-3 ps-3 border-s border-slate-800">
            <span class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 grid place-items-center font-bold text-purple-300 text-sm">{{ (auth.user?.name || 'م')[0] }}</span>
            <span class="leading-tight">
              <span class="block text-sm font-bold text-slate-200">{{ auth.user?.name }}</span>
              <span class="block text-[11px] text-slate-500">{{ auth.isAdmin ? 'مشرف' : 'عميل' }}</span>
            </span>
          </div>
          <button class="btn btn-ghost !px-3" :disabled="loggingOut" title="تسجيل الخروج" @click="logout">
            <Icon name="logout" size="w-4 h-4" />
            <span class="hidden sm:inline">تسجيل الخروج</span>
          </button>
        </div>
      </div>

      <!-- التنقل على الجوال -->
      <nav class="md:hidden flex gap-1 px-3 pb-3 overflow-x-auto">
        <router-link v-for="l in links" :key="l.to" :to="l.to" class="nav-link" active-class="nav-active" exact-active-class="nav-active">
          <Icon :name="l.icon" size="w-4 h-4" />{{ l.label }}
        </router-link>
      </nav>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- keep-alive: الصفحة تبقى بحالتها وبياناتها عند التنقل بين التبويبات ثم تتحدث بصمت -->
      <router-view v-slot="{ Component }">
        <keep-alive>
          <component :is="Component" />
        </keep-alive>
      </router-view>
    </main>
  </div>
</template>
