<?php
declare(strict_types=1);

namespace WeatherAgent\Mcp;

use WeatherAgent\Config;
use WeatherAgent\Support\Logger;

/**
 * MCP protocol handler.
 */
class McpServer
{
    private const PROTOCOL_VERSION = '2025-03-26';

    private bool $initialized = false;

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
    ) {}

    /** @return array<string, mixed> */
    public function handleRequest(array $rpc): array
    {
        $method = $rpc['method'] ?? '';
        $params = $rpc['params'] ?? [];
        $id = $rpc['id'] ?? null;

        // Notifications (no id) - acknowledge silently
        if ($id === null && $method !== 'initialize') {
            return JsonRpc::successResponse($id, []);
        }

        return match ($method) {
            'initialize' => $this->handleInitialize($params, $id),
            'notifications/initialized', 'ping' => JsonRpc::successResponse($id, []),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($params, $id),
            default => JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, "Method not found: {$method}"),
        };
    }

    /** @return array<string, mixed> */
    private function handleInitialize(array $params, mixed $id): array
    {
        $this->initialized = true;

        return JsonRpc::successResponse($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => Capabilities::build(),
            'serverInfo' => [
                'name' => 'weatheragent-link',
                'version' => Config::getVersion(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function handleToolsList(mixed $id): array
    {
        return JsonRpc::successResponse($id, [
            'tools' => $this->toolRegistry->listTools(),
        ]);
    }

    /** @return array<string, mixed> */
    private function handleToolsCall(array $params, mixed $id): array
    {
        $toolName = $params['name'] ?? null;
        if ($toolName === null || !is_string($toolName)) {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, 'Missing required parameter: name');
        }

        $tool = $this->toolRegistry->getTool($toolName);
        if ($tool === null) {
            return JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, "Unknown tool: {$toolName}");
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, 'Parameter "arguments" must be an object');
        }

        try {
            $result = $tool->execute($arguments);

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    ],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error("Tool execution failed: {$toolName}: " . $e->getMessage());

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'error' => true,
                            'message' => 'Tool execution failed: ' . $e->getMessage(),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'isError' => true,
            ]);
        }
    }
}
