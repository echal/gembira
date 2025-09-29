<?php

namespace App\Command;

use App\Entity\Pegawai;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Membuat user pegawai baru untuk testing aplikasi Gembira',
)]
class CreateUserCommand extends Command
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
            ->addArgument('nip', InputArgument::REQUIRED, 'NIP pegawai')
            ->addArgument('nama', InputArgument::REQUIRED, 'Nama lengkap pegawai')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password login (default: sama dengan NIP)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $nip = $input->getArgument('nip');
        $nama = $input->getArgument('nama');
        $password = $input->getArgument('password') ?: $nip; // Default password = NIP

        // Cek apakah NIP sudah ada
        $existingPegawai = $this->entityManager->getRepository(Pegawai::class)->findOneBy(['nip' => $nip]);
        if ($existingPegawai) {
            $io->error('Pegawai dengan NIP ' . $nip . ' sudah ada!');
            return Command::FAILURE;
        }

        // Buat pegawai baru
        $pegawai = new Pegawai();
        $pegawai->setNip($nip);
        $pegawai->setNama($nama);
        $pegawai->setEmail($nip . '@example.com'); // Email dummy
        $pegawai->setJabatan('Staff');
        $pegawai->setUnitKerja('Bagian Umum');
        $pegawai->setTanggalMulaiKerja(new \DateTime());
        $pegawai->setStatusKepegawaian('aktif');
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($pegawai, $password);
        $pegawai->setPassword($hashedPassword);

        // Simpan ke database
        $this->entityManager->persist($pegawai);
        $this->entityManager->flush();

        $io->success('Pegawai berhasil dibuat!');
        $io->table(['Field', 'Value'], [
            ['NIP', $nip],
            ['Nama', $nama],
            ['Email', $pegawai->getEmail()],
            ['Jabatan', $pegawai->getJabatan()],
            ['Unit Kerja', $pegawai->getUnitKerja()],
            ['Status', $pegawai->getStatusKepegawaian()],
        ]);

        return Command::SUCCESS;
    }
}