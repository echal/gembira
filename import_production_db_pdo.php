<?php
/**
 * Script untuk mengimport database production ke localhost menggunakan PHP PDO
 * Digunakan jika mysql command line tidak tersedia
 * Mengatasi error umum seperti DEFINER, charset, dll
 */

echo "==================================================\n";
echo "IMPORT DATABASE (PHP PDO METHOD)\n";
echo "==================================================\n\n";

// Parse .env.local
$envFile = __DIR__ . '/.env.local';
if (!file_exists($envFile)) {
    die("❌ Error: File .env.local tidak ditemukan!\n");
}

$envContent = file_get_contents($envFile);
preg_match('/DATABASE_URL="mysql:\/\/(.+?):(.+?)@(.+?):(\d+)\/(.+?)\?/', $envContent, $matches);

if (empty($matches)) {
    die("❌ Error: DATABASE_URL tidak ditemukan dalam .env.local!\n");
}

$dbUser = $matches[1];
$dbPass = $matches[2];
$dbHost = $matches[3];
$dbPort = $matches[4];
$dbName = $matches[5];

if (!isset($argv[1])) {
    die("Usage: php import_production_db_pdo.php [path_to_sql_file]\n");
}

$sqlFile = $argv[1];

if (!file_exists($sqlFile)) {
    die("❌ Error: File SQL tidak ditemukan: $sqlFile\n");
}

$fileSize = filesize($sqlFile);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);
echo "📁 File SQL: $sqlFile ($fileSizeMB MB)\n\n";

echo "⚠️  PERINGATAN: Import ini akan menghapus semua data yang ada!\n";
echo "Lanjutkan? (yes/no): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$confirmation = trim(strtolower($line));
fclose($handle);

if ($confirmation !== 'yes') {
    exit("❌ Import dibatalkan.\n");
}

echo "\n🔄 Membaca file SQL...\n";

// Baca file SQL
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die("❌ Error: Tidak bisa membaca file SQL\n");
}

echo "✅ File SQL berhasil dibaca\n\n";

// Clean up SQL yang bermasalah
echo "🔧 Membersihkan SQL dari syntax yang bermasalah...\n";

// 1. Hapus DEFINER (common error saat import)
$sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i', '', $sql);

// 2. Ganti database name production dengan localhost
$sql = preg_replace('/DATABASE\s+`gaspulco_gembira`/i', "DATABASE `$dbName`", $sql);
$sql = preg_replace('/USE\s+`gaspulco_gembira`/i', "USE `$dbName`", $sql);

// 3. Pastikan charset utf8mb4
$sql = str_replace('utf8mb3', 'utf8mb4', $sql);

// 4. Set SQL_MODE yang lebih permisif
$sqlModePrefix = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sqlModePrefix .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sqlModePrefix .= "SET time_zone = '+00:00';\n\n";

$sql = $sqlModePrefix . $sql;

echo "✅ SQL cleanup selesai\n\n";

// Connect ke database
echo "🔗 Koneksi ke database...\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    echo "✅ Koneksi berhasil\n\n";

    // Drop database jika ada, lalu buat ulang
    echo "🔄 Membuat ulang database $dbName...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    echo "✅ Database berhasil dibuat\n\n";

    // Split SQL into statements
    echo "🔄 Memproses SQL statements...\n";
    $statements = array_filter(
        array_map('trim', explode(";\n", $sql)),
        function($stmt) {
            return !empty($stmt) && $stmt !== '';
        }
    );

    $totalStatements = count($statements);
    echo "   Total statements: $totalStatements\n\n";

    // Execute statements
    $successCount = 0;
    $errorCount = 0;
    $startTime = microtime(true);

    foreach ($statements as $index => $statement) {
        // Skip comments
        if (strpos(trim($statement), '--') === 0 || strpos(trim($statement), '/*') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement . ';');
            $successCount++;

            // Progress indicator setiap 100 statements
            if ($successCount % 100 === 0) {
                $progress = round(($successCount / $totalStatements) * 100, 1);
                echo "   Progress: $progress% ($successCount/$totalStatements)\n";
            }

        } catch (PDOException $e) {
            $errorCount++;

            // Log error tapi lanjutkan (beberapa error bisa diabaikan)
            if ($errorCount <= 10) {
                echo "   ⚠️  Error pada statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            }

            // Jika terlalu banyak error, stop
            if ($errorCount > 50) {
                echo "\n❌ Terlalu banyak error ($errorCount). Menghentikan import.\n";
                break;
            }
        }
    }

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n✅ Import selesai! ($duration detik)\n";
    echo "   Berhasil: $successCount statements\n";
    echo "   Error: $errorCount statements\n\n";

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // Verifikasi
    echo "🔍 Verifikasi hasil import...\n\n";

    $tables = ['pegawai', 'admin', 'absensi', 'quote', 'user_quote_interaction'];
    echo "📊 Jumlah data per tabel:\n";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   $table: " . $result['total'] . "\n";
        } catch (PDOException $e) {
            echo "   $table: ⚠️  Tabel tidak ada atau error\n";
        }
    }

    echo "\n✅ Import database selesai!\n\n";

    echo "📝 Langkah selanjutnya:\n";
    echo "   1. Clear cache: php bin/console cache:clear\n";
    echo "   2. Test login: http://localhost/gembira/public/\n";

} catch (PDOException $e) {
    die("\n❌ Error: " . $e->getMessage() . "\n");
}
