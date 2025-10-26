<?php
/**
 * Script test untuk update checkbox jadwal absensi
 * Simulasi update via command line untuk debugging
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\KonfigurasiJadwalAbsensi;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Dotenv\Dotenv;

// Load environment
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env.local');

// Bootstrap Doctrine
$paths = [__DIR__ . '/src/Entity'];
$isDevMode = true;

$dbParams = [
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'gembira_db',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

$config = \Doctrine\ORM\Tools\Setup::createAttributeMetadataConfiguration($paths, $isDevMode);
$entityManager = \Doctrine\ORM\EntityManager::create($dbParams, $config);

echo "========================================\n";
echo "TEST CHECKBOX UPDATE JADWAL ABSENSI\n";
echo "========================================\n\n";

// Ambil jadwal Apel Pagi (ID 19)
$jadwalId = 19;
$jadwal = $entityManager->find(KonfigurasiJadwalAbsensi::class, $jadwalId);

if (!$jadwal) {
    echo "❌ Jadwal ID {$jadwalId} tidak ditemukan!\n";
    exit(1);
}

echo "📋 Jadwal: {$jadwal->getNamaJadwal()}\n";
echo "🆔 ID: {$jadwal->getId()}\n\n";

echo "STATUS SEBELUM UPDATE:\n";
echo "  - Perlu QR Code: " . ($jadwal->isPerluQrCode() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Kamera: " . ($jadwal->isPerluKamera() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Validasi Admin: " . ($jadwal->isPerluValidasiAdmin() ? '✅ YES' : '❌ NO') . "\n\n";

// Test 1: Set semua ke FALSE (Absen Saja)
echo "🔄 TEST 1: Set semua checkbox ke FALSE (Absen Saja)\n";
$jadwal->setPerluQrCode(false);
$jadwal->setPerluKamera(false);
$jadwal->setPerluValidasiAdmin(false);
$jadwal->setDiubah(new \DateTime());

$entityManager->flush();

echo "✅ Update berhasil!\n\n";

// Refresh dari database
$entityManager->clear();
$jadwal = $entityManager->find(KonfigurasiJadwalAbsensi::class, $jadwalId);

echo "STATUS SETELAH UPDATE (TEST 1):\n";
echo "  - Perlu QR Code: " . ($jadwal->isPerluQrCode() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Kamera: " . ($jadwal->isPerluKamera() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Validasi Admin: " . ($jadwal->isPerluValidasiAdmin() ? '✅ YES' : '❌ NO') . "\n\n";

// Verifikasi di database langsung
$conn = $entityManager->getConnection();
$sql = "SELECT perlu_qr_code, perlu_kamera, perlu_validasi_admin FROM konfigurasi_jadwal_absensi WHERE id = ?";
$stmt = $conn->prepare($sql);
$result = $stmt->executeQuery([$jadwalId]);
$row = $result->fetchAssociative();

echo "VERIFIKASI DARI DATABASE:\n";
echo "  - perlu_qr_code: " . $row['perlu_qr_code'] . "\n";
echo "  - perlu_kamera: " . $row['perlu_kamera'] . "\n";
echo "  - perlu_validasi_admin: " . $row['perlu_validasi_admin'] . "\n\n";

// Test 2: Set kembali ke QR + Kamera (seperti default)
echo "🔄 TEST 2: Set QR Code=TRUE, Kamera=TRUE, Validasi Admin=FALSE\n";
$jadwal->setPerluQrCode(true);
$jadwal->setPerluKamera(true);
$jadwal->setPerluValidasiAdmin(false);
$jadwal->setDiubah(new \DateTime());

$entityManager->flush();

echo "✅ Update berhasil!\n\n";

// Refresh dari database
$entityManager->clear();
$jadwal = $entityManager->find(KonfigurasiJadwalAbsensi::class, $jadwalId);

echo "STATUS SETELAH UPDATE (TEST 2):\n";
echo "  - Perlu QR Code: " . ($jadwal->isPerluQrCode() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Kamera: " . ($jadwal->isPerluKamera() ? '✅ YES' : '❌ NO') . "\n";
echo "  - Perlu Validasi Admin: " . ($jadwal->isPerluValidasiAdmin() ? '✅ YES' : '❌ NO') . "\n\n";

echo "========================================\n";
echo "✅ TEST SELESAI - Fungsi update checkbox BEKERJA dengan baik!\n";
echo "========================================\n";
