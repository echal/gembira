<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Pegawai;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command untuk migrasi data Admin dengan role 'pegawai' ke table Pegawai
 *
 * Command ini akan:
 * 1. Mencari semua record di table admin dengan role = 'pegawai'
 * 2. Membuat record baru di table pegawai dengan data yang sesuai
 * 3. Menghapus record lama di table admin (opsional dengan flag --delete-admin)
 *
 * Tujuan: Memastikan pegawai login sebagai instance Pegawai, bukan Admin
 * sehingga diarahkan ke dashboard pegawai, bukan dashboard admin.
 */
#[AsCommand(
    name: 'app:migrate-admin-pegawai',
    description: 'Migrasi data Admin dengan role pegawai ke table Pegawai'
)]
class MigrateAdminPegawaiCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('delete-admin', null, InputOption::VALUE_NONE, 'Hapus record Admin setelah migrasi berhasil')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview saja, tidak melakukan perubahan database')
            ->setHelp('
Command ini akan memindahkan data Admin dengan role "pegawai" ke table Pegawai.

Setelah migrasi:
- Pegawai akan login sebagai instance Pegawai dengan role ROLE_USER
- Pegawai akan diarahkan ke dashboard pegawai (/dashboard)
- Admin tetap login sebagai instance Admin dengan role ROLE_ADMIN
- Admin akan diarahkan ke dashboard admin (/admin/dashboard)

Contoh penggunaan:
  php bin/console app:migrate-admin-pegawai                 # Preview migrasi
  php bin/console app:migrate-admin-pegawai --dry-run       # Preview saja
  php bin/console app:migrate-admin-pegawai --delete-admin  # Migrasi + hapus Admin
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $deleteAdmin = $input->getOption('delete-admin');

        $io->title('Migrasi Admin Role Pegawai ke Table Pegawai');

        if ($isDryRun) {
            $io->note('Mode DRY RUN - Tidak ada perubahan yang akan disimpan ke database');
        }

        try {
            // Ambil semua admin dengan role pegawai
            $adminPegawai = $this->entityManager->getRepository(Admin::class)
                ->findBy(['role' => 'pegawai']);

            $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);

            $io->section('Data Admin dengan Role Pegawai yang Ditemukan');
            $io->text(sprintf('Total: %d record', count($adminPegawai)));

            if (empty($adminPegawai)) {
                $io->success('Tidak ada data Admin dengan role pegawai yang perlu dimigrasi.');
                return Command::SUCCESS;
            }

            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Preview data
            $io->table(
                ['ID', 'Username', 'Nama', 'Email', 'NIP', 'Status'],
                array_map(function(Admin $admin) {
                    return [
                        $admin->getId(),
                        $admin->getUsername(),
                        $admin->getNamaLengkap(),
                        $admin->getEmail() ?? '-',
                        $admin->getNip() ?? '-',
                        $admin->getStatus()
                    ];
                }, $adminPegawai)
            );

            if ($isDryRun) {
                $io->warning('Ini adalah preview saja. Jalankan tanpa --dry-run untuk melakukan migrasi sesungguhnya.');
                return Command::SUCCESS;
            }

            $io->section('Memulai Proses Migrasi');

            foreach ($adminPegawai as $admin) {
                try {
                    $io->text(sprintf('Memproses: %s (%s)', $admin->getNamaLengkap(), $admin->getUsername()));

                    // Check apakah pegawai sudah ada (by NIP atau email)
                    $existingPegawai = null;
                    if ($admin->getNip()) {
                        $existingPegawai = $pegawaiRepo->findOneBy(['nip' => $admin->getNip()]);
                    }
                    if (!$existingPegawai && $admin->getEmail()) {
                        $existingPegawai = $pegawaiRepo->findOneBy(['email' => $admin->getEmail()]);
                    }

                    if ($existingPegawai) {
                        $io->text('  → Skip: Pegawai sudah ada di table pegawai');
                        $skippedCount++;
                        continue;
                    }

                    // Buat pegawai baru
                    $pegawai = new Pegawai();
                    $pegawai->setNip($admin->getNip() ?: $admin->getUsername());
                    $pegawai->setNama($admin->getNamaLengkap());
                    $pegawai->setEmail($admin->getEmail());
                    $pegawai->setPassword($admin->getPassword()); // Copy encrypted password
                    $pegawai->setJabatan('Pegawai'); // Default jabatan
                    $pegawai->setStatusKepegawaian($admin->getStatus() === 'aktif' ? 'aktif' : 'nonaktif');
                    $pegawai->setTanggalMulaiKerja(new \DateTime()); // Default hari ini
                    $pegawai->setNomorTelepon($admin->getNomorTelepon());

                    // Set roles untuk pegawai
                    $pegawai->setRoles(['ROLE_USER']);

                    // Copy relasi unit kerja jika ada
                    if ($admin->getUnitKerjaEntity()) {
                        $pegawai->setUnitKerjaEntity($admin->getUnitKerjaEntity());
                    }

                    // Copy timestamps
                    $pegawai->setCreatedAt($admin->getCreatedAt() ?: new \DateTime());
                    $pegawai->setUpdatedAt(new \DateTime());

                    $this->entityManager->persist($pegawai);

                    // Hapus admin lama jika diminta
                    if ($deleteAdmin) {
                        $this->entityManager->remove($admin);
                        $io->text('  → Berhasil: Pegawai dibuat, Admin dihapus');
                    } else {
                        $io->text('  → Berhasil: Pegawai dibuat, Admin tetap ada');
                    }

                    $migratedCount++;

                } catch (\Exception $e) {
                    $errorMsg = sprintf('Error migrasi %s: %s', $admin->getNamaLengkap(), $e->getMessage());
                    $errors[] = $errorMsg;
                    $io->error('  → ' . $errorMsg);
                    $errorCount++;
                }
            }

            // Flush semua perubahan
            $this->entityManager->flush();

            // Summary
            $io->section('Hasil Migrasi');
            $io->success(sprintf('Migrasi selesai! %d berhasil, %d di-skip, %d error',
                $migratedCount, $skippedCount, $errorCount));

            if (!empty($errors)) {
                $io->section('Error yang Terjadi');
                foreach ($errors as $error) {
                    $io->text('• ' . $error);
                }
            }

            // Rekomendasi
            $io->section('Langkah Selanjutnya');
            $io->text([
                '1. Test login pegawai - pastikan diarahkan ke dashboard pegawai',
                '2. Test login admin - pastikan diarahkan ke dashboard admin',
                '3. Import pegawai baru dari Excel akan otomatis membuat instance Pegawai',
                '4. Hapus record Admin lama jika migrasi berhasil (gunakan --delete-admin)'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error fatal: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}