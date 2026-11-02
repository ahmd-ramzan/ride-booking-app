<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * An expected, client-facing rejection of a request (business rule or protocol
 * violation). Rendered by ApiExceptionRenderer with a stable machine-readable
 * error code; not reported to the error tracker because it is not a fault.
 */
abstract class ApiException extends RuntimeException implements ShouldntReport
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    /**
     * Additional machine-readable details included in the response body.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }
}
