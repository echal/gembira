<?php
/**
 * Fix UTF-8 encoding issue in current_badge column
 * The default value shows as ƒî▒ instead of 🌱
 */

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

try {
    $conn = DriverManager::getConnection([
        'dbname' => 'gembira_db',
        'user' => 'root',
        'password' => '',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
        'charset' => 'utf8mb4',
    ]);

    echo "=== FIXING BADGE ENCODING ISSUE ===\n\n";

    // Show current state
    echo "Current badge distribution:\n";
    $result = $conn->fetchAllAssociative(
        "SELECT current_badge, COUNT(*) as total
         FROM pegawai
         GROUP BY current_badge"
    );

    foreach ($result as $row) {
        echo sprintf("Badge: '%s' (%d users)\n",
            $row['current_badge'],
            $row['total']
        );
    }

    echo "\n--- Fixing badges ---\n";

    // Fix the default badge for all users with broken encoding
    // The broken encoding appears as ƒî▒ which is the UTF-8 representation of 🌱
    $affected = $conn->executeStatement(
        "UPDATE pegawai
         SET current_badge = '🌱'
         WHERE current_badge NOT IN ('🌱', '🌿', '🌺', '🌞', '🏆')"
    );
    echo "✓ Fixed broken badge encoding for $affected users → '🌱'\n";

    // Also update based on current_level to ensure consistency
    echo "\n--- Ensuring badge consistency with level ---\n";

    $conn->executeStatement("UPDATE pegawai SET current_badge = '🌱' WHERE current_level = 1");
    echo "✓ Level 1 → 🌱\n";

    $conn->executeStatement("UPDATE pegawai SET current_badge = '🌿' WHERE current_level = 2");
    echo "✓ Level 2 → 🌿\n";

    $conn->executeStatement("UPDATE pegawai SET current_badge = '🌺' WHERE current_level = 3");
    echo "✓ Level 3 → 🌺\n";

    $conn->executeStatement("UPDATE pegawai SET current_badge = '🌞' WHERE current_level = 4");
    echo "✓ Level 4 → 🌞\n";

    $conn->executeStatement("UPDATE pegawai SET current_badge = '🏆' WHERE current_level = 5");
    echo "✓ Level 5 → 🏆\n";

    // Also fix the column default value
    echo "\n--- Fixing column default value ---\n";
    $conn->executeStatement(
        "ALTER TABLE pegawai
         MODIFY COLUMN current_badge VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '🌱'"
    );
    echo "✓ Updated column default to '🌱'\n";

    // Show updated state
    echo "\n--- Updated badge distribution ---\n";
    $result = $conn->fetchAllAssociative(
        "SELECT current_badge, COUNT(*) as total
         FROM pegawai
         GROUP BY current_badge"
    );

    foreach ($result as $row) {
        echo sprintf("Badge: '%s' (%d users)\n",
            $row['current_badge'],
            $row['total']
        );
    }

    echo "\n✅ Badge encoding fixed successfully!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
