<?php
declare(strict_types=1);

namespace WeatherAgent\Middleware;

use WeatherAgent\Config;
use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Mcp\JsonRpc;
use WeatherAgent\Support\Logger;

/**
 * Validates Origin header against allowed origins.
 */
class OriginValidationMiddleware
{
    public function handle(Request $request): ?bool
    {
        if ($request->getPath() !== '/mcp' || $request->getMethod() !== 'POST') {
            return null;
        }

        $allowedOrigins = Config::get('allowed_origins', '*');

        if ($allowedOrigins === '*' || $allowedOrigins === '' || $allowedOrigins === null) {
            return null;
        }

        $origin = $request->getHeader('origin');

        if ($origin === null || $origin === '') {
            return null;
        }

        $allowed = array_map('trim', explode(',', (string) $allowedOrigins));

        if (!in_array($origin, $allowed, true)) {
            Logger::warning("Origin rejected: {$origin}");
            $error = JsonRpc::errorResponse(null, JsonRpc::INTERNAL_ERROR, 'Origin not allowed');
            Response::json($error, 403, ['Cache-Control' => 'no-store']);
        }

        return null;
    }
}
