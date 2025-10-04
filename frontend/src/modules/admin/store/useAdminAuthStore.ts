import { defineStore } from 'pinia'
import { loginAdmin, type LoginAdminResponse } from '@/modules/admin/api/loginAdmin'

interface AdminAuthState {
  user: Omit<LoginAdminResponse, 'token' | 'expiresAt'> | null
  token: string | null
  tokenExpiresAt: string | null
  initialized: boolean
  loading: boolean
  error: string | null
}

export const useAdminAuthStore = defineStore('adminAuth', {
  state: (): AdminAuthState => ({
    user: null,
    token: null,
    tokenExpiresAt: null,
    initialized: false,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated(state): boolean {
      if (!state.token || !state.tokenExpiresAt) {
        return false;
      }

      return new Date(state.tokenExpiresAt).getTime() > Date.now();
    },
  },
  actions: {
    initializeFromStorage(): void {
      if (this.initialized) {
        return;
      }

      this.initialized = true;

      try {
        const raw = typeof window !== 'undefined' ? window.localStorage.getItem('vm-admin-auth') : null;
        if (!raw) {
          return;
        }

        const parsed = JSON.parse(raw) as {
          token: string
          tokenExpiresAt: string
          user: Omit<LoginAdminResponse, 'token' | 'expiresAt'>
        } | null;

        if (!parsed) {
          return;
        }

        if (new Date(parsed.tokenExpiresAt).getTime() <= Date.now()) {
          this.clearPersistedAuth();
          return;
        }

        this.user = parsed.user;
        this.token = parsed.token;
        this.tokenExpiresAt = parsed.tokenExpiresAt;
      } catch (error) {
        console.warn('Failed to restore admin auth state', error);
        this.clearPersistedAuth();
      }
    },
    async login(email: string, password: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await loginAdmin({ email, password })
        const { token, expiresAt, ...user } = response

        this.user = user
        this.token = token
        this.tokenExpiresAt = expiresAt
        this.persistAuthState()
      } catch (error) {
        if (error instanceof Error) {
          this.error = error.message
        } else {
          this.error = 'Unable to login. Please try again.'
        }

        throw error
      } finally {
        this.loading = false
      }
    },
    logout(): void {
      this.user = null
      this.token = null
       this.tokenExpiresAt = null
      this.error = null
      this.clearPersistedAuth()
    },
    clearError(): void {
      this.error = null
    },
    persistAuthState(): void {
      if (typeof window === 'undefined' || !this.token || !this.user || !this.tokenExpiresAt) {
        return
      }

      const payload = {
        token: this.token,
        tokenExpiresAt: this.tokenExpiresAt,
        user: this.user,
      }

      window.localStorage.setItem('vm-admin-auth', JSON.stringify(payload))
    },
    clearPersistedAuth(): void {
      if (typeof window !== 'undefined') {
        window.localStorage.removeItem('vm-admin-auth')
      }
    },
  },
})
