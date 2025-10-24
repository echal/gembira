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

echo "=== Checking quotes Table Constraints ===\n\n";

// Show create table
echo "--- Table Structure ---\n";
$createTable = $conn->fetchAssociative("SHOW CREATE TABLE quotes");
echo $createTable['Create Table'] . "\n\n";

// Check for triggers
echo "--- Triggers on quotes table ---\n";
$triggers = $conn->fetchAllAssociative("SHOW TRIGGERS WHERE `Table` = 'quotes'");
if (empty($triggers)) {
    echo "✓ No triggers found\n";
} else {
    foreach ($triggers as $trigger) {
        echo "⚠️  Trigger: {$trigger['Trigger']} ({$trigger['Timing']} {$trigger['Event']})\n";
        echo "   Statement: {$trigger['Statement']}\n\n";
    }
}

// Check foreign key constraints
echo "\n--- Foreign Key Constraints ---\n";
$fks = $conn->fetchAllAssociative(
    "SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'gembira_db'
    AND TABLE_NAME = 'quotes'
    AND REFERENCED_TABLE_NAME IS NOT NULL"
);

if (empty($fks)) {
    echo "✓ No foreign key constraints\n";
} else {
    foreach ($fks as $fk) {
        echo "  {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
}
