<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates logs, error responses and client reports. Honours a well-formed
 * inbound X-Request-Id (e.g. from a load balancer) or generates one; the id is
 * added to the log context and echoed back on the response.
 */
class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $inbound = $request->headers->get(self::HEADER);

        $requestId = is_string($inbound) && preg_match('/^[A-Za-z0-9._\-]{8,128}$/', $inbound) === 1
            ? $inbound
            : (string) Str::uuid();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
