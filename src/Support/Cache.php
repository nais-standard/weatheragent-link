<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

/**
 * Cache interface.
 */
interface Cache
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 300): void;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): void;
}
