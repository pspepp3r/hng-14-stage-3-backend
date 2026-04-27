<?php

declare(strict_types=1);

namespace App\External\Clients;

use App\External\ExternalApiClientInterface;
use App\External\HttpClient;
use Exception;

final class CountriesClient implements ExternalApiClientInterface
{
    private HttpClient $httpClient;
    private const string BASE_URL = 'https://restcountries.com/v3.1/all';
    private static ?array $cache = null;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
    }

    public function getName(): string
    {
        return 'Countries';
    }

    /**
     * Fetch all countries data with caching
     */
    public function fetch(string $name = ''): array
    {
        // Return cached data if available
        if (self::$cache !== null) {
            return self::$cache;
        }

        $url = self::BASE_URL;
        return $this->httpClient->get($url);
    }

    /**
     * Validate and cache the countries response
     */
    public function validate(array $response): array
    {
        if (!\is_array($response) || empty($response)) {
            throw new Exception('Countries API returned empty data');
        }

        // Cache the response for subsequent calls
        self::$cache = $response;

        return $response;
    }

    /**
     * Find country code by name (official, common, or demonym)
     */
    public function findCountryCode(string $countryName): ?string
    {
        try {
            $countries = $this->fetch();
            $countries = $this->validate($countries);

            $searchTerm = strtolower(trim($countryName));

            foreach ($countries as $country) {
                // Check official name
                if (strtolower($country['name']['official'] ?? '') === $searchTerm) {
                    return $country['cca2'];
                }

                // Check common name
                if (strtolower($country['name']['common'] ?? '') === $searchTerm) {
                    return $country['cca2'];
                }

                // Check alternative spellings
                if (isset($country['altSpellings'])) {
                    foreach ($country['altSpellings'] as $spelling) {
                        if (strtolower($spelling) === $searchTerm) {
                            return $country['cca2'];
                        }
                    }
                }

                // Check demonyms (e.g., "Nigerian" -> "NG")
                if (isset($country['demonyms']['eng'])) {
                    $engDemonym = $country['demonyms']['eng'];

                    if (\is_array($engDemonym)) {
                        foreach ($engDemonym as $demonym) {
                            if (strtolower($demonym) === $searchTerm) {
                                return $country['cca2'];
                            }
                        }
                    } elseif (\is_string($engDemonym) && strtolower($engDemonym) === $searchTerm) {
                        return $country['cca2'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Countries API lookup failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get country name by ISO 3166-1 alpha-2 code
     * 
     * @param string $countryCode The ISO code (e.g., 'NG', 'US')
     * @return string The official country name, or the code if not found
     */
    public function getCountryNameByCode(string $countryCode): string
    {
        try {
            $countries = $this->fetch();
            $countries = $this->validate($countries);
            $upperCode = strtoupper(trim($countryCode));

            foreach ($countries as $country) {
                if (($country['cca2'] ?? '') === $upperCode) {
                    return $country['name']['official'] ?? $country['name']['common'] ?? $upperCode;
                }
            }
        } catch (Exception $e) {
            error_log("Country name lookup failed: " . $e->getMessage());
        }

        return $countryCode;
    }

    /**
     * Clear cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
