<?php
declare(strict_types=1);

namespace WeatherAgent\Support;

/**
 * Centralized error code mapping for JSON-RPC and HTTP errors.
 */
class Errors
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    private const HTTP_MESSAGES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        413 => 'Payload Too Large',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private const RPC_MESSAGES = [
        self::PARSE_ERROR => 'Parse error',
        self::INVALID_REQUEST => 'Invalid Request',
        self::METHOD_NOT_FOUND => 'Method not found',
        self::INVALID_PARAMS => 'Invalid params',
        self::INTERNAL_ERROR => 'Internal error',
    ];

    public static function httpMessage(int $code): string
    {
        return self::HTTP_MESSAGES[$code] ?? 'Unknown Error';
    }

    public static function rpcMessage(int $code): string
    {
        return self::RPC_MESSAGES[$code] ?? 'Unknown error';
    }

    public static function httpToRpc(int $httpStatus): int
    {
        return match ($httpStatus) {
            400, 413 => self::INVALID_REQUEST,
            404, 405 => self::METHOD_NOT_FOUND,
            default => self::INTERNAL_ERROR,
        };
    }
}
