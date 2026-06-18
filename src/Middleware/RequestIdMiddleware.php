<?php
declare(strict_types=1);

namespace WeatherAgent\Middleware;

use WeatherAgent\Http\Request;
use WeatherAgent\Support\Logger;

/**
 * Generates X-Request-Id if not present.
 */
class RequestIdMiddleware
{
    public function handle(Request $request): ?bool
    {
        $requestId = $request->getHeader('x-request-id');

        if ($requestId === null || $requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        $request->setRequestId($requestId);
        header('X-Request-Id: ' . $requestId);
        Logger::setRequestId($requestId);

        return null;
    }
}
