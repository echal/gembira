<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Notifikasi;
use App\Entity\Pegawai;
use App\Entity\UnitKerja;
use App\Entity\UserNotifikasi;
use App\Repository\UserNotifikasiRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotifikasiService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * # SERVICE UPDATE: Kirim notifikasi event baru dengan UserNotifikasi pivot
     * Kirim notifikasi event baru ke pegawai berdasarkan target audience
     */
    public function kirimNotifikasiEventBaru(Event $event): void
    {
        // hubungan: ambil semua pegawai berdasarkan target audience event
        $targetPegawai = $this->getTargetPegawai($event);

        // Buat satu notifikasi utama
        $notifikasi = new Notifikasi();
        $notifikasi->setEvent($event);
        $notifikasi->setTipe('event_baru');
        $notifikasi->setJudul('Event Baru: ' . $event->getJudulEvent());
        
        $pesan = $this->generatePesanEventBaru($event);
        $notifikasi->setPesan($pesan);
        
        $this->entityManager->persist($notifikasi);
        $this->entityManager->flush(); // flush untuk mendapatkan ID notifikasi

        // # SERVICE UPDATE: Buat UserNotifikasi untuk setiap target pegawai
        foreach ($targetPegawai as $pegawai) {
            $userNotifikasi = new UserNotifikasi();
            $userNotifikasi->setPegawai($pegawai);
            $userNotifikasi->setNotifikasi($notifikasi);
            $userNotifikasi->setPriority($this->determinePriority($event));
            
            $this->entityManager->persist($userNotifikasi);
        }

        $this->entityManager->flush();
    }

    /**
     * # SERVICE UPDATE: Kirim notifikasi pengingat event dengan UserNotifikasi pivot
     * Kirim notifikasi pengingat event
     */
    public function kirimNotifikasiReminderEvent(Event $event): void
    {
        $targetPegawai = $this->getTargetPegawai($event);

        // Buat satu notifikasi utama untuk reminder
        $notifikasi = new Notifikasi();
        $notifikasi->setEvent($event);
        $notifikasi->setTipe('reminder');
        $notifikasi->setJudul('Pengingat: ' . $event->getJudulEvent());
        
        $tanggalEvent = $event->getTanggalMulai()->format('d F Y, H:i');
        $pesan = "Jangan lupa! Event \"{$event->getJudulEvent()}\" akan berlangsung pada {$tanggalEvent}";
        if ($event->getLokasi()) {
            $pesan .= " di {$event->getLokasi()}";
        }
        $pesan .= ".";
        $notifikasi->setPesan($pesan);
        
        $this->entityManager->persist($notifikasi);
        $this->entityManager->flush();

        // # SERVICE UPDATE: Buat UserNotifikasi untuk setiap target pegawai dengan priority tinggi
        foreach ($targetPegawai as $pegawai) {
            $userNotifikasi = new UserNotifikasi();
            $userNotifikasi->setPegawai($pegawai);
            $userNotifikasi->setNotifikasi($notifikasi);
            $userNotifikasi->setPriority('high'); // reminder selalu high priority
            
            $this->entityManager->persist($userNotifikasi);
        }

        $this->entityManager->flush();
    }

    /**
     * # SERVICE UPDATE: Kirim notifikasi pengumuman dengan UserNotifikasi pivot
     * Kirim notifikasi pengumuman umum
     */
    public function kirimNotifikasiPengumuman(string $judul, string $pesan, array $targetUnitKerja = [], string $priority = 'normal'): void
    {
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        
        if (empty($targetUnitKerja)) {
            // Kirim ke semua pegawai aktif
            $targetPegawai = $pegawaiRepo->findBy(['statusKepegawaian' => 'aktif']);
        } else {
            // hubungan: kirim ke pegawai di unit kerja tertentu
            $targetPegawai = $pegawaiRepo->createQueryBuilder('p')
                ->join('p.unitKerjaEntity', 'u')
                ->where('u.id IN (:unitIds)')
                ->andWhere('p.statusKepegawaian = :status')
                ->setParameter('unitIds', $targetUnitKerja)
                ->setParameter('status', 'aktif')
                ->getQuery()
                ->getResult();
        }

        // Buat satu notifikasi utama
        $notifikasi = new Notifikasi();
        $notifikasi->setTipe('pengumuman');
        $notifikasi->setJudul($judul);
        $notifikasi->setPesan($pesan);
        
        $this->entityManager->persist($notifikasi);
        $this->entityManager->flush();

        // # SERVICE UPDATE: Buat UserNotifikasi untuk setiap target pegawai
        foreach ($targetPegawai as $pegawai) {
            $userNotifikasi = new UserNotifikasi();
            $userNotifikasi->setPegawai($pegawai);
            $userNotifikasi->setNotifikasi($notifikasi);
            $userNotifikasi->setPriority($priority);
            
            $this->entityManager->persist($userNotifikasi);
        }

        $this->entityManager->flush();
    }

    /**
     * Ambil target pegawai berdasarkan target audience event
     */
    private function getTargetPegawai(Event $event): array
    {
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        
        if ($event->getTargetAudience() === 'all') {
            // Semua pegawai aktif
            return $pegawaiRepo->findBy(['statusKepegawaian' => 'aktif']);
        }
        
        if ($event->getTargetAudience() === 'custom' && $event->getTargetUnits()) {
            // hubungan: pegawai di unit kerja tertentu berdasarkan targetUnits
            return $pegawaiRepo->createQueryBuilder('p')
                ->join('p.unitKerjaEntity', 'u')
                ->where('u.id IN (:unitIds)')
                ->andWhere('p.statusKepegawaian = :status')
                ->setParameter('unitIds', $event->getTargetUnits())
                ->setParameter('status', 'aktif')
                ->getQuery()
                ->getResult();
        }

        return [];
    }

    /**
     * Generate pesan notifikasi untuk event baru
     */
    private function generatePesanEventBaru(Event $event): string
    {
        $tanggalEvent = $event->getTanggalMulai()->format('d F Y, H:i');
        $pesan = "Event baru \"{$event->getJudulEvent()}\" telah dijadwalkan pada {$tanggalEvent}";
        
        if ($event->getLokasi()) {
            $pesan .= " di {$event->getLokasi()}";
        }
        
        $pesan .= ". Kategori: {$event->getKategoriNama()}";
        
        if ($event->getDeskripsi()) {
            $pesan .= "\n\nDeskripsi: " . $event->getDeskripsi();
        }

        if ($event->isButuhAbsensi()) {
            $pesan .= "\n\n⚠️ Event ini membutuhkan absensi. Jangan lupa untuk hadir dan melakukan absensi.";
        }

        return $pesan;
    }

    /**
     * # SERVICE UPDATE: DEPRECATED - gunakan UserNotifikasiRepository
     * Tandai notifikasi sebagai dibaca
     */
    public function tandaiSudahDibaca(Notifikasi $notifikasi): void
    {
        $notifikasi->setSudahDibaca(true);
        $this->entityManager->flush();
    }
    
    /**
     * # SERVICE UPDATE: Helper method untuk menentukan prioritas berdasarkan event
     */
    private function determinePriority(Event $event): string
    {
        // Event dengan absensi wajib = high priority
        if ($event->isButuhAbsensi()) {
            return 'high';
        }
        
        // Event dalam 3 hari ke depan = normal priority
        $now = new \DateTime();
        $daysDiff = $now->diff($event->getTanggalMulai())->days;
        
        if ($daysDiff <= 3) {
            return 'normal';
        }
        
        // Event jauh ke depan = low priority
        return 'low';
    }

    /**
     * Hapus notifikasi lama secara otomatis
     */
    public function bersihkanNotifikasiLama(int $hariLalu = 30): void
    {
        $notifikasiRepo = $this->entityManager->getRepository(Notifikasi::class);
        $notifikasiRepo->deleteOldNotifications($hariLalu);
    }
}