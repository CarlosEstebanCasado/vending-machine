import { getJson } from '@/core/api/httpClient'

export interface CoinInventoryBalance {
  denomination: number
  available: number
  reserved: number
}

export interface CoinInventoryResponse {
  machineId: string
  balances: CoinInventoryBalance[]
  insufficientChange: boolean
  updatedAt: string
}

export function getCoinInventory(machineId?: string): Promise<CoinInventoryResponse> {
  const url = machineId ? `/admin/coins?machineId=${encodeURIComponent(machineId)}` : '/admin/coins'
  return getJson<CoinInventoryResponse>(url)
}
