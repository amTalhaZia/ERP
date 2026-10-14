import { createSlice, type PayloadAction } from '@reduxjs/toolkit';

export type ThemeMode = 'light' | 'dark' | 'system';

interface UiState {
  theme: ThemeMode;
  highContrast: boolean;
  sidebarCollapsed: boolean;
  /** Mobile drawer open state */
  mobileNavOpen: boolean;
  /** Currently selected branch filter (null = all permitted branches) */
  branchId: number | null;
}

const initialState: UiState = {
  theme: 'system',
  highContrast: false,
  sidebarCollapsed: false,
  mobileNavOpen: false,
  branchId: null,
};

const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    setTheme(state, action: PayloadAction<ThemeMode>) {
      state.theme = action.payload;
    },
    setHighContrast(state, action: PayloadAction<boolean>) {
      state.highContrast = action.payload;
    },
    toggleSidebar(state) {
      state.sidebarCollapsed = !state.sidebarCollapsed;
    },
    setSidebarCollapsed(state, action: PayloadAction<boolean>) {
      state.sidebarCollapsed = action.payload;
    },
    setMobileNavOpen(state, action: PayloadAction<boolean>) {
      state.mobileNavOpen = action.payload;
    },
    setBranchId(state, action: PayloadAction<number | null>) {
      state.branchId = action.payload;
    },
    hydrateUi(state, action: PayloadAction<Partial<UiState>>) {
      Object.assign(state, action.payload);
    },
  },
});

export const { setTheme, setHighContrast, toggleSidebar, setSidebarCollapsed, setMobileNavOpen, setBranchId, hydrateUi } =
  uiSlice.actions;
export default uiSlice.reducer;
