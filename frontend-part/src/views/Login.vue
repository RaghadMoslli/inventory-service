<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Icon from '../components/Icon.vue'
import { useAuthStore } from '../stores/auth'

const MAX_ATTEMPTS = 5
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: String(route.query.email || ''), password: '' })
const loading = ref(false)
const errorMessage = ref('')
const failures = ref([]) // أوقات المحاولات الفاشلة خلال آخر دقيقة (تقدير محلي)
const now = ref(Date.now())
let ticker = null

const remainingLock = computed(() => Math.max(0, Math.ceil((auth.lockUntil - now.value) / 1000)))
const locked = computed(() => remainingLock.value > 0)
const clock = computed(() => `${String(Math.floor(remainingLock.value / 60)).padStart(2, '0')}:${String(remainingLock.value % 60).padStart(2, '0')}`)
const attemptsLeft = computed(() => MAX_ATTEMPTS - failures.value.filter((t) => now.value - t < 60000).length)

function startTicker() {
  stopTicker()
  now.value = Date.now()
  ticker = setInterval(() => {
    now.value = Date.now()
    if (!locked.value) { stopTicker(); failures.value = []; errorMessage.value = '' }
  }, 500)
}
function stopTicker() { clearInterval(ticker); ticker = null }
onMounted(() => { if (locked.value) startTicker() })
onBeforeUnmount(stopTicker)

const safeRedirect = (value) => (typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : '/')

async function handleLogin() {
  if (locked.value || loading.value) return
  errorMessage.value = ''
  loading.value = true
  try {
    await auth.login({ email: form.email.trim(), password: form.password })
    router.replace(safeRedirect(route.query.redirect))
  } catch (err) {
    // الصفحة لا تُعاد تحميلها: تبقى البيانات المدخلة كما هي ليصحّح المستخدم ما يريد
    if (err.status === 429) {
      startTicker()
    } else if (err.status === 401) {
      failures.value.push(Date.now())
      errorMessage.value = 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
    } else {
      errorMessage.value = err.message
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen grid place-items-center p-4 relative overflow-hidden">
    <div class="w-full max-w-md card !bg-white/5 p-8 sm:p-10 shadow-2xl">
      <div class="text-center mb-8">
        <span class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 grid place-items-center text-white shadow-lg shadow-purple-500/30"><Icon name="bag" size="w-7 h-7" /></span>
        <h1 class="text-2xl font-extrabold text-white">تسجيل الدخول</h1>
        <p class="text-slate-400 text-sm mt-2">مرحباً بك مجدداً، أدخل بياناتك للمتابعة</p>
      </div>

      <p v-if="route.query.registered" class="mb-5 rounded-xl bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 text-sm p-3 text-center">تم إنشاء حسابك بنجاح، سجّل الدخول للمتابعة.</p>

      <form class="space-y-5" novalidate @submit.prevent="handleLogin">
        <div>
          <label class="label" for="email">البريد الإلكتروني</label>
          <div class="relative">
            <Icon name="mail" size="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-4 text-slate-500" />
            <input id="email" v-model="form.email" type="email" required autocomplete="email" dir="ltr" placeholder="name@example.com" class="input ps-11 text-start" />
          </div>
        </div>
        <div>
          <label class="label" for="password">كلمة المرور</label>
          <div class="relative">
            <Icon name="lock" size="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-4 text-slate-500" />
            <input id="password" v-model="form.password" type="password" required autocomplete="current-password" dir="ltr" placeholder="••••••••" class="input ps-11 text-start" />
          </div>
        </div>

        <div v-if="locked" class="rounded-xl bg-amber-950/40 border border-amber-700/50 text-amber-200 p-4 text-sm leading-7">
          <p class="font-bold flex items-center gap-2"><Icon name="clock" size="w-4 h-4" />تم إيقاف تسجيل الدخول مؤقتاً</p>
          <p class="text-amber-300/80 text-xs mt-1">بسبب كثرة المحاولات الخاطئة. يمكنك المحاولة بعد <b class="text-amber-100 tabular-nums">{{ clock }}</b></p>
        </div>
        <div v-else-if="errorMessage" class="rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 p-3 text-center text-sm font-medium" role="alert">
          {{ errorMessage }}
          <p v-if="attemptsLeft <= 2 && attemptsLeft > 0" class="text-xs text-rose-300/70 mt-1">تبقّى {{ attemptsLeft }} محاولات قبل الإيقاف المؤقت</p>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="loading || locked">
          <span v-if="locked">متاح بعد {{ clock }}</span>
          <span v-else-if="loading">جاري الدخول...</span>
          <span v-else>دخول</span>
        </button>
      </form>

      <p class="mt-8 text-center text-slate-400 text-sm">
        ليس لديك حساب؟
        <router-link to="/register" class="text-purple-300 font-bold hover:text-purple-200">إنشاء حساب جديد</router-link>
      </p>
    </div>
  </div>
</template>
