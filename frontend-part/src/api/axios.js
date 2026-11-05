import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api', // رابط الباك إند
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Interceptor لإضافة التوكن تلقائياً مع كل طلب
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}, (error) => {
  return Promise.reject(error)
})

// Interceptor لمعالجة الاستجابات والأخطاء العامة (مثل 401)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token')
      // إعادة التوجيه لتسجيل الدخول إذا انتهت صلاحية الجلسة
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api