<?php
declare(strict_types=1);

namespace WeatherAgent\Http;

use WeatherAgent\Config;

/**
 * HTTP Response helper.
 */
class Response
{
    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

        $allowedOrigins = Config::get('allowed_origins', '*');
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins === '*' ? '*' : $allowedOrigins));

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function error(string $message, int $status = 500): never
    {
        self::json(['error' => $message], $status);
    }
}
