<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

/**
 * File-based cache implementation.
 */
class FileCache implements Cache
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/cache';

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false) {
            @unlink($file);
            return null;
        }

        if (!is_array($data) || !isset($data['expires_at']) || !isset($data['value'])) {
            @unlink($file);
            return null;
        }

        if ($data['expires_at'] > 0 && $data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        $file = $this->getFilePath($key);

        $data = [
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ];

        @file_put_contents($file, serialize($data), LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    public function clear(): void
    {
        $files = glob($this->directory . '/cache_*.dat');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->directory . '/cache_' . md5($key) . '.dat';
    }
}
