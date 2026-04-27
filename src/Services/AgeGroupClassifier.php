<?php

declare(strict_types=1);

namespace App\Services;

enum AgeGroupClassifier: int
{
    case CHILD_MAX = 12;
    case TEENAGER_MAX = 19;
    case ADULT_MAX = 59;

    public static function classify(int $age): string
    {
        return match (true) {
            $age <= self::CHILD_MAX => 'child',
            $age <= self::TEENAGER_MAX => 'teenager',
            $age <= self::ADULT_MAX => 'adult',
            default => 'senior',
        };
    }
}
