import { createRouter, createWebHistory } from 'vue-router'
import type { NavigationGuardNext, RouteLocationNormalized, RouteRecordRaw } from 'vue-router'
import MachineDashboard from '@/modules/machine/views/MachineDashboard.vue'
import AdminLogin from '@/modules/admin/views/AdminLogin.vue'
import AdminDashboard from '@/modules/admin/views/AdminDashboard.vue'
import { useAdminAuthStore } from '@/modules/admin/store/useAdminAuthStore'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'machine.dashboard',
    component: MachineDashboard,
  },
  {
    path: '/admin',
    name: 'admin.login',
    component: AdminLogin,
  },
  {
    path: '/admin/dashboard',
    name: 'admin.dashboard',
    component: AdminDashboard,
    meta: { requiresAdmin: true },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) => {
  const authStore = useAdminAuthStore()
  authStore.initializeFromStorage()

  if (to.meta.requiresAdmin && !authStore.isAuthenticated) {
    return next({ name: 'admin.login', query: { redirect: to.fullPath } })
  }

  if (to.name === 'admin.login' && authStore.isAuthenticated) {
    return next({ name: 'admin.dashboard' })
  }

  return next()
})

export default router
