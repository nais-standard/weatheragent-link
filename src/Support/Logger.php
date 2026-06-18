<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

use WeatherAgent\Config;

/**
 * Simple file logger.
 */
class Logger
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    private static string $logFile = '';
    private static string $requestId = '';
    private static int $minLevel = 1;

    public static function initialize(): void
    {
        self::$logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';

        $configLevel = Config::get('log_level', 'info');
        $configLevel = is_string($configLevel) ? strtolower($configLevel) : 'info';
        self::$minLevel = self::LEVELS[$configLevel] ?? 1;
    }

    public static function setRequestId(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    /** @param array<string, mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private static function log(string $level, string $message, array $context = []): void
    {
        $levelNum = self::LEVELS[$level] ?? 1;
        if ($levelNum < self::$minLevel) {
            return;
        }

        if (self::$logFile === '') {
            self::$logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        }

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $levelUpper = strtoupper($level);
        $requestId = self::$requestId !== '' ? self::$requestId : '-';

        $line = "[{$timestamp}] [{$levelUpper}] [{$requestId}] {$message}";

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= "\n";

        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
