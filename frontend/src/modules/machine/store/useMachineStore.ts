import { defineStore } from 'pinia'
import type {
  MachineAlerts,
  MachineCatalogItem,
  MachineCoins,
  MachineSession,
  MachineState,
} from '@/modules/machine/api/getMachineState'
import { getMachineState } from '@/modules/machine/api/getMachineState'

interface MachineStoreState {
  machineState: MachineState | null
  loading: boolean
  error: string | null
}

export const useMachineStore = defineStore('machine', {
  state: (): MachineStoreState => ({
    machineState: null,
    loading: false,
    error: null,
  }),
  getters: {
    catalog(): MachineCatalogItem[] {
      return this.machineState?.catalog ?? []
    },
    session(): MachineSession | null {
      return this.machineState?.session ?? null
    },
    coins(): MachineCoins {
      return this.machineState?.coins ?? { available: {}, reserved: {} }
    },
    alerts(): MachineAlerts {
      return (
        this.machineState?.alerts ?? {
          insufficientChange: false,
          outOfStock: [],
        }
      )
    },
  },
  actions: {
    async fetchMachineState() {
      this.loading = true
      this.error = null

      try {
        this.machineState = await getMachineState()
      } catch (error) {
        if (error instanceof Error) {
          this.error = error.message
        } else {
          this.error = 'Unexpected error retrieving machine state'
        }
      } finally {
        this.loading = false
      }
    },
  },
})
