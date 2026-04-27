<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class Migration
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function up(): void
    {
        // Create profiles table with UUID v7 support
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS profiles (
            id BINARY(16) PRIMARY KEY COMMENT 'UUID v7',
            name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Person name',
            gender VARCHAR(50) COMMENT 'Gender classification',
            gender_probability DECIMAL(3, 2) COMMENT 'Gender probability (0-1)',
            age INT COMMENT 'Age estimation',
            age_group VARCHAR(20) COMMENT 'Age group classification',
            country_id CHAR(2) COMMENT 'Country ISO code',
            country_name VARCHAR(100) COMMENT 'The full name of the country',
            country_probability DECIMAL(3, 2) COMMENT 'Country probability (0-1)',
            created_at VARCHAR(30) COMMENT 'Creation timestamp in ISO 8601 UTC',
            INDEX idx_name (name),
            INDEX idx_gender (gender),
            INDEX idx_age_group (age_group),
            INDEX idx_country_id (country_id),
            INDEX idx_age (age),
            INDEX idx_gender_prob (gender_probability),
            INDEX idx_country_prob (country_probability)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->db->exec($sql);
        echo "✓ Profiles table created successfully\n";
    }

    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS profiles;');
        echo "✓ Migration rolled back\n";
    }

    public function reset(): void
    {
        $this->down();
        $this->up();
        echo "✓ Database reset\n";
    }
}
