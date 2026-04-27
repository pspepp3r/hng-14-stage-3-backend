<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;

final class Response implements JsonSerializable
{
    private int $statusCode;
    private string $status;
    private array $data;
    private ?string $message;
    private ?int $page;
    private ?int $limit;
    private ?int $total;

    private function __construct(
        int $statusCode,
        string $status,
        array $data = [],
        ?string $message = null,
        ?int $page = null,
        ?int $limit = null,
        ?int $total = null
    ) {
        $this->statusCode = $statusCode;
        $this->status = $status;
        $this->data = $data;
        $this->message = $message;
        $this->page = $page;
        $this->limit = $limit;
        $this->total = $total;
    }

    public static function success(
        array $data,
        int $statusCode = 200,
        ?string $message = null,
        ?int $page = null,
        ?int $limit = null,
        ?int $total = null
    ): self {
        return new self($statusCode, 'success', $data, $message, $page, $limit, $total);
    }

    public static function error(
        string $message,
        int $statusCode = 400
    ): self {
        return new self($statusCode, 'error', [], $message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function jsonSerialize(): array
    {
        if ($this->status === 'error') {
            return [
                'status' => 'error',
                'message' => $this->message,
            ];
        }

        $response = [
            'status' => 'success',
        ];

        if ($this->message !== null) {
            $response['message'] = $this->message;
        }

        if ($this->page !== null && $this->limit !== null && $this->total !== null) {
            $totalPages = (int)\ceil($this->total / $this->limit);
            $response['page'] = $this->page;
            $response['limit'] = $this->limit;
            $response['total'] = $this->total;
            $response['total_pages'] = $totalPages;

            // Build links
            $uri = \parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $queryParams = $_GET;
            
            $buildUrl = function($p) use ($uri, $queryParams) {
                $queryParams['page'] = $p;
                return $uri . '?' . \http_build_query($queryParams);
            };

            $response['links'] = [
                'self' => $buildUrl($this->page),
                'next' => $this->page < $totalPages ? $buildUrl($this->page + 1) : null,
                'prev' => $this->page > 1 ? $buildUrl($this->page - 1) : null,
            ];
        }

        $response['data'] = $this->data;

        return $response;
    }

    public function send(): void
    {
        \http_response_code($this->statusCode);
        \header('Content-Type: application/json');
        echo \json_encode($this, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
