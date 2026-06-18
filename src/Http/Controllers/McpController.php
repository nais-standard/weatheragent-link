<?php
declare(strict_types=1);

namespace WeatherAgent\Http\Controllers;

use WeatherAgent\Config;
use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Mcp\JsonRpc;
use WeatherAgent\Mcp\McpServer;
use WeatherAgent\Mcp\ToolRegistry;
use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ForecastClient;
use WeatherAgent\OpenMeteo\ResponseMapper;
use WeatherAgent\Tools\GetCurrentWeatherTool;
use WeatherAgent\Tools\GetHourlyForecastTool;
use WeatherAgent\Tools\GetDailyForecastTool;
use WeatherAgent\Tools\GeocodeLocationTool;
use WeatherAgent\Tools\CompareWeatherTool;
use WeatherAgent\Support\Logger;

/**
 * MCP endpoint controller.
 */
class McpController
{
    public function handleGet(Request $request): void
    {
        Response::json([
            'name' => 'weatheragent-link',
            'version' => Config::getVersion(),
            'protocol' => 'MCP',
            'protocolVersion' => '2025-03-26',
            'description' => 'Weather MCP server powered by Open-Meteo. Send JSON-RPC requests via POST to this endpoint.',
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
        ]);
    }

    public function handlePost(Request $request): void
    {
        // Validate Content-Type
        $contentType = $request->getHeader('content-type');
        if ($contentType === null || strpos($contentType, 'application/json') === false) {
            $error = JsonRpc::errorResponse(null, JsonRpc::PARSE_ERROR, 'Content-Type must be application/json');
            Response::json($error, 400, [
                'Cache-Control' => 'no-store',
            ]);
            return;
        }

        // Enforce max request size
        $maxBytes = (int) Config::get('max_request_bytes', 65536);
        if ($request->getBodyLength() > $maxBytes) {
            $error = JsonRpc::errorResponse(null, JsonRpc::INVALID_REQUEST, 'Request body too large');
            Response::json($error, 413, [
                'Cache-Control' => 'no-store',
            ]);
            return;
        }

        // Parse JSON-RPC request
        $body = $request->getBody();
        $parsed = JsonRpc::parseRequest($body);

        if (isset($parsed['error'])) {
            Response::json($parsed, 400, [
                'Cache-Control' => 'no-store',
            ]);
            return;
        }

        // Reject batch requests
        if (isset($parsed['batch'])) {
            $error = JsonRpc::errorResponse(null, JsonRpc::INVALID_REQUEST, 'Batch requests are not supported');
            Response::json($error, 400, [
                'Cache-Control' => 'no-store',
            ]);
            return;
        }

        // Create MCP server and handle request
        $registry = $this->buildToolRegistry();
        $server = new McpServer($registry);

        $result = $server->handleRequest($parsed);

        Logger::info("MCP request: method={$parsed['method']}", [
            'request_id' => $request->getRequestId(),
        ]);

        Response::json($result, 200, [
            'Cache-Control' => 'no-store',
        ]);
    }

    private function buildToolRegistry(): ToolRegistry
    {
        $geocodingClient = new GeocodingClient();
        $forecastClient = new ForecastClient();
        $responseMapper = new ResponseMapper();

        $registry = new ToolRegistry();
        $registry->register(new GetCurrentWeatherTool($geocodingClient, $forecastClient, $responseMapper));
        $registry->register(new GetHourlyForecastTool($geocodingClient, $forecastClient, $responseMapper));
        $registry->register(new GetDailyForecastTool($geocodingClient, $forecastClient, $responseMapper));
        $registry->register(new GeocodeLocationTool($geocodingClient, $responseMapper));
        $registry->register(new CompareWeatherTool($geocodingClient, $forecastClient, $responseMapper));

        return $registry;
    }
}
