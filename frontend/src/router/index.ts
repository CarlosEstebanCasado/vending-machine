import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import MachineDashboard from '@/modules/machine/views/MachineDashboard.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'machine.dashboard',
    component: MachineDashboard,
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router
