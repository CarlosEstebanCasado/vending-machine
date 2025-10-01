<template>
  <main class="machine-layout">
    <div class="machine-wrapper">
      <section class="catalog-panel">
        <header class="panel-header">
          <div>
            <h1>Vending Machine</h1>
            <p class="panel-subtitle">A fully simulated experience</p>
          </div>
          <div class="panel-meta">
            <span class="meta-item">Machine ID: {{ machineState?.machineId ?? '—' }}</span>
            <span class="meta-item">Last update: {{ formattedTimestamp }}</span>
          </div>
          <button type="button" class="refresh" @click="refresh" :disabled="loading">
            {{ loading ? 'Refreshing…' : 'Refresh' }}
          </button>
        </header>

        <div class="product-grid">
          <article
            v-for="item in productCards"
            :key="item.slotCode"
            class="product-card"
            :class="{
              'product-card--selected': isSelected(item.slotCode),
              'product-card--empty': !item.productId,
            }"
            @click="selectSlot(item.slotCode)"
          >
            <div class="product-card__image" :class="imageClass(item.productId)"></div>
            <div class="product-card__info">
              <span class="product-card__slot">{{ item.slotCode }}</span>
              <div class="product-card__details">
                <span class="product-card__name">{{ item.productName ?? 'Empty' }}</span>
                <span class="product-card__price">
                  {{ item.priceCents !== null ? centsToCurrency(item.priceCents) : '—' }}
                </span>
              </div>
            </div>
          </article>
        </div>

        <div class="dispense-tray"></div>
      </section>

      <aside class="control-panel">
        <div class="product-display" :class="{ 'product-display--idle': showPrompt }">
          <div class="product-display__header">
            <span class="product-display__slot">Slot {{ selectedSlotCode || '—' }}</span>
            <span class="product-display__entered" v-if="enteredCode">{{ enteredCode }}</span>
          </div>

          <h2 class="product-display__name">{{ displayProductName }}</h2>
          <div class="product-display__price">{{ displayPriceText }}</div>

          <dl class="product-display__status">
            <div>
              <dt>Inserted coins</dt>
              <dd>{{ centsToCurrency(balanceAmount) }}</dd>
            </div>
            <div>
              <dt>Required</dt>
              <dd :class="{ negative: !showPrompt && balanceAmount < requiredAmount }">
                {{ showPrompt ? '—' : centsToCurrency(requiredAmount) }}
              </dd>
            </div>
          </dl>

          <div v-if="showPrompt" class="product-display__marquee">
            <div class="marquee-track">
              <span>Select a product to start the sale · </span>
              <span>Select a product to start the sale · </span>
              <span>Select a product to start the sale · </span>
            </div>
          </div>
        </div>

        <div class="keypad">
          <div
            v-for="row in keypadButtons"
            :key="row.join('-')"
            class="keypad-row"
          >
            <button
              v-for="key in row"
              :key="key"
              class="keypad-key"
              type="button"
              :disabled="loading"
              @click="handleKeypadPress(key)"
            >
              {{ key }}
            </button>
          </div>
        </div>

        <div class="coin-insert"></div>

        <div class="actions">
          <button class="action primary" type="button" disabled>Buy product</button>
          <button class="action secondary" type="button" disabled>Return coin</button>
        </div>

        <div class="coin-slot"></div>

        <p v-if="error" class="inline-alert error">{{ error }}</p>
        <p v-else-if="alerts.outOfStock.length" class="inline-alert warning">
          Out of stock: {{ alerts.outOfStock.join(', ') }}
        </p>
      </aside>
    </div>
  </main>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { mapState, mapStores } from 'pinia'
import type {
  MachineAlerts,
  MachineCatalogItem,
  MachineCoins,
  MachineSession,
  MachineState,
} from '@/modules/machine/api/getMachineState'
import { useMachineStore } from '@/modules/machine/store/useMachineStore'

export default defineComponent({
  name: 'MachineDashboard',
  data() {
    return {
      selectedSlotCode: '' as string,
      enteredCode: '' as string,
    }
  },
  computed: {
    ...mapStores(useMachineStore),
    ...mapState(useMachineStore, {
      machineState: (store) => store.machineState as MachineState | null,
      session: (store) => store.session as MachineSession | null,
      catalog: (store) => store.catalog as MachineCatalogItem[],
      coins: (store) => store.coins as MachineCoins,
      alerts: (store) => store.alerts as MachineAlerts,
      loading: (store) => store.loading,
      error: (store) => store.error,
    }),
    formattedTimestamp(): string {
      if (!this.machineState) {
        return '—'
      }

      return new Date(this.machineState.timestamp).toLocaleString()
    },
    productCards(): MachineCatalogItem[] {
      return [...this.catalog].sort((a, b) => Number(a.slotCode) - Number(b.slotCode))
    },
    selectedProduct(): MachineCatalogItem | null {
      if (!this.selectedSlotCode) {
        return null
      }

      return this.productCards.find((item) => item.slotCode === this.selectedSlotCode) ?? null
    },
    balanceAmount(): number {
      return this.session?.balanceCents ?? 0
    },
    requiredAmount(): number {
      return this.selectedProduct?.priceCents ?? 0
    },
    displayProductName(): string {
      return this.selectedProduct?.productName ?? 'Select a product'
    },
    displayPriceText(): string {
      if (!this.selectedProduct || this.selectedProduct.priceCents === null) {
        return '—'
      }

      return this.centsToCurrency(this.selectedProduct.priceCents)
    },
    showPrompt(): boolean {
      return this.selectedProduct === null
    },
    keypadButtons(): string[][] {
      return [
        ['1', '2', '3'],
        ['4', '5', '6'],
        ['7', '8', '9'],
        ['CLR', '0', 'OK'],
      ]
    },
  },
  created() {
    this.machineStore.fetchMachineState()
  },
  methods: {
    refresh(): void {
      this.machineStore.fetchMachineState()
    },
    centsToCurrency(cents: number): string {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
      }).format(cents / 100)
    },
    selectSlot(slotCode: string): void {
      this.selectedSlotCode = slotCode
      this.enteredCode = ''
    },
    handleKeypadPress(value: string): void {
      if (value === '' || this.loading) {
        return
      }

      if (value === 'CLR') {
        this.enteredCode = ''
        this.selectedSlotCode = ''
        return
      }

      if (value === 'OK') {
        if (this.enteredCode) {
          const match = this.productCards.find((item) => item.slotCode === this.enteredCode)
          if (match) {
            this.selectedSlotCode = match.slotCode
          }
        }
        this.enteredCode = ''
        return
      }

      const candidate = `${this.enteredCode}${value}`.slice(0, 3)
      this.enteredCode = candidate

      const exact = this.productCards.find((item) => item.slotCode === candidate)
      if (exact) {
        this.selectedSlotCode = exact.slotCode
        this.enteredCode = ''
        return
      }

      const hasPartialMatch = this.productCards.some((item) => item.slotCode.startsWith(candidate))
      if (!hasPartialMatch) {
        this.enteredCode = value
        const fallback = this.productCards.find((item) => item.slotCode === this.enteredCode)
        if (fallback) {
          this.selectedSlotCode = fallback.slotCode
          this.enteredCode = ''
        } else {
          this.selectedSlotCode = ''
          this.enteredCode = ''
        }
      }
    },
    imageClass(productId: string | null): string {
      switch (productId) {
        case 'prod-water':
          return 'product-water'
        case 'prod-soda':
          return 'product-soda'
        case 'prod-juice':
          return 'product-juice'
        default:
          return 'product-empty'
      }
    },
    isSelected(slotCode: string): boolean {
      return this.selectedSlotCode === slotCode
    },
  },
})
</script>

<style scoped>
.machine-layout {
  min-height: 100vh;
  padding: 2.5rem;
  background: #ffffff;
  display: flex;
  justify-content: center;
  align-items: center;
  box-sizing: border-box;
}

.machine-wrapper {
  width: 100%;
  max-width: 1400px;
  display: grid;
  gap: 2rem;
  grid-template-columns: minmax(0, 3fr) minmax(320px, 1fr);
  background: linear-gradient(145deg, #d7dce8, #eef1f7);
  border-radius: 28px;
  padding: 2.5rem;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
}

.catalog-panel {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.panel-header {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: center;
}

.panel-header h1 {
  margin: 0;
  font-size: 2rem;
}

.panel-subtitle {
  margin: 0.25rem 0 0;
  color: #475569;
  font-size: 0.95rem;
}

.panel-meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  color: #475569;
}

.meta-item {
  font-size: 0.85rem;
}

.refresh {
  justify-self: end;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.65rem 1.2rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.refresh:disabled {
  opacity: 0.6;
  cursor: wait;
  box-shadow: none;
}

.refresh:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
}

.product-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.product-card {
  background: linear-gradient(160deg, #f8fafc 0%, #e7ecf5 100%);
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 18px;
  padding: 1.25rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  box-shadow: 0 12px 16px rgba(15, 23, 42, 0.08);
}

.product-card--selected {
  border-color: #2563eb;
  box-shadow: 0 14px 25px rgba(37, 99, 235, 0.22);
  transform: translateY(-2px);
}

.product-card__image {
  height: 140px;
  border-radius: 16px;
  background: linear-gradient(145deg, #cbd5f5 0%, #94a3ff 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.product-water {
  background: linear-gradient(180deg, #93c5fd 0%, #3b82f6 100%);
}

.product-soda {
  background: linear-gradient(180deg, #fca5a5 0%, #ef4444 100%);
}

.product-juice {
  background: linear-gradient(180deg, #fde68a 0%, #f59e0b 100%);
}

.product-empty {
  background: repeating-linear-gradient(135deg, #e2e8f0, #e2e8f0 12px, #cbd5e1 12px, #cbd5e1 24px);
}

.product-card__info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.product-card__slot {
  font-weight: 700;
  font-size: 1.1rem;
  color: #1e293b;
}

.product-card__details {
  display: flex;
  justify-content: space-between;
  color: #475569;
  font-weight: 600;
}

.product-card__price {
  color: #1e293b;
}

.dispense-tray {
  height: 200px;
  border-radius: 18px;
  background: linear-gradient(180deg, #111827 0%, #1f2937 100%);
  box-shadow: inset 0 12px 28px rgba(0, 0, 0, 0.35);
}

.control-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  background: linear-gradient(155deg, #0f172a 0%, #1e293b 60%, #0b1220 100%);
  border-radius: 24px;
  padding: 1.75rem;
  color: white;
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.45);
}

.product-display {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: rgba(15, 23, 42, 0.6);
  border-radius: 18px;
  padding: 1.5rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  position: relative;
  overflow: hidden;
  min-height: 240px;
}

.product-display__marquee {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  background: rgba(15, 23, 42, 0.82);
  pointer-events: none;
}

.marquee-track {
  display: inline-flex;
  animation: marquee 12s linear infinite;
  white-space: nowrap;
  color: #cbd5f5;
  font-weight: 600;
  font-size: 0.95rem;
}

.marquee-track span {
  padding-right: 1.5rem;
}

@keyframes marquee {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%);
  }
}

.product-display__header {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
  color: #cbd5f5;
}

.product-display__name {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 600;
}

.product-display__price {
  font-size: 2rem;
  font-weight: 700;
  color: #60a5fa;
}

.product-display__status {
  margin: 0;
  display: grid;
  gap: 0.5rem;
}

.product-display__status div {
  display: flex;
  justify-content: space-between;
  color: #cbd5f5;
}

.product-display__status dd {
  margin: 0;
  font-weight: 600;
  color: #f8fafc;
}

.product-display__status dd.negative {
  color: #f87171;
}

.keypad {
  display: grid;
  gap: 0.75rem;
}

.keypad-row {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, 1fr);
}

.keypad-key {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 52px;
  border: none;
  border-radius: 12px;
  background: rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.25);
  font-size: 1.1rem;
  color: #e2e8f0;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.keypad-key:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 14px rgba(15, 23, 42, 0.2);
}

.keypad-key:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.actions {
  display: grid;
  gap: 0.75rem;
}

.action {
  border: none;
  border-radius: 14px;
  padding: 0.75rem 1.25rem;
  font-weight: 600;
  font-size: 1rem;
  cursor: not-allowed;
}

.action.primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white;
}

.action.secondary {
  background: rgba(148, 163, 184, 0.2);
  color: #cbd5f5;
}

.coin-slot {
  height: 160px;
  border-radius: 18px;
  background: linear-gradient(180deg, #111827 0%, #050a13 100%);
  box-shadow: inset 0 18px 32px rgba(0, 0, 0, 0.45);
}

.coin-insert {
  height: 100px;
  border-radius: 18px;
  background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
  box-shadow: inset 0 12px 24px rgba(148, 163, 184, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.coin-insert::before {
  content: '';
  width: 60px;
  height: 14px;
  border-radius: 999px;
  background: linear-gradient(180deg, #0f172a 0%, #1f2937 100%);
  box-shadow: inset 0 3px 8px rgba(15, 23, 42, 0.45);
}

.inline-alert {
  margin: 0;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.inline-alert.error {
  background: rgba(248, 113, 113, 0.15);
  color: #fecaca;
}

.inline-alert.warning {
  background: rgba(251, 191, 36, 0.15);
  color: #fde68a;
}

@media (max-width: 1024px) {
  .machine-wrapper {
    grid-template-columns: 1fr;
    padding: 1.75rem;
  }

  .control-panel {
    order: -1;
  }
}

@media (max-width: 640px) {
  .machine-layout {
    padding: 1.5rem 1rem;
  }

  .panel-header {
    grid-template-columns: 1fr;
  }

  .refresh {
    justify-self: stretch;
  }

  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .product-grid {
    grid-template-columns: 1fr;
  }
}
</style>
