import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useMachineStore } from '@/modules/machine/store/useMachineStore'
import type { MachineState } from '@/modules/machine/api/getMachineState'
import * as machineApi from '@/modules/machine/api/getMachineState'
import * as insertCoinApi from '@/modules/machine/api/insertMachineCoin'
import * as returnCoinsApi from '@/modules/machine/api/returnMachineCoins'
import * as startSessionApi from '@/modules/machine/api/startMachineSession'

const mockMachineState = (): MachineState => ({
  machineId: 'vm-001',
  timestamp: new Date('2024-01-01T10:00:00Z').toISOString(),
  session: {
    id: 'session-123',
    state: 'collecting',
    balanceCents: 125,
    insertedCoins: { 100: 1, 25: 1 },
    selectedProductId: 'prod-water',
    selectedSlotCode: '11',
    changePlan: { 25: 2 },
  },
  catalog: [
    {
      slotCode: '11',
      productId: 'prod-water',
      productName: 'Water',
      priceCents: 65,
      availableQuantity: 5,
      capacity: 10,
      recommendedSlotQuantity: 8,
      status: 'available',
      lowStock: false,
    },
  ],
  coins: {
    available: { 100: 5, 25: 20 },
    reserved: {},
  },
  alerts: {
    insufficientChange: false,
    outOfStock: [],
  },
})

describe('useMachineStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('loads machine state successfully', async () => {
    const state = mockMachineState()
    const spy = vi.spyOn(machineApi, 'getMachineState').mockResolvedValue(state)

    const store = useMachineStore()
    await store.fetchMachineState()

    expect(spy).toHaveBeenCalledTimes(1)
    expect(store.machineState).toEqual(state)
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('captures errors when loading fails', async () => {
    const spy = vi.spyOn(machineApi, 'getMachineState').mockRejectedValue(new Error('Network error'))

    const store = useMachineStore()
    await store.fetchMachineState()

    expect(spy).toHaveBeenCalledTimes(1)
    expect(store.machineState).toBeNull()
    expect(store.error).toBe('Network error')
    expect(store.loading).toBe(false)
  })

  it('inserts coin and updates session from API response', async () => {
    const initialState = mockMachineState()
    const sessionResult = {
      machineId: 'vm-001',
      session: {
        ...initialState.session!,
        balanceCents: 225,
        insertedCoins: { 100: 2, 25: 1 },
        selectedSlotCode: '11',
      },
    }

    vi.spyOn(startSessionApi, 'startMachineSession').mockResolvedValue(sessionResult)
    vi.spyOn(insertCoinApi, 'insertMachineCoin').mockResolvedValue(sessionResult)

    const store = useMachineStore()
    store.machineState = initialState

    await store.insertCoin(100)

    expect(insertCoinApi.insertMachineCoin).toHaveBeenCalledWith({
      sessionId: initialState.session!.id,
      denominationCents: 100,
    })
    expect(store.machineState?.session?.balanceCents).toBe(225)
    expect(store.machineState?.session?.insertedCoins).toEqual({ 100: 2, 25: 1 })
    expect(store.error).toBeNull()
  })

  it('clears error when clearError is called', () => {
    const store = useMachineStore()
    store.error = 'Something went wrong'

    store.clearError()

    expect(store.error).toBeNull()
  })

  it('returns coins and updates session state', async () => {
    const state = mockMachineState()
    state.session = {
      ...state.session!,
      balanceCents: 150,
      insertedCoins: { 100: 1, 50: 1 },
    }

    const returnResult = {
      machineId: 'vm-001',
      session: {
        ...state.session,
        balanceCents: 0,
        insertedCoins: {},
        selectedProductId: null,
        selectedSlotCode: null,
      },
      returnedCoins: { 100: 1, 50: 1 },
    }

    vi.spyOn(returnCoinsApi, 'returnMachineCoins').mockResolvedValue(returnResult)

    const store = useMachineStore()
    store.machineState = state

    const response = await store.returnCoins()

    expect(returnCoinsApi.returnMachineCoins).toHaveBeenCalledWith(state.session!.id)
    expect(store.machineState?.session?.balanceCents).toBe(0)
    expect(store.machineState?.session?.insertedCoins).toEqual({})
    expect(response.returnedCoins).toEqual({ 100: 1, 50: 1 })
  })
})
