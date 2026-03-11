<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$pdo = Database::connection();
$files = glob(__DIR__ . '/../database/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$file}\n");
        exit(1);
    }

    $pdo->exec($sql);
    echo 'Applied migration: ' . basename($file) . PHP_EOL;
}
