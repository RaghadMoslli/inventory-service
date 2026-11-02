<template>
    <div class="min-h-screen bg-gray-50" dir="rtl">
      <!-- Navbar بسيط للـ Home -->
      <nav class="bg-white shadow p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-gray-800">منصة التجارة الإلكترونية</h1>
        
        <div class="flex items-center gap-4">
          <span class="text-gray-600 font-medium">
            مرحباً، {{ authStore.user?.name || 'مستخدم' }}
          </span>
          <button 
            @click="handleLogout" 
            class="bg-red-500 text-white px-4 py-1.5 rounded text-sm hover:bg-red-600 transition"
          >
            تسجيل الخروج
          </button>
        </div>
      </nav>
  
      <main class="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-lg shadow">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">الصفحة الرئيسية</h2>
        <p class="text-gray-600 leading-relaxed">
          تم تسجيل الدخول بنجاح! هذه هي الصفحة الرئيسية المبدئية المحمية، وسيتم عرض المنتجات والقوائم عليها في المراحل القادمة.
        </p>
      </main>
    </div>
  </template>
  
  <script setup>
  import { useAuthStore } from '../stores/auth'
  import { useRouter } from 'vue-router'
  
  const authStore = useAuthStore()
  const router = useRouter()
  
  const handleLogout = async () => {
    await authStore.logout()
    router.push('/login')
  }
  </script>