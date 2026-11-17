import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from './api/axios'
import { useAuthStore } from './stores/auth'
import { useUiStore } from './stores/ui'
import './style.css'

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)
app.use(router)


const auth = useAuthStore(pinia)
let redirecting = false
setUnauthorizedHandler(() => {
  if (redirecting || !auth.token) return
  redirecting = true
  auth.clearSession()
  useUiStore(pinia).error('انتهت الجلسة، يرجى تسجيل الدخول مرة أخرى')
  router.replace({ name: 'Login' }).finally(() => { redirecting = false })
})

app.mount('#app')

if (auth.token) auth.fetchMe()
