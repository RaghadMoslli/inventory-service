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
    meta: { requiresAuth: true, requiresAdmin: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // 1. إذا كانت الصفحة تتطلب تسجيل دخول والمستخدم غير مسجل
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next('/login')
  }

  // 2. إذا كان المستخدم أدمن وحاول الذهاب لأي صفحة غير صفحة الإضافة
  if (authStore.isAuthenticated && authStore.isAdmin) {
    if (to.path !== '/admin/products/create') {
      return next('/admin/products/create')
    }
    return next() // للسماح بالدخول إذا كان المسار بالفعل /admin/products/create
  }

  // 3. إذا كان مستخدم عادي وحاول دخول صفحة الأدمن
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return next('/')
  }

  // 4. السير الطبيعي لباقي الحالات
  next()
})

export default router