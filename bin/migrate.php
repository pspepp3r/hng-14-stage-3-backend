<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Migration;
use App\Database\Seeder;

$command = $argv[1] ?? 'up';
$seedFile = $argv[2] ?? null;

$migration = new Migration();
$seeder = new Seeder();

switch ($command) {
    case 'down':
        $migration->down();
        break;
    case 'reset':
        $migration->reset();
        break;
    case 'seed':
        if (!$seedFile) {
            echo "Error: Seed file path required. Usage: php migrate.php seed <path/to/file.json>\n";
            exit(1);
        }
        if (!file_exists($seedFile)) {
            echo "Error: Seed file not found: $seedFile\n";
            exit(1);
        }
        $seeder->seedFromJson($seedFile);
        break;
    case 'reseed':
        if (!$seedFile) {
            echo "Error: Seed file path required. Usage: php migrate.php reseed <path/to/file.json>\n";
            exit(1);
        }
        if (!file_exists($seedFile)) {
            echo "Error: Seed file not found: $seedFile\n";
            exit(1);
        }
        $seeder->reseedFromJson($seedFile);
        break;
    case 'truncate':
        $seeder->truncate();
        break;
    default:
        $migration->up();
        break;
}
