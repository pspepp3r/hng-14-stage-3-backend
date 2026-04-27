<?php

declare(strict_types=1);

namespace App\External\Clients;

use App\External\ExternalApiClientInterface;
use App\External\HttpClient;
use Exception;

final class AgifyClient implements ExternalApiClientInterface
{
    private HttpClient $httpClient;
    private const string BASE_URL = 'https://api.agify.io';

    public function __construct()
    {
        $this->httpClient = new HttpClient();
    }

    public function getName(): string
    {
        return 'Agify';
    }

    public function fetch(string $name): array
    {
        $url = self::BASE_URL . '?name=' . \urlencode($name);
        return $this->httpClient->get($url);
    }

    public function validate(array $response): array
    {
        // Check for null age
        if ($response['age'] === null) {
            throw new Exception('Agify API returned null age');
        }

        return [
            'age' => (int)$response['age'],
        ];
    }
}
