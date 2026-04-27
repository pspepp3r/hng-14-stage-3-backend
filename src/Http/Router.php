<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\RbacMiddleware;
use App\Http\Middleware\VersionMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Exception;

use function preg_match;

final class Router
{
    private ProfileController $profileController;
    private AuthController $authController;
    private AuthMiddleware $authMiddleware;
    private RbacMiddleware $rbacMiddleware;
    private VersionMiddleware $versionMiddleware;
    private RateLimitMiddleware $rateLimitMiddleware;

    public function __construct()
    {
        $this->profileController = new ProfileController();
        $this->authController = new AuthController();
        $this->authMiddleware = new AuthMiddleware();
        $this->rbacMiddleware = new RbacMiddleware();
        $this->versionMiddleware = new VersionMiddleware();
        $this->rateLimitMiddleware = new RateLimitMiddleware();
    }

    public function dispatch(string $method, string $uri): void
    {
        // Parse URI (remove query string)
        $path = \parse_url($uri, PHP_URL_PATH);
        $path = \str_replace('/hng-14-task-2', '', $path);

        try {
            // Auth Routes (Public but rate-limited)
            if (str_starts_with($path, '/auth/')) {
                $this->rateLimitMiddleware->handle('auth_' . $_SERVER['REMOTE_ADDR'], 10, 60);
                
                if ($method === 'GET' && $path === '/auth/github') {
                    $this->authController->githubRedirect();
                    return;
                }
                if ($method === 'GET' && $path === '/auth/github/callback') {
                    $this->authController->githubCallback();
                    return;
                }
                if ($method === 'POST' && $path === '/auth/refresh') {
                    $this->authController->refresh();
                    return;
                }
                if ($method === 'POST' && $path === '/auth/logout') {
                    $this->authController->logout();
                    return;
                }
            }

            // API Routes (Protected)
            if (str_starts_with($path, '/api/')) {
                // 1. Versioning
                $this->versionMiddleware->handle();

                // 2. Authentication
                $user = $this->authMiddleware->handle();

                // 3. Rate Limiting (per user)
                $this->rateLimitMiddleware->handle('user_' . $user['sub'], 60, 60);

                // Export route
                if ($method === 'GET' && $path === '/api/profiles/export') {
                    $this->profileController->export();
                    return;
                }

                if ($method === 'POST' && $path === '/api/profiles') {
                    $this->rbacMiddleware->enforce($user, 'admin');
                    $this->profileController->create();
                    return;
                }

                // Natural language search route (must come before generic /{id} routes)
                if ($method === 'GET' && $path === '/api/profiles/search') {
                    $this->profileController->search();
                    return;
                }

                if ($method === 'GET' && $path === '/api/profiles') {
                    $this->profileController->getAll();
                    return;
                }

                // Check parameterized routes (/{id})
                if ($method === 'GET' && preg_match('#^/api/profiles/([a-f0-9\-]+)$#i', $path, $matches)) {
                    $this->profileController->getById($matches[1]);
                    return;
                }

                if ($method === 'DELETE' && preg_match('#^/api/profiles/([a-f0-9\-]+)$#i', $path, $matches)) {
                    $this->rbacMiddleware->enforce($user, 'admin');
                    $this->profileController->delete($matches[1]);
                    return;
                }
            }

            // No route matched
            $this->notFound();
        } catch (Exception $e) {
            \http_response_code(500);
            \header('Content-Type: application/json');
            echo \json_encode([
                'status' => 'error',
                'message' => APP_ENV === 'development' ? $e->getMessage() : 'Internal server error',
            ]);
        }
    }

    private function notFound(): void
    {
        Response::error('Endpoint not found', 404)->send();
    }
}
