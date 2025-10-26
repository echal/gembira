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

echo "Checking user_xp_log table...\n";

try {
    $count = $conn->fetchOne('SELECT COUNT(*) FROM user_xp_log');
    echo "✓ Table user_xp_log exists with $count rows\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nChecking UserXpLog repository...\n";
try {
    $tables = $conn->fetchAllAssociative("SHOW TABLES LIKE 'user_xp_log'");
    if (empty($tables)) {
        echo "✗ Table user_xp_log does NOT exist!\n";
    } else {
        echo "✓ Table user_xp_log exists\n";
        echo "\nColumns:\n";
        $columns = $conn->fetchAllAssociative('SHOW COLUMNS FROM user_xp_log');
        foreach ($columns as $col) {
            echo sprintf("  - %s (%s)\n", $col['Field'], $col['Type']);
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
