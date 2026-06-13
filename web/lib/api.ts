// Single source of truth for talking to the Laravel API.
// In dev `next.config.ts` proxies `/api/v1/*` to the backend, so both server
// and client components can use the same relative URL.

const BASE = '/api/v1';

export type ApiError = { status: number; title: string; detail?: string };

export async function api<T = unknown>(
  path: string,
  opts: RequestInit & { token?: string | null } = {},
): Promise<T> {
  const { token, headers, ...rest } = opts;
  const res = await fetch(`${BASE}${path}`, {
    ...rest,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(headers || {}),
    },
    cache: 'no-store',
  });
  if (!res.ok) {
    let err: ApiError = { status: res.status, title: res.statusText };
    try {
      const body = await res.json();
      err = { ...err, ...body };
    } catch { /* non-JSON response */ }
    throw err;
  }
  if (res.status === 204) return undefined as T;
  return res.json() as Promise<T>;
}

export const tokenStorageKey = 'a1sms.token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem(tokenStorageKey);
}

export function setToken(t: string | null) {
  if (typeof window === 'undefined') return;
  if (t) window.localStorage.setItem(tokenStorageKey, t);
  else   window.localStorage.removeItem(tokenStorageKey);
}
