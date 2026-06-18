<?php
declare(strict_types=1);

namespace WeatherAgent\Mcp;

/**
 * JSON-RPC 2.0 helpers.
 */
class JsonRpc
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    /** @return array<string, mixed> */
    public static function parseRequest(string $body): array
    {
        if ($body === '') {
            return self::errorResponse(null, self::PARSE_ERROR, 'Empty request body');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return self::errorResponse(null, self::PARSE_ERROR, 'Parse error: invalid JSON');
        }

        // Detect batch request (numerically indexed array)
        if (isset($decoded[0])) {
            return ['batch' => true];
        }

        if (!isset($decoded['jsonrpc']) || $decoded['jsonrpc'] !== '2.0') {
            return self::errorResponse(
                $decoded['id'] ?? null,
                self::INVALID_REQUEST,
                'Invalid JSON-RPC: missing or invalid "jsonrpc" field'
            );
        }

        if (!isset($decoded['method']) || !is_string($decoded['method'])) {
            return self::errorResponse(
                $decoded['id'] ?? null,
                self::INVALID_REQUEST,
                'Invalid JSON-RPC: missing or invalid "method" field'
            );
        }

        return [
            'jsonrpc' => '2.0',
            'method' => $decoded['method'],
            'params' => $decoded['params'] ?? [],
            'id' => $decoded['id'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public static function successResponse(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];
    }

    /** @return array<string, mixed> */
    public static function errorResponse(mixed $id, int $code, string $message, mixed $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id,
        ];
    }
}
