import { postJson } from '@/core/api/httpClient'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

export interface ReturnMachineCoinsResult extends MachineSessionResult {
  returnedCoins: Record<number, number>
}

interface ReturnMachineCoinsPayload {
  session_id: string
}

interface ReturnMachineCoinsResponse extends MachineSessionResponse {
  returned_coins: Record<string, number>
}

const toPayload = (sessionId: string): ReturnMachineCoinsPayload => ({
  session_id: sessionId,
})

const toNumberRecord = (input: Record<string, number>): Record<number, number> =>
  Object.fromEntries(
    Object.entries(input).map(([key, value]) => [Number(key), value])
  )

export async function returnMachineCoins(sessionId: string): Promise<ReturnMachineCoinsResult> {
  const response = await postJson<ReturnMachineCoinsResponse>(
    '/machine/session/coins/return',
    toPayload(sessionId)
  )

  const sessionResult = mapSessionResponse(response)

  return {
    ...sessionResult,
    returnedCoins: toNumberRecord(response.returned_coins),
  }
}
