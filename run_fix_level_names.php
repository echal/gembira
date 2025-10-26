<?php
/**
 * Script to fix level names in database
 * Removes "Ikhlas" references from existing pegawai records
 */

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

try {
    // Database connection parameters
    $connectionParams = [
        'dbname' => 'gembira_db',
        'user' => 'root',
        'password' => '',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
        'charset' => 'utf8mb4',
    ];

    $conn = DriverManager::getConnection($connectionParams);

    echo "=== FIXING LEVEL NAMES IN DATABASE ===\n\n";

    // Show current state
    echo "Current level distribution:\n";
    $result = $conn->fetchAllAssociative(
        "SELECT current_level, level_title, COUNT(*) as total
         FROM pegawai
         GROUP BY current_level, level_title
         ORDER BY current_level"
    );

    foreach ($result as $row) {
        echo sprintf("Level %d: %s (%d users)\n",
            $row['current_level'],
            $row['level_title'],
            $row['total']
        );
    }

    echo "\n--- Updating level names ---\n";

    // Update Level 1
    $affected = $conn->executeStatement(
        "UPDATE pegawai SET level_title = 'Pemula' WHERE current_level = 1"
    );
    echo "✓ Updated Level 1: $affected users → 'Pemula'\n";

    // Update Level 4
    $affected = $conn->executeStatement(
        "UPDATE pegawai SET level_title = 'Inspirator Gembira' WHERE current_level = 4"
    );
    echo "✓ Updated Level 4: $affected users → 'Inspirator Gembira'\n";

    // Update Level 5
    $affected = $conn->executeStatement(
        "UPDATE pegawai SET level_title = 'Teladan Kinerja' WHERE current_level = 5"
    );
    echo "✓ Updated Level 5: $affected users → 'Teladan Kinerja'\n";

    // Show updated state
    echo "\n--- Updated level distribution ---\n";
    $result = $conn->fetchAllAssociative(
        "SELECT current_level, level_title, COUNT(*) as total
         FROM pegawai
         GROUP BY current_level, level_title
         ORDER BY current_level"
    );

    foreach ($result as $row) {
        echo sprintf("Level %d: %s (%d users)\n",
            $row['current_level'],
            $row['level_title'],
            $row['total']
        );
    }

    echo "\n✅ Level names updated successfully!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
