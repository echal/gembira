<?php
/**
 * Script untuk backup database SEBELUM deploy
 * JALANKAN SCRIPT INI DI PRODUCTION sebelum deploy!
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env
(new Dotenv())->bootEnv(__DIR__ . '/.env');

echo "=================================================\n";
echo "BACKUP DATABASE BEFORE DEPLOYMENT\n";
echo "=================================================\n\n";

// Parse database URL
$databaseUrl = $_ENV['DATABASE_URL'] ?? '';

if (empty($databaseUrl)) {
    echo "❌ ERROR: DATABASE_URL tidak ditemukan di .env\n";
    exit(1);
}

// Parse database connection
preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/(.+)$/', $databaseUrl, $matches);

if (count($matches) < 6) {
    echo "❌ ERROR: Format DATABASE_URL tidak valid\n";
    echo "Format: mysql://user:password@host:port/database\n";
    exit(1);
}

$dbUser = $matches[1];
$dbPass = $matches[2];
$dbHost = $matches[3];
$dbPort = $matches[4];
$dbName = $matches[5];

echo "Database Information:\n";
echo "-------------------------------------------\n";
echo "Host: $dbHost:$dbPort\n";
echo "Database: $dbName\n";
echo "User: $dbUser\n";
echo "\n";

// Create backup directory
$backupDir = __DIR__ . '/var/backup';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "✅ Created backup directory: $backupDir\n";
}

// Generate backup filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "gembira_backup_before_deploy_{$timestamp}.sql";
$filepath = $backupDir . '/' . $filename;

echo "Backup File:\n";
echo "-------------------------------------------\n";
echo "Location: $filepath\n";
echo "\n";

// Build mysqldump command
$command = sprintf(
    'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --routines --triggers --add-drop-table --complete-insert %s > %s 2>&1',
    escapeshellarg($dbHost),
    $dbPort,
    escapeshellarg($dbUser),
    escapeshellarg($dbPass),
    escapeshellarg($dbName),
    escapeshellarg($filepath)
);

echo "🔄 Creating backup...\n";
echo "Please wait...\n\n";

// Execute backup
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    echo "❌ BACKUP FAILED!\n";
    echo "Error output:\n";
    echo implode("\n", $output);
    echo "\n";
    exit(1);
}

// Check if file was created and has content
if (!file_exists($filepath)) {
    echo "❌ ERROR: Backup file tidak terbuat!\n";
    exit(1);
}

$filesize = filesize($filepath);
if ($filesize === 0) {
    echo "❌ ERROR: Backup file kosong!\n";
    unlink($filepath);
    exit(1);
}

// Success!
echo "=================================================\n";
echo "✅ BACKUP SUCCESSFUL!\n";
echo "=================================================\n\n";

echo "Backup Details:\n";
echo "-------------------------------------------\n";
echo "File: $filename\n";
echo "Size: " . number_format($filesize / 1024, 2) . " KB\n";
echo "Location: $filepath\n";
echo "\n";

// Count tables in backup
$sqlContent = file_get_contents($filepath);
preg_match_all('/CREATE TABLE/', $sqlContent, $matches);
$tableCount = count($matches[0]);

echo "Backup Contains:\n";
echo "-------------------------------------------\n";
echo "Tables: ~$tableCount tables\n";
echo "Size: " . number_format($filesize / 1024 / 1024, 2) . " MB\n";
echo "\n";

// List recent backups
echo "Recent Backups:\n";
echo "-------------------------------------------\n";
$backupFiles = glob($backupDir . '/gembira_backup_*.sql');
rsort($backupFiles); // Sort by newest first

if (count($backupFiles) > 0) {
    $count = 0;
    foreach ($backupFiles as $file) {
        if ($count >= 5) break; // Show only last 5
        $fname = basename($file);
        $fsize = number_format(filesize($file) / 1024 / 1024, 2);
        $ftime = date('Y-m-d H:i:s', filemtime($file));
        echo "  - $fname ($fsize MB) - $ftime\n";
        $count++;
    }
} else {
    echo "  No backups found\n";
}

echo "\n";
echo "=================================================\n";
echo "NEXT STEPS:\n";
echo "=================================================\n";
echo "1. ✅ Database backup created successfully\n";
echo "2. 📥 DOWNLOAD backup file ke komputer lokal:\n";
echo "   - Via cPanel File Manager\n";
echo "   - Download: $filename\n";
echo "   - Simpan di tempat AMAN!\n";
echo "\n";
echo "3. 🚀 Sekarang AMAN untuk deploy:\n";
echo "   - Upload file code yang berubah\n";
echo "   - JANGAN upload/import database\n";
echo "   - Clear cache setelah deploy\n";
echo "\n";
echo "4. 🔄 Jika ada masalah, restore dengan:\n";
echo "   - phpMyAdmin → Import\n";
echo "   - Select file: $filename\n";
echo "=================================================\n";

echo "\n";
echo "⚠️  PENTING:\n";
echo "- Download file backup ke lokal sebagai DOUBLE BACKUP\n";
echo "- Jangan hapus file backup di server\n";
echo "- Keep at least 3 latest backups\n";
echo "\n";
