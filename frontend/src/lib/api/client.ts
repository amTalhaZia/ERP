import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import { normalizeApiError, type ApiError } from './errors';

/**
 * Single Axios instance for all `/api/v1` calls.
 *
 * Auth is Laravel Sanctum SPA cookie auth: the browser holds an HttpOnly
 * session cookie, so nothing secret is ever stored in JS. CSRF is handled by
 * fetching `/sanctum/csrf-cookie` once; Axios then mirrors the XSRF-TOKEN
 * cookie into the X-XSRF-TOKEN header automatically.
 */
export const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

let csrfReady: Promise<void> | null = null;

export function ensureCsrfCookie(): Promise<void> {
  if (!csrfReady) {
    csrfReady = axios
      .get(`${API_BASE}/sanctum/csrf-cookie`, { withCredentials: true })
      .then(() => undefined)
      .catch((e) => {
        csrfReady = null;
        throw e;
      });
  }
  return csrfReady;
}

/** Company context header; set by the tenant slice, read on every request. */
let currentCompanyId: number | null = null;
export function setApiCompanyId(id: number | null) {
  currentCompanyId = id;
}

type UnauthorizedHandler = () => void;
let onUnauthorized: UnauthorizedHandler | null = null;
export function setUnauthorizedHandler(fn: UnauthorizedHandler | null) {
  onUnauthorized = fn;
}

export const api: AxiosInstance = axios.create({
  baseURL: `${API_BASE}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 30_000,
});

api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = (config.method ?? 'get').toLowerCase();
  if (method !== 'get' && method !== 'head') {
    await ensureCsrfCookie();
  }
  if (currentCompanyId) {
    config.headers.set('X-Company-Id', String(currentCompanyId));
  }
  return config;
});

api.interceptors.response.use(
  (res) => res,
  async (error: AxiosError) => {
    // Blob downloads (PDF/CSV/files) receive JSON errors as a Blob — decode so the
    // server's message and code reach the UI.
    const data = error.response?.data;
    if (typeof Blob !== 'undefined' && data instanceof Blob && data.type.includes('json') && error.response) {
      try {
        error.response.data = JSON.parse(await data.text());
      } catch {
        /* keep the generic message */
      }
    }
    const normalized: ApiError = normalizeApiError(error);
    if (normalized.status === 401 && onUnauthorized) {
      onUnauthorized();
    }
    // 419 = CSRF token mismatch (expired session) — reset so the next call refetches.
    if (normalized.status === 419) {
      csrfReady = null;
    }
    return Promise.reject(normalized);
  },
);

/** Unwrap `{ data, meta }` envelopes. */
export async function get<T>(url: string, params?: Record<string, unknown>) {
  const { data } = await api.get<T>(url, { params });
  return data;
}
export async function post<T>(url: string, body?: unknown) {
  const { data } = await api.post<T>(url, body);
  return data;
}
export async function put<T>(url: string, body?: unknown) {
  const { data } = await api.put<T>(url, body);
  return data;
}
export async function patch<T>(url: string, body?: unknown) {
  const { data } = await api.patch<T>(url, body);
  return data;
}
export async function del<T = void>(url: string) {
  const { data } = await api.delete<T>(url);
  return data;
}
