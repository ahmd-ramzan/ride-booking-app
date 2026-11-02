import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '../api/client';

const MAX_AUTOMATIC_RETRIES = 2;

/**
 * Reads are retried automatically (with exponential backoff) only for transient
 * failures; a 4xx means the request itself is wrong and retrying cannot help.
 */
export function shouldRetry(failureCount: number, error: unknown): boolean {
  if (error instanceof ApiError) return error.isRetryable && failureCount < MAX_AUTOMATIC_RETRIES;
  return failureCount < MAX_AUTOMATIC_RETRIES;
}

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: shouldRetry,
        staleTime: 5_000,
      },
      mutations: {
        // Mutations are only retried by hooks that send an Idempotency-Key.
        retry: false,
      },
    },
  });
}
