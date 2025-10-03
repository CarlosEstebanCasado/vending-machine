import { postJson } from '@/core/api/httpClient'
import type { MachineSession } from '@/modules/machine/api/getMachineState'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

export interface VendMachineProductResult {
  machineId: string
  session: MachineSession
  sale: {
    status: 'completed' | 'cancelled_insufficient_change'
    productId: string | null
    slotCode: string | null
    priceCents: number
    changeDispensed: Record<number, number>
    returnedCoins: Record<number, number>
  }
}

type VendMachineProductResponse = {
  machine_id: string
  session: MachineSessionResponse['session']
  sale: {
    status: string
    product_id: string | null
    slot_code: string | null
    price_cents: number
    change_dispensed: Record<string, number>
    returned_coins: Record<string, number>
  }
}

const toNumberRecord = (input: Record<string, number>): Record<number, number> =>
  Object.fromEntries(
    Object.entries(input).map(([key, value]) => [Number(key), value])
  )

export async function vendMachineProduct(sessionId: string): Promise<VendMachineProductResult> {
  const response = await postJson<VendMachineProductResponse>('/machine/session/purchase', {
    session_id: sessionId,
  })

  const sessionResult: MachineSessionResult = mapSessionResponse({
    machine_id: response.machine_id,
    session: response.session,
  })

  return {
    machineId: sessionResult.machineId,
    session: sessionResult.session,
    sale: {
      status: response.sale.status as VendMachineProductResult['sale']['status'],
      productId: response.sale.product_id,
      slotCode: response.sale.slot_code,
      priceCents: response.sale.price_cents,
      changeDispensed: toNumberRecord(response.sale.change_dispensed),
      returnedCoins: toNumberRecord(response.sale.returned_coins),
    },
  }
}
