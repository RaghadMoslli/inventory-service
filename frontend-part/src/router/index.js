import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../components/AppLayout.vue'

const routes = [
  { path: '/login', name: 'Login', component: () => import('../views/Login.vue'), meta: { guestOnly: true, title: 'تسجيل الدخول' } },
  { path: '/register', name: 'Register', component: () => import('../views/Register.vue'), meta: { guestOnly: true, title: 'إنشاء حساب' } },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'Home', component: () => import('../views/Home.vue'), meta: { title: 'المنتجات' } },
      { path: 'orders', name: 'Orders', component: () => import('../views/Orders.vue'), meta: { title: 'الطلبات' } },
      { path: 'cart', name: 'Cart', component: () => import('../views/Cart.vue'), meta: { requiresUser: true, title: 'السلة' } },      {
        path: 'admin/products/create',
        name: 'AdminAddProduct',
        component: () => import('../views/AdminAddProduct.vue'),
        meta: { requiresAdmin: true, title: 'إضافة منتج' },
      },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  const auth = useAuthStore()
  const needsAuth = to.matched.some((r) => r.meta.requiresAuth)
  const needsAdmin = to.matched.some((r) => r.meta.requiresAdmin)
  const guestOnly = to.matched.some((r) => r.meta.guestOnly)

  if (needsAuth && !auth.isAuthenticated) {
    return { name: 'Login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }
  if (guestOnly && auth.isAuthenticated) return { name: 'Home' }
  if (needsAdmin && !auth.isAdmin) return { name: 'Home' }
  if (to.matched.some((r) => r.meta.requiresUser) && auth.isAdmin) return { name: 'Home' } // الأدمن لا يملك سلة  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | منصة التجارة الإلكترونية` : 'منصة التجارة الإلكترونية'
})

export default router
