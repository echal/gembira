<?php
/**
 * Import database langsung tanpa konfirmasi
 * Khusus untuk automation
 */

echo "==================================================\n";
echo "IMPORT DATABASE (AUTO MODE)\n";
echo "==================================================\n\n";

// Parse .env.local
$envFile = __DIR__ . '/.env.local';
if (!file_exists($envFile)) {
    die("❌ Error: File .env.local tidak ditemukan!\n");
}

$envContent = file_get_contents($envFile);
preg_match('/DATABASE_URL="mysql:\/\/(.+?):(.*)@(.+?):(\d+)\/(.+?)\?/', $envContent, $matches);

if (empty($matches)) {
    die("❌ Error: DATABASE_URL tidak ditemukan!\n");
}

$dbUser = $matches[1];
$dbPass = $matches[2];
$dbHost = $matches[3];
$dbPort = $matches[4];
$dbName = $matches[5];

$sqlFile = $argv[1] ?? 'gaspulco_gembira.sql';

if (!file_exists($sqlFile)) {
    die("❌ Error: File SQL tidak ditemukan: $sqlFile\n");
}

$fileSize = filesize($sqlFile);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);
echo "📁 File: $sqlFile ($fileSizeMB MB)\n";
echo "📊 Target: $dbName@$dbHost\n\n";

echo "🔄 Membaca file SQL...\n";
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die("❌ Error: Tidak bisa membaca file SQL\n");
}

echo "🔧 Membersihkan SQL...\n";

// Clean up SQL
$sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i', '', $sql);
$sql = preg_replace('/DATABASE\s+`gaspulco_gembira`/i', "DATABASE `$dbName`", $sql);
$sql = preg_replace('/USE\s+`gaspulco_gembira`/i', "USE `$dbName`", $sql);
$sql = str_replace('utf8mb3', 'utf8mb4', $sql);

$sqlPrefix = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sqlPrefix .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sqlPrefix .= "SET time_zone = '+00:00';\n\n";
$sql = $sqlPrefix . $sql;

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

    echo "🔄 Drop & create database...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");

    echo "🔄 Executing SQL statements...\n";

    // Split and execute
    $statements = array_filter(
        array_map('trim', explode(";\n", $sql)),
        function($stmt) {
            return !empty($stmt) && $stmt !== '' &&
                   strpos(trim($stmt), '--') !== 0 &&
                   strpos(trim($stmt), '/*') !== 0;
        }
    );

    $total = count($statements);
    $success = 0;
    $errors = 0;

    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement . ';');
            $success++;

            if ($success % 50 === 0) {
                $pct = round(($success / $total) * 100, 1);
                echo "   Progress: $pct% ($success/$total)\n";
            }
        } catch (PDOException $e) {
            $errors++;
            if ($errors <= 5) {
                echo "   ⚠️  Error: " . substr($e->getMessage(), 0, 100) . "\n";
            }
        }
    }

    echo "\n✅ Import selesai!\n";
    echo "   Success: $success\n";
    echo "   Errors: $errors\n\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // Verify
    echo "🔍 Verifikasi:\n";
    $tables = ['pegawai', 'admin', 'absensi', 'quote', 'user_quote_interaction'];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   ✅ $table: " . $result['total'] . " rows\n";
        } catch (PDOException $e) {
            echo "   ⚠️  $table: tidak ada\n";
        }
    }

    echo "\n🎉 Database berhasil diimport!\n\n";
    echo "📝 Langkah selanjutnya:\n";
    echo "   php bin/console cache:clear\n";

} catch (PDOException $e) {
    die("\n❌ Error: " . $e->getMessage() . "\n");
}
