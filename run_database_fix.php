<?php
/**
 * Script untuk run semua database fixes
 * Jalankan: php run_database_fix.php
 */

echo "\n🔧 RUNNING DATABASE FIXES...\n";
echo str_repeat("=", 60) . "\n\n";

$commands = [
    // 1. Fix tabel absensi
    [
        'name' => 'Add latitude & longitude to absensi',
        'sql' => "ALTER TABLE absensi
                  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL,
                  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL"
    ],

    // 2. Fix tabel pegawai - kolom dasar
    [
        'name' => 'Add photo to pegawai',
        'sql' => "ALTER TABLE pegawai
                  ADD COLUMN IF NOT EXISTS photo VARCHAR(255) NULL"
    ],

    [
        'name' => 'Add tracking fields to pegawai',
        'sql' => "ALTER TABLE pegawai
                  ADD COLUMN IF NOT EXISTS tanda_tangan VARCHAR(255) NULL,
                  ADD COLUMN IF NOT EXISTS tanda_tangan_uploaded_at DATETIME NULL,
                  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL,
                  ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL"
    ],

    // 3. Fix tabel pegawai - kolom XP
    [
        'name' => 'Add XP fields to pegawai',
        'sql' => "ALTER TABLE pegawai
                  ADD COLUMN IF NOT EXISTS total_xp INT DEFAULT 0,
                  ADD COLUMN IF NOT EXISTS current_level INT DEFAULT 1,
                  ADD COLUMN IF NOT EXISTS current_badge VARCHAR(10) DEFAULT '🌱',
                  ADD COLUMN IF NOT EXISTS level_title VARCHAR(50) DEFAULT 'Pemula Ikhlas'"
    ],

    // 4. Fix tabel konfigurasi_jadwal_absensi
    [
        'name' => 'Add perlu_validasi_admin to konfigurasi_jadwal_absensi',
        'sql' => "ALTER TABLE konfigurasi_jadwal_absensi
                  ADD COLUMN IF NOT EXISTS perlu_validasi_admin TINYINT(1) DEFAULT 0"
    ],
];

$success = 0;
$failed = 0;

foreach ($commands as $cmd) {
    echo "▶ " . $cmd['name'] . "...\n";

    $escapedSql = escapeshellarg($cmd['sql']);
    $output = shell_exec("php bin/console dbal:run-sql $escapedSql 2>&1");

    if (strpos($output, 'OK') !== false || strpos($output, '0 rows affected') !== false) {
        echo "  ✅ Success\n";
        $success++;
    } else {
        echo "  ⚠️  Warning: $output\n";
        $failed++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 SUMMARY:\n";
echo "  ✅ Successful: $success\n";
echo "  ⚠️  Failed/Warning: $failed\n";
echo "\n";

if ($failed == 0) {
    echo "🎉 ALL FIXES APPLIED SUCCESSFULLY!\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Clear cache: php bin/console cache:clear\n";
    echo "2. Test login: http://localhost/gembira/login\n";
    echo "3. Verify: php check_missing_columns.php\n";
} else {
    echo "⚠️  Some fixes had warnings. Check output above.\n";
    echo "Try running manually via phpMyAdmin if needed.\n";
}

echo "\n";
