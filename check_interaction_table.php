<?php
require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection([
    'dbname' => 'gembira_db',
    'user' => 'root',
    'password' => '',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
]);

echo "=== Checking user_quotes_interaction table ===\n\n";

try {
    $columns = $conn->fetchAllAssociative('SHOW COLUMNS FROM user_quotes_interaction');

    echo "Current columns:\n";
    foreach ($columns as $col) {
        echo sprintf("  - %-20s | %-20s | %s\n",
            $col['Field'],
            $col['Type'],
            $col['Key']
        );
    }

    // Check if pegawai_id still exists
    $hasPegawaiId = false;
    $hasUserId = false;
    $hasComment = false;

    foreach ($columns as $col) {
        if ($col['Field'] === 'pegawai_id') $hasPegawaiId = true;
        if ($col['Field'] === 'user_id') $hasUserId = true;
        if ($col['Field'] === 'comment') $hasComment = true;
    }

    echo "\n";
    echo "✓ Has user_id column: " . ($hasUserId ? "YES" : "NO") . "\n";
    echo "✗ Still has pegawai_id: " . ($hasPegawaiId ? "YES (PROBLEM!)" : "NO") . "\n";
    echo "✓ Has comment column: " . ($hasComment ? "YES" : "NO") . "\n";

    if ($hasPegawaiId && !$hasUserId) {
        echo "\n⚠️  DATABASE NOT UPDATED!\n";
        echo "You need to run: fix_user_quotes_interaction.sql\n";
    } elseif ($hasUserId) {
        echo "\n✅ Database is up to date!\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
