
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue')
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('../views/Register.vue')
  },
  {
    path: '/',
    name: 'Home',
    component: () => import('../views/Home.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/products/create',
    name: 'AdminAddProduct',
    component: () => import('../views/AdminAddProduct.vue'),
    meta: {
      requiresAuth: true,
      requiresAdmin: true
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const authStore = useAuthStore()

  // الصفحات التي تتطلب تسجيل الدخول
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return '/login'
  }

  // الصفحات المخصصة للأدمن
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return '/'
  }

  // السماح للأدمن والمستخدم العادي بفتح الرئيسية
  return true
})

export default router
