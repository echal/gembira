<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command untuk memperbaiki badge validasi absen
 * Mengubah semua absensi dengan status 'pending' menjadi 'disetujui'
 * sehingga badge validasi hilang dari sidebar
 */
#[AsCommand(
    name: 'app:fix-validation-badge',
    description: 'Memperbaiki badge validasi absen dengan mengubah status pending menjadi disetujui'
)]
class FixValidationBadgeCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔧 Memperbaiki Badge Validasi Absen');

        try {
            // Cek berapa banyak absensi pending
            $absensiRepo = $this->entityManager->getRepository('App\Entity\Absensi');

            $pendingCount = $absensiRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult();

            $io->info("📊 Ditemukan {$pendingCount} absensi dengan status 'pending'");

            if ($pendingCount == 0) {
                $io->success('✅ Tidak ada absensi pending. Badge sudah seharusnya hilang.');
                return Command::SUCCESS;
            }

            // Update semua absensi pending menjadi disetujui
            $updated = $this->entityManager->createQueryBuilder()
                ->update('App\Entity\Absensi', 'a')
                ->set('a.statusValidasi', ':new_status')
                ->where('a.statusValidasi = :old_status')
                ->setParameter('new_status', 'disetujui')
                ->setParameter('old_status', 'pending')
                ->getQuery()
                ->execute();

            $io->success("✅ Berhasil mengubah {$updated} absensi dari 'pending' menjadi 'disetujui'");
            $io->info('🎯 Badge validasi absen sekarang seharusnya hilang dari sidebar');

            // Verifikasi
            $remainingPending = $absensiRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult();

            if ($remainingPending == 0) {
                $io->success('✅ Verifikasi: Semua absensi pending sudah diproses');
            } else {
                $io->warning("⚠️ Masih ada {$remainingPending} absensi pending");
            }

        } catch (\Exception $e) {
            $io->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}