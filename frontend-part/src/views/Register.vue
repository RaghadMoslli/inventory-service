<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '../components/Icon.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', confirm: '' })
const errors = reactive({ name: '', email: '', password: '', confirm: '' })
const loading = ref(false)
const errorMessage = ref('')

function validate() {
  errors.name = form.name.trim() ? '' : 'الاسم مطلوب'
  errors.email = /^\S+@\S+\.\S+$/.test(form.email.trim()) ? '' : 'أدخل بريداً إلكترونياً صحيحاً'
  errors.password = form.password.length >= 8 ? '' : 'كلمة المرور يجب ألا تقل عن 8 أحرف'
  errors.confirm = form.confirm === form.password ? '' : 'كلمتا المرور غير متطابقتين'
  return !Object.values(errors).some(Boolean)
}

async function handleRegister() {
  if (loading.value || !validate()) return
  loading.value = true
  errorMessage.value = ''
  try {
    await auth.register({ name: form.name.trim(), email: form.email.trim(), password: form.password })
    router.replace({ name: 'Login', query: { registered: '1', email: form.email.trim() } })
  } catch (err) {
    for (const [field, msg] of Object.entries(err.fields || {})) if (field in errors) errors[field] = msg
    errorMessage.value = Object.keys(err.fields || {}).length ? '' : err.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen grid place-items-center p-4">
    <div class="w-full max-w-md card !bg-white/5 p-8 sm:p-10 shadow-2xl">
      <div class="text-center mb-8">
        <span class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 grid place-items-center text-white shadow-lg shadow-purple-500/30"><Icon name="user" size="w-7 h-7" /></span>
        <h1 class="text-2xl font-extrabold text-white">إنشاء حساب جديد</h1>
        <p class="text-slate-400 text-sm mt-2">انضم إلينا وابدأ التسوق</p>
      </div>

      <form class="space-y-4" novalidate @submit.prevent="handleRegister">
        <div>
          <label class="label" for="name">الاسم الكامل</label>
          <input id="name" v-model="form.name" type="text" autocomplete="name" placeholder="أدخل اسمك الكامل" class="input" :class="{ 'input-error': errors.name }" />
          <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
        </div>
        <div>
          <label class="label" for="email">البريد الإلكتروني</label>
          <input id="email" v-model="form.email" type="email" autocomplete="email" dir="ltr" placeholder="example@domain.com" class="input text-start" :class="{ 'input-error': errors.email }" />
          <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
        </div>
        <div>
          <label class="label" for="password">كلمة المرور</label>
          <input id="password" v-model="form.password" type="password" autocomplete="new-password" dir="ltr" placeholder="8 أحرف على الأقل" class="input text-start" :class="{ 'input-error': errors.password }" />
          <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
        </div>
        <div>
          <label class="label" for="confirm">تأكيد كلمة المرور</label>
          <input id="confirm" v-model="form.confirm" type="password" autocomplete="new-password" dir="ltr" placeholder="أعد كتابة كلمة المرور" class="input text-start" :class="{ 'input-error': errors.confirm }" />
          <p v-if="errors.confirm" class="field-error">{{ errors.confirm }}</p>
        </div>

        <p v-if="errorMessage" class="rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 p-3 text-center text-sm" role="alert">{{ errorMessage }}</p>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="loading">{{ loading ? 'جاري إنشاء الحساب...' : 'إنشاء حساب' }}</button>
      </form>

      <p class="mt-8 text-center text-slate-400 text-sm">
        لديك حساب بالفعل؟
        <router-link to="/login" class="text-purple-300 font-bold hover:text-purple-200">تسجيل الدخول</router-link>
      </p>
    </div>
  </div>
</template>
