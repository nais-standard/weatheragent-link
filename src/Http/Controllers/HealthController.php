<?php
declare(strict_types=1);

namespace WeatherAgent\Http\Controllers;

use WeatherAgent\Config;
use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;

/**
 * Health and info endpoints.
 */
class HealthController
{
    public function index(Request $request): void
    {
        if ($this->wantsHtml($request)) {
            $template = dirname(__DIR__, 3) . '/templates/home.html';
            Response::html(file_get_contents($template));
        }

        Response::json([
            'name' => Config::getAppName(),
            'description' => 'MCP weather server powered by Open-Meteo. Provides current conditions, hourly and daily forecasts, geocoding, and multi-location comparison.',
            'version' => Config::getVersion(),
            'mcp_endpoint' => '/mcp',
        ]);
    }

    /**
     * Determine if the request is from a browser expecting HTML.
     */
    private function wantsHtml(Request $request): bool
    {
        $accept = $request->getHeader('accept') ?? '';
        $ua     = $request->getHeader('user-agent') ?? '';

        // Explicit JSON request
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return false;
        }

        // Known bot/CLI user agents get JSON
        if (preg_match('/bot|crawl|spider|curl|wget|httpie|python|node-fetch|go-http|java|php/i', $ua)) {
            return false;
        }

        // Browser-like Accept header
        if (str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    public function health(Request $request): void
    {
        Response::json([
            'status' => 'ok',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public function ready(Request $request): void
    {
        Response::json([
            'ready' => true,
        ]);
    }

    public function version(Request $request): void
    {
        Response::json([
            'name' => Config::getAppName(),
            'version' => Config::getVersion(),
            'php' => PHP_VERSION,
            'mcp_protocol' => '2025-03-26',
        ]);
    }
}
