<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Profile;

interface ProfileRepositoryInterface
{
    public function create(Profile $profile): Profile;

    public function findById(string $id): ?Profile;

    public function findByName(string $name): ?Profile;

    public function findByCountryName(string $countryName): ?string;

    /**
     * Find all profiles with filtering, sorting, and pagination
     */
    public function findAll(
        array $filters = [],
        string $sortBy = 'created_at',
        string $order = 'desc',
        int $limit = 10,
        int $offset = 0
    ): array;

    public function count(array $filters = []): int;

    public function delete(string $id): bool;

    public function existsByName(string $name): bool;

    public function batchCreate(array $profiles): void;
}
