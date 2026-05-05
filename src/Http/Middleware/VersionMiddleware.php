<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class VersionMiddleware
{
    public function handle(): void
    {
        $headers = \getallheaders();
        $version = $headers['X-API-Version'] ?? $headers['x-api-version'] ?? $headers['X-Api-Version'] ?? null;

        if ($version !== '1') {
            Response::error('API version header required', 400)->send();
            exit;
        }
    }
}
