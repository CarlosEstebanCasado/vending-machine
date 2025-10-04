import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type {
  MachineAlerts,
  MachineCatalogItem,
  MachineCoins,
  MachineSession,
  MachineState,
} from '@/modules/machine/api/getMachineState'
import { useMachineStore } from '@/modules/machine/store/useMachineStore'
import type { DispensedCoin } from '@/modules/machine/components/MachineControlPanel.vue'
import { getProductImage } from '@/modules/machine/utils/productAssets'

const KEYPAD_LAYOUT: string[][] = [
  ['1', '2', '3'],
  ['4', '5', '6'],
  ['7', '8', '9'],
  ['CLR', '0', 'OK'],
]

type ProductSnapshot = {
  slotCode: string
  productName: string
}

type DispensedProduct = ProductSnapshot & {
  id: number
  imageSrc?: string
}

type SelectionState = 'idle' | 'ready' | 'unavailable'

type RequirementTone = 'neutral' | 'warning' | 'positive'

export function useMachineDashboardState() {
  const machineStore = useMachineStore()

  const selectedSlotCode = ref('')
  const enteredCode = ref('')
  const lastConfirmedSlotCode = ref('')
  const panelError = ref<string | null>(null)
  const panelInfo = ref<string | null>(null)
  const returnInProgress = ref(false)
  const dispensedCoins = ref<DispensedCoin[]>([])
  const vendInProgress = ref(false)
  const dispensedProduct = ref<DispensedProduct | null>(null)

  const timerHandles = reactive({
    selectionTimeoutId: null as number | null,
    errorTimeoutId: null as number | null,
    infoTimeoutId: null as number | null,
    returnCountdownId: null as number | null,
    coinDispenseTimeoutId: null as number | null,
    vendCountdownId: null as number | null,
  })

  const machineState = computed(() => machineStore.machineState as MachineState | null)
  const session = computed(() => machineStore.session as MachineSession | null)
  const catalog = computed(() => machineStore.catalog as MachineCatalogItem[])
  const coins = computed(() => machineStore.coins as MachineCoins)
  const alerts = computed(() => machineStore.alerts as MachineAlerts)
  const loading = computed(() => machineStore.loading)
  const error = computed(() => machineStore.error)

  const formattedTimestamp = computed(() => {
    if (!machineState.value) {
      return '—'
    }

    return new Date(machineState.value.timestamp).toLocaleString()
  })

  const productCards = computed(() => [...catalog.value].sort((a, b) => Number(a.slotCode) - Number(b.slotCode)))

  const rawSelection = computed(() =>
    productCards.value.find((item) => item.slotCode === selectedSlotCode.value)
  )

  const selectedProduct = computed(() => {
    const product = rawSelection.value

    if (!product || !product.productId) {
      return null
    }

    const isReservedByCurrentSession =
      product.status === 'reserved' && session.value?.selectedSlotCode === product.slotCode

    if (product.status !== 'available' && !isReservedByCurrentSession) {
      return null
    }

    return product
  })

  const selectionState = computed<SelectionState>(() => {
    if (!selectedSlotCode.value) {
      return 'idle'
    }

    return selectedProduct.value ? 'ready' : 'unavailable'
  })

  const balanceAmount = computed(() => session.value?.balanceCents ?? 0)

  const requiredAmount = computed(() => {
    const product = selectedProduct.value

    if (product?.priceCents === null || product?.priceCents === undefined) {
      return 0
    }

    return product.priceCents
  })

  const differenceAmount = computed(() => {
    if (selectionState.value !== 'ready') {
      return 0
    }

    return requiredAmount.value - balanceAmount.value
  })

  const balanceDisplay = computed(() => centsToCurrency(balanceAmount.value))

  const displayName = computed(() => {
    if (selectionState.value === 'unavailable') {
      return 'Product unavailable'
    }

    const product = selectedProduct.value
    if (product) {
      return product.productName ?? 'Product'
    }

    return 'Select a product'
  })

  const displayPriceText = computed(() => {
    const product = selectedProduct.value

    if (product?.priceCents !== null && product?.priceCents !== undefined) {
      return centsToCurrency(product.priceCents)
    }

    return '—'
  })

  const requiredDisplay = computed(() => {
    if (selectionState.value !== 'ready') {
      return '—'
    }

    const amount = Math.abs(differenceAmount.value)
    return centsToCurrency(amount)
  })

  const requirementLabel = computed(() => {
    if (selectionState.value !== 'ready') {
      return 'Required'
    }

    return differenceAmount.value > 0 ? 'Required' : 'Change'
  })

  const requirementTone = computed<RequirementTone>(() => {
    if (selectionState.value !== 'ready') {
      return 'neutral'
    }

    if (differenceAmount.value > 0) {
      return 'warning'
    }

    if (differenceAmount.value < 0) {
      return 'positive'
    }

    return 'neutral'
  })

  const keypadButtons = computed(() => KEYPAD_LAYOUT)

  const canReturnCoins = computed(() => {
    if (dispensedCoins.value.length > 0) {
      return true
    }

    if (!session.value) {
      return false
    }

    const hasBalance = (session.value.balanceCents ?? 0) > 0
    const hasInsertedCoins = Object.values(session.value.insertedCoins ?? {}).some((quantity) => quantity > 0)

    return hasBalance || hasInsertedCoins
  })

  const returnButtonDisabled = computed(
    () => loading.value || returnInProgress.value || vendInProgress.value || !canReturnCoins.value
  )

  const purchaseDisabled = computed(() => {
    if (loading.value || returnInProgress.value || vendInProgress.value || dispensedProduct.value) {
      return true
    }

    const product = selectedProduct.value
    if (!product || product.priceCents === null || product.priceCents === undefined) {
      return true
    }

    return balanceAmount.value < product.priceCents
  })

  watch(
    session,
    (newSession) => {
      if (newSession?.selectedSlotCode) {
        selectedSlotCode.value = newSession.selectedSlotCode
        lastConfirmedSlotCode.value = newSession.selectedSlotCode
      } else if (!enteredCode.value) {
        selectedSlotCode.value = ''
        lastConfirmedSlotCode.value = ''
      }
    },
    { immediate: true }
  )

  watch(
    error,
    (newError) => {
      clearErrorTimeout()

      if (newError) {
        setInfo(null)
        panelError.value = newError
        timerHandles.errorTimeoutId = window.setTimeout(() => {
          panelError.value = null
          machineStore.clearError()
          timerHandles.errorTimeoutId = null
        }, 5000)
      } else {
        panelError.value = null
      }
    },
    { immediate: true }
  )

  onMounted(() => {
    void machineStore.fetchMachineState()
  })

  onBeforeUnmount(() => {
    clearSelectionTimeout()
    clearErrorTimeout()
    clearInfoTimeout()
    clearReturnCountdown()
    clearCoinDispenseTimeout()
    clearVendCountdown()
  })

  async function refresh(): Promise<void> {
    await machineStore.fetchMachineState()
  }

  function centsToCurrency(cents: number): string {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(cents / 100)
  }

  async function selectSlot(slotCode: string): Promise<void> {
    if (vendInProgress.value) {
      return
    }

    if (await trySelectSlot(slotCode)) {
      resetEnteredCode()
    }
  }

  async function handleKeypadPress(value: string): Promise<void> {
    if (!canProcessKeypad(value)) {
      return
    }

    if (value === 'CLR') {
      await handleClearSelection()
      return
    }

    if (value === 'OK') {
      await handleConfirmCode()
      return
    }

    await handleNumericKey(value)
  }

  async function handleInsertCoin(coinValue: number): Promise<void> {
    if (vendInProgress.value) {
      return
    }

    const ready = await ensureSessionReady()
    if (!ready) {
      return
    }

    try {
      await machineStore.insertCoin(coinValue)
    } catch (error) {
      console.error('Failed to insert coin', error)
    }
  }

  async function handlePurchase(): Promise<void> {
    if (purchaseDisabled.value) {
      return
    }

    const ready = await ensureSessionReady()
    if (!ready) {
      return
    }

    const product = selectedProduct.value
    if (!product) {
      return
    }

    const snapshot: ProductSnapshot = {
      slotCode: product.slotCode,
      productName: product.productName ?? 'Product',
    }

    vendInProgress.value = true
    setInfo('Preparing your product...')
    clearVendCountdown()

    timerHandles.vendCountdownId = window.setTimeout(() => {
      void executePurchase(snapshot)
    }, 5000)
  }

  async function executePurchase(snapshot: ProductSnapshot): Promise<void> {
    clearVendCountdown()

    dispensedProduct.value = createDispensedProduct(snapshot)
    setInfo('Product ready! Tap to collect')

    try {
      const result = await machineStore.purchaseProduct()

      if (result.sale.status === 'completed') {
        if (Object.keys(result.sale.changeDispensed).length > 0) {
          enqueueReturnedCoins(result.sale.changeDispensed)
          setInfo('Please collect your product and change')
        } else {
          setInfo('Please collect your product')
        }

        try {
          await machineStore.fetchMachineState()
        } catch (refreshError) {
          console.error('Failed to refresh machine state', refreshError)
        }

        selectedSlotCode.value = ''
        lastConfirmedSlotCode.value = ''
      } else if (result.sale.status === 'cancelled_insufficient_change') {
        if (Object.keys(result.sale.returnedCoins).length > 0) {
          enqueueReturnedCoins(result.sale.returnedCoins)
        }

        dispensedProduct.value = null
        setInfo('Unable to provide exact change. Returning coins.')

        try {
          await machineStore.fetchMachineState()
        } catch (refreshError) {
          console.error('Failed to refresh machine state after cancellation', refreshError)
        }

        selectedSlotCode.value = ''
        lastConfirmedSlotCode.value = ''
      }
    } catch (error) {
      console.error('Failed to complete purchase', error)
      dispensedProduct.value = null
      setInfo(null)
    } finally {
      vendInProgress.value = false
    }
  }

  async function handleReturnCoins(): Promise<void> {
    if (vendInProgress.value || returnInProgress.value) {
      return
    }

    const hasDispensedCoins = dispensedCoins.value.length > 0

    if (hasDispensedCoins) {
      if (dispensedProduct.value) {
        setInfo('Please collect your product and change')
      } else {
        setInfo('Please collect your coins')
      }
      return
    }

    if (!canReturnCoins.value) {
      return
    }

    const ready = await ensureSessionReady()
    if (!ready) {
      return
    }

    returnInProgress.value = true
    setInfo('Returning coins...')
    clearReturnCountdown()

    timerHandles.returnCountdownId = window.setTimeout(() => {
      void executeReturnCoins()
    }, 5000)
  }

  async function executeReturnCoins(): Promise<void> {
    clearReturnCountdown()

    try {
      const result = await machineStore.returnCoins()
      enqueueReturnedCoins(result.returnedCoins)
      selectedSlotCode.value = ''
      lastConfirmedSlotCode.value = ''

      const totalReturned = Object.values(result.returnedCoins).reduce((sum, quantity) => sum + quantity, 0)
      if (totalReturned > 0) {
        setInfo('Please collect your coins')
      } else {
        setInfo('No coins to return', 3000)
      }
    } catch (error) {
      console.error('Failed to return coins', error)
      setInfo(null)
    } finally {
      returnInProgress.value = false
    }
  }

  function enqueueReturnedCoins(returnedCoins: Record<number, number>): void {
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
          label: coinLabel(value),
        })
      }
    })

    if (queue.length === 0) {
      dispensedCoins.value = []
      return
    }

    clearCoinDispenseTimeout()

    const dispenseNext = () => {
      const next = queue.shift()
      if (!next) {
        timerHandles.coinDispenseTimeoutId = null
        return
      }

      dispensedCoins.value.push(next)
      timerHandles.coinDispenseTimeoutId = window.setTimeout(dispenseNext, 400)
    }

    dispenseNext()
  }

  function collectReturnedCoin(id: number): void {
    dispensedCoins.value = dispensedCoins.value.filter((coin) => coin.id !== id)

    if (dispensedCoins.value.length === 0 && !returnInProgress.value) {
      if (dispensedProduct.value) {
        setInfo('Please collect your product')
      } else {
        setInfo(null)
      }
    }
  }

  function collectDispensedProduct(): void {
    if (!dispensedProduct.value) {
      return
    }

    dispensedProduct.value = null

    if (dispensedCoins.value.length > 0) {
      setInfo('Please collect your change')
    } else {
      setInfo('Enjoy your product!', 3000)
    }
  }

  function createDispensedProduct(snapshot: ProductSnapshot): DispensedProduct {
    return {
      id: Date.now() + Math.floor(Math.random() * 1000),
      slotCode: snapshot.slotCode,
      productName: snapshot.productName,
      imageSrc: getProductImage(snapshot.productName),
    }
  }

  async function ensureSessionReady(): Promise<boolean> {
    try {
      await machineStore.ensureSession()
      return true
    } catch (error) {
      console.error('Failed to start session', error)
      return false
    }
  }

  async function trySelectSlot(slotCode: string): Promise<boolean> {
    if (vendInProgress.value) {
      return false
    }

    const ready = await ensureSessionReady()
    if (!ready) {
      return false
    }

    const previousSlot = selectedSlotCode.value
    selectedSlotCode.value = slotCode
    clearSelectionTimeout()

    const selected = productCards.value.find((item) => item.slotCode === slotCode)

    if (!selected || !selected.productId || selected.status !== 'available') {
      scheduleSelectionRevert(lastConfirmedSlotCode.value || '')
      return true
    }

    try {
      await machineStore.selectProduct(selected.productId, selected.slotCode)
      lastConfirmedSlotCode.value = selected.slotCode
      clearSelectionTimeout()
      return true
    } catch (error) {
      console.error('Failed to update selected product', error)
      selectedSlotCode.value = previousSlot
      clearSelectionTimeout()
      return false
    }
  }

  async function handleConfirmCode(): Promise<void> {
    if (!enteredCode.value) {
      resetEnteredCode()
      return
    }

    const match = findSlotByCode(enteredCode.value)
    resetEnteredCode()

    if (!match) {
      return
    }

    await trySelectSlot(match.slotCode)
  }

  async function handleNumericKey(value: string): Promise<void> {
    const candidate = appendToCode(value)

    const exact = findSlotByCode(candidate)
    if (exact) {
      resetEnteredCode()
      await trySelectSlot(exact.slotCode)
      return
    }

    if (hasPartialMatch(candidate)) {
      return
    }

    const fallback = findSlotByCode(value)
    resetEnteredCode()

    if (fallback) {
      await trySelectSlot(fallback.slotCode)
      return
    }

    selectedSlotCode.value = candidate
    scheduleSelectionRevert(lastConfirmedSlotCode.value || '')
  }

  function findSlotByCode(code: string): MachineCatalogItem | undefined {
    return productCards.value.find((item) => item.slotCode === code)
  }

  function hasPartialMatch(prefix: string): boolean {
    return productCards.value.some((item) => item.slotCode.startsWith(prefix))
  }

  function appendToCode(value: string): string {
    const maxCodeLength = 3
    enteredCode.value = `${enteredCode.value}${value}`.slice(0, maxCodeLength)
    return enteredCode.value
  }

  async function handleClearSelection(): Promise<void> {
    if (vendInProgress.value) {
      return
    }

    resetEnteredCode()

    clearReturnCountdown()
    clearCoinDispenseTimeout()
    dispensedCoins.value = []
    returnInProgress.value = false
    setInfo(null)

    const hadSelection = Boolean(lastConfirmedSlotCode.value || selectedSlotCode.value)

    if (hadSelection) {
      const ready = await ensureSessionReady()
      if (ready) {
        try {
          await machineStore.clearSelection()
        } catch (error) {
          console.error('Failed to clear selection', error)
        }
      }
    }

    selectedSlotCode.value = ''
    lastConfirmedSlotCode.value = ''
    clearSelectionTimeout()
  }

  function resetEnteredCode(): void {
    enteredCode.value = ''
  }

  function canProcessKeypad(value: string): boolean {
    return value !== '' && !loading.value && !vendInProgress.value
  }

  function clearSelectionTimeout(): void {
    if (timerHandles.selectionTimeoutId !== null) {
      window.clearTimeout(timerHandles.selectionTimeoutId)
      timerHandles.selectionTimeoutId = null
    }
  }

  function clearErrorTimeout(): void {
    if (timerHandles.errorTimeoutId !== null) {
      window.clearTimeout(timerHandles.errorTimeoutId)
      timerHandles.errorTimeoutId = null
    }
  }

  function clearInfoTimeout(): void {
    if (timerHandles.infoTimeoutId !== null) {
      window.clearTimeout(timerHandles.infoTimeoutId)
      timerHandles.infoTimeoutId = null
    }
  }

  function clearReturnCountdown(): void {
    if (timerHandles.returnCountdownId !== null) {
      window.clearTimeout(timerHandles.returnCountdownId)
      timerHandles.returnCountdownId = null
    }
  }

  function clearCoinDispenseTimeout(): void {
    if (timerHandles.coinDispenseTimeoutId !== null) {
      window.clearTimeout(timerHandles.coinDispenseTimeoutId)
      timerHandles.coinDispenseTimeoutId = null
    }
  }

  function clearVendCountdown(): void {
    if (timerHandles.vendCountdownId !== null) {
      window.clearTimeout(timerHandles.vendCountdownId)
      timerHandles.vendCountdownId = null
    }
  }

  function setInfo(message: string | null, duration = 0): void {
    clearInfoTimeout()
    panelInfo.value = message

    if (message && duration > 0) {
      timerHandles.infoTimeoutId = window.setTimeout(() => {
        panelInfo.value = null
        timerHandles.infoTimeoutId = null
      }, duration)
    }
  }

  function coinLabel(value: number): string {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(value / 100)
  }

  function scheduleSelectionRevert(targetSlotCode: string): void {
    clearSelectionTimeout()

    timerHandles.selectionTimeoutId = window.setTimeout(() => {
      selectedSlotCode.value = targetSlotCode
      timerHandles.selectionTimeoutId = null
    }, 5000)
  }

  return {
    machineState,
    session,
    productCards,
    coins,
    alerts,
    loading,
    panelError,
    panelInfo,
    selectedSlotCode,
    enteredCode,
    lastConfirmedSlotCode,
    returnInProgress,
    dispensedCoins,
    vendInProgress,
    dispensedProduct,
    formattedTimestamp,
    displayName,
    displayPriceText,
    balanceDisplay,
    requiredDisplay,
    requirementLabel,
    requirementTone,
    selectionState,
    keypadButtons,
    returnButtonDisabled,
    purchaseDisabled,
    refresh,
    selectSlot,
    handleKeypadPress,
    handleInsertCoin,
    handlePurchase,
    handleReturnCoins,
    collectReturnedCoin,
    collectDispensedProduct,
  }
}
