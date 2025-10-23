<?php
/**
 * Script untuk mengecek data user Faisal Kasim
 * Untuk debugging redirect issue
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
echo "CHECKING USER: Faisal Kasim\n";
echo "=================================================\n\n";

// Check di table Admin
echo "1. Checking table 'admin'...\n";
$adminRepo = $entityManager->getRepository(\App\Entity\Admin::class);
$admins = $adminRepo->createQueryBuilder('a')
    ->where('a.namaLengkap LIKE :nama OR a.username LIKE :nama')
    ->setParameter('nama', '%Faisal%')
    ->getQuery()
    ->getResult();

if (count($admins) > 0) {
    foreach ($admins as $admin) {
        echo "   ✅ Found in Admin table:\n";
        echo "      - ID: " . $admin->getId() . "\n";
        echo "      - Username: " . $admin->getUsername() . "\n";
        echo "      - Nama: " . $admin->getNamaLengkap() . "\n";
        echo "      - Role: " . $admin->getRole() . " ⚠️ IMPORTANT!\n";
        echo "      - Status: " . $admin->getStatus() . "\n";
        echo "      - NIP: " . ($admin->getNip() ?? 'NULL') . "\n";
        echo "      - Entity Class: " . get_class($admin) . "\n";
        echo "\n";

        // Cek getRoles() Symfony
        echo "      - Symfony Roles: " . implode(', ', $admin->getRoles()) . "\n";
        echo "\n";

        // Predict redirect
        if ($admin->getRole() === 'pegawai') {
            echo "      ✅ EXPECTED REDIRECT: /absensi\n";
        } else {
            echo "      ✅ EXPECTED REDIRECT: /admin/dashboard\n";
        }
        echo "\n";
    }
} else {
    echo "   ❌ NOT found in Admin table\n\n";
}

// Check di table Pegawai
echo "2. Checking table 'pegawai'...\n";
$pegawaiRepo = $entityManager->getRepository(\App\Entity\Pegawai::class);
$pegawais = $pegawaiRepo->createQueryBuilder('p')
    ->where('p.nama LIKE :nama OR p.nip LIKE :nama')
    ->setParameter('nama', '%Faisal%')
    ->getQuery()
    ->getResult();

if (count($pegawais) > 0) {
    foreach ($pegawais as $pegawai) {
        echo "   ✅ Found in Pegawai table:\n";
        echo "      - ID: " . $pegawai->getId() . "\n";
        echo "      - NIP: " . $pegawai->getNip() . "\n";
        echo "      - Nama: " . $pegawai->getNama() . "\n";
        echo "      - Status: " . $pegawai->getStatusKepegawaian() . "\n";
        echo "      - Entity Class: " . get_class($pegawai) . "\n";
        echo "\n";

        // Cek getRoles() Symfony
        echo "      - Symfony Roles: " . implode(', ', $pegawai->getRoles()) . "\n";
        echo "\n";

        echo "      ✅ EXPECTED REDIRECT: /absensi\n";
        echo "\n";
    }
} else {
    echo "   ❌ NOT found in Pegawai table\n\n";
}

echo "=================================================\n";
echo "DIAGNOSIS:\n";
echo "=================================================\n";
if (count($admins) > 0) {
    $admin = $admins[0];
    if ($admin->getRole() === 'pegawai') {
        echo "✅ User 'Faisal Kasim' memiliki role='pegawai'\n";
        echo "✅ Seharusnya diarahkan ke: /absensi\n";
        echo "\n";
        echo "📝 LoginSuccessHandler.php akan:\n";
        echo "   1. Cek: \$user instanceof Admin → TRUE\n";
        echo "   2. Cek: \$user->getRole() === 'pegawai' → TRUE\n";
        echo "   3. Redirect ke: app_absensi_dashboard (/absensi)\n";
    } else {
        echo "⚠️  User 'Faisal Kasim' memiliki role='" . $admin->getRole() . "'\n";
        echo "✅ Seharusnya diarahkan ke: /admin/dashboard\n";
    }
} elseif (count($pegawais) > 0) {
    echo "✅ User 'Faisal Kasim' adalah entity Pegawai\n";
    echo "✅ Seharusnya diarahkan ke: /absensi\n";
} else {
    echo "❌ User 'Faisal Kasim' tidak ditemukan!\n";
}

echo "\n";
echo "=================================================\n";
echo "NEXT STEPS:\n";
echo "=================================================\n";
echo "1. ✅ Cache sudah di-clear: php bin/console cache:clear\n";
echo "2. 🔄 Silakan logout dari admin panel\n";
echo "3. 🔄 Login ulang dengan user Faisal Kasim\n";
echo "4. ✅ Seharusnya sekarang diarahkan ke /absensi\n";
echo "\n";
echo "Jika masih ke /admin/dashboard:\n";
echo "- Cek apakah ada session yang masih tersimpan\n";
echo "- Coba clear browser cache / gunakan incognito mode\n";
echo "=================================================\n";
