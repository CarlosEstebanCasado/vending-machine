import { postJson } from '@/core/api/httpClient'

export type CoinInventoryOperation = 'deposit' | 'withdraw'

export interface UpdateCoinInventoryPayload {
  machineId?: string
  operation: CoinInventoryOperation
  denominations: Record<number, number>
}

export function updateCoinInventory(payload: UpdateCoinInventoryPayload): Promise<void> {
  return postJson<void, UpdateCoinInventoryPayload>('/admin/coins', payload)
}
