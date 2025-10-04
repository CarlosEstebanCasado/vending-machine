<template>
  <main class="admin-dashboard">
    <section class="admin-dashboard__panel">
      <header class="admin-dashboard__header">
        <h1>Machine Administration</h1>
        <p>Welcome back, {{ adminEmail }}.</p>
      </header>

      <p class="admin-dashboard__placeholder">
        This area will host inventory, pricing, and maintenance tools for the vending machine.
      </p>

      <button class="admin-dashboard__logout" type="button" @click="handleLogout">
        Log out
      </button>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '@/modules/admin/store/useAdminAuthStore'

const router = useRouter()
const authStore = useAdminAuthStore()

authStore.initializeFromStorage()

const adminEmail = computed(() => authStore.user?.email ?? 'admin')

onMounted(() => {
  if (!authStore.isAuthenticated) {
    void router.replace({ name: 'admin.login' })
  }
})

function handleLogout(): void {
  authStore.logout()
  void router.replace({ name: 'machine.dashboard' })
}
</script>

<style scoped>
.admin-dashboard {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  background: linear-gradient(140deg, #0f172a 0%, #1f2a40 100%);
  box-sizing: border-box;
}

.admin-dashboard__panel {
  width: min(720px, 100%);
  background: rgba(15, 23, 42, 0.92);
  border-radius: 26px;
  padding: 2.75rem;
  box-shadow: 0 28px 56px rgba(15, 23, 42, 0.5);
  color: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.admin-dashboard__header h1 {
  margin: 0;
  font-size: 2.15rem;
  font-weight: 700;
}

.admin-dashboard__header p {
  margin: 0.35rem 0 0;
  color: #cbd5f5;
}

.admin-dashboard__placeholder {
  margin: 0;
  line-height: 1.7;
  color: #e2e8f0;
}

.admin-dashboard__logout {
  align-self: flex-start;
  padding: 0.8rem 1.4rem;
  border: none;
  border-radius: 12px;
  background: rgba(248, 113, 113, 0.2);
  color: #fecaca;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.admin-dashboard__logout:hover {
  transform: translateY(-1px);
  box-shadow: 0 18px 32px rgba(248, 113, 113, 0.35);
}

@media (max-width: 640px) {
  .admin-dashboard {
    padding: 1.5rem;
  }

  .admin-dashboard__panel {
    padding: 2rem;
  }
}
</style>
