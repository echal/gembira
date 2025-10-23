<?php
/**
 * Script untuk membersihkan data Admin dengan role='pegawai'
 * Menghapus user dari table admin yang sudah ada di table pegawai
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
echo "CLEANUP: Hapus Admin dengan role='pegawai'\n";
echo "=================================================\n\n";

// Cek admin dengan role pegawai
$adminsWithPegawaiRole = $entityManager->getRepository(\App\Entity\Admin::class)
    ->findBy(['role' => 'pegawai']);

echo "Total Admin dengan role='pegawai': " . count($adminsWithPegawaiRole) . "\n\n";

if (count($adminsWithPegawaiRole) === 0) {
    echo "✅ Tidak ada data yang perlu dibersihkan\n";
    exit(0);
}

echo "User yang akan dihapus dari table 'admin':\n";
echo "-------------------------------------------\n";

$canDelete = [];
$cantDelete = [];

foreach ($adminsWithPegawaiRole as $admin) {
    $line = sprintf(
        "- ID: %d, Username: %s, Nama: %s, NIP: %s",
        $admin->getId(),
        $admin->getUsername(),
        $admin->getNamaLengkap(),
        $admin->getNip() ?? 'NULL'
    );

    // Cek apakah sudah ada di table pegawai
    if ($admin->getNip()) {
        $pegawai = $entityManager->getRepository(\App\Entity\Pegawai::class)
            ->findOneBy(['nip' => $admin->getNip()]);

        if ($pegawai) {
            echo "✅ " . $line . "\n";
            echo "   → Sudah ada di table pegawai (ID: " . $pegawai->getId() . ")\n";
            $canDelete[] = $admin;
        } else {
            echo "⚠️  " . $line . "\n";
            echo "   → BELUM ada di table pegawai! SKIP untuk safety\n";
            $cantDelete[] = $admin;
        }
    } else {
        echo "⚠️  " . $line . "\n";
        echo "   → Tidak punya NIP! SKIP untuk safety\n";
        $cantDelete[] = $admin;
    }
}

echo "\n";
echo "=================================================\n";
echo "SUMMARY:\n";
echo "=================================================\n";
echo "✅ Bisa dihapus: " . count($canDelete) . " user\n";
echo "⚠️  Skip (untuk safety): " . count($cantDelete) . " user\n";
echo "\n";

if (count($canDelete) > 0) {
    echo "Apakah Anda yakin ingin menghapus " . count($canDelete) . " user dari table admin? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));

    if (strtolower($line) !== 'yes') {
        echo "\n❌ Dibatalkan oleh user\n";
        exit(0);
    }

    echo "\n🔄 Menghapus data...\n\n";

    $deleted = 0;
    foreach ($canDelete as $admin) {
        try {
            echo "Menghapus: " . $admin->getUsername() . " (" . $admin->getNamaLengkap() . ")... ";
            $entityManager->remove($admin);
            $entityManager->flush();
            echo "✅ Berhasil\n";
            $deleted++;
        } catch (\Exception $e) {
            echo "❌ Gagal: " . $e->getMessage() . "\n";
        }
    }

    echo "\n";
    echo "=================================================\n";
    echo "HASIL:\n";
    echo "=================================================\n";
    echo "✅ Berhasil dihapus: " . $deleted . " user\n";
    echo "✅ Data sudah bersih!\n";
    echo "\n";
    echo "Struktur data sekarang:\n";
    echo "- Table 'admin' → Hanya Super Admin & Admin\n";
    echo "- Table 'pegawai' → Semua pegawai\n";
    echo "\n";
    echo "Login behavior setelah cleanup:\n";
    echo "✅ Admin/Super Admin → /admin/dashboard\n";
    echo "✅ Pegawai (Faisal Kasim, dll) → /absensi\n";
    echo "=================================================\n";
} else {
    echo "⚠️  Tidak ada data yang bisa dihapus dengan aman\n";
}
