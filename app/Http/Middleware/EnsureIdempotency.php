<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes unsafe requests retry-safe via the `Idempotency-Key` header
 * (draft-ietf-httpapi-idempotency-key-header).
 *
 *  - First request with a key: the key is claimed atomically
 *    (INSERT ... ON CONFLICT DO NOTHING), the request runs, and its response
 *    is stored.
 *  - Retry with the same key and same payload: the stored response is
 *    replayed with `Idempotent-Replayed: true`; the action does not run again.
 *  - Same key, different payload: 422 idempotency_key_reused.
 *  - Same key while the first request is still running: 409 with Retry-After.
 *  - 5xx responses are not stored, so the client may retry them.
 *
 * Keys are scoped per user. Usage: `->middleware('idempotent:required')`.
 */
class EnsureIdempotency
{
    public const string HEADER = 'Idempotency-Key';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $key = $request->headers->get(self::HEADER);

        if ($key === null || $key === '') {
            if ($mode === 'required') {
                throw IdempotencyException::missing();
            }

            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9_\-:.]{8,255}$/', $key) !== 1) {
            throw IdempotencyException::malformed();
        }

        /** @var User $user */
        $user = $request->user();
        $fingerprint = $this->fingerprint($request);

        $record = $this->claim($user, $key, $fingerprint);

        if ($record !== null) {
            return $this->replay($record);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->release($user, $key);

            throw $exception;
        }

        $this->store($user, $key, $response);

        return $response;
    }

    /**
     * Claims the key for this request. Returns null when the caller now owns the
     * key, or the completed record whose response must be replayed.
     *
     * @throws IdempotencyException
     */
    private function claim(User $user, string $key, string $fingerprint): ?IdempotencyKey
    {
        $lockTimeout = (int) config('rides.idempotency.lock_timeout_seconds');

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $user->id,
            'key' => $key,
            'request_fingerprint' => $fingerprint,
            'locked_until' => now()->addSeconds($lockTimeout),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            return null;
        }

        /** @var IdempotencyKey $existing */
        $existing = IdempotencyKey::query()->where('user_id', $user->id)->where('key', $key)->firstOrFail();

        if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
            throw IdempotencyException::reusedWithDifferentRequest();
        }

        if ($existing->isCompleted()) {
            return $existing;
        }

        // The original request is still running, unless its lock expired (worker died): reclaim atomically.
        $reclaimed = IdempotencyKey::query()
            ->whereKey($existing->id)
            ->whereNull('response_status')
            ->where('locked_until', '<', now())
            ->update(['locked_until' => now()->addSeconds($lockTimeout), 'updated_at' => now()]);

        if ($reclaimed === 1) {
            return null;
        }

        throw IdempotencyException::inProgress();
    }

    private function store(User $user, string $key, Response $response): void
    {
        if ($response->getStatusCode() >= 500) {
            $this->release($user, $key);

            return;
        }

        IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent() ?: null,
                'locked_until' => null,
                'updated_at' => now(),
            ]);
    }

    private function release(User $user, string $key): void
    {
        IdempotencyKey::query()->where('user_id', $user->id)->where('key', $key)->whereNull('response_status')->delete();
    }

    private function replay(IdempotencyKey $record): JsonResponse
    {
        Log::info('idempotency.replayed', ['idempotency_key_id' => $record->id]);

        return new JsonResponse($record->response_body, (int) $record->response_status, ['Idempotent-Replayed' => 'true']);
    }

    private function fingerprint(Request $request): string
    {
        $payload = $request->all();
        $this->sortRecursively($payload);

        return hash('sha256', implode('|', [$request->method(), $request->path(), json_encode($payload)]));
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function sortRecursively(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
    }
}
