const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, '');

function buildUrl(path: string): string {
  if (!path.startsWith('/')) {
    return `${API_BASE_URL}/${path}`;
  }

  return `${API_BASE_URL}${path}`;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers ?? {})
  if (!headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const storedAuth = getStoredAdminAuth()
  if (storedAuth) {
    headers.set('Authorization', `Bearer ${storedAuth.token}`)
  }

  const response = await fetch(buildUrl(path), {
    ...init,
    headers,
  });

  if (!response.ok) {
    const errorBody = await response.text();
    throw new Error(errorBody || response.statusText || 'Request failed');
  }

  return (await response.json()) as T;
}

export function getJson<T>(path: string, init?: RequestInit): Promise<T> {
  return request<T>(path, {
    method: 'GET',
    ...init,
  });
}

export function postJson<T, B = unknown>(path: string, body?: B, init?: RequestInit): Promise<T> {
  return request<T>(path, {
    method: 'POST',
    body: body !== undefined ? JSON.stringify(body) : undefined,
    ...init,
  });
}

function getStoredAdminAuth(): { token: string; tokenExpiresAt: string } | null {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const raw = window.localStorage.getItem('vm-admin-auth');
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as { token: string; tokenExpiresAt: string } | null;
    if (!parsed) {
      return null;
    }

    if (new Date(parsed.tokenExpiresAt).getTime() <= Date.now()) {
      window.localStorage.removeItem('vm-admin-auth');
      return null;
    }

    return parsed;
  } catch (error) {
    console.warn('Failed to read admin auth token', error);
    return null;
  }
}
