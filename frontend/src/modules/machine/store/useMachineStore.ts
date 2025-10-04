import { defineStore } from 'pinia'
import type {
  MachineAlerts,
  MachineCatalogItem,
  MachineCoins,
  MachineSession,
  MachineState,
} from '@/modules/machine/api/getMachineState'
import { getMachineState } from '@/modules/machine/api/getMachineState'
import { clearMachineSelection } from '@/modules/machine/api/clearMachineSelection'
import { insertMachineCoin } from '@/modules/machine/api/insertMachineCoin'
import { returnMachineCoins, type ReturnMachineCoinsResult } from '@/modules/machine/api/returnMachineCoins'
import { selectMachineProduct } from '@/modules/machine/api/selectMachineProduct'
import { vendMachineProduct, type VendMachineProductResult } from '@/modules/machine/api/vendMachineProduct'
import { startMachineSession } from '@/modules/machine/api/startMachineSession'

const ACTIVE_SESSION_STATES = new Set(['collecting', 'ready'])

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
    isReusableSession(session: MachineSession | null | undefined): session is MachineSession {
      if (!session) {
        return false
      }

      return ACTIVE_SESSION_STATES.has(session.state)
    },
    resolveErrorMessage(error: unknown): string {
      if (error instanceof Error) {
        const message = error.message.trim()

        if (message.startsWith('{')) {
          try {
            const parsed = JSON.parse(message)
            if (parsed && typeof parsed === 'object' && 'error' in parsed) {
              const payload = parsed.error as { message?: string }
              if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
                return payload.message
              }
            }

            if (parsed && typeof parsed === 'object' && 'message' in parsed) {
              const text = (parsed as { message?: string }).message
              if (typeof text === 'string' && text.trim() !== '') {
                return text
              }
            }
          } catch {
            // ignore JSON parse errors and fall back to original message
          }

          return message
        }

        return message !== '' ? message : 'Unexpected error'
      }

      if (typeof error === 'string' && error.trim() !== '') {
        return error
      }

      return 'Unexpected error'
    },
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
      const existingSession = this.machineState?.session ?? null

      if (this.isReusableSession(existingSession)) {
        return existingSession
      }

      if (existingSession && this.machineState) {
        this.machineState = {
          ...this.machineState,
          session: null,
        }
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
          this.error = this.resolveErrorMessage(error)

          throw error
        })
        .finally(() => {
          this.sessionPromise = null
        })

      this.sessionPromise = promise
      return promise
    },
    async selectProduct(productId: string, slotCode: string): Promise<MachineSession> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await selectMachineProduct({
          sessionId: activeSession.id,
          productId,
          slotCode,
        })

        let refreshedState: MachineState | null = null

        try {
          refreshedState = await getMachineState()
        } catch (refreshError) {
          console.error('Failed to refresh machine state after product selection', refreshError)
        }

        if (refreshedState) {
          this.machineState = refreshedState
        } else if (this.machineState) {
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
        const message = this.resolveErrorMessage(error)

        if (message.includes('No active session')) {
          try {
            await this.ensureSession()
            const retry = await selectMachineProduct({
              sessionId: this.machineState!.session!.id,
              productId,
              slotCode,
            })

            if (this.machineState) {
              this.machineState = {
                ...this.machineState,
                machineId: retry.machineId,
                session: retry.session,
              }
            } else {
              this.machineState = {
                machineId: retry.machineId,
                timestamp: new Date().toISOString(),
                session: retry.session,
                catalog: [],
                coins: { available: {}, reserved: {} },
                alerts: { insufficientChange: false, outOfStock: [] },
              }
            }

            return retry.session
          } catch (retryError) {
            this.error = this.resolveErrorMessage(retryError)
            throw retryError
          }
        }

        this.error = message
        throw error
      } finally {
        this.loading = false
      }
    },
    async insertCoin(denominationCents: number): Promise<MachineSession> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await insertMachineCoin({
          sessionId: activeSession.id,
          denominationCents,
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
        this.error = this.resolveErrorMessage(error)

        throw error
      } finally {
        this.loading = false
      }
    },
    clearError(): void {
      this.error = null
    },
    async clearSelection(): Promise<MachineSession> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await clearMachineSelection({
          sessionId: activeSession.id,
        })

        let refreshedState: MachineState | null = null

        try {
          refreshedState = await getMachineState()
        } catch (refreshError) {
          console.error('Failed to refresh machine state after clearing selection', refreshError)
        }

        if (refreshedState) {
          this.machineState = refreshedState
        } else if (this.machineState) {
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
        this.error = this.resolveErrorMessage(error)

        throw error
      } finally {
        this.loading = false
      }
    },
    async returnCoins(): Promise<ReturnMachineCoinsResult> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await returnMachineCoins(activeSession.id)

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

        return result
      } catch (error) {
        this.error = this.resolveErrorMessage(error)

        throw error
      } finally {
        this.loading = false
      }
    },
    async purchaseProduct(): Promise<VendMachineProductResult> {
      const activeSession = await this.ensureSession()

      this.loading = true
      this.error = null

      try {
        const result = await vendMachineProduct(activeSession.id)

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

        return result
      } catch (error) {
        this.error = this.resolveErrorMessage(error)

        throw error
      } finally {
        this.loading = false
      }
    },
  },
})
