<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Profile;
use PDO;

final class ProfileRepository implements ProfileRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function create(Profile $profile): Profile
    {
        $query = <<<SQL
        INSERT INTO profiles (
            id, name, gender, gender_probability,
            age, age_group, country_id, country_name, country_probability,
            created_at
        ) VALUES (
            UNHEX(REPLACE(?, '-', '')),
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?
        )
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $profile->getId(),
            $profile->getName(),
            $profile->getGender(),
            $profile->getGenderProbability(),
            $profile->getAge(),
            $profile->getAgeGroup(),
            $profile->getCountryId(),
            $profile->getCountryName(),
            $profile->getCountryProbability(),
            $profile->getCreatedAt(),
        ]);

        return $profile;
    }

    public function findById(string $id): ?Profile
    {
        $query = 'SELECT * FROM profiles WHERE id = UNHEX(REPLACE(?, \'-\', \'\'))';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->mapToProfile($result) : null;
    }

    public function findByName(string $name): ?Profile
    {
        $query = 'SELECT * FROM profiles WHERE LOWER(name) = LOWER(?)';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$name]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->mapToProfile($result) : null;
    }

    public function findByCountryName(string $countryName): ?string
    {
        $query = 'SELECT country_id FROM profiles WHERE LOWER(country_name) = LOWER(?) LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$countryName]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['country_id'] : null;
    }

    /**
     * Find all profiles with filtering, sorting, and pagination
     * 
     * @param array $filters Filter criteria
     * @param string $sortBy Field to sort by (age, created_at, gender_probability)
     * @param string $order Sort order (asc, desc)
     * @param int $limit Limit results
     * @param int $offset Offset results
     */
    public function findAll(
        array $filters = [],
        string $sortBy = 'created_at',
        string $order = 'desc',
        int $limit = 10,
        int $offset = 0
    ): array {
        $query = 'SELECT * FROM profiles WHERE 1=1';
        $params = [];

        $query = $this->applyFilters($query, $filters, $params);
        $query = $this->applySorting($query, $sortBy, $order);
        $query .= ' LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($query);

        // Bind filter parameters
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }

        // Bind pagination parameters as integers
        $stmt->bindValue(\count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(\count($params) + 2, $offset, PDO::PARAM_INT);

        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return \array_map([$this, 'mapToProfile'], $results);
    }

    /**
     * Count profiles with filtering
     */
    public function count(array $filters = []): int
    {
        $query = 'SELECT COUNT(*) as total FROM profiles WHERE 1=1';
        $params = [];

        $query = $this->applyFilters($query, $filters, $params);

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }

    public function delete(string $id): bool
    {
        $query = 'DELETE FROM profiles WHERE id = UNHEX(REPLACE(?, \'-\', \'\'))';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if profile with name exists
     */
    public function existsByName(string $name): bool
    {
        $query = 'SELECT 1 FROM profiles WHERE LOWER(name) = LOWER(?) LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$name]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Batch insert profiles (for seeding)
     */
    public function batchCreate(array $profiles): void
    {
        $query = <<<SQL
        INSERT INTO profiles (
            id, name, gender, gender_probability,
            age, age_group, country_id, country_name, country_probability,
            created_at
        ) VALUES (
            UNHEX(REPLACE(?, '-', '')),
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?
        )
        SQL;

        $stmt = $this->db->prepare($query);

        foreach ($profiles as $profile) {
            $stmt->execute([
                $profile->getId(),
                $profile->getName(),
                $profile->getGender(),
                $profile->getGenderProbability(),
                $profile->getAge(),
                $profile->getAgeGroup(),
                $profile->getCountryId(),
                $profile->getCountryName(),
                $profile->getCountryProbability(),
                $profile->getCreatedAt(),
            ]);
        }
    }

    /**
     * Apply filters to query
     */
    private function applyFilters(string $query, array $filters, array &$params): string
    {
        if (!empty($filters['gender'])) {
            $query .= ' AND LOWER(gender) = LOWER(?)';
            $params[] = $filters['gender'];
        }

        if (!empty($filters['country_id'])) {
            $query .= ' AND LOWER(country_id) = LOWER(?)';
            $params[] = $filters['country_id'];
        }

        if (!empty($filters['age_group'])) {
            $query .= ' AND LOWER(age_group) = LOWER(?)';
            $params[] = $filters['age_group'];
        }

        if (isset($filters['min_age']) && $filters['min_age'] !== null) {
            $query .= ' AND age >= ?';
            $params[] = (int)$filters['min_age'];
        }

        if (isset($filters['max_age']) && $filters['max_age'] !== null) {
            $query .= ' AND age <= ?';
            $params[] = (int)$filters['max_age'];
        }

        if (isset($filters['min_gender_probability']) && $filters['min_gender_probability'] !== null) {
            $query .= ' AND gender_probability >= ?';
            $params[] = (float)$filters['min_gender_probability'];
        }

        if (isset($filters['min_country_probability']) && $filters['min_country_probability'] !== null) {
            $query .= ' AND country_probability >= ?';
            $params[] = (float)$filters['min_country_probability'];
        }

        return $query;
    }

    /**
     * Apply sorting to query
     */
    private function applySorting(string $query, string $sortBy, string $order): string
    {
        $validSortFields = ['age', 'created_at', 'gender_probability'];
        $validOrders = ['asc', 'desc'];

        $sortBy = \in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
        $order = \in_array(\strtolower($order), $validOrders) ? \strtoupper($order) : 'DESC';

        return "$query ORDER BY $sortBy $order";
    }

    /**
     * Map database row to Profile model
     */
    private function mapToProfile(array $data): Profile
    {
        return new Profile(
            name: $data['name'],
            gender: $data['gender'],
            genderProbability: $data['gender_probability'] !== null ? (float)$data['gender_probability'] : null,
            age: $data['age'] !== null ? (int)$data['age'] : null,
            ageGroup: $data['age_group'],
            countryId: $data['country_id'],
            countryName: $data['country_name'],
            countryProbability: $data['country_probability'] !== null ? (float)$data['country_probability'] : null,
            id: $this->binaryToUuid($data['id']),
            createdAt: $data['created_at'],
        );
    }

    /**
     * Convert binary UUID to string format
     */
    private function binaryToUuid(string $binary): string
    {
        $hex = \bin2hex($binary);
        return \substr($hex, 0, 8) . '-' .
            \substr($hex, 8, 4) . '-' .
            \substr($hex, 12, 4) . '-' .
            \substr($hex, 16, 4) . '-' .
            \substr($hex, 20, 12);
    }
}
