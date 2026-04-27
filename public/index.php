<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Middleware\CorsMiddleware;
use App\Http\Router;
use App\Services\LoggerService;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');

try {
    // Log incoming request
    LoggerService::logRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

    // Initialize application
    $router = new Router();

    // Apply middleware
    $cors = new CorsMiddleware();
    $cors->handlePreFlight();

    // Route requests
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => match (getenv('APP_ENV')) {
            'development' => $e->getMessage(),
            default => 'Internal server error'
        }
    ]);
}
