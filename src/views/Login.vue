<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4" dir="rtl">
      <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">تسجيل الدخول</h2>
        
        <form @submit.prevent="handleLogin">
          <div class="mb-4">
            <label class="block text-gray-700 mb-1 font-medium">البريد الإلكتروني</label>
            <input v-model="form.email" type="email" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 mb-1 font-medium">كلمة المرور</label>
            <input v-model="form.password" type="password" required class="w-full border border-gray-300 p-2.5 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded font-semibold hover:bg-blue-700 transition">
            دخول
          </button>
        </form>
  
        <p v-if="errorMessage" class="text-red-500 mt-4 text-center text-sm font-medium">{{ errorMessage }}</p>
  
        <p class="mt-6 text-center text-gray-600 text-sm">
          ليس لديك حساب؟ 
          <router-link to="/register" class="text-blue-600 font-semibold hover:underline">إنشاء حساب جديد</router-link>
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
  
  const form = ref({ email: '', password: '' })
  const errorMessage = ref('')
  
  const handleLogin = async () => {
    try {
      errorMessage.value = ''
      await authStore.login(form.value)
      router.push('/')
    } catch (err) {
      errorMessage.value = err.error?.message || err.message || 'بيانات الدخول غير صحيحة'
    }
  }
  </script>