<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenController extends Controller
{
    private const int MAX_FAILED_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Exchange credentials for a bearer token.
     *
     * Failed attempts are limited per email + IP (brute-force protection);
     * a successful login resets the counter.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $email = $request->string('email')->lower()->toString();
        $throttleKey = 'login:'.Str::transliterate($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS_PER_MINUTE)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => (string) RateLimiter::availableIn($throttleKey)]);
        }

        $user = User::query()->with('driver.user')->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($throttleKey);
            Log::notice('auth.login_failed', ['email_hash' => hash('sha256', $email)]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $expirationMinutes = config('sanctum.expiration');

        $token = $user->createToken(
            $request->string('device_name', 'web')->toString(),
            expiresAt: is_numeric($expirationMinutes) ? now()->addMinutes((int) $expirationMinutes) : null,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Revoke the token used for this request.
     */
    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
