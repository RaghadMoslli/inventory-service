<template>
    <div class="min-h-screen bg-slate-900 text-slate-100 selection:bg-purple-500 selection:text-white" dir="rtl">
      
      <!-- Navbar الرئيسي -->
      <header class="sticky top-0 z-40 backdrop-blur-xl bg-slate-900/80 border-b border-slate-800 shadow-lg shadow-slate-950/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex justify-between items-center">
          
          <!-- شعار المنصة / العنوان -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
            </div>
            <h1 class="text-xl font-black bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent tracking-wide">
              منصة التجارة الإلكترونية
            </h1>
          </div>
          
          <div class="flex items-center gap-4 sm:gap-6">
            
            <!-- قسم الإشعارات -->
            <div class="relative">
              <button 
                @click="toggleNotifications"
                class="relative p-2.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl focus:outline-none transition-all duration-200 border border-transparent hover:border-slate-700"
                title="الإشعارات"
              >
                <!-- أيقونة الجرس -->
                <svg class="w-6 h-6 transition-transform hover:scale-105" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
   
                <!-- عداد الإشعارات غير المقروءة -->
                <span 
                  v-if="notifStore.unreadCount > 0"
                  class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-bold leading-none text-white bg-gradient-to-r from-rose-500 to-red-600 rounded-full shadow-md shadow-red-500/40 animate-pulse"
                >
                  {{ notifStore.unreadCount }}
                </span>
              </button>
   
              <!-- القائمة المنسدلة للإشعارات Dropdown -->
              <div 
                v-if="showNotifications" 
                class="absolute left-0 mt-3 w-80 sm:w-96 bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 z-50 overflow-hidden backdrop-blur-2xl ring-1 ring-white/10"
              >
                <div class="p-4 bg-slate-950/50 border-b border-slate-800 flex justify-between items-center">
                  <span class="font-bold text-sm text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    الإشعارات
                  </span>
                  <span class="text-xs px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-400 font-medium border border-purple-500/20">
                    {{ notifStore.unreadCount }} غير مقروء
                  </span>
                </div>
   
                <div class="max-h-80 overflow-y-auto divide-y divide-slate-800/60 custom-scrollbar">
                  <!-- حالة التحميل -->
                  <div v-if="notifStore.loading && !notifStore.notifications.length" class="p-8 text-center text-sm text-slate-400 flex flex-col items-center gap-2">
                    <div class="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
                    جاري التحميل...
                  </div>
   
                  <!-- قائمة الإشعارات -->
                  <div 
                    v-for="item in notifStore.notifications" 
                    :key="item.id"
                    @click="handleReadNotification(item)"
                    :class="[
                      'p-4 text-sm cursor-pointer transition-all duration-200 hover:bg-slate-800/60 relative',
                      (!item.read_at && !item.is_read) ? 'bg-purple-950/20 font-semibold border-r-2 border-purple-500' : 'text-slate-400'
                    ]"
                  >
                    <div class="flex justify-between items-start mb-1.5">
                      <p class="text-slate-200 text-xs font-bold tracking-wide">{{ item.title || 'إشعار جديد' }}</p>
                      <span class="text-[10px] text-slate-500 bg-slate-800 px-2 py-0.5 rounded-md">
                        {{ formatDate(item.created_at) }}
                      </span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">{{ item.message || item.data?.message || item.content }}</p>
                  </div>
   
                  <!-- القائمة فارغة -->
                  <div v-if="!notifStore.notifications.length && !notifStore.loading" class="p-8 text-center text-xs text-slate-500 flex flex-col items-center gap-2">
                    <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    لا توجد إشعارات حالياً
                  </div>
                </div>
              </div>
            </div>
   
            <!-- تفاصيل المستخدم وزر الخروج -->
            <div class="hidden md:flex items-center gap-3 pl-3 border-l border-slate-800">
              <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-purple-400 text-sm shadow-inner">
                {{ (authStore.user?.name || 'م')[0] }}
              </div>
              <span class="text-slate-300 font-medium text-sm">
                مرحباً، {{ authStore.user?.name || 'مستخدم' }}
              </span>
            </div>
  
            <button 
              @click="handleLogout" 
              class="bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white px-4 py-2 rounded-xl text-sm font-medium shadow-lg shadow-red-600/20 hover:shadow-red-600/40 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition-all duration-200 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>تسجيل الخروج</span>
            </button>
          </div>
        </div>
      </header>
   
      <!-- محتوى الصفحة -->
      <main class="max-w-4xl mx-auto mt-12 px-4 sm:px-6">
        <div class="bg-slate-800/50 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
          <!-- لمسة جمالية خلفية -->
          <div class="absolute -top-24 -left-24 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
          <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
  
          <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold border border-emerald-500/20 mb-4">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
              حالة الاتصال: نشط وآمن
            </div>
  
            <h2 class="text-3xl font-extrabold text-white mb-4 tracking-tight">الصفحة الرئيسية</h2>
            <p class="text-slate-300 leading-relaxed text-base">
              تم تسجيل الدخول بنجاح! هذه هي الصفحة الرئيسية المبدئية المحمية، وسيتم عرض المنتجات والقوائم عليها في المراحل القادمة.
            </p>
          </div>
        </div>
      </main>
    </div>
  </template>
   
  <script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import { useAuthStore } from '../stores/auth'
  import { useNotificationStore } from '../stores/notifications'
  import { useRouter } from 'vue-router'
   
  const authStore = useAuthStore()
  const notifStore = useNotificationStore()
  const router = useRouter()
   
  const showNotifications = ref(false)
  let pollingTimer = null
   
  // إظهار/إخفاء القائمة المنسدلة
  const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value
  }
   
  // تعليم الإشعار كمقروء عند الضغط عليه
  const handleReadNotification = async (item) => {
    if (!item.read_at && !item.is_read) {
      await notifStore.markAsRead(item.id)
    }
  }
   
  // تنسيق التاريخ بشكل بسيط
  const formatDate = (dateStr) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return date.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
  }
   
  const handleLogout = async () => {
    await authStore.logout()
    router.push('/login')
  }
   
  // عند تحميل المكون: جلب الإشعارات وبدء الـ Polling كل 30 ثانية
  onMounted(() => {
    notifStore.fetchNotifications()
    
    pollingTimer = setInterval(() => {
      notifStore.fetchNotifications()
    }, 30000)
  })
   
  // تنظيف التايمر عند مغادرة الصفحة
  onUnmounted(() => {
    if (pollingTimer) clearInterval(pollingTimer)
  })
  </script>