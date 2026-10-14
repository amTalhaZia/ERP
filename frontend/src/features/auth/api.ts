import { api, ensureCsrfCookie, get, post } from '@/lib/api/client';
import type { LoginRequest, LoginResponse, SessionPayload } from './types';

export const authApi = {
  async login(body: LoginRequest) {
    await ensureCsrfCookie();
    return post<LoginResponse>('/auth/login', body);
  },
  twoFactorChallenge(code: string, recovery = false) {
    return post<{ session: SessionPayload }>('/auth/2fa/challenge', recovery ? { recovery_code: code } : { code });
  },
  logout() {
    return post<void>('/auth/logout');
  },
  session() {
    return get<{ data: SessionPayload }>('/auth/session');
  },
  switchCompany(companyId: number) {
    return post<{ data: SessionPayload }>('/auth/switch-company', { company_id: companyId });
  },
  forgotPassword(email: string) {
    return post<{ message: string }>('/auth/forgot-password', { email });
  },
  resetPassword(body: { token: string; email: string; password: string; password_confirmation: string }) {
    return post<{ message: string }>('/auth/reset-password', body);
  },
  resendVerification() {
    return post<{ message: string }>('/auth/email/verification-notification');
  },
  changePassword(body: { current_password: string; password: string; password_confirmation: string }) {
    return post<{ message: string }>('/auth/password', body);
  },
  /** Re-authenticate before a sensitive action; returns a short-lived confirmation window. */
  confirmPassword(password: string) {
    return post<{ confirmed_until: string }>('/auth/confirm-password', { password });
  },
  twoFactor: {
    enable: () => post<{ qr_svg: string; secret: string }>('/auth/2fa/enable'),
    confirm: (code: string) => post<{ recovery_codes: string[] }>('/auth/2fa/confirm', { code }),
    disable: (password: string) => post<void>('/auth/2fa/disable', { password }),
    recoveryCodes: () => get<{ recovery_codes: string[] }>('/auth/2fa/recovery-codes'),
  },
  updatePreferences(prefs: Partial<SessionPayload['user']['preferences']>) {
    return api.patch<{ data: SessionPayload['user'] }>('/auth/preferences', prefs).then((r) => r.data);
  },
};
