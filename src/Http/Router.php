<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Controllers\ProfileController;
use Exception;

use function preg_match;

final class Router
{
    private ProfileController $profileController;

    public function __construct()
    {
        $this->profileController = new ProfileController();
    }

    public function dispatch(string $method, string $uri): void
    {
        // Parse URI (remove query string)
        $path = \parse_url($uri, PHP_URL_PATH);
        $path = \str_replace('/hng-14-task-2', '', $path);

        try {
            // Check specific routes first
            if ($method === 'POST' && $path === '/api/profiles') {
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
                $this->profileController->delete($matches[1]);
                return;
            }

            // No route matched
            $this->notFound();
        } catch (Exception $e) {
            \http_response_code(500);
            \header('Content-Type: application/json');
            echo \json_encode([
                'status' => 'error',
                'message' => 'Internal server error',
            ]);
        }
    }

    private function notFound(): void
    {
        Response::error('Endpoint not found', 404)->send();
    }
}
