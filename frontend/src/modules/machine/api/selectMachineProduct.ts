import { postJson } from '@/core/api/httpClient'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

type SelectMachineProductRequest = {
  sessionId: string
  productId: string
  slotCode: string
}

type SelectMachineProductPayload = {
  session_id: string
  product_id: string
  slot_code: string
}

const toPayload = ({ sessionId, productId, slotCode }: SelectMachineProductRequest): SelectMachineProductPayload => ({
  session_id: sessionId,
  product_id: productId,
  slot_code: slotCode,
})

export async function selectMachineProduct({
  sessionId,
  productId,
  slotCode,
}: SelectMachineProductRequest): Promise<MachineSessionResult> {
  const response = await postJson<MachineSessionResponse>(
    '/machine/session/product',
    toPayload({ sessionId, productId, slotCode })
  )

  return mapSessionResponse(response)
}
