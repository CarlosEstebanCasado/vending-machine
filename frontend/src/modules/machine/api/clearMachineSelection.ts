import { postJson } from '@/core/api/httpClient'
import {
  mapSessionResponse,
  type MachineSessionResponse,
  type MachineSessionResult,
} from '@/modules/machine/api/startMachineSession'

interface ClearMachineSelectionRequest {
  sessionId: string
}

interface ClearMachineSelectionPayload {
  session_id: string
}

const toPayload = ({ sessionId }: ClearMachineSelectionRequest): ClearMachineSelectionPayload => ({
  session_id: sessionId,
})

export async function clearMachineSelection({ sessionId }: ClearMachineSelectionRequest): Promise<MachineSessionResult> {
  const response = await postJson<MachineSessionResponse>(
    '/machine/session/product/clear',
    toPayload({ sessionId })
  )

  return mapSessionResponse(response)
}
