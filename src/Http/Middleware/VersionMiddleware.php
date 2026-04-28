<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class VersionMiddleware
{
    public function handle(): void
    {
        // Read from $_SERVER for Nginx/Apache compatibility
        $version = $_SERVER['HTTP_X_API_VERSION'] ??
            $_SERVER['X-API-Version'] ??
            $_SERVER['X_API_VERSION'] ??
            null;

        if ($version !== '1') {
            Response::error('API version header required', 401)->send();
            exit;
        }
    }
}
