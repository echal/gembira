<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Membuat user admin baru untuk aplikasi Gembira',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username admin')
            ->addArgument('nama', InputArgument::REQUIRED, 'Nama lengkap admin')
            ->addArgument('email', InputArgument::REQUIRED, 'Email admin')
            ->addArgument('password', InputArgument::REQUIRED, 'Password login')
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'Set sebagai super admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $username = $input->getArgument('username');
        $nama = $input->getArgument('nama');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $isSuperAdmin = $input->getOption('super-admin');

        // Cek apakah username sudah ada
        $existingAdmin = $this->entityManager->getRepository(Admin::class)->findOneBy(['username' => $username]);
        if ($existingAdmin) {
            $io->error('Admin dengan username ' . $username . ' sudah ada!');
            return Command::FAILURE;
        }

        // Cek apakah email sudah ada
        $existingEmail = $this->entityManager->getRepository(Admin::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            $io->error('Admin dengan email ' . $email . ' sudah ada!');
            return Command::FAILURE;
        }

        // Buat admin baru
        $admin = new Admin();
        $admin->setUsername($username);
        $admin->setNamaLengkap($nama);
        $admin->setEmail($email);
        $admin->setRole($isSuperAdmin ? 'super_admin' : 'admin');
        
        // Default permissions
        $permissions = ['validasi_absensi', 'kelola_jadwal'];
        if ($isSuperAdmin) {
            $permissions = ['kelola_pegawai', 'kelola_jadwal', 'validasi_absensi', 'laporan', 'kelola_admin'];
        }
        $admin->setPermissions($permissions);
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setPassword($hashedPassword);

        // Simpan ke database
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Admin berhasil dibuat!');
        $io->table(['Field', 'Value'], [
            ['Username', $username],
            ['Nama', $nama],
            ['Email', $email],
            ['Role', $admin->getRole()],
            ['Permissions', implode(', ', $permissions)],
            ['Status', $admin->getStatus()],
        ]);

        return Command::SUCCESS;
    }
}