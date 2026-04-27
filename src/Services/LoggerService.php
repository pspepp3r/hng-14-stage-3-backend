<?php

declare(strict_types=1);

namespace App\Services;

class LoggerService
{
    private const LOG_FILE = __DIR__ . '/../../storage/logs/app.log';

    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = \dirname(self::LOG_FILE);

        // Create directory if it doesn't exist
        if (!\is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = \sprintf("[%s] %s: %s%s\n", $timestamp, strtoupper($level), $message, $contextString);

        \file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function logRequest(string $method, string $uri): void
    {
        $requestData = [
            'method' => $method,
            'uri' => $uri,
        ];

        // Log query parameters for GET requests
        if ($method === 'GET') {
            $queryString = parse_url($uri, PHP_URL_QUERY);
            if ($queryString) {
                parse_str($queryString, $queryParams);
                $requestData['query'] = $queryParams;
            }
        }

        // Log request body for POST and other methods that have a body
        if (\in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $input = \file_get_contents('php://input');
            if ($input) {
                $requestData['body'] = \json_decode($input, true) ?? $input;
            }
        }

        self::log('info', 'Incoming Request', $requestData);
    }
}
