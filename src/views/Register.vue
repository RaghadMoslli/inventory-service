<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4" dir="rtl">
      <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">إنشاء حساب جديد</h2>
        
        <form @submit.prevent="handleRegister">
          <!-- الاسم -->
          <div class="mb-4">
            <label class="block text-gray-700 mb-1 font-medium">الاسم الكامل</label>
            <input 
              v-model="form.name" 
              type="text" 
              required 
              placeholder="أدخلي اسمك الكامل"
              class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" 
            />
          </div>
  
          <!-- البريد الإلكتروني -->
          <div class="mb-4">
            <label class="block text-gray-700 mb-1 font-medium">البريد الإلكتروني</label>
            <input 
              v-model="form.email" 
              type="email" 
              required 
              placeholder="example@domain.com"
              class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" 
            />
          </div>
  
          <!-- كلمة المرور -->
          <div class="mb-6">
            <label class="block text-gray-700 mb-1 font-medium">كلمة المرور</label>
            <input 
              v-model="form.password" 
              type="password" 
              required 
              placeholder="••••••••"
              class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" 
            />
          </div>
  
          <!-- زر الإرسال -->
          <button 
            type="submit" 
            :disabled="loading"
            class="w-full bg-blue-600 text-white py-2.5 rounded font-semibold hover:bg-blue-700 transition duration-200 disabled:bg-blue-300"
          >
            <span v-if="loading">جاري إنشاء الحساب...</span>
            <span v-else>إنشاء حساب</span>
          </button>
        </form>
  
        <!-- أخطاء الاستجابة -->
        <p v-if="errorMessage" class="text-red-500 mt-4 text-center text-sm font-medium">
          {{ errorMessage }}
        </p>
  
        <!-- رابط الانتقال لتسجيل الدخول -->
        <p class="mt-6 text-center text-gray-600 text-sm">
          لديك حساب بالفعل؟ 
          <router-link to="/login" class="text-blue-600 font-semibold hover:underline">
            تسجيل الدخول
          </router-link>
        </p>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref } from 'vue'
  import { useAuthStore } from '../stores/auth'
  import { useRouter } from 'vue-router'
  
  const authStore = useAuthStore()
  const router = useRouter()
  
  const form = ref({
    name: '',
    email: '',
    password: ''
  })
  
  const loading = ref(false)
  const errorMessage = ref('')
  
  const handleRegister = async () => {
    try {
      loading.value = true
      errorMessage.value = ''
      await authStore.register(form.value)
      router.push('/login')
    } catch (err) {
      errorMessage.value = err.error?.message || err.message || 'حدث خطأ أثناء إنشاء الحساب'
    } finally {
      loading.value = false
    }
  }
  </script>