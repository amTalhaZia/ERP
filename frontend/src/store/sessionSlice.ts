import { createSlice, type PayloadAction } from '@reduxjs/toolkit';
import type { SessionPayload } from '@/features/auth/types';

export type SessionStatus = 'unknown' | 'loading' | 'authenticated' | 'guest';

interface SessionState extends Partial<SessionPayload> {
  status: SessionStatus;
}

const initialState: SessionState = {
  status: 'unknown',
  user: undefined,
  companies: [],
  current_company: null,
  permissions: [],
  branches: [],
};

const sessionSlice = createSlice({
  name: 'session',
  initialState,
  reducers: {
    sessionLoading(state) {
      state.status = 'loading';
    },
    sessionResolved(state, action: PayloadAction<SessionPayload>) {
      Object.assign(state, action.payload);
      state.status = 'authenticated';
    },
    sessionCleared() {
      return { ...initialState, status: 'guest' };
    },
    userUpdated(state, action: PayloadAction<SessionPayload['user']>) {
      state.user = action.payload;
    },
  },
});

export const { sessionLoading, sessionResolved, sessionCleared, userUpdated } = sessionSlice.actions;
export default sessionSlice.reducer;

// Selectors ------------------------------------------------------------------
import type { RootState } from './index';

export const selectSession = (s: RootState) => s.session;
export const selectUser = (s: RootState) => s.session.user;
export const selectCompany = (s: RootState) => s.session.current_company ?? null;
export const selectCompanies = (s: RootState) => s.session.companies ?? [];
export const selectBranches = (s: RootState) => s.session.branches ?? [];
export const selectPermissions = (s: RootState) => s.session.permissions ?? [];
export const selectIsPlatformAdmin = (s: RootState) => s.session.user?.is_platform_admin ?? false;
