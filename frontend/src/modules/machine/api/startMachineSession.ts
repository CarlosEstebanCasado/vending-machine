import { postJson } from '@/core/api/httpClient'
import type { MachineSession } from '@/modules/machine/api/getMachineState'

export interface MachineSessionResult {
  machineId: string
  session: MachineSession
}

export type StartMachineSessionResult = MachineSessionResult

export type MachineSessionResponse = {
  machine_id: string
  session: {
    id: string
    state: string
    balance_cents: number
    inserted_coins: Record<string, number>
    selected_product_id: string | null
    change_plan?: Record<string, number> | null
    selected_slot_code?: string | null
  }
}

const toNumberRecord = (input: Record<string, number>): Record<number, number> =>
  Object.fromEntries(
    Object.entries(input).map(([key, value]) => [Number(key), value])
  )

export function mapSessionResponse(response: MachineSessionResponse): MachineSessionResult {
  const sessionPayload = response.session
  const session: MachineSession = {
    id: sessionPayload.id,
    state: sessionPayload.state,
    balanceCents: sessionPayload.balance_cents,
    insertedCoins: toNumberRecord(sessionPayload.inserted_coins),
    selectedProductId: sessionPayload.selected_product_id,
    selectedSlotCode:
      sessionPayload.selected_slot_code === undefined
        ? null
        : sessionPayload.selected_slot_code,
    changePlan:
      sessionPayload.change_plan === undefined || sessionPayload.change_plan === null
        ? null
        : toNumberRecord(sessionPayload.change_plan),
  }

  return {
    machineId: response.machine_id,
    session,
  }
}

export async function startMachineSession(): Promise<MachineSessionResult> {
  const response = await postJson<MachineSessionResponse>('/machine/session')
  return mapSessionResponse(response)
}
