import { defineStore } from 'pinia'
import { adjustSlotInventory, type AdjustSlotInventoryOperation } from '@/modules/admin/api/adjustSlotInventory'
import { getSlotInventory, type AdminSlotInventoryItem } from '@/modules/admin/api/getSlotInventory'
import { getAdminProducts, type AdminProductOption } from '@/modules/admin/api/getProducts'

interface AdminInventoryFilters {
  status: 'all' | 'available' | 'reserved' | 'disabled'
  needsRestockOnly: boolean
  search: string
}

interface AdminInventoryState {
  machineId: string | null
  slots: AdminSlotInventoryItem[]
  products: AdminProductOption[]
  loading: boolean
  error: string | null
  selectedSlotCode: string | null
  filters: AdminInventoryFilters
  submitting: boolean
  submitError: string | null
  submitSuccess: string | null
}

export const useAdminInventoryStore = defineStore('adminInventory', {
  state: (): AdminInventoryState => ({
    machineId: null,
    slots: [],
    products: [],
    loading: false,
    error: null,
    selectedSlotCode: null,
    filters: {
      status: 'all',
      needsRestockOnly: false,
      search: '',
    },
    submitting: false,
    submitError: null,
    submitSuccess: null,
  }),
  getters: {
    filteredSlots(state): AdminSlotInventoryItem[] {
      const normalizedSearch = state.filters.search.trim().toLowerCase()

      return state.slots.filter((slot) => {
        if (state.filters.status !== 'all' && slot.status !== state.filters.status) {
          return false
        }

        if (state.filters.needsRestockOnly && !slot.needsRestock) {
          return false
        }

        if (normalizedSearch.length > 0) {
          const matchesCode = slot.code.toLowerCase().includes(normalizedSearch)
          const matchesProduct = slot.productName?.toLowerCase().includes(normalizedSearch) ?? false

          if (!matchesCode && !matchesProduct) {
            return false
          }
        }

        return true
      })
    },
    selectedSlot(state): AdminSlotInventoryItem | null {
      if (!state.selectedSlotCode) {
        return null
      }

      return state.slots.find((slot) => slot.code === state.selectedSlotCode) ?? null
    },
    productOptions(state): AdminProductOption[] {
      return state.products
    },
    lowStockCount(state): number {
      return state.slots.filter((slot) => slot.needsRestock).length
    },
    disabledCount(state): number {
      return state.slots.filter((slot) => slot.status === 'disabled').length
    },
  },
  actions: {
    async fetchSlots(): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await getSlotInventory()
        this.machineId = response.machineId
        this.slots = response.slots

        await this.fetchProducts()

        if (!this.selectedSlotCode && this.slots.length > 0) {
          this.selectedSlotCode = this.slots[0].code
        }
      } catch (error) {
        console.error('Failed to load slot inventory', error)
        this.error = error instanceof Error ? error.message : 'Unable to load slot inventory.'
      } finally {
        this.loading = false
      }
    },
    async fetchProducts(): Promise<void> {
      try {
        const response = await getAdminProducts()
        this.products = response.products
      } catch (error) {
        console.error('Failed to load product catalog', error)
        this.products = []
      }
    },
    selectSlot(code: string): void {
      this.selectedSlotCode = code
      this.submitError = null
      this.submitSuccess = null
    },
    setStatusFilter(status: AdminInventoryFilters['status']): void {
      this.filters.status = status
    },
    setNeedsRestockOnly(value: boolean): void {
      this.filters.needsRestockOnly = value
    },
    setSearch(value: string): void {
      this.filters.search = value
    },
    resetFeedback(): void {
      this.submitError = null
      this.submitSuccess = null
    },
    async adjustSlot(
      operation: AdjustSlotInventoryOperation,
      slotCode: string,
      quantity: number,
      productId?: string | null,
    ): Promise<void> {
      if (quantity <= 0) {
        this.submitError = 'Quantity must be greater than zero.'
        return
      }

      this.submitting = true
      this.submitError = null
      this.submitSuccess = null

      try {
        await adjustSlotInventory({
          slotCode,
          operation,
          quantity,
          productId,
          machineId: this.machineId ?? undefined,
        })

        await this.fetchSlots()
        this.selectedSlotCode = slotCode
        this.submitSuccess = operation === 'restock' ? 'Slot restocked.' : 'Slot updated.'
      } catch (error) {
        console.error('Failed to adjust slot inventory', error)
        this.submitError = error instanceof Error ? error.message : 'Unable to adjust slot inventory.'
      } finally {
        this.submitting = false
      }
    },
  },
})
