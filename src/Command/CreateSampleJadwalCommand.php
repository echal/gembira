<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\KonfigurasiJadwalAbsensi;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command untuk membuat data sample jadwal absensi
 * untuk testing sistem absensi fleksibel baru.
 * 
 * @author Indonesian Developer
 */
#[AsCommand(
    name: 'app:create-sample-jadwal',
    description: 'Buat data sample jadwal absensi untuk testing sistem baru'
)]
class CreateSampleJadwalCommand extends Command
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

        $io->title('🚀 Membuat Data Sample Jadwal Absensi');

        // Cari admin pertama untuk dijadikan creator
        $admin = $this->adminRepository->findOneBy([]);
        if (!$admin) {
            $io->error('Tidak ada admin di database. Silakan buat admin terlebih dahulu.');
            return Command::FAILURE;
        }

        $io->info('Menggunakan admin: ' . $admin->getNamaLengkap());

        // Data sample jadwal absensi
        $sampleJadwal = [
            [
                'nama' => 'Apel Pagi',
                'deskripsi' => 'Apel pagi rutin setiap hari kerja',
                'hari_mulai' => 1, // Senin
                'hari_selesai' => 5, // Jumat
                'jam_mulai' => '07:00',
                'jam_selesai' => '07:30',
                'perlu_qr' => true,
                'perlu_kamera' => true,
                'emoji' => '🏢',
                'warna' => '#3B82F6'
            ],
            [
                'nama' => 'Rapat Mingguan',
                'deskripsi' => 'Rapat koordinasi setiap hari Senin',
                'hari_mulai' => 1, // Senin
                'hari_selesai' => 1, // Senin
                'jam_mulai' => '09:00',
                'jam_selesai' => '11:00',
                'perlu_qr' => false,
                'perlu_kamera' => true,
                'emoji' => '📊',
                'warna' => '#10B981'
            ],
            [
                'nama' => 'Kegiatan Ekoteologi',
                'deskripsi' => 'Kegiatan kajian ekoteologi setiap Rabu',
                'hari_mulai' => 3, // Rabu
                'hari_selesai' => 3, // Rabu
                'jam_mulai' => '14:00',
                'jam_selesai' => '16:00',
                'perlu_qr' => true,
                'perlu_kamera' => false,
                'emoji' => '🌱',
                'warna' => '#8B5CF6'
            ],
            [
                'nama' => 'Senam Pagi',
                'deskripsi' => 'Senam pagi sehat setiap Jumat',
                'hari_mulai' => 5, // Jumat
                'hari_selesai' => 5, // Jumat
                'jam_mulai' => '06:30',
                'jam_selesai' => '07:30',
                'perlu_qr' => false,
                'perlu_kamera' => false,
                'emoji' => '🏃‍♀️',
                'warna' => '#F59E0B'
            ],
            [
                'nama' => 'Ibadah Malam',
                'deskripsi' => 'Ibadah malam setiap Kamis',
                'hari_mulai' => 4, // Kamis
                'hari_selesai' => 4, // Kamis
                'jam_mulai' => '19:00',
                'jam_selesai' => '21:00',
                'perlu_qr' => true,
                'perlu_kamera' => true,
                'emoji' => '🤲',
                'warna' => '#EF4444'
            ]
        ];

        $created = 0;

        foreach ($sampleJadwal as $data) {
            // Cek apakah jadwal dengan nama ini sudah ada
            $existing = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)
                ->findOneBy(['namaJadwal' => $data['nama']]);

            if ($existing) {
                $io->note('Jadwal "' . $data['nama'] . '" sudah ada, dilewat.');
                continue;
            }

            // Buat jadwal baru
            $jadwal = new KonfigurasiJadwalAbsensi();
            $jadwal->setNamaJadwal($data['nama']);
            $jadwal->setDeskripsi($data['deskripsi']);
            $jadwal->setHariMulai($data['hari_mulai']);
            $jadwal->setHariSelesai($data['hari_selesai']);
            
            // Set jam
            $jamMulai = \DateTime::createFromFormat('H:i', $data['jam_mulai']);
            $jamSelesai = \DateTime::createFromFormat('H:i', $data['jam_selesai']);
            $jadwal->setJamMulai($jamMulai);
            $jadwal->setJamSelesai($jamSelesai);
            
            $jadwal->setPerluQrCode($data['perlu_qr']);
            $jadwal->setPerluKamera($data['perlu_kamera']);
            $jadwal->setEmoji($data['emoji']);
            $jadwal->setWarnaKartu($data['warna']);
            $jadwal->setIsAktif(true);
            $jadwal->setDibuatOleh($admin);

            // Generate QR Code jika diperlukan
            if ($data['perlu_qr']) {
                $qrCode = 'JDW_SAMPLE_' . strtoupper(str_replace(' ', '_', $data['nama'])) . '_' . date('Ymd');
                $jadwal->setQrCode($qrCode);
            }

            $this->entityManager->persist($jadwal);
            $created++;
            
            $io->success('✅ Jadwal "' . $data['nama'] . '" berhasil dibuat');
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $io->success("🎉 Total {$created} jadwal sample berhasil dibuat!");
        } else {
            $io->info('Tidak ada jadwal baru yang dibuat (semua sudah ada).');
        }

        $io->section('📋 Jadwal yang Tersedia:');
        $allJadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->findAllAktif();
        
        foreach ($allJadwal as $jadwal) {
            $qr = $jadwal->getQrCode() ? '📱' : '';
            $camera = $jadwal->isPerluKamera() ? '📸' : '';
            $io->text(sprintf(
                '%s %s - %s (%s) | %s - %s %s%s',
                $jadwal->getEmoji(),
                $jadwal->getNamaJadwal(),
                $jadwal->getNamaHariTersedia(),
                $jadwal->isAktif() ? 'Aktif' : 'Nonaktif',
                $jadwal->getJamMulai()->format('H:i'),
                $jadwal->getJamSelesai()->format('H:i'),
                $qr,
                $camera
            ));
        }

        $io->note([
            '🌐 Akses admin panel di: http://localhost:8002/admin/jadwal-absensi',
            '👥 Dashboard pegawai di: http://localhost:8002/absensi',
            '🔧 Anda bisa mengedit, menambah, atau menghapus jadwal melalui admin panel'
        ]);

        return Command::SUCCESS;
    }
}