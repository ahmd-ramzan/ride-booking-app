<?php

namespace App\Exceptions;

/**
 * Protocol errors for the Idempotency-Key header.
 */
class IdempotencyException extends ApiException
{
    private function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $machineCode,
        /** @var array<string, string> */
        private readonly array $extraHeaders = [],
    ) {
        parent::__construct($message);
    }

    public static function missing(): self
    {
        return new self('The Idempotency-Key header is required for this request.', 400, 'idempotency_key_required');
    }

    public static function malformed(): self
    {
        return new self('The Idempotency-Key header must be 8-255 characters of letters, digits, "-", "_", ":" or ".".', 400, 'idempotency_key_invalid');
    }

    public static function reusedWithDifferentRequest(): self
    {
        return new self('This Idempotency-Key was already used with a different request.', 422, 'idempotency_key_reused');
    }

    public static function inProgress(): self
    {
        return new self('A request with this Idempotency-Key is still being processed. Retry shortly.', 409, 'idempotency_request_in_progress', ['Retry-After' => '1']);
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->machineCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->extraHeaders;
    }
}
