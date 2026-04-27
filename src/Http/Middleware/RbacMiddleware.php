<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;
use App\Models\UserRole;

final class RbacMiddleware
{
    public function enforce(array $user, string|UserRole $requiredRole): void
    {
        $userRole = $user['role'] instanceof UserRole ? $user['role'] : UserRole::fromString($user['role']);
        $reqRole = $requiredRole instanceof UserRole ? $requiredRole : UserRole::fromString($requiredRole);

        if ($userRole !== UserRole::ADMIN && $userRole !== $reqRole) {
            Response::error('Forbidden: Insufficient permissions', 403)->send();
            exit;
        }
    }
}
