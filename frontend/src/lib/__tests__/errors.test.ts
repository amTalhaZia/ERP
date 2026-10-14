import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios';
import { describe, expect, it } from 'vitest';
import { isApiError, normalizeApiError } from '@/lib/api/errors';

function axiosError(status: number | undefined, data?: unknown) {
  const response = status === undefined
    ? undefined
    : ({ status, data, statusText: '', headers: {}, config: { headers: new AxiosHeaders() } } as AxiosResponse);
  return new AxiosError('Request failed', 'ERR', undefined, undefined, response);
}

describe('normalizeApiError', () => {
  it('maps 422 with field errors', () => {
    const e = normalizeApiError(axiosError(422, { message: 'The email field is required.', code: 'validation.failed', errors: { email: ['The email field is required.'] } }));
    expect(e).toMatchObject({ status: 422, code: 'validation.failed', message: 'The email field is required.', errors: { email: ['The email field is required.'] } });
  });

  it('maps a missing response to a network error', () => {
    const e = normalizeApiError(axiosError(undefined));
    expect(e.status).toBe(0);
    expect(e.code).toBe('network.unreachable');
    expect(e.message).toMatch(/cannot reach the server/i);
  });

  it('keeps 409 details', () => {
    const details = { product_id: 7, available: '2.0000', requested: '5.0000' };
    const e = normalizeApiError(axiosError(409, { message: 'Insufficient stock.', code: 'inventory.insufficient_stock', details }));
    expect(e.status).toBe(409);
    expect(e.code).toBe('inventory.insufficient_stock');
    expect(e.details).toEqual(details);
  });

  it('falls back to default codes and messages per status', () => {
    expect(normalizeApiError(axiosError(403, {}))).toMatchObject({ code: 'auth.forbidden', message: "You don't have permission to do that." });
    expect(normalizeApiError(axiosError(409))).toMatchObject({ code: 'conflict' });
    expect(normalizeApiError(axiosError(422, {}))).toMatchObject({ code: 'validation.failed', message: 'Please correct the highlighted fields.' });
    expect(normalizeApiError(axiosError(503, {}))).toMatchObject({ code: 'server.error' });
    expect(normalizeApiError(axiosError(418, {}))).toMatchObject({ code: 'error', message: 'Request failed.' });
  });

  it('passes through an already-normalized ApiError and wraps unknowns', () => {
    const already = { status: 404, code: 'not_found', message: 'Gone' };
    expect(normalizeApiError(already)).toBe(already);
    expect(isApiError(already)).toBe(true);
    expect(normalizeApiError(new Error('boom'))).toEqual({ status: 0, code: 'unknown', message: 'Something went wrong.' });
  });
});
