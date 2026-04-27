<?php

declare(strict_types=1);

namespace App\Services;

use App\External\Clients\GenderizeClient;
use App\External\Clients\AgifyClient;
use App\External\Clients\NationalizeClient;
use App\External\Clients\CountriesClient;
use App\Models\Profile;
use App\Repositories\ProfileRepositoryInterface;
use Exception;

final class ProfileService
{
    private GenderizeClient $genderizeClient;
    private AgifyClient $agifyClient;
    private NationalizeClient $nationalizeClient;
    private CountriesClient $countriesClient;
    private ProfileRepositoryInterface $repository;

    public function __construct(ProfileRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->genderizeClient = new GenderizeClient();
        $this->agifyClient = new AgifyClient();
        $this->nationalizeClient = new NationalizeClient();
        $this->countriesClient = new CountriesClient();
    }

    /**
     * Create or retrieve a profile
     * @throws Exception
     */
    public function createOrGetProfile(string $name): array
    {
        // Check if profile already exists
        $existing = $this->repository->findByName($name);
        if ($existing !== null) {
            return [
                'profile' => $existing,
                'isNew' => false,
                'message' => 'Profile already exists',
            ];
        }

        // Fetch data from external APIs
        try {
            $genderizeData = $this->fetchGenderizeData($name);
            $agifyData = $this->fetchAgifyData($name);
            $nationalizeData = $this->fetchNationalizeData($name);
        } catch (Exception $e) {
            throw new Exception($this->extractApiName($e) . ' returned an invalid response');
        }

        // Classify age group
        $ageGroup = AgeGroupClassifier::classify($agifyData['age']);

        // Create profile model
        $profile = new Profile(
            name: $name,
            gender: $genderizeData['gender'],
            genderProbability: $genderizeData['gender_probability'],
            age: $agifyData['age'],
            ageGroup: $ageGroup,
            countryId: $nationalizeData['country_id'],
            countryName: $nationalizeData['country_name'],
            countryProbability: $nationalizeData['country_probability'],
        );

        // Store in database
        $this->repository->create($profile);

        return [
            'profile' => $profile,
            'isNew' => true,
            'message' => null,
        ];
    }

    public function getProfileById(string $id): ?Profile
    {
        return $this->repository->findById($id);
    }

    /**
     * @return Profile[]
     */
    public function getAllProfiles(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    /**
     * Get profiles with filtering, sorting, and pagination
     * 
     * @param array $filters Filter criteria
     * @param string $sortBy Sort by field
     * @param string $order Sort order
     * @param int $page Page number (1-indexed)
     * @param int $limit Results per page
     * @return array
     */
    public function getProfilesWithPagination(
        array $filters = [],
        string $sortBy = 'created_at',
        string $order = 'desc',
        int $page = 1,
        int $limit = 10
    ): array {
        // Ensure page is at least 1
        $page = max(1, $page);

        // Ensure limit is between 1 and 50
        $limit = max(1, min(50, $limit));

        $offset = ($page - 1) * $limit;
        $profiles = $this->repository->findAll($filters, $sortBy, $order, $limit, $offset);
        $total = $this->repository->count($filters);

        return [
            'profiles' => $profiles,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * Search profiles using natural language query
     * 
     * @param string $query Natural language query
     * @param int $page Page number
     * @param int $limit Results per page
     * @return array
     * @throws Exception
     */
    public function searchProfiles(string $query, int $page = 1, int $limit = 10): array
    {
        // Parse natural language query
        $parser = new NaturalLanguageParser($this->repository, $this->countriesClient);
        $filters = $parser->parse($query);

        // Get paginated results
        return $this->getProfilesWithPagination($filters, 'created_at', 'desc', $page, $limit);
    }

    public function deleteProfile(string $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * @throws Exception
     */
    private function fetchGenderizeData(string $name): array
    {
        try {
            $response = $this->genderizeClient->fetch($name);
            return $this->genderizeClient->validate($response);
        } catch (Exception $e) {
            throw new Exception('Genderize: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function fetchAgifyData(string $name): array
    {
        try {
            $response = $this->agifyClient->fetch($name);
            return $this->agifyClient->validate($response);
        } catch (Exception $e) {
            throw new Exception('Agify: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function fetchNationalizeData(string $name): array
    {
        try {
            $response = $this->nationalizeClient->fetch($name);
            return $this->nationalizeClient->validate($response);
        } catch (Exception $e) {
            throw new Exception('Nationalize: ' . $e->getMessage());
        }
    }

    private function extractApiName(Exception $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Genderize')) return 'Genderize';
        if (str_contains($message, 'Agify')) return 'Agify';
        if (str_contains($message, 'Nationalize')) return 'Nationalize';
        return 'Unknown';
    }
}
