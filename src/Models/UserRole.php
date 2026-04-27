<?php

declare(strict_types=1);

namespace App\Models;

enum UserRole: string
{
    case ADMIN = 'admin';
    case ANALYST = 'analyst';

    public static function fromString(string $role): self
    {
        return self::tryFrom(strtolower($role)) ?? self::ANALYST;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}
