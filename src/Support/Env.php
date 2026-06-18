<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

/**
 * Environment variable helper.
 */
class Env
{
    /**
     * Get an environment variable value.
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Get a boolean environment variable.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }
}
