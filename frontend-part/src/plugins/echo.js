// اتصال Laravel Echo (WebSocket عبر Reverb) — مثيل واحد مشترك لكل التطبيق.
// [غالية] أنشأته؛ رغد وراما تستعملانه (getEcho) ولا تنشئان اتصالاً ثانياً.
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'
import { API_BASE_URL } from '../api/axios'

window.Pusher = Pusher // Echo (broadcaster: reverb) يعتمد على pusher-js

const KEY = import.meta.env.VITE_REVERB_APP_KEY || 'ecommerce-key'
const HOST = import.meta.env.VITE_REVERB_HOST || window.location.hostname
const PORT = Number(import.meta.env.VITE_REVERB_PORT || 8085)
const SCHEME = import.meta.env.VITE_REVERB_SCHEME || 'http'

/** حالة الاتصال كما يعرّفها pusher-js: connecting | connected | unavailable | disconnected | failed | initialized */
export const connectionState = ref('disconnected')

let echo = null
let echoToken = null

export function getEcho() {
  const token = localStorage.getItem('token')
  if (!token) return null
  if (echo && echoToken === token) return echo

  disconnectEcho() // توكن جديد (تسجيل دخول مستخدم آخر) => اتصال جديد

  echo = new Echo({
    broadcaster: 'reverb',
    key: KEY,
    wsHost: HOST,
    wsPort: PORT,
    wssPort: PORT,
    forceTLS: SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    // الاشتراك بالقنوات الخاصة يمرّ بـ POST /api/v1/broadcasting/auth مع توكن JWT نفسه
    authEndpoint: `${API_BASE_URL}/broadcasting/auth`,
    auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
  })
  echoToken = token

  const connection = echo.connector.pusher.connection
  connectionState.value = connection.state
  connection.bind('state_change', ({ current }) => {
    connectionState.value = current
  })

  return echo
}

export function disconnectEcho() {
  if (echo) {
    try { echo.disconnect() } catch { /* لا شيء */ }
  }
  echo = null
  echoToken = null
  connectionState.value = 'disconnected'
}

/** نص عربي ولون لمؤشر الاتصال */
export function connectionLabel(state) {
  switch (state) {
    case 'connected': return { text: 'متصل', tone: 'ok' }
    case 'connecting':
    case 'initialized': return { text: 'جارٍ الاتصال...', tone: 'warn' }
    case 'unavailable': return { text: 'إعادة اتصال...', tone: 'warn' }
    default: return { text: 'منقطع', tone: 'bad' }
  }
}
