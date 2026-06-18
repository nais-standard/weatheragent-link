<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

/**
 * Input validation helpers.
 */
class Validation
{
    /**
     * Require a non-empty string parameter.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param string|null $label
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function requireString(array $params, string $key, ?string $label = null): string
    {
        $label = $label ?? $key;

        if (!isset($params[$key])) {
            throw new \InvalidArgumentException("Missing required parameter: {$label}");
        }

        if (!is_string($params[$key]) || $params[$key] === '') {
            throw new \InvalidArgumentException("Parameter \"{$label}\" must be a non-empty string");
        }

        return $params[$key];
    }

    /**
     * Get an optional string parameter.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function optionalString(array $params, string $key, ?string $default = null): ?string
    {
        if (!isset($params[$key]) || !is_string($params[$key]) || $params[$key] === '') {
            return $default;
        }

        return $params[$key];
    }

    /**
     * Get an optional integer parameter.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param int|null $default
     * @param int|null $min
     * @param int|null $max
     * @return int|null
     * @throws \InvalidArgumentException
     */
    public static function optionalInt(array $params, string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
    {
        if (!isset($params[$key])) {
            return $default;
        }

        $value = (int) $params[$key];

        if ($min !== null && $value < $min) {
            throw new \InvalidArgumentException("Parameter \"{$key}\" must be at least {$min}");
        }

        if ($max !== null && $value > $max) {
            throw new \InvalidArgumentException("Parameter \"{$key}\" must be at most {$max}");
        }

        return $value;
    }

    /**
     * Get an optional enum parameter.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param array<int, string> $allowed
     * @param string|null $default
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public static function optionalEnum(array $params, string $key, array $allowed, ?string $default = null): ?string
    {
        if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
            return $default;
        }

        $value = (string) $params[$key];

        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            throw new \InvalidArgumentException("Parameter \"{$key}\" must be one of: {$allowedStr}");
        }

        return $value;
    }

    /**
     * Require an array parameter.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param string|null $label
     * @return array<mixed>
     * @throws \InvalidArgumentException
     */
    public static function requireArray(array $params, string $key, ?string $label = null): array
    {
        $label = $label ?? $key;

        if (!isset($params[$key])) {
            throw new \InvalidArgumentException("Missing required parameter: {$label}");
        }

        if (!is_array($params[$key])) {
            throw new \InvalidArgumentException("Parameter \"{$label}\" must be an array");
        }

        return $params[$key];
    }

    /**
     * Validate latitude.
     *
     * @param mixed $value
     * @return float
     * @throws \InvalidArgumentException
     */
    public static function latitude(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Latitude must be a number');
        }

        $lat = (float) $value;

        if ($lat < -90.0 || $lat > 90.0) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90');
        }

        return $lat;
    }

    /**
     * Validate longitude.
     *
     * @param mixed $value
     * @return float
     * @throws \InvalidArgumentException
     */
    public static function longitude(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Longitude must be a number');
        }

        $lon = (float) $value;

        if ($lon < -180.0 || $lon > 180.0) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180');
        }

        return $lon;
    }
}
