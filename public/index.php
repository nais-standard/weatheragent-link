<?php
declare(strict_types=1);

/**
 * WeatherAgent MCP Server - Front Controller
 *
 * All requests are routed through this file.
 */

// Load the application bootstrap
$config = require __DIR__ . '/../bootstrap/app.php';

use WeatherAgent\Http\Request;
use WeatherAgent\Http\Response;
use WeatherAgent\Router;
use WeatherAgent\Middleware\RequestIdMiddleware;
use WeatherAgent\Middleware\AuthMiddleware;
use WeatherAgent\Middleware\OriginValidationMiddleware;
use WeatherAgent\Middleware\RateLimitMiddleware;
use WeatherAgent\Support\Logger;

// Set error handler
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    Logger::error("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
    return true;
});

set_exception_handler(function (\Throwable $e) {
    Logger::error("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    Response::error('Internal server error', 500);
});

try {
    // Create the request
    $request = new Request();

    // Run middleware pipeline
    $middlewares = [
        new RequestIdMiddleware(),
        new RateLimitMiddleware(),
        new OriginValidationMiddleware(),
        new AuthMiddleware(),
    ];

    foreach ($middlewares as $middleware) {
        $result = $middleware->handle($request);
        if ($result !== null) {
            // Middleware returned a response, send it and stop
            return;
        }
    }

    // Route the request
    $router = new Router();
    $router->dispatch($request);
} catch (\Throwable $e) {
    Logger::error("Request failed: " . $e->getMessage());
    Response::error('Internal server error', 500);
}
