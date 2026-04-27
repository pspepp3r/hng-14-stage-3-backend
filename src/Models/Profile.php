<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class Profile
{
    private string $id;
    private string $name;
    private ?string $gender;
    private ?float $genderProbability;
    private ?int $age;
    private ?string $ageGroup;
    private ?string $countryId;
    private ?string $countryName;
    private ?float $countryProbability;
    private string $createdAt;

    public function __construct(
        string $name,
        ?string $gender = null,
        ?float $genderProbability = null,
        ?int $age = null,
        ?string $ageGroup = null,
        ?string $countryId = null,
        ?string $countryName = null,
        ?float $countryProbability = null,
        ?string $id = null,
        ?string $createdAt = null
    ) {
        $this->validateName($name);
        $this->id = $id ?? Uuid::uuid7()->toString();
        $this->name = $name;
        $this->gender = $gender;
        $this->genderProbability = $genderProbability;
        $this->age = $age;
        $this->ageGroup = $ageGroup;
        $this->countryId = $countryId;
        $this->countryName = $countryName;
        $this->countryProbability = $countryProbability;
        $this->createdAt = $createdAt ?? $this->getCurrentTimestamp();
    }

    private function validateName(string $name): void
    {
        if (empty(\trim($name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
    }

    private function getCurrentTimestamp(): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))
            ->format('c');
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getGenderProbability(): ?float
    {
        return $this->genderProbability;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function getAgeGroup(): ?string
    {
        return $this->ageGroup;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function getCountryProbability(): ?float
    {
        return $this->countryProbability;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender' => $this->gender,
            'gender_probability' => $this->genderProbability,
            'age' => $this->age,
            'age_group' => $this->ageGroup,
            'country_id' => $this->countryId,
            'country_name' => $this->countryName,
            'country_probability' => $this->countryProbability,
            'created_at' => $this->createdAt,
        ];
    }
}
