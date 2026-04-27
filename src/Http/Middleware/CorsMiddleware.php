<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use function header;

final class CorsMiddleware
{
    public function handlePreFlight(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        // When using credentials, Origin cannot be '*'
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Version');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            \http_response_code(200);
            exit;
        }
    }
}
