<?php

declare(strict_types=1);

namespace App\External;

interface ExternalApiClientInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $name): array;

    /**
     * @return array<string, mixed>
     */
    public function validate(array $response): array;
}
