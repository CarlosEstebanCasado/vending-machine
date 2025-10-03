import { postJson } from '@/core/api/httpClient'
import type { MachineSession } from '@/modules/machine/api/getMachineState'

export interface StartMachineSessionResult {
  machineId: string
  session: MachineSession
}

type StartMachineSessionResponse = {
  machine_id: string
  session: {
    id: string
    state: string
    balance_cents: number
    inserted_coins: Record<string, number>
    selected_product_id: string | null
  }
}

const toNumberRecord = (input: Record<string, number>): Record<number, number> =>
  Object.fromEntries(
    Object.entries(input).map(([key, value]) => [Number(key), value])
  )

function mapResponse(response: StartMachineSessionResponse): StartMachineSessionResult {
  const session: MachineSession = {
    id: response.session.id,
    state: response.session.state,
    balanceCents: response.session.balance_cents,
    insertedCoins: toNumberRecord(response.session.inserted_coins),
    selectedProductId: response.session.selected_product_id,
    changePlan: null,
  }

  return {
    machineId: response.machine_id,
    session,
  }
}

export async function startMachineSession(): Promise<StartMachineSessionResult> {
  const response = await postJson<StartMachineSessionResponse>('/machine/session')
  return mapResponse(response)
}
