<?php

declare(strict_types=1);

namespace App\External;

use Exception;

final class HttpClient
{
    private int $timeout = 10;

    public function get(string $url): array
    {
        try {
            $context = \stream_context_create([
                'http' => [
                    'timeout' => $this->timeout,
                    'user_agent' => 'PHP-Client/1.0',
                ]
            ]);

            $response = @\file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('Failed to fetch URL');
            }

            $data = \json_decode($response, true);

            if (\json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response');
            }

            return $data ?? [];
        } catch (Exception $e) {
            throw new Exception("HTTP request failed: " . $e->getMessage());
        }
    }
}
