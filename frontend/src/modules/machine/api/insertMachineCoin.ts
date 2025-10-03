import { postJson } from '@/core/api/httpClient'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

interface InsertMachineCoinRequest {
  sessionId: string
  denominationCents: number
}

interface InsertMachineCoinPayload {
  session_id: string
  denomination_cents: number
}

const toPayload = ({ sessionId, denominationCents }: InsertMachineCoinRequest): InsertMachineCoinPayload => ({
  session_id: sessionId,
  denomination_cents: denominationCents,
})

export async function insertMachineCoin({
  sessionId,
  denominationCents,
}: InsertMachineCoinRequest): Promise<MachineSessionResult> {
  const response = await postJson<MachineSessionResponse>(
    '/machine/session/coin',
    toPayload({ sessionId, denominationCents })
  )

  return mapSessionResponse(response)
}
