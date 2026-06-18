<?php
declare(strict_types=1);

namespace WeatherAgent;

use WeatherAgent\Support\Env;

/**
 * Static configuration accessor.
 */
class Config
{
    private static array $config = [];
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = [
            'app_env' => Env::get('APP_ENV', 'production'),
            'app_debug' => Env::bool('APP_DEBUG', false),
            'app_name' => Env::get('APP_NAME', 'WeatherAgent'),
            'app_version' => Env::get('APP_VERSION', '1.0.0'),
            'host' => Env::get('HOST', '0.0.0.0'),
            'port' => (int) Env::get('PORT', '8080'),
            'log_level' => Env::get('LOG_LEVEL', 'info'),
            'mcp_bearer_token' => Env::get('MCP_BEARER_TOKEN', ''),
            'allowed_origins' => Env::get('ALLOWED_ORIGINS', '*'),
            'request_timeout_ms' => (int) Env::get('REQUEST_TIMEOUT_MS', '10000'),
            'cache_ttl_seconds' => (int) Env::get('CACHE_TTL_SECONDS', '300'),
            'rate_limit_window_ms' => (int) Env::get('RATE_LIMIT_WINDOW_MS', '60000'),
            'rate_limit_max' => (int) Env::get('RATE_LIMIT_MAX', '60'),
            'max_request_bytes' => (int) Env::get('MAX_REQUEST_BYTES', '65536'),
        ];

        self::$initialized = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('app_debug', false);
    }

    public static function getVersion(): string
    {
        return (string) self::get('app_version', '1.0.0');
    }

    public static function getAppName(): string
    {
        return (string) self::get('app_name', 'WeatherAgent');
    }

    public static function getRequestTimeoutSeconds(): float
    {
        return (float) self::get('request_timeout_ms', 10000) / 1000.0;
    }

    public static function getCacheTtl(): int
    {
        return (int) self::get('cache_ttl_seconds', 300);
    }

    public static function all(): array
    {
        return self::$config;
    }
}
