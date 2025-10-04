<template>
  <main class="admin-dashboard">
    <section class="admin-dashboard__panel">
      <header class="admin-dashboard__header">
        <div>
          <h1>Machine Administration</h1>
          <p>Welcome back, {{ adminEmail }}.</p>
        </div>
        <button class="admin-dashboard__logout" type="button" @click="handleLogout">
          Log out
        </button>
      </header>

      <section class="admin-dashboard__section">
        <header>
          <h2>Coin Inventory</h2>
          <p class="admin-dashboard__section-subtitle">Monitor change availability per denomination.</p>
        </header>

        <div v-if="coinLoading" class="admin-dashboard__card admin-dashboard__card--loading">
          Fetching coin inventory...
        </div>
        <div v-else-if="coinError" class="admin-dashboard__card admin-dashboard__card--error">
          {{ coinError }}
        </div>
        <div v-else class="admin-dashboard__card">
          <table class="coin-table">
            <thead>
              <tr>
                <th scope="col">Denomination</th>
                <th scope="col">Available</th>
                <th scope="col">Reserved</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="balance in coinBalances" :key="balance.denomination">
                <th scope="row">{{ formatCoin(balance.denomination) }}</th>
                <td>{{ balance.available }}</td>
                <td>{{ balance.reserved }}</td>
              </tr>
            </tbody>
          </table>

          <footer class="coin-table__footer">
            <span :class="{ 'coin-table__alert': insufficientChange }">
              {{ insufficientChange ? 'Attention: insufficient change available' : 'Change availability is healthy' }}
            </span>
            <small>Updated {{ relativeUpdatedAt }}</small>
          </footer>
        </div>
      </section>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getCoinInventory, type CoinInventoryResponse } from '@/modules/admin/api/getCoinInventory'
import { useAdminAuthStore } from '@/modules/admin/store/useAdminAuthStore'

const router = useRouter()
const authStore = useAdminAuthStore()

authStore.initializeFromStorage()

const adminEmail = computed(() => authStore.user?.email ?? 'admin')

const coinInventory = ref<CoinInventoryResponse | null>(null)
const coinLoading = ref(true)
const coinError = ref<string | null>(null)

const coinBalances = computed(() => coinInventory.value?.balances ?? [])
const insufficientChange = computed(() => coinInventory.value?.insufficientChange ?? false)
const relativeUpdatedAt = computed(() => {
  if (!coinInventory.value) {
    return '—'
  }

  return new Date(coinInventory.value.updatedAt).toLocaleString()
})

onMounted(() => {
  if (!authStore.isAuthenticated) {
    void router.replace({ name: 'admin.login' })
    return
  }

  void loadCoinInventory()
})

function handleLogout(): void {
  authStore.logout()
  void router.replace({ name: 'machine.dashboard' })
}

async function loadCoinInventory(): Promise<void> {
  coinLoading.value = true
  coinError.value = null

  try {
    coinInventory.value = await getCoinInventory()
  } catch (error) {
    console.error('Failed to load coin inventory', error)
    coinError.value = error instanceof Error ? error.message : 'Unable to load coin inventory.'
  } finally {
    coinLoading.value = false
  }
}

function formatCoin(value: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
  }).format(value / 100)
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
  width: min(960px, 100%);
  background: rgba(15, 23, 42, 0.92);
  border-radius: 26px;
  padding: 2.75rem;
  box-shadow: 0 28px 56px rgba(15, 23, 42, 0.5);
  color: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.admin-dashboard__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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

.admin-dashboard__section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.admin-dashboard__section h2 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.admin-dashboard__section-subtitle {
  margin: 0.35rem 0 0;
  color: #cbd5f5;
}

.admin-dashboard__card {
  background: rgba(12, 20, 35, 0.95);
  border-radius: 18px;
  padding: 1.5rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.35);
}

.admin-dashboard__card--loading,
.admin-dashboard__card--error {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: #cbd5f5;
  background: rgba(12, 20, 35, 0.85);
}

.admin-dashboard__card--error {
  color: #fecaca;
}

.coin-table {
  width: 100%;
  border-collapse: collapse;
  color: #f8fafc;
}

.coin-table th,
.coin-table td {
  padding: 0.75rem 0.5rem;
  text-align: left;
}

.coin-table thead {
  border-bottom: 1px solid rgba(148, 163, 184, 0.3);
  color: #cbd5f5;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.coin-table tbody tr:nth-child(odd) {
  background: rgba(17, 24, 39, 0.45);
}

.coin-table__footer {
  margin-top: 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #cbd5f5;
}

.coin-table__alert {
  color: #fbbf24;
}

.admin-dashboard__logout {
  padding: 0.65rem 1.2rem;
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

  .coin-table__footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>
