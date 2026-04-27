<?php

declare(strict_types=1);

namespace App\External\Clients;

use App\External\ExternalApiClientInterface;
use App\External\HttpClient;
use Exception;

final class GenderizeClient implements ExternalApiClientInterface
{
    private HttpClient $httpClient;
    private const string BASE_URL = 'https://api.genderize.io';

    public function __construct()
    {
        $this->httpClient = new HttpClient();
    }

    public function getName(): string
    {
        return 'Genderize';
    }

    public function fetch(string $name): array
    {
        $url = self::BASE_URL . '?name=' . urlencode($name);
        return $this->httpClient->get($url);
    }

    public function validate(array $response): array
    {
        // Check for null gender or zero count
        if (empty($response['gender']) || empty($response['count'])) {
            throw new Exception('Genderize API returned insufficient data');
        }

        return [
            'gender' => $response['gender'],
            'gender_probability' => (float)$response['probability'],
            'sample_size' => (int)$response['count'],
        ];
    }
}
