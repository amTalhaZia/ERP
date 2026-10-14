'use client';

import { useState, type ReactNode } from 'react';
import { Provider as ReduxProvider } from 'react-redux';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MotionConfig } from 'framer-motion';
import { makeStore, type AppStore } from '@/store';
import { ThemeProvider } from '@/components/theme/ThemeProvider';
import { SessionProvider } from '@/lib/auth/SessionProvider';
import { ToastProvider } from '@/components/ui/Toast';
import { isApiError } from '@/lib/api/errors';

function makeQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          // Never retry auth/permission/validation failures.
          if (isApiError(error) && [401, 403, 404, 422, 419].includes(error.status)) return false;
          return failureCount < 2;
        },
      },
      mutations: { retry: false },
    },
  });
}

export function Providers({ children }: { children: ReactNode }) {
  const [store] = useState<AppStore>(makeStore);
  const [queryClient] = useState<QueryClient>(makeQueryClient);

  return (
    <ReduxProvider store={store}>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <MotionConfig reducedMotion="user">
            <ToastProvider>
              <SessionProvider>{children}</SessionProvider>
            </ToastProvider>
          </MotionConfig>
        </ThemeProvider>
      </QueryClientProvider>
    </ReduxProvider>
  );
}
