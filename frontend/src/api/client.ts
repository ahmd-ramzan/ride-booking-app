import type { ApiErrorBody } from './types';

const BASE_URL = '/api/v1';
const REQUEST_TIMEOUT_MS = 15_000;

/**
 * Every failed request surfaces as an ApiError. `code` mirrors the server's
 * stable error codes, plus two client-side ones: `network_error` (offline,
 * DNS, CORS, timeout) and `invalid_response`.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly requestId: string | null;
  readonly fieldErrors: Record<string, string[]>;
  readonly details: Record<string, unknown>;

  constructor(status: number, body: ApiErrorBody) {
    super(body.message);
    this.name = 'ApiError';
    this.status = status;
    this.code = body.code;
    this.requestId = body.request_id ?? null;
    this.fieldErrors = body.errors ?? {};
    this.details = body.details ?? {};
  }

  /** Worth retrying automatically: the network failed or the server had a transient fault. */
  get isRetryable(): boolean {
    return this.code === 'network_error' || this.status >= 500 || this.status === 429;
  }

  firstFieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0];
  }
}

let authToken: string | null = null;
let onUnauthenticated: (() => void) | null = null;

export function setAuthToken(token: string | null): void {
  authToken = token;
}

export function setUnauthenticatedHandler(handler: (() => void) | null): void {
  onUnauthenticated = handler;
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: unknown;
  query?: Record<string, string | number | undefined>;
  idempotencyKey?: string;
  signal?: AbortSignal;
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, query, idempotencyKey, signal } = options;

  const url = new URL(BASE_URL + path, window.location.origin);
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined) {
      url.searchParams.set(key, String(value));
    }
  }

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (authToken) headers.Authorization = `Bearer ${authToken}`;
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

  const timeout = AbortSignal.timeout(REQUEST_TIMEOUT_MS);

  let response: Response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: signal ? AbortSignal.any([signal, timeout]) : timeout,
    });
  } catch (error) {
    if (signal?.aborted) throw error;

    throw new ApiError(0, {
      code: 'network_error',
      message: timeout.aborted
        ? 'The server took too long to respond. Check your connection and try again.'
        : 'Could not reach the server. Check your connection and try again.',
    });
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const errorBody = isErrorBody(payload)
      ? payload
      : { code: 'invalid_response', message: `Unexpected response from the server (HTTP ${response.status}).` };

    if (response.status === 401) onUnauthenticated?.();

    throw new ApiError(response.status, {
      ...errorBody,
      request_id: errorBody.request_id ?? response.headers.get('X-Request-Id'),
    });
  }

  if (payload === null) {
    throw new ApiError(response.status, { code: 'invalid_response', message: 'The server returned an empty response.' });
  }

  return payload as T;
}

function isErrorBody(value: unknown): value is ApiErrorBody {
  return typeof value === 'object' && value !== null && 'message' in value && 'code' in value;
}

export function newIdempotencyKey(): string {
  return crypto.randomUUID();
}
