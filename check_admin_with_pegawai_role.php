<?php
/**
 * Script untuk mengecek user di table Admin yang memiliki role='pegawai'
 * User ini seharusnya dipindahkan ke table Pegawai
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Load .env
(new Dotenv())->bootEnv(__DIR__ . '/.env');

// Boot Symfony kernel
$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

// Get entity manager
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

echo "=================================================\n";
echo "CHECKING: Admin dengan role='pegawai' (DATA SALAH!)\n";
echo "=================================================\n\n";

// Cek admin dengan role pegawai
$adminsWithPegawaiRole = $entityManager->getRepository(\App\Entity\Admin::class)
    ->findBy(['role' => 'pegawai']);

echo "Total Admin dengan role='pegawai': " . count($adminsWithPegawaiRole) . "\n\n";

if (count($adminsWithPegawaiRole) > 0) {
    echo "⚠️  DITEMUKAN DATA YANG SALAH:\n";
    echo "User berikut ada di table 'admin' dengan role='pegawai'\n";
    echo "Seharusnya user ini HANYA ada di table 'pegawai'\n\n";

    foreach ($adminsWithPegawaiRole as $admin) {
        echo "-------------------------------------------\n";
        echo "ID: " . $admin->getId() . "\n";
        echo "Username: " . $admin->getUsername() . "\n";
        echo "Nama: " . $admin->getNamaLengkap() . "\n";
        echo "NIP: " . ($admin->getNip() ?? 'NULL') . "\n";
        echo "Role: " . $admin->getRole() . " ⚠️\n";
        echo "Email: " . ($admin->getEmail() ?? 'NULL') . "\n";

        // Cek apakah sudah ada di table pegawai
        if ($admin->getNip()) {
            $pegawai = $entityManager->getRepository(\App\Entity\Pegawai::class)
                ->findOneBy(['nip' => $admin->getNip()]);

            if ($pegawai) {
                echo "\n✅ User ini SUDAH ADA di table pegawai (ID: " . $pegawai->getId() . ")\n";
                echo "   → Bisa DIHAPUS dari table admin\n";
            } else {
                echo "\n❌ User ini BELUM ADA di table pegawai\n";
                echo "   → Perlu DIPINDAHKAN ke table pegawai\n";
            }
        }
        echo "\n";
    }

    echo "=================================================\n";
    echo "REKOMENDASI:\n";
    echo "=================================================\n";
    echo "❌ HAPUS semua user dengan role='pegawai' dari table 'admin'\n";
    echo "✅ PASTIKAN semua pegawai HANYA ada di table 'pegawai'\n";
    echo "\n";
    echo "Struktur yang BENAR:\n";
    echo "1. Table 'admin' → Hanya untuk Super Admin & Admin (role='super_admin' atau 'admin')\n";
    echo "2. Table 'pegawai' → Untuk semua pegawai\n";
    echo "\n";
    echo "Login behavior:\n";
    echo "- Admin (role='admin' atau 'super_admin') → /admin/dashboard\n";
    echo "- Pegawai (entity Pegawai) → /absensi\n";
    echo "\n";
    echo "=================================================\n";
    echo "SOLUSI:\n";
    echo "=================================================\n";
    echo "Jalankan command berikut untuk membersihkan data:\n";
    echo "\n";
    echo "php bin/console dbal:run-sql \"DELETE FROM admin WHERE role='pegawai'\"\n";
    echo "\n";
    echo "ATAU jika ingin migrasi otomatis:\n";
    echo "php migrate_pegawai_role_to_pegawai_table.php\n";
    echo "=================================================\n";

} else {
    echo "✅ BAGUS! Tidak ada admin dengan role='pegawai'\n";
    echo "✅ Struktur data sudah benar\n";
}
