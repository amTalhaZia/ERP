import { configureStore } from '@reduxjs/toolkit';
import { useDispatch, useSelector, type TypedUseSelectorHook } from 'react-redux';
import sessionReducer from './sessionSlice';
import uiReducer from './uiSlice';

/**
 * Redux Toolkit is used only for genuinely shared application state:
 * the authenticated session (user, companies, permissions) and UI chrome
 * (theme, sidebar, drawers). Server data lives in TanStack Query.
 */
export const makeStore = () =>
  configureStore({
    reducer: {
      session: sessionReducer,
      ui: uiReducer,
    },
    devTools: process.env.NODE_ENV !== 'production',
  });

export type AppStore = ReturnType<typeof makeStore>;
export type RootState = ReturnType<AppStore['getState']>;
export type AppDispatch = AppStore['dispatch'];

export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
