import { getJson } from '@/core/api/httpClient'

export interface AdminSlotInventoryItem {
  code: string
  status: 'available' | 'reserved' | 'disabled'
  capacity: number
  quantity: number
  restockThreshold: number
  needsRestock: boolean
  productId: string | null
  productName: string | null
  priceCents: number | null
  recommendedSlotQuantity: number | null
}

export interface AdminSlotInventoryResponse {
  machineId: string
  slots: AdminSlotInventoryItem[]
}

export function getSlotInventory(): Promise<AdminSlotInventoryResponse> {
  return getJson<AdminSlotInventoryResponse>('/admin/slots')
}
