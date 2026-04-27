<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;
use App\Services\AuthService;

final class AuthMiddleware
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function handle(): ?array
    {
        // Try to get token from Authorization header or Cookies
        $token = $this->getBearerToken() ?? $_COOKIE['access_token'] ?? null;

        if (!$token) {
            Response::error('Authentication required', 401)->send();
            exit;
        }

        $decoded = $this->authService->validateToken($token);
        if (!$decoded || isset($decoded['type'])) { // Should not be a refresh token
            Response::error('Invalid or expired token', 401)->send();
            exit;
        }

        return $decoded;
    }

    private function getBearerToken(): ?string
    {
        $headers = \getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader && \preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
