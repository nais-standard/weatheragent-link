<?php
declare(strict_types=1);

namespace WeatherAgent\Middleware;

use WeatherAgent\Config;
use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Mcp\JsonRpc;
use WeatherAgent\Support\Logger;

/**
 * File-based rate limiting using IP + time window.
 */
class RateLimitMiddleware
{
    public function handle(Request $request): ?bool
    {
        $maxRequests = (int) Config::get('rate_limit_max', 60);
        $windowMs = (int) Config::get('rate_limit_window_ms', 60000);

        if ($maxRequests <= 0) {
            return null;
        }

        $ip = $request->getClientIp();
        $windowSeconds = $windowMs / 1000.0;
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/ratelimit_' . md5($ip) . '.json';

        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        $requests = [];
        if (file_exists($file)) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $requests = $decoded;
                }
            }
        }

        $requests = array_values(array_filter($requests, fn(float $timestamp): bool => $timestamp > $windowStart));

        if (count($requests) >= $maxRequests) {
            Logger::warning("Rate limit exceeded for IP: {$ip}");

            $retryAfter = (int) ceil($windowSeconds);
            header('Retry-After: ' . $retryAfter);
            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: 0');

            $error = JsonRpc::errorResponse(null, JsonRpc::INTERNAL_ERROR, 'Rate limit exceeded. Try again later.');
            Response::json($error, 429, ['Cache-Control' => 'no-store']);
        }

        $requests[] = $now;
        @file_put_contents($file, json_encode($requests), LOCK_EX);

        $remaining = $maxRequests - count($requests);
        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: ' . $remaining);

        return null;
    }
}
