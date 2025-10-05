import { getJson } from '@/core/api/httpClient'

export interface AdminProductOption {
  id: string
  name: string
  priceCents: number | null
  status: 'active' | 'inactive'
  recommendedSlotQuantity: number | null
}

interface AdminProductCatalogResponse {
  products: AdminProductOption[]
}

export function getAdminProducts(): Promise<AdminProductCatalogResponse> {
  return getJson<AdminProductCatalogResponse>('/admin/products')
}

