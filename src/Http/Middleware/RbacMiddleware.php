<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class RbacMiddleware
{
    public function enforce(array $user, string $requiredRole): void
    {
        if ($user['role'] !== $requiredRole && $user['role'] !== 'admin') {
            Response::error('Forbidden: Insufficient permissions', 403)->send();
            exit;
        }
    }
}
