<?php
/**
 * Script untuk cek kolom-kolom yang missing di database
 * Jalankan: php check_missing_columns.php
 */

// Suppress deprecation warnings
error_reporting(E_ERROR | E_PARSE);

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env
(new Dotenv())->bootEnv(__DIR__.'/.env');

// Get database config
$dbUrl = $_ENV['DATABASE_URL'] ?? '';
preg_match('/mysql:\/\/([^:]+):([^@]*)@([^:\/]+):(\d+)\/([^?]+)/', $dbUrl, $matches);

if (count($matches) < 6) {
    echo "❌ Error parsing DATABASE_URL: $dbUrl\n";
    exit(1);
}

$user = $matches[1];
$pass = $matches[2];
$host = $matches[3];
$port = $matches[4];
$dbname = $matches[5];

// Connect
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Connected to database: $dbname\n\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Expected columns per table
$expectedColumns = [
    'absensi' => ['latitude', 'longitude'],
    'pegawai' => ['photo', 'tanda_tangan', 'tanda_tangan_uploaded_at', 'last_login_at', 'last_login_ip', 'total_xp', 'current_level', 'current_badge', 'level_title'],
    'konfigurasi_jadwal_absensi' => ['perlu_validasi_admin'],
];

// Expected tables
$expectedTables = ['quote', 'quote_comment', 'user_quote_interaction', 'user_xp_log', 'monthly_leaderboard', 'user_points', 'user_badges'];

$hasIssues = false;

// Check missing tables
echo "📋 CHECKING MISSING TABLES:\n";
echo str_repeat("=", 60) . "\n";

foreach ($expectedTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Missing table: $table\n";
        $hasIssues = true;
    } else {
        echo "✅ Table exists: $table\n";
    }
}

echo "\n";

// Check missing columns
echo "📋 CHECKING MISSING COLUMNS:\n";
echo str_repeat("=", 60) . "\n";

foreach ($expectedColumns as $table => $columns) {
    echo "\nTable: $table\n";

    // Check if table exists first
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) {
        echo "  ⚠️  Table doesn't exist!\n";
        $hasIssues = true;
        continue;
    }

    foreach ($columns as $column) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            echo "  ❌ Missing column: $column\n";
            $hasIssues = true;
        } else {
            echo "  ✅ Column exists: $column\n";
        }
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";

if ($hasIssues) {
    echo "❌ FOUND MISSING TABLES/COLUMNS!\n";
    echo "\n";
    echo "🔧 SOLUSI:\n";
    echo "Jalankan script SQL fix:\n";
    echo "  mysql -u $user -p $dbname < fix_database_after_import.sql\n";
    echo "\n";
    echo "Atau via phpMyAdmin:\n";
    echo "  1. Buka phpMyAdmin\n";
    echo "  2. Pilih database $dbname\n";
    echo "  3. Tab SQL\n";
    echo "  4. Copy-paste isi file fix_database_after_import.sql\n";
    echo "  5. Klik Go\n";
    exit(1);
} else {
    echo "✅ ALL TABLES & COLUMNS EXIST!\n";
    echo "Database structure is complete. 🎉\n";
    exit(0);
}
