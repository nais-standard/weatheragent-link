<?php
declare(strict_types=1);

namespace WeatherAgent\Middleware;

use WeatherAgent\Config;
use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Mcp\JsonRpc;
use WeatherAgent\Support\Logger;

/**
 * Bearer token authentication for MCP endpoints.
 */
class AuthMiddleware
{
    public function handle(Request $request): ?bool
    {
        if ($request->getPath() !== '/mcp') {
            return null;
        }

        $token = Config::get('mcp_bearer_token', '');

        if ($token === '' || $token === null) {
            return null;
        }

        $authHeader = $request->getHeader('authorization');

        if ($authHeader === null || $authHeader === '') {
            Logger::warning('Missing Authorization header');
            $error = JsonRpc::errorResponse(null, JsonRpc::INTERNAL_ERROR, 'Authentication required');
            Response::json($error, 401, ['Cache-Control' => 'no-store']);
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Logger::warning('Invalid Authorization header format');
            $error = JsonRpc::errorResponse(null, JsonRpc::INTERNAL_ERROR, 'Invalid authorization format. Expected: Bearer <token>');
            Response::json($error, 401, ['Cache-Control' => 'no-store']);
        }

        $providedToken = substr($authHeader, 7);

        if (!hash_equals((string) $token, $providedToken)) {
            Logger::warning('Invalid bearer token');
            $error = JsonRpc::errorResponse(null, JsonRpc::INTERNAL_ERROR, 'Invalid bearer token');
            Response::json($error, 401, ['Cache-Control' => 'no-store']);
        }

        return null;
    }
}
