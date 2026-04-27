<?php

declare(strict_types=1);

namespace App\Services;

use App\External\Clients\CountriesClient;
use App\Repositories\ProfileRepositoryInterface;
use InvalidArgumentException;

/**
 * Natural Language Query Parser
 * 
 * Parses plain English queries and converts them into filter criteria.
 * Supports keywords like: young, males, females, teenagers, adult, senior, child, countries, etc.
 * 
 * Uses dependency injection for country resolution via CountriesClient.
 */
final class NaturalLanguageParser
{
    private const array GENDER_PATTERNS = [
        '/\b(ma(?:le)?s?|man|men)\b/i' => 'male',
        '/\b(fe(?:male)?s?|wom[ea]n)\b/i' => 'female',
    ];

    private const array AGE_GROUP_PATTERNS = [
        '/\bchild(?:ren)?\b/i' => 'child',
        '/\bteenager?s?\b/i' => 'teenager',
        '/\badult?s?\b/i' => 'adult',
        '/\bsenior?s?\b/i' => 'senior',
    ];

    private ProfileRepositoryInterface $repository;
    private CountriesClient $countriesClient;

    public function __construct(ProfileRepositoryInterface $repository, CountriesClient $countriesClient)
    {
        $this->repository = $repository;
        $this->countriesClient = $countriesClient;
    }

    /**
     * Parse a natural language query and return filter criteria
     * 
     * @param string $query The query string
     * @return array The parsed filters
     * @throws InvalidArgumentException If query cannot be parsed
     */
    public function parse(string $query): array
    {
        $query = trim($query);

        if (empty($query)) {
            throw new InvalidArgumentException('Query cannot be empty');
        }

        $filters = [];

        // Extract gender
        foreach (self::GENDER_PATTERNS as $pattern => $gender) {
            if (preg_match($pattern, $query, $matches)) {
                $filters['gender'] = $gender;
                break;
            }
        }

        // Extract age group
        foreach (self::AGE_GROUP_PATTERNS as $pattern => $ageGroup) {
            if (preg_match($pattern, $query, $matches)) {
                $filters['age_group'] = $ageGroup;
                break;
            }
        }

        // Handle specific age ranges and descriptors
        $this->parseAgeDescriptors($query, $filters);

        // Extract country
        $this->parseCountry($query, $filters);

        // If no filters were extracted, throw error
        if (empty($filters)) {
            throw new InvalidArgumentException('Unable to interpret query');
        }

        return $filters;
    }

    /**
     * Parse age-related descriptors
     */
    private function parseAgeDescriptors(string $query, array &$filters): void
    {
        // Range: "ages 25-35"
        if (preg_match('/\bages?\s+(\d+)\s*-\s*(\d+)\b/i', $query, $matches)) {
            $filters['min_age'] = (int) $matches[1];
            $filters['max_age'] = (int) $matches[2];
            return;
        }

        // Above: "above 30", "over 25"
        if (preg_match('/\b(?:above|over|more\s+than|at\s+least)\s+(\d+)\b/i', $query, $matches)) {
            $filters['min_age'] = (int)$matches[1];
            return;
        }

        // Below: "below 20", "under 18"
        if (preg_match('/\b(?:below|under|less\s+than)\s+(\d+)\b/i', $query, $matches)) {
            $filters['max_age'] = (int)$matches[1];
            return;
        }

        // Young: map to 16-24
        if (preg_match('/\byoung\b/i', $query)) {
            $filters['min_age'] = 16;
            $filters['max_age'] = 24;
            return;
        }

        // Old: map to 50+
        if (preg_match('/\bold\b/i', $query)) {
            $filters['min_age'] = 50;
            return;
        }
    }

    /**
     * Parse country information
     * 
     * Extracts country from "from [country]" pattern and resolves to ISO code
     * using external API via CountriesClient, with database fallback.
     */
    private function parseCountry(string $query, array &$filters): void
    {
        // Extract country name from "from [country]" pattern
        // Updated pattern to better handle multi-word country names
        preg_match('/\bfrom\s+([^,;|]+?)(?:\s+(?:age|male|female|teenager|adult|senior|child|young|old)|$)/i', $query, $matches);

        if (empty($matches[1])) {
            return;
        }

        $countryName = trim($matches[1]);

        // Remove any trailing keywords that might have been captured
        $keywords = ['age', 'male', 'female', 'teenager', 'adult', 'senior', 'child', 'young', 'old'];
        $countryLower = strtolower($countryName);

        foreach ($keywords as $keyword) {
            if (preg_match('/\s+' . preg_quote($keyword) . '\s*$/', $countryLower)) {
                $countryName = trim(preg_replace('/\s+' . preg_quote($keyword) . '\s*$/i', '', $countryName));
            }
        }

        if (empty($countryName)) {
            return;
        }

        // Try API first (via CountriesClient)
        $countryCode = $this->countriesClient->findCountryCode($countryName);

        if ($countryCode) {
            $filters['country_id'] = $countryCode;
            return;
        }

        // Fall back to database lookup
        $countryId = $this->repository->findByCountryName($countryName);
        if ($countryId) {
            $filters['country_id'] = $countryId;
            return;
        }

        // If country name is provided but not found, throw exception to indicate a parsing issue
        throw new InvalidArgumentException("Country '{$countryName}' not found. Please provide a valid country name.");
    }

    /**
     * Check if a query is interpretable
     */
    public function isInterpretable(string $query): bool
    {
        try {
            $this->parse($query);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
