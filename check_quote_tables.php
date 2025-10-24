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

echo "=== Checking Quote Tables ===\n\n";

// Check if both tables exist
$tables = $conn->fetchAllAssociative("SHOW TABLES LIKE 'quote%'");

echo "Tables found:\n";
foreach ($tables as $table) {
    $tableName = array_values($table)[0];
    echo "  - $tableName\n";
}

echo "\n";

// Check 'quote' table (singular)
try {
    $countQuote = $conn->fetchOne("SELECT COUNT(*) FROM quote");
    echo "✓ Table 'quote' exists with $countQuote rows\n";

    $sampleQuote = $conn->fetchAssociative("SELECT * FROM quote LIMIT 1");
    if ($sampleQuote) {
        echo "  Sample: " . substr($sampleQuote['content'], 0, 50) . "...\n";
    }
} catch (\Exception $e) {
    echo "✗ Table 'quote' error: " . $e->getMessage() . "\n";
}

echo "\n";

// Check 'quotes' table (plural)
try {
    $countQuotes = $conn->fetchOne("SELECT COUNT(*) FROM quotes");
    echo "✓ Table 'quotes' exists with $countQuotes rows\n";

    $sampleQuotes = $conn->fetchAssociative("SELECT * FROM quotes LIMIT 1");
    if ($sampleQuotes) {
        echo "  Sample: " . substr($sampleQuotes['content'], 0, 50) . "...\n";
    }
} catch (\Exception $e) {
    echo "✗ Table 'quotes' error: " . $e->getMessage() . "\n";
}

echo "\n=== Analysis ===\n";
echo "This is likely the root cause of error 500!\n";
echo "Entity expects one table name, but database has both.\n";
