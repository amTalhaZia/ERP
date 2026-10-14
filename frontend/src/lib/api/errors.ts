import { AxiosError } from 'axios';

export interface ApiError {
  status: number;
  /** Machine code from the API, e.g. `validation.failed`, `tenant.forbidden`. */
  code: string;
  message: string;
  /** Field → messages, present on 422. */
  errors?: Record<string, string[]>;
  /** Extra structured context (e.g. 409 insufficient-stock details). */
  details?: object;
}

interface ErrorBody {
  details?: object;
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}

export function normalizeApiError(error: unknown): ApiError {
  if (error instanceof AxiosError) {
    const status = error.response?.status ?? 0;
    const body = (error.response?.data ?? {}) as ErrorBody;
    if (status === 0) {
      return {
        status: 0,
        code: 'network.unreachable',
        message: 'Cannot reach the server. Check your connection and that the API is running.',
      };
    }
    return {
      status,
      code: body.code ?? defaultCode(status),
      message: body.message ?? defaultMessage(status),
      errors: body.errors,
      details: body.details,
    };
  }
  if (isApiError(error)) return error;
  return { status: 0, code: 'unknown', message: 'Something went wrong.' };
}

export function isApiError(e: unknown): e is ApiError {
  return typeof e === 'object' && e !== null && 'status' in e && 'code' in e && 'message' in e;
}

function defaultCode(status: number) {
  switch (status) {
    case 401:
      return 'auth.unauthenticated';
    case 403:
      return 'auth.forbidden';
    case 404:
      return 'not_found';
    case 409:
      return 'conflict';
    case 419:
      return 'auth.csrf';
    case 422:
      return 'validation.failed';
    case 429:
      return 'throttled';
    default:
      return status >= 500 ? 'server.error' : 'error';
  }
}

function defaultMessage(status: number) {
  switch (status) {
    case 401:
      return 'Your session has expired. Please sign in again.';
    case 403:
      return "You don't have permission to do that.";
    case 404:
      return 'The requested record was not found.';
    case 409:
      return 'This action conflicts with the current state of the record.';
    case 422:
      return 'Please correct the highlighted fields.';
    case 429:
      return 'Too many attempts. Please wait a moment and try again.';
    default:
      return status >= 500 ? 'The server encountered an error. Please try again.' : 'Request failed.';
  }
}
