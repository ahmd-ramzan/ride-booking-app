import { QueryClient } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import type { User } from '../api/types';
import { AppProviders, AppRoutes } from '../App';
import { shouldRetry } from '../lib/queryClient';

/**
 * Renders the whole app (real providers, routing, API client) at a route,
 * optionally signed in. Network calls are answered by MSW. The production
 * retry policy applies, without backoff delays.
 */
export function renderApp({ route = '/', user }: { route?: string; user?: User } = {}) {
  if (user) {
    window.localStorage.setItem('ride-booking.session', JSON.stringify({ token: 'test-token', user }));
  }

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: shouldRetry, retryDelay: 0, staleTime: 0 },
      mutations: { retry: false, retryDelay: 0 },
    },
  });

  return {
    user: userEvent.setup(),
    queryClient,
    ...render(
      <AppProviders queryClient={queryClient}>
        <MemoryRouter initialEntries={[route]}>
          <AppRoutes />
        </MemoryRouter>
      </AppProviders>,
    ),
  };
}
