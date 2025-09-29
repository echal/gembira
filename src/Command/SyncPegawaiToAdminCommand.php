<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Pegawai;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:sync-pegawai-to-admin',
    description: 'Sinkronisasi data Pegawai ke tabel Admin dengan role Pegawai',
)]
class SyncPegawaiToAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sinkronisasi Data Pegawai ke Admin');

        // Ambil semua pegawai yang aktif
        $pegawaiList = $this->entityManager->getRepository(Pegawai::class)
            ->findBy(['statusKepegawaian' => 'aktif']);

        $synced = 0;
        $skipped = 0;

        foreach ($pegawaiList as $pegawai) {
            // Cek apakah pegawai sudah ada di tabel admin
            $existingAdmin = $this->entityManager->getRepository(Admin::class)
                ->findOneBy(['nip' => $pegawai->getNip()]);

            if ($existingAdmin) {
                $io->text("Pegawai {$pegawai->getNama()} (NIP: {$pegawai->getNip()}) sudah ada di tabel admin - dilewati");
                $skipped++;
                continue;
            }

            // Cek apakah email sudah digunakan
            $existingEmailAdmin = $this->entityManager->getRepository(Admin::class)
                ->findOneBy(['email' => $pegawai->getEmail()]);

            if ($existingEmailAdmin) {
                $io->warning("Email {$pegawai->getEmail()} sudah digunakan oleh admin lain - pegawai {$pegawai->getNama()} dilewati");
                $skipped++;
                continue;
            }

            // Buat username dari NIP
            $username = $pegawai->getNip();

            // Cek apakah username sudah ada
            $existingUsernameAdmin = $this->entityManager->getRepository(Admin::class)
                ->findOneBy(['username' => $username]);

            if ($existingUsernameAdmin) {
                $io->warning("Username {$username} sudah digunakan - pegawai {$pegawai->getNama()} dilewati");
                $skipped++;
                continue;
            }

            // Buat admin baru
            $admin = new Admin();
            $admin->setUsername($username);
            $admin->setNamaLengkap($pegawai->getNama());
            $admin->setEmail($pegawai->getEmail());
            $admin->setRole('pegawai');
            $admin->setStatus('aktif');
            $admin->setNip($pegawai->getNip());
            $admin->setNomorTelepon($pegawai->getNomorTelepon());

            // Set unit kerja jika ada
            if ($pegawai->getUnitKerjaEntity()) {
                $admin->setUnitKerjaEntity($pegawai->getUnitKerjaEntity());
                
                // Set kepala bidang jika unit kerja memiliki kepala bidang
                if ($pegawai->getKepalaBidang()) {
                    $admin->setKepalaBidang($pegawai->getKepalaBidang());
                }
            }

            // Set password default (NIP)
            $hashedPassword = $this->passwordHasher->hashPassword($admin, $pegawai->getNip());
            $admin->setPassword($hashedPassword);

            // Set permissions default untuk pegawai
            $admin->setPermissions(['absensi_pegawai', 'profile_pegawai']);

            $this->entityManager->persist($admin);

            $io->text("✅ Pegawai {$pegawai->getNama()} (NIP: {$pegawai->getNip()}) berhasil disinkronisasi");
            $synced++;
        }

        $this->entityManager->flush();

        $io->success("Sinkronisasi selesai!");
        $io->table(
            ['Status', 'Jumlah'],
            [
                ['Berhasil disinkronisasi', $synced],
                ['Dilewati', $skipped],
                ['Total pegawai aktif', count($pegawaiList)]
            ]
        );

        if ($synced > 0) {
            $io->note('Password default untuk semua pegawai adalah NIP masing-masing. Silakan informasikan kepada pegawai untuk mengganti password setelah login pertama.');
        }

        return Command::SUCCESS;
    }
}