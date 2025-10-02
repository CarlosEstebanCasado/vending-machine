const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, '');

function buildUrl(path: string): string {
  if (!path.startsWith('/')) {
    return `${API_BASE_URL}/${path}`;
  }

  return `${API_BASE_URL}${path}`;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(buildUrl(path), {
    headers: {
      'Content-Type': 'application/json',
      ...init?.headers,
    },
    ...init,
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
