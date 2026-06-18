<?php
declare(strict_types=1);

namespace WeatherAgent;

use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Http\Controllers\HealthController;
use WeatherAgent\Http\Controllers\McpController;

/**
 * Simple request router.
 */
class Router
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private array $routes = [];

    public function __construct()
    {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->addRoute('GET', '/', HealthController::class, 'index');
        $this->addRoute('GET', '/health', HealthController::class, 'health');
        $this->addRoute('GET', '/ready', HealthController::class, 'ready');
        $this->addRoute('GET', '/version', HealthController::class, 'version');
        $this->addRoute('GET', '/mcp', McpController::class, 'handleGet');
        $this->addRoute('POST', '/mcp', McpController::class, 'handlePost');
    }

    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[$method][$path] = [$controller, $action];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if ($method === 'OPTIONS') {
            $this->handleCors($request);
            return;
        }

        if (!isset($this->routes[$method][$path])) {
            Response::error('Not found', 404);
            return;
        }

        $route = $this->routes[$method][$path];
        $controllerClass = $route[0];
        $action = $route[1];

        $controller = new $controllerClass();
        $controller->$action($request);
    }

    private function handleCors(Request $request): void
    {
        $origin = $request->getHeader('Origin');
        $allowedOrigins = Config::get('allowed_origins', '*');

        $responseOrigin = '*';
        if ($allowedOrigins !== '*' && $origin !== null) {
            $allowed = array_map('trim', explode(',', $allowedOrigins));
            if (in_array($origin, $allowed, true)) {
                $responseOrigin = $origin;
            }
        }

        http_response_code(204);
        header('Access-Control-Allow-Origin: ' . $responseOrigin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-Id');
        header('Access-Control-Max-Age: 86400');
    }
}
