<template>
  <main class="admin-login">
    <section class="admin-login__panel">
      <h1 class="admin-login__title">Vending Machine Admin</h1>

      <p class="admin-login__subtitle">Sign in to manage inventory, pricing, and maintenance tasks.</p>

      <form class="admin-login__form" @submit.prevent="handleSubmit">
        <label class="admin-login__label" for="email">Email</label>
        <input
          id="email"
          v-model="email"
          class="admin-login__input"
          type="email"
          autocomplete="username"
          required
          :disabled="loading"
        />

        <label class="admin-login__label" for="password">Password</label>
        <input
          id="password"
          v-model="password"
          class="admin-login__input"
          type="password"
          autocomplete="current-password"
          required
          :disabled="loading"
        />

        <p v-if="error" class="admin-login__error">{{ errorMessage }}</p>

        <button class="admin-login__submit" type="submit" :disabled="loading">
          <span v-if="loading">Signing in…</span>
          <span v-else>Sign in</span>
        </button>
        <button class="admin-login__cancel" type="button" :disabled="loading" @click="handleCancel">
          Cancel
        </button>
      </form>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAdminAuthStore } from '@/modules/admin/store/useAdminAuthStore'

const authStore = useAdminAuthStore()
const email = ref('')
const password = ref('')
const router = useRouter()
const route = useRoute()

const loading = computed(() => authStore.loading)
const error = computed(() => authStore.error)

const errorMessage = computed(() => {
  if (!error.value) {
    return ''
  }

  try {
    const parsed = JSON.parse(error.value)
    if (parsed && typeof parsed === 'object' && 'error' in parsed) {
      return (parsed as { error?: { message?: string } }).error?.message ?? 'Unable to login.'
    }
  } catch {
    // if parsing fails we'll show the raw error message below
  }

  return error.value
})

onMounted(() => {
  authStore.initializeFromStorage()

  if (authStore.isAuthenticated) {
    void router.replace({ name: 'admin.dashboard' })
  }
})

watch([email, password], () => {
  if (authStore.error) {
    authStore.clearError()
  }
})

async function handleSubmit(): Promise<void> {
  try {
    await authStore.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    if (redirect) {
      void router.replace(redirect)
    } else {
      void router.replace({ name: 'admin.dashboard' })
    }
  } catch (error) {
    console.error('Failed to login as admin', error)
  }
}

function handleCancel(): void {
  void router.replace({ name: 'machine.dashboard' })
}
</script>

<style scoped>
.admin-login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  padding: 2rem;
  box-sizing: border-box;
}

.admin-login__panel {
  width: min(420px, 100%);
  background: rgba(15, 23, 42, 0.9);
  border-radius: 24px;
  padding: 2.5rem;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.45);
  color: #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.admin-login__title {
  margin: 0;
  font-size: 2rem;
  font-weight: 700;
}

.admin-login__subtitle {
  margin: 0;
  color: #cbd5f5;
  line-height: 1.6;
}

.admin-login__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.admin-login__label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #cbd5f5;
}

.admin-login__input {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.4);
  background: rgba(15, 23, 42, 0.6);
  color: #f8fafc;
  font-size: 1rem;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.admin-login__input:focus {
  outline: none;
  border-color: #60a5fa;
  box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.35);
}

.admin-login__error {
  margin: 0;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  background: rgba(248, 113, 113, 0.2);
  border: 1px solid rgba(248, 113, 113, 0.35);
  color: #fecaca;
  font-weight: 600;
}

.admin-login__submit {
  margin-top: 0.5rem;
  padding: 0.9rem 1rem;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
  color: #0f172a;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.2s ease;
}

.admin-login__submit:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.admin-login__submit:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 18px 28px rgba(37, 99, 235, 0.35);
}

.admin-login__cancel {
  margin-top: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: transparent;
  color: #cbd5f5;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.admin-login__cancel:hover {
  border-color: rgba(96, 165, 250, 0.6);
  color: #e0e7ff;
}

@media (max-width: 480px) {
  .admin-login__panel {
    padding: 2rem 1.5rem;
  }
}
</style>
