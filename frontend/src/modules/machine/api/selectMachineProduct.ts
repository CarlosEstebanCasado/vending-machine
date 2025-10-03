import { postJson } from '@/core/api/httpClient'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

type SelectMachineProductRequest = {
  sessionId: string
  productId: string
}

type SelectMachineProductPayload = {
  session_id: string
  product_id: string
}

const toPayload = ({ sessionId, productId }: SelectMachineProductRequest): SelectMachineProductPayload => ({
  session_id: sessionId,
  product_id: productId,
})

export async function selectMachineProduct({
  sessionId,
  productId,
}: SelectMachineProductRequest): Promise<MachineSessionResult> {
  const response = await postJson<MachineSessionResponse>(
    '/machine/session/product',
    toPayload({ sessionId, productId})
  )

  return mapSessionResponse(response)
}
