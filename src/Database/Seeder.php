<?php

declare(strict_types=1);

namespace App\Database;

use App\Models\Profile;
use App\Repositories\ProfileRepository;
use RuntimeException;

/**
 * Database Seeder
 * 
 * Seeds the database with profile data from a JSON file
 */
class Seeder
{
    private ProfileRepository $repository;

    public function __construct()
    {
        $this->repository = new ProfileRepository();
    }

    /**
     * Seed the database from a JSON file
     * 
     * @param string $filePath Path to the JSON file
     * @return void
     * @throws RuntimeException
     */
    public function seedFromJson(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Seed file not found: $filePath");
        }

        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new RuntimeException("Could not read seed file: $filePath");
        }

        $data = json_decode($jsonContent, true)['profiles'];
        if ($data === null) {
            throw new RuntimeException("Invalid JSON in seed file: $filePath");
        }

        $profiles = [];
        $duplicateCount = 0;

        foreach ($data as $item) {
            // Check if profile already exists
            if ($this->repository->existsByName($item['name'])) {
                $duplicateCount++;
                continue;
            }

            try {
                $profile = new Profile(
                    name: $item['name'],
                    gender: $item['gender'] ?? null,
                    genderProbability: isset($item['gender_probability']) ? (float)$item['gender_probability'] : null,
                    age: isset($item['age']) ? (int)$item['age'] : null,
                    ageGroup: $item['age_group'] ?? null,
                    countryId: $item['country_id'] ?? null,
                    countryName: $item['country_name'] ?? null,
                    countryProbability: isset($item['country_probability']) ? (float)$item['country_probability'] : null,
                );

                $profiles[] = $profile;
            } catch (\Exception $e) {
                echo "Warning: Failed to create profile for {$item['name']}: {$e->getMessage()}\n";
                continue;
            }

            // Batch insert every 100 records
            if (\count($profiles) >= 100) {
                $this->repository->batchCreate($profiles);
                echo "Inserted " . \count($profiles) . " profiles\n";
                $profiles = [];
            }
        }

        // Insert remaining profiles
        if (!empty($profiles)) {
            $this->repository->batchCreate($profiles);
            echo "Inserted " . \count($profiles) . " profiles\n";
        }

        echo "✓ Seeding complete!\n";
        echo "  - Profiles created: " . \count($data) - $duplicateCount . "\n";
        echo "  - Duplicates skipped: $duplicateCount\n";
    }

    /**
     * Clear all profiles from the database
     */
    public function truncate(): void
    {
        $db = Connection::getInstance();
        $db->exec('TRUNCATE TABLE profiles');
        echo "✓ Profiles table truncated\n";
    }

    /**
     * Seed and reset (clear then seed)
     */
    public function reseedFromJson(string $filePath): void
    {
        $this->truncate();
        $this->seedFromJson($filePath);
    }
}
