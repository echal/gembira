<?php
/**
 * Script untuk mengimport database production ke localhost dengan aman
 * Mengatasi error umum saat import database
 */

echo "==================================================\n";
echo "IMPORT DATABASE PRODUCTION KE LOCALHOST\n";
echo "==================================================\n\n";

// Konfigurasi
$sqlFile = __DIR__ . '/backup_production.sql'; // Sesuaikan nama file backup Anda

// Parse .env.local untuk mendapatkan DATABASE_URL
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

echo "📊 Konfigurasi Database Localhost:\n";
echo "   Host: $dbHost\n";
echo "   Port: $dbPort\n";
echo "   Database: $dbName\n";
echo "   User: $dbUser\n\n";

// Cek apakah file SQL ada
if (!isset($argv[1])) {
    echo "⚠️  File SQL tidak dispesifikasikan.\n";
    echo "Usage: php import_production_db.php [path_to_sql_file]\n\n";
    echo "Contoh:\n";
    echo "  php import_production_db.php backup_production.sql\n";
    echo "  php import_production_db.php C:\\Users\\Downloads\\gembira_backup.sql\n\n";
    exit(1);
}

$sqlFile = $argv[1];

if (!file_exists($sqlFile)) {
    die("❌ Error: File SQL tidak ditemukan: $sqlFile\n");
}

$fileSize = filesize($sqlFile);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);
echo "📁 File SQL: $sqlFile\n";
echo "   Size: $fileSizeMB MB\n\n";

// Konfirmasi
echo "⚠️  PERINGATAN:\n";
echo "   Import ini akan menghapus semua data di database '$dbName' yang ada!\n";
echo "   Pastikan Anda sudah backup database localhost jika diperlukan.\n\n";
echo "Lanjutkan import? (yes/no): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$confirmation = trim(strtolower($line));
fclose($handle);

if ($confirmation !== 'yes') {
    echo "\n❌ Import dibatalkan.\n";
    exit(0);
}

echo "\n🔄 Memulai import database...\n\n";

// Method 1: Menggunakan mysql command (lebih cepat dan reliable)
$mysqlCmd = "mysql";

// Cek apakah mysql command tersedia
exec("$mysqlCmd --version 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Menggunakan MySQL command line client\n\n";

    // Buat command untuk import
    $password = $dbPass ? "-p$dbPass" : "";
    $command = "$mysqlCmd -u $dbUser $password -h $dbHost -P $dbPort $dbName < \"$sqlFile\" 2>&1";

    echo "Executing import... (ini mungkin memakan waktu beberapa menit)\n";

    $startTime = microtime(true);

    // Execute command
    exec($command, $output, $returnCode);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    if ($returnCode === 0) {
        echo "\n✅ Import berhasil! ($duration detik)\n\n";
    } else {
        echo "\n❌ Import gagal!\n";
        echo "Error output:\n";
        echo implode("\n", $output) . "\n\n";

        echo "💡 Coba gunakan Method 2 (PHP PDO) dengan menjalankan:\n";
        echo "   php import_production_db_pdo.php \"$sqlFile\"\n";
        exit(1);
    }
} else {
    echo "⚠️  MySQL command tidak ditemukan.\n";
    echo "   Gunakan Method 2 dengan menjalankan:\n";
    echo "   php import_production_db_pdo.php \"$sqlFile\"\n";
    exit(1);
}

// Verifikasi hasil import
echo "🔍 Verifikasi hasil import...\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Cek beberapa tabel penting
    $tables = ['pegawai', 'admin', 'absensi', 'quote', 'user_quote_interaction'];

    echo "\n📊 Jumlah data per tabel:\n";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   $table: " . $result['total'] . "\n";
        } catch (PDOException $e) {
            echo "   $table: ⚠️  Error (tabel mungkin tidak ada)\n";
        }
    }

    echo "\n✅ Verifikasi selesai!\n\n";

    echo "📝 Langkah selanjutnya:\n";
    echo "   1. Clear cache: php bin/console cache:clear\n";
    echo "   2. Jalankan script cleanup jika diperlukan:\n";
    echo "      - php cleanup_admin_pegawai_role.php\n";
    echo "      - php delete_invalid_quotes_simple.php\n";
    echo "   3. Test login di browser: http://localhost/gembira/public/\n";

} catch (PDOException $e) {
    echo "\n⚠️  Tidak bisa verifikasi database: " . $e->getMessage() . "\n";
}
