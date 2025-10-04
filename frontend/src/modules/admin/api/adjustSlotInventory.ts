import { postJson } from '@/core/api/httpClient'

export type AdjustSlotInventoryOperation = 'restock' | 'withdraw'

export interface AdjustSlotInventoryRequest {
  slotCode: string
  operation: AdjustSlotInventoryOperation
  quantity: number
  productId?: string | null
  machineId?: string
}

export function adjustSlotInventory(payload: AdjustSlotInventoryRequest): Promise<void> {
  return postJson<void>('/admin/slots/stock', payload)
}
