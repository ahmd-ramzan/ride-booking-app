<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error with one envelope:
 *
 *   { "message": string, "code": string, "request_id": string, "errors"?: object, "details"?: object }
 *
 * Clients branch on `code` (stable), show `message` (human readable), and quote
 * `request_id` when reporting a problem.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): JsonResponse
    {
        [$status, $code, $message, $extra, $headers] = $this->describe($exception);

        $body = array_merge([
            'message' => $message,
            'code' => $code,
            'request_id' => Context::get('request_id'),
        ], $extra);

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>, 4: array<string, string>}
     */
    private function describe(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ApiException => [
                $exception->status(),
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->details() === [] ? [] : ['details' => $exception->details()],
                $exception->headers(),
            ],
            $exception instanceof ValidationException => [
                422, 'validation_failed', $exception->getMessage(), ['errors' => $exception->errors()], [],
            ],
            $exception instanceof AuthenticationException => [
                401, 'unauthenticated', 'Authentication is required.', [], [],
            ],
            $exception instanceof AuthorizationException, $exception instanceof AccessDeniedHttpException => [
                403, 'forbidden', $exception->getMessage() ?: 'This action is not allowed.', [], [],
            ],
            $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => [
                404, 'not_found', 'The requested resource was not found.', [], [],
            ],
            $exception instanceof MethodNotAllowedHttpException => [
                405, 'method_not_allowed', 'This HTTP method is not supported for this endpoint.', [], $this->stringHeaders($exception->getHeaders()),
            ],
            $exception instanceof ThrottleRequestsException => [
                429, 'too_many_requests', 'Too many requests. Please slow down.', [], $this->stringHeaders($exception->getHeaders()),
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(), 'http_error', $exception->getMessage() ?: 'The request could not be processed.', [], $this->stringHeaders($exception->getHeaders()),
            ],
            default => [
                500, 'server_error', 'Something went wrong on our side. Please try again.', [], [],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function stringHeaders(array $headers): array
    {
        return array_map(fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value, $headers);
    }
}
