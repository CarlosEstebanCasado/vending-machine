<template>
  <main class="machine-layout">
    <div class="machine-wrapper">
      <section class="catalog-panel">
        <MachineHeader
          :machine-id="machineState?.machineId ?? '—'"
          :timestamp="formattedTimestamp"
          :loading="loading"
          @refresh="refresh"
        />

        <MachineProductGrid
          :products="productCards"
          :selected-slot-code="selectedSlotCode"
          @select="selectSlot"
        />

        <div class="dispense-tray"></div>
      </section>

      <MachineControlPanel
        :selected-slot-code="selectedSlotCode"
        :entered-code="enteredCode"
        :display-name="displayName"
        :display-price="displayPriceText"
        :balance-display="balanceDisplay"
        :required-display="requiredDisplay"
        :show-negative="showNegative"
        :selection-state="selectionState"
        :keypad-buttons="keypadButtons"
        :alerts="alerts"
        :error="error"
        :loading="loading"
        @keypad="handleKeypadPress"
      />
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
import MachineHeader from '@/modules/machine/components/MachineHeader.vue'
import MachineProductGrid from '@/modules/machine/components/MachineProductGrid.vue'
import MachineControlPanel from '@/modules/machine/components/MachineControlPanel.vue'

export default defineComponent({
  name: 'MachineDashboard',
  components: {
    MachineHeader,
    MachineProductGrid,
    MachineControlPanel,
  },
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
    rawSelection(): MachineCatalogItem | undefined {
      return this.productCards.find((item) => item.slotCode === this.selectedSlotCode)
    },
    selectionState(): 'idle' | 'ready' | 'unavailable' {
      if (!this.selectedSlotCode) {
        return 'idle'
      }

      if (!this.rawSelection) {
        return 'unavailable'
      }

      if (!this.rawSelection.productId || this.rawSelection.status !== 'available') {
        return 'unavailable'
      }

      return 'ready'
    },
    selectedProduct(): MachineCatalogItem | null {
      return this.selectionState === 'ready' ? (this.rawSelection as MachineCatalogItem) : null
    },
    balanceAmount(): number {
      return this.session?.balanceCents ?? 0
    },
    requiredAmount(): number {
      return this.selectedProduct?.priceCents ?? 0
    },
    showPrompt(): boolean {
      return this.selectionState === 'idle'
    },
    balanceDisplay(): string {
      return this.centsToCurrency(this.balanceAmount)
    },
    displayName(): string {
      if (this.selectionState === 'unavailable') {
        return 'Product unavailable'
      }

      if (this.selectedProduct) {
        return this.selectedProduct.productName ?? 'Product'
      }

      return 'Select a product'
    },
    displayPriceText(): string {
      if (this.selectionState === 'ready' && this.selectedProduct?.priceCents !== null) {
        return this.centsToCurrency(this.selectedProduct.priceCents)
      }

      return '—'
    },
    requiredDisplay(): string {
      if (this.selectionState !== 'ready') {
        return '—'
      }

      return this.centsToCurrency(this.requiredAmount)
    },
    showNegative(): boolean {
      return this.selectionState === 'ready' && this.balanceAmount < this.requiredAmount
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
      if (hasPartialMatch) {
        this.selectedSlotCode = ''
        return
      }

      const fallback = this.productCards.find((item) => item.slotCode === value)
      if (fallback) {
        this.selectedSlotCode = fallback.slotCode
      } else {
        this.selectedSlotCode = candidate
      }
      this.enteredCode = ''
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

.dispense-tray {
  height: 200px;
  border-radius: 18px;
  background: linear-gradient(180deg, #111827 0%, #1f2937 100%);
  box-shadow: inset 0 12px 28px rgba(0, 0, 0, 0.35);
}

@media (max-width: 1024px) {
  .machine-wrapper {
    grid-template-columns: 1fr;
    padding: 1.75rem;
  }
}

@media (max-width: 640px) {
  .machine-layout {
    padding: 1.5rem 1rem;
  }
}
</style>
