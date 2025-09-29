<?php

namespace App\Command;

use App\Entity\JadwalAbsensi;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-default-schedule',
    description: 'Membuat jadwal absensi default untuk aplikasi Gembira'
)]
class CreateDefaultScheduleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminRepository $adminRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Membuat Jadwal Absensi Default');

        // Cari admin pertama atau buat default
        $admin = $this->adminRepository->findOneBy([]) ?: null;

        $defaultSchedules = [
            [
                'jenis' => 'ibadah_pagi',
                'hari' => ['1', '2', '3', '4'], // Senin-Kamis
                'jam_mulai' => '07:00',
                'jam_selesai' => '08:15',
                'keterangan' => 'Ibadah pagi rutin ASN setiap hari kerja'
            ],
            [
                'jenis' => 'bbaq',
                'hari' => ['1', '2', '3', '4'], // Senin-Kamis  
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:00',
                'keterangan' => 'Baca Buku Al-Quran setiap hari kerja'
            ],
            [
                'jenis' => 'apel_pagi',
                'hari' => ['1'], // Hanya Senin
                'jam_mulai' => '07:00',
                'jam_selesai' => '08:15',
                'keterangan' => 'Apel pagi setiap hari Senin'
            ]
        ];

        $createdCount = 0;

        foreach ($defaultSchedules as $scheduleData) {
            // Cek apakah jadwal sudah ada
            $existing = $this->entityManager->getRepository(JadwalAbsensi::class)
                ->findOneBy(['jenisAbsensi' => $scheduleData['jenis']]);

            if ($existing) {
                $io->note("Jadwal {$scheduleData['jenis']} sudah ada, dilewati.");
                continue;
            }

            $jadwal = new JadwalAbsensi();
            $jadwal->setJenisAbsensi($scheduleData['jenis']);
            $jadwal->setHariDiizinkan($scheduleData['hari']);
            $jadwal->setJamMulai(new \DateTime($scheduleData['jam_mulai']));
            $jadwal->setJamSelesai(new \DateTime($scheduleData['jam_selesai']));
            $jadwal->setKeterangan($scheduleData['keterangan']);
            $jadwal->setIsAktif(true);

            if ($admin) {
                $jadwal->setCreatedBy($admin);
            }

            // Generate QR Code untuk Apel Pagi
            if ($scheduleData['jenis'] === 'apel_pagi') {
                $qrCode = 'AP_' . date('Ymd') . '_DEFAULT';
                $jadwal->setQrCode($qrCode);
            }

            $this->entityManager->persist($jadwal);
            $createdCount++;

            $io->text("✅ Dibuat: {$scheduleData['jenis']} - " . implode(',', $scheduleData['hari']) . " - {$scheduleData['jam_mulai']}-{$scheduleData['jam_selesai']}");
        }

        if ($createdCount > 0) {
            $this->entityManager->flush();
            $io->success("Berhasil membuat {$createdCount} jadwal absensi default.");
        } else {
            $io->info('Tidak ada jadwal yang dibuat. Semua jadwal sudah ada.');
        }

        $io->note([
            'Jadwal yang dibuat:',
            '• Ibadah Pagi: Senin-Kamis, 07:00-08:15',
            '• BBAQ: Senin-Kamis, 08:00-09:00', 
            '• Apel Pagi: Senin, 07:00-08:15 (dengan QR Code)',
            '',
            'Untuk Upacara Nasional, admin perlu membuat jadwal manual',
            'melalui panel admin dengan tanggal khusus.'
        ]);

        return Command::SUCCESS;
    }
}