import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useMachineStore } from '@/modules/machine/store/useMachineStore'
import type { MachineState } from '@/modules/machine/api/getMachineState'
import * as machineApi from '@/modules/machine/api/getMachineState'

const mockMachineState = (): MachineState => ({
  machineId: 'vm-001',
  timestamp: new Date('2024-01-01T10:00:00Z').toISOString(),
  session: {
    id: 'session-123',
    state: 'collecting',
    balanceCents: 125,
    insertedCoins: { 100: 1, 25: 1 },
    selectedProductId: 'prod-water',
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
})
