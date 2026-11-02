import { useMutation, type UseMutationOptions } from '@tanstack/react-query';
import { useRef } from 'react';
import { ApiError, newIdempotencyKey } from '../api/client';
import { shouldRetry } from '../lib/queryClient';

/**
 * A mutation that is safe to retry. The same Idempotency-Key is reused for every
 * attempt of the same logical operation (automatic retries after a network
 * failure, or the user pressing "Try again"), so the server performs it at most
 * once. A new key is generated when the variables change, after success, or
 * after a definitive (4xx) rejection, so a deliberate new attempt is not
 * answered with a replay of the old response.
 */
export function useIdempotentMutation<TData, TVariables>(
  mutate: (variables: TVariables, idempotencyKey: string) => Promise<TData>,
  options: Omit<UseMutationOptions<TData, ApiError, TVariables>, 'mutationFn' | 'retry'> = {},
) {
  const attempt = useRef<{ fingerprint: string; key: string } | null>(null);

  return useMutation<TData, ApiError, TVariables>({
    ...options,
    retry: shouldRetry,
    mutationFn: (variables) => {
      const fingerprint = JSON.stringify(variables);
      if (attempt.current?.fingerprint !== fingerprint) {
        attempt.current = { fingerprint, key: newIdempotencyKey() };
      }
      return mutate(variables, attempt.current.key);
    },
    onSuccess: (...args) => {
      attempt.current = null;
      return options.onSuccess?.(...args);
    },
    onError: (error, ...rest) => {
      if (!error.isRetryable) attempt.current = null;
      return options.onError?.(error, ...rest);
    },
  });
}
