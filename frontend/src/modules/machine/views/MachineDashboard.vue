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
        :requirement-label="requirementLabel"
        :requirement-tone="requirementTone"
        :selection-state="selectionState"
        :keypad-buttons="keypadButtons"
        :alerts="alerts"
        :error="panelError"
        :info="panelInfo"
        :loading="loading"
        :return-disabled="returnButtonDisabled"
        :purchase-disabled="purchaseDisabled"
        :dispensed-coins="dispensedCoins"
        @keypad="handleKeypadPress"
        @insert-coin="handleInsertCoin"
        @purchase="handlePurchase"
        @return-coins="handleReturnCoins"
        @collect-coin="collectReturnedCoin"
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
import MachineControlPanel, { type DispensedCoin } from '@/modules/machine/components/MachineControlPanel.vue'

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
      lastConfirmedSlotCode: '' as string,
      selectionTimeoutId: null as number | null,
      panelError: null as string | null,
      errorTimeoutId: null as number | null,
      panelInfo: null as string | null,
      infoTimeoutId: null as number | null,
      returnInProgress: false,
      returnCountdownId: null as number | null,
      coinDispenseTimeoutId: null as number | null,
      dispensedCoins: [] as DispensedCoin[],
    }
  },
  watch: {
    session: {
      immediate: true,
      handler(newSession: MachineSession | null) {
        if (newSession?.selectedSlotCode) {
          this.selectedSlotCode = newSession.selectedSlotCode
          this.lastConfirmedSlotCode = newSession.selectedSlotCode
        } else if (!this.enteredCode) {
          this.selectedSlotCode = ''
          this.lastConfirmedSlotCode = ''
        }
      },
    },
    error: {
      immediate: true,
      handler(newError: string | null) {
        this.clearErrorTimeout()

        if (newError) {
          this.setInfo(null)
          this.panelError = newError
          this.errorTimeoutId = window.setTimeout(() => {
            this.panelError = null
            this.machineStore.clearError()
            this.errorTimeoutId = null
          }, 5000)
        } else {
          this.panelError = null
        }
      },
    },
  },
  beforeUnmount() {
    this.clearSelectionTimeout()
    this.clearErrorTimeout()
    this.clearInfoTimeout()
    this.clearReturnCountdown()
    this.clearCoinDispenseTimeout()
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
    selectedProduct(): MachineCatalogItem | null {
      const product = this.rawSelection

      if (!product) {
        return null
      }

      if (!product.productId || product.status !== 'available') {
        return null
      }

      return product
    },
    selectionState(): 'idle' | 'ready' | 'unavailable' {
      if (!this.selectedSlotCode) {
        return 'idle'
      }

      return this.selectedProduct ? 'ready' : 'unavailable'
    },
    balanceAmount(): number {
      return this.session?.balanceCents ?? 0
    },
    requiredAmount(): number {
      const product = this.selectedProduct
      if (product?.priceCents === null || product?.priceCents === undefined) {
        return 0
      }

      return product.priceCents
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

      const product = this.selectedProduct
      if (product) {
        return product.productName ?? 'Product'
      }

      return 'Select a product'
    },
    displayPriceText(): string {
      const product = this.selectedProduct
      if (product?.priceCents !== null && product?.priceCents !== undefined) {
        return this.centsToCurrency(product.priceCents)
      }

      return '—'
    },
    requiredDisplay(): string {
      if (this.selectionState !== 'ready') {
        return '—'
      }

      const difference = this.differenceAmount
      const amount = Math.abs(difference)

      return this.centsToCurrency(amount)
    },
    requirementLabel(): string {
      if (this.selectionState !== 'ready') {
        return 'Required'
      }

      return this.differenceAmount > 0 ? 'Required' : 'Change'
    },
    requirementTone(): 'neutral' | 'warning' | 'positive' {
      if (this.selectionState !== 'ready') {
        return 'neutral'
      }

      if (this.differenceAmount > 0) {
        return 'warning'
      }

      if (this.differenceAmount < 0) {
        return 'positive'
      }

      return 'neutral'
    },
    differenceAmount(): number {
      if (this.selectionState !== 'ready') {
        return 0
      }

      return this.requiredAmount - this.balanceAmount
    },
    keypadButtons(): string[][] {
      return [
        ['1', '2', '3'],
        ['4', '5', '6'],
        ['7', '8', '9'],
        ['CLR', '0', 'OK'],
      ]
    },
    canReturnCoins(): boolean {
      if (this.dispensedCoins.length > 0) {
        return true
      }

      if (!this.session) {
        return false
      }

      const hasBalance = (this.session.balanceCents ?? 0) > 0
      const hasInsertedCoins = Object.values(this.session.insertedCoins ?? {}).some((quantity) => quantity > 0)

      return hasBalance || hasInsertedCoins
    },
    returnButtonDisabled(): boolean {
      return this.loading || this.returnInProgress || !this.canReturnCoins
    },
    purchaseDisabled(): boolean {
      if (this.loading || this.returnInProgress) {
        return true
      }

      const product = this.selectedProduct

      if (!product || product.priceCents === null || product.priceCents === undefined) {
        return true
      }

      return this.balanceAmount < product.priceCents
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
    async selectSlot(slotCode: string): Promise<void> {
      if (await this.trySelectSlot(slotCode)) {
        this.resetEnteredCode()
      }
    },
    async handleKeypadPress(value: string): Promise<void> {
      if (!this.canProcessKeypad(value)) {
        return
      }

      if (value === 'CLR') {
        await this.handleClearSelection()
        return
      }

      if (value === 'OK') {
        await this.handleConfirmCode()
        return
      }

      await this.handleNumericKey(value)
    },
    async handleInsertCoin(coinValue: number): Promise<void> {
      const ready = await this.ensureSessionReady()
      if (!ready) {
        return
      }

      try {
        await this.machineStore.insertCoin(coinValue)
      } catch (error) {
        console.error('Failed to insert coin', error)
      }
    },
    async handlePurchase(): Promise<void> {
      if (this.purchaseDisabled) {
        return
      }

      const ready = await this.ensureSessionReady()
      if (!ready) {
        return
      }

      this.setInfo('Dispensing product...')

      try {
        const result = await this.machineStore.purchaseProduct()

        if (result.sale.status === 'completed') {
          if (Object.keys(result.sale.changeDispensed).length > 0) {
            this.enqueueReturnedCoins(result.sale.changeDispensed)
            this.setInfo('Please collect your change')
          } else {
            this.setInfo('Enjoy your product!', 3000)
          }

          await this.machineStore.fetchMachineState()
          this.selectedSlotCode = ''
          this.lastConfirmedSlotCode = ''
        } else if (result.sale.status === 'cancelled_insufficient_change') {
          if (Object.keys(result.sale.returnedCoins).length > 0) {
            this.enqueueReturnedCoins(result.sale.returnedCoins)
          }

          this.setInfo('Unable to provide exact change. Returning coins.')
        }
      } catch (error) {
        console.error('Failed to complete purchase', error)
      }
    },
    async handleReturnCoins(): Promise<void> {
      if (this.returnInProgress) {
        return
      }

      const hasDispensedCoins = this.dispensedCoins.length > 0

      if (hasDispensedCoins) {
        this.setInfo('Please collect your coins')
        return
      }

      if (!this.canReturnCoins) {
        return
      }

      const ready = await this.ensureSessionReady()
      if (!ready) {
        return
      }

      this.returnInProgress = true
      this.setInfo('Returning coins...')
      this.clearReturnCountdown()

      this.returnCountdownId = window.setTimeout(() => {
        void this.executeReturnCoins()
      }, 5000)
    },
    async executeReturnCoins(): Promise<void> {
      this.clearReturnCountdown()

      try {
        const result = await this.machineStore.returnCoins()
        this.enqueueReturnedCoins(result.returnedCoins)
        this.selectedSlotCode = ''
        this.lastConfirmedSlotCode = ''

        const totalReturned = Object.values(result.returnedCoins).reduce((sum, quantity) => sum + quantity, 0)
        if (totalReturned > 0) {
          this.setInfo('Please collect your coins')
        } else {
          this.setInfo('No coins to return', 3000)
        }
      } catch (error) {
        console.error('Failed to return coins', error)
        this.setInfo(null)
      } finally {
        this.returnInProgress = false
      }
    },
    enqueueReturnedCoins(returnedCoins: Record<number, number>): void {
      const queue: DispensedCoin[] = []

      const denominations = Object.keys(returnedCoins)
        .map((value) => Number(value))
        .filter((value) => returnedCoins[value] > 0)
        .sort((a, b) => b - a)

      denominations.forEach((value) => {
        const quantity = returnedCoins[value]
        for (let index = 0; index < quantity; index += 1) {
          queue.push({
            id: Date.now() + Math.floor(Math.random() * 1000) + index,
            value,
            label: this.coinLabel(value),
          })
        }
      })

      if (queue.length === 0) {
        this.dispensedCoins = []
        return
      }

      this.clearCoinDispenseTimeout()

      const dispenseNext = () => {
        const next = queue.shift()
        if (!next) {
          this.coinDispenseTimeoutId = null
          return
        }

        this.dispensedCoins.push(next)
        this.coinDispenseTimeoutId = window.setTimeout(dispenseNext, 400)
      }

      dispenseNext()
    },
    collectReturnedCoin(id: number): void {
      this.dispensedCoins = this.dispensedCoins.filter((coin) => coin.id !== id)

      if (this.dispensedCoins.length === 0 && !this.returnInProgress) {
        this.setInfo(null)
      }
    },
    async ensureSessionReady(): Promise<boolean> {
      try {
        await this.machineStore.ensureSession()
        return true
      } catch (error) {
        console.error('Failed to start session', error)
        return false
      }
    },
    async trySelectSlot(slotCode: string): Promise<boolean> {
      const ready = await this.ensureSessionReady()
      if (!ready) {
        return false
      }

      const previousSlot = this.selectedSlotCode
      this.selectedSlotCode = slotCode
      this.clearSelectionTimeout()

      const selected = this.productCards.find((item) => item.slotCode === slotCode)

      if (!selected || !selected.productId || selected.status !== 'available') {
        this.scheduleSelectionRevert(this.lastConfirmedSlotCode || '')
        return true
      }

      try {
        await this.machineStore.selectProduct(selected.productId, selected.slotCode)
        this.lastConfirmedSlotCode = selected.slotCode
        this.clearSelectionTimeout()
        return true
      } catch (error) {
        console.error('Failed to update selected product', error)
        this.selectedSlotCode = previousSlot
        this.clearSelectionTimeout()
        return false
      }
    },
    async handleConfirmCode(): Promise<void> {
      if (!this.enteredCode) {
        this.resetEnteredCode()
        return
      }

      const match = this.findSlotByCode(this.enteredCode)
      this.resetEnteredCode()

      if (!match) {
        return
      }

      await this.trySelectSlot(match.slotCode)
    },
    async handleNumericKey(value: string): Promise<void> {
      const candidate = this.appendToCode(value)

      const exact = this.findSlotByCode(candidate)
      if (exact) {
        this.resetEnteredCode()
        await this.trySelectSlot(exact.slotCode)
        return
      }

      if (this.hasPartialMatch(candidate)) {
        return
      }

      const fallback = this.findSlotByCode(value)
      this.resetEnteredCode()

      if (fallback) {
        await this.trySelectSlot(fallback.slotCode)
        return
      }

      this.selectedSlotCode = candidate
      this.scheduleSelectionRevert(this.lastConfirmedSlotCode || '')
    },
    findSlotByCode(code: string): MachineCatalogItem | undefined {
      return this.productCards.find((item) => item.slotCode === code)
    },
    hasPartialMatch(prefix: string): boolean {
      return this.productCards.some((item) => item.slotCode.startsWith(prefix))
    },
    appendToCode(value: string): string {
      const maxCodeLength = 3
      this.enteredCode = `${this.enteredCode}${value}`.slice(0, maxCodeLength)
      return this.enteredCode
    },
    async handleClearSelection(): Promise<void> {
      this.resetEnteredCode()

      this.clearReturnCountdown()
      this.clearCoinDispenseTimeout()
      this.dispensedCoins = []
      this.returnInProgress = false
      this.setInfo(null)

      const hadSelection = Boolean(this.lastConfirmedSlotCode || this.selectedSlotCode)

      if (hadSelection) {
        const ready = await this.ensureSessionReady()
        if (ready) {
          try {
            await this.machineStore.clearSelection()
          } catch (error) {
            console.error('Failed to clear selection', error)
          }
        }
      }

      this.selectedSlotCode = ''
      this.lastConfirmedSlotCode = ''
      this.clearSelectionTimeout()
    },
    resetSelection(): void {
      void this.handleClearSelection()
    },
    resetEnteredCode(): void {
      this.enteredCode = ''
    },
    canProcessKeypad(value: string): boolean {
      return value !== '' && !this.loading
    },
    clearSelectionTimeout(): void {
      if (this.selectionTimeoutId !== null) {
        window.clearTimeout(this.selectionTimeoutId)
        this.selectionTimeoutId = null
      }
    },
    clearErrorTimeout(): void {
      if (this.errorTimeoutId !== null) {
        window.clearTimeout(this.errorTimeoutId)
        this.errorTimeoutId = null
      }
    },
    clearInfoTimeout(): void {
      if (this.infoTimeoutId !== null) {
        window.clearTimeout(this.infoTimeoutId)
        this.infoTimeoutId = null
      }
    },
    clearReturnCountdown(): void {
      if (this.returnCountdownId !== null) {
        window.clearTimeout(this.returnCountdownId)
        this.returnCountdownId = null
      }
    },
    clearCoinDispenseTimeout(): void {
      if (this.coinDispenseTimeoutId !== null) {
        window.clearTimeout(this.coinDispenseTimeoutId)
        this.coinDispenseTimeoutId = null
      }
    },
    setInfo(message: string | null, duration = 0): void {
      this.clearInfoTimeout()
      this.panelInfo = message

      if (message && duration > 0) {
        this.infoTimeoutId = window.setTimeout(() => {
          this.panelInfo = null
          this.infoTimeoutId = null
        }, duration)
      }
    },
    coinLabel(value: number): string {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
      }).format(value / 100)
    },
    scheduleSelectionRevert(targetSlotCode: string): void {
      this.clearSelectionTimeout()

      this.selectionTimeoutId = window.setTimeout(() => {
        this.selectedSlotCode = targetSlotCode
        this.selectionTimeoutId = null
      }, 5000)
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
