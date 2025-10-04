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

          <section class="coin-adjustment">
            <header class="coin-adjustment__header">
              <h3>Adjust Coin Inventory</h3>
              <p>Record deposits or withdrawals to keep reserves in sync.</p>
            </header>

            <form class="coin-adjustment__form" @submit.prevent="submitCoinAdjustment">
              <fieldset class="coin-adjustment__operation" :disabled="adjustmentSubmitting">
                <legend class="visually-hidden">Select adjustment type</legend>

                <label
                  class="coin-adjustment__operation-option"
                  :class="{ 'coin-adjustment__operation-option--active': adjustmentOperation === 'deposit' }"
                >
                  <input
                    v-model="adjustmentOperation"
                    type="radio"
                    name="coin-adjustment-operation"
                    value="deposit"
                    :disabled="adjustmentSubmitting"
                  />
                  <span>Deposit</span>
                </label>

                <label
                  class="coin-adjustment__operation-option"
                  :class="{ 'coin-adjustment__operation-option--active': adjustmentOperation === 'withdraw' }"
                >
                  <input
                    v-model="adjustmentOperation"
                    type="radio"
                    name="coin-adjustment-operation"
                    value="withdraw"
                    :disabled="adjustmentSubmitting"
                  />
                  <span>Withdraw</span>
                </label>
              </fieldset>

              <div class="coin-adjustment__grid">
                <label
                  v-for="denomination in DENOMINATIONS"
                  :key="denomination"
                  class="coin-adjustment__input"
                >
                  <span>{{ formatCoin(denomination) }}</span>
                  <input
                    v-model.number="adjustmentValues[denomination]"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    :disabled="adjustmentSubmitting"
                    placeholder="0"
                  />
                </label>
              </div>

              <p v-if="adjustmentError" class="coin-adjustment__feedback coin-adjustment__feedback--error">
                {{ adjustmentError }}
              </p>
              <p v-else-if="adjustmentSuccess" class="coin-adjustment__feedback coin-adjustment__feedback--success">
                {{ adjustmentSuccess }}
              </p>

              <button class="coin-adjustment__submit" type="submit" :disabled="adjustmentSubmitting">
                {{ adjustmentSubmitting ? 'Saving...' : submitLabel }}
              </button>
            </form>
          </section>
        </div>
      </section>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getCoinInventory, type CoinInventoryResponse } from '@/modules/admin/api/getCoinInventory'
import { updateCoinInventory, type CoinInventoryOperation } from '@/modules/admin/api/updateCoinInventory'
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

const DENOMINATIONS = [100, 25, 10, 5] as const

const adjustmentOperation = ref<CoinInventoryOperation>('deposit')
const adjustmentValues = reactive<Record<number, number>>(
  DENOMINATIONS.reduce((accumulator, denomination) => {
    accumulator[denomination] = 0
    return accumulator
  }, {} as Record<number, number>),
)
const adjustmentSubmitting = ref(false)
const adjustmentError = ref<string | null>(null)
const adjustmentSuccess = ref<string | null>(null)

const availableByDenomination = computed<Record<number, number>>(() => {
  const lookup: Record<number, number> = {}

  for (const balance of coinBalances.value) {
    lookup[balance.denomination] = balance.available
  }

  return lookup
})

const submitLabel = computed(() =>
  adjustmentOperation.value === 'deposit' ? 'Record Deposit' : 'Record Withdrawal',
)

watch(adjustmentOperation, () => {
  adjustmentError.value = null
  adjustmentSuccess.value = null
})

watch(adjustmentValues, () => {
  adjustmentError.value = null
  adjustmentSuccess.value = null
}, { deep: true })

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

async function loadCoinInventory(options: { withSpinner?: boolean } = {}): Promise<void> {
  const { withSpinner = true } = options

  if (withSpinner) {
    coinLoading.value = true
  }

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

function resetAdjustmentValues(): void {
  DENOMINATIONS.forEach((denomination) => {
    adjustmentValues[denomination] = 0
  })
}

async function submitCoinAdjustment(): Promise<void> {
  adjustmentError.value = null
  adjustmentSuccess.value = null

  const normalizedEntries: Array<[number, number]> = []

  DENOMINATIONS.forEach((denomination) => {
    const rawValue = adjustmentValues[denomination] ?? 0
    const normalized = Number.isFinite(rawValue) ? Math.max(0, Math.floor(rawValue)) : 0

    adjustmentValues[denomination] = normalized

    if (normalized > 0) {
      normalizedEntries.push([denomination, normalized])
    }
  })

  if (normalizedEntries.length === 0) {
    adjustmentError.value = 'Specify at least one denomination with a positive quantity.'
    return
  }

  if (adjustmentOperation.value === 'withdraw') {
    for (const [denomination, quantity] of normalizedEntries) {
      const available = availableByDenomination.value[denomination] ?? 0

      if (quantity > available) {
        adjustmentError.value = `Cannot withdraw more than available for ${formatCoin(denomination)}.`
        return
      }
    }
  }

  adjustmentSubmitting.value = true

  try {
    const denominationsPayload: Record<number, number> = {}

    for (const [denomination, quantity] of normalizedEntries) {
      denominationsPayload[denomination] = quantity
    }

    await updateCoinInventory({
      operation: adjustmentOperation.value,
      denominations: denominationsPayload,
    })

    await loadCoinInventory({ withSpinner: false })
    resetAdjustmentValues()
    adjustmentSuccess.value = 'Coin inventory updated.'
  } catch (error) {
    console.error('Failed to update coin inventory', error)
    adjustmentError.value = error instanceof Error ? error.message : 'Unable to update coin inventory.'
  } finally {
    adjustmentSubmitting.value = false
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

.coin-adjustment {
  margin-top: 1.75rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(148, 163, 184, 0.25);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.coin-adjustment__header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.coin-adjustment__header p {
  margin: 0.35rem 0 0;
  color: #9fb7ff;
  font-size: 0.9rem;
}

.coin-adjustment__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.coin-adjustment__operation {
  margin: 0;
  padding: 0;
  border: none;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.coin-adjustment__operation-option {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  background: rgba(30, 41, 59, 0.65);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  user-select: none;
}

.coin-adjustment__operation-option--active {
  border-color: rgba(56, 189, 248, 0.8);
  background: rgba(14, 165, 233, 0.18);
}

.coin-adjustment__operation-option input {
  accent-color: #38bdf8;
}

.coin-adjustment__grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.coin-adjustment__input {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  background: rgba(17, 24, 39, 0.6);
  border-radius: 12px;
  padding: 0.75rem;
  border: 1px solid rgba(148, 163, 184, 0.18);
}

.coin-adjustment__input span {
  font-size: 0.9rem;
  color: #cbd5f5;
}

.coin-adjustment__input input {
  background: rgba(12, 20, 35, 0.85);
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 10px;
  padding: 0.5rem 0.65rem;
  color: #f8fafc;
  font-size: 1rem;
}

.coin-adjustment__input input:disabled {
  opacity: 0.6;
}

.coin-adjustment__feedback {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.coin-adjustment__feedback--error {
  color: #fecaca;
}

.coin-adjustment__feedback--success {
  color: #bbf7d0;
}

.coin-adjustment__submit {
  align-self: flex-start;
  padding: 0.65rem 1.4rem;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(56, 189, 248, 0.35), rgba(59, 130, 246, 0.45));
  color: #f8fafc;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.coin-adjustment__submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 18px 32px rgba(56, 189, 248, 0.35);
}

.coin-adjustment__submit:disabled {
  cursor: not-allowed;
  opacity: 0.65;
  box-shadow: none;
  transform: none;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
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
