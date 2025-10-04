import { postJson } from '@/core/api/httpClient'

export interface LoginAdminResponse {
  id: string
  email: string
  roles: string[]
  token: string
  expiresAt: string
}

export interface LoginAdminPayload {
  email: string
  password: string
}

export function loginAdmin(payload: LoginAdminPayload): Promise<LoginAdminResponse> {
  return postJson<LoginAdminResponse, LoginAdminPayload>('/admin/login', payload)
}
