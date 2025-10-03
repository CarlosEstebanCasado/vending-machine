import { defineStore } from 'pinia'
import type {
  MachineAlerts,
  MachineCatalogItem,
  MachineCoins,
  MachineSession,
  MachineState,
} from '@/modules/machine/api/getMachineState'
import { getMachineState } from '@/modules/machine/api/getMachineState'
import { selectMachineProduct } from '@/modules/machine/api/selectMachineProduct'
import { startMachineSession } from '@/modules/machine/api/startMachineSession'

interface MachineStoreState {
  machineState: MachineState | null
  loading: boolean
  error: string | null
  sessionPromise: Promise<MachineSession> | null
}

export const useMachineStore = defineStore('machine', {
  state: (): MachineStoreState => ({
    machineState: null,
    loading: false,
    error: null,
    sessionPromise: null,
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
    async ensureSession(): Promise<MachineSession> {
      if (this.machineState?.session) {
        return this.machineState.session
      }

      if (this.sessionPromise) {
        return this.sessionPromise
      }

      this.error = null

      const promise = startMachineSession()
        .then((result) => {
          const session = result.session

          if (this.machineState) {
            this.machineState = {
              ...this.machineState,
              machineId: result.machineId,
              session,
            }
          } else {
            this.machineState = {
              machineId: result.machineId,
              timestamp: new Date().toISOString(),
              session,
              catalog: [],
              coins: { available: {}, reserved: {} },
              alerts: { insufficientChange: false, outOfStock: [] },
            }
          }

          return session
        })
        .catch((error) => {
          if (error instanceof Error) {
            this.error = error.message
          } else {
            this.error = 'Unexpected error starting session'
          }

          throw error
        })
        .finally(() => {
          this.sessionPromise = null
        })

      this.sessionPromise = promise
      return promise
    },
    async selectProduct(productId: string): Promise<MachineSession> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await selectMachineProduct({
          sessionId: activeSession.id,
          productId,
        })

        if (this.machineState) {
          this.machineState = {
            ...this.machineState,
            machineId: result.machineId,
            session: result.session,
          }
        } else {
          this.machineState = {
            machineId: result.machineId,
            timestamp: new Date().toISOString(),
            session: result.session,
            catalog: [],
            coins: { available: {}, reserved: {} },
            alerts: { insufficientChange: false, outOfStock: [] },
          }
        }

        return result.session
      } catch (error) {
        if (error instanceof Error) {
          this.error = error.message
        } else {
          this.error = 'Unexpected error selecting product'
        }

        throw error
      } finally {
        this.loading = false
      }
    },
  },
})
