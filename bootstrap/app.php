<?php
declare(strict_types=1);

/**
 * Application Bootstrap
 *
 * Loads autoloader, environment, and initializes configuration.
 */

// Load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Dependencies not installed. Run: composer install']);
    exit(1);
}
require $autoloadPath;

// Load .env file if it exists
$envPath = __DIR__ . '/..';
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// Initialize configuration
\WeatherAgent\Config::initialize();

// Initialize logger
\WeatherAgent\Support\Logger::initialize();

return \WeatherAgent\Config::all();
