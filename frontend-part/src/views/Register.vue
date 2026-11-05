<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-purple-950 to-slate-950 p-4 relative overflow-hidden" dir="rtl">

      <div class="absolute -top-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
  
      <div class="w-full max-w-md bg-white/10 backdrop-blur-xl border border-white/10 p-8 rounded-2xl shadow-2xl relative z-10">
        <div class="text-center mb-8">
          <h2 class="text-3xl font-extrabold text-white tracking-tight">إنشاء حساب جديد</h2>
          <p class="text-slate-400 text-sm mt-2">انضم إلينا وابدأ رحلتك معنا</p>
        </div>
        
        <form @submit.prevent="handleRegister" class="space-y-4">
          <div>
            <label class="block text-slate-300 mb-2 text-sm font-medium">الاسم الكامل</label>
            <input 
              v-model="form.name" 
              type="text" 
              required 
              placeholder="أدخلي اسمك الكامل"
              class="w-full bg-slate-900/50 border border-slate-700/60 text-white placeholder-slate-500 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200" 
            />
          </div>
  
          <div>
            <label class="block text-slate-300 mb-2 text-sm font-medium">البريد الإلكتروني</label>
            <input 
              v-model="form.email" 
              type="email" 
              required 
              placeholder="example@domain.com"
              class="w-full bg-slate-900/50 border border-slate-700/60 text-white placeholder-slate-500 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200" 
            />
          </div>
  
          <div>
            <label class="block text-slate-300 mb-2 text-sm font-medium">كلمة المرور</label>
            <input 
              v-model="form.password" 
              type="password" 
              required 
              placeholder="••••••••"
              class="w-full bg-slate-900/50 border border-slate-700/60 text-white placeholder-slate-500 px-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200" 
            />
          </div>
  
          <button 
            type="submit" 
            :disabled="loading"
            class="w-full mt-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-xl font-medium shadow-lg shadow-purple-500/25 hover:from-purple-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading">جاري إنشاء الحساب...</span>
            <span v-else>إنشاء حساب</span>
          </button>
        </form>
  
        <p v-if="errorMessage" class="bg-red-500/10 border border-red-500/20 text-red-400 mt-4 p-3 rounded-xl text-center text-sm font-medium">
          {{ errorMessage }}
        </p>
  
        <p class="mt-8 text-center text-slate-400 text-sm">
          لديك حساب بالفعل؟ 
          <router-link to="/login" class="text-purple-400 font-semibold hover:text-purple-300 transition duration-150 underline-offset-4 hover:underline">
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
      loading.value = `true`
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