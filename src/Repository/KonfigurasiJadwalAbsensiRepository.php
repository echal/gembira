<?php

namespace App\Repository;

use App\Entity\KonfigurasiJadwalAbsensi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository untuk Konfigurasi Jadwal Absensi yang Fleksibel
 * 
 * Berisi method-method untuk mengambil data jadwal absensi
 * berdasarkan konfigurasi admin, tanpa logika hardcoded.
 * 
 * @extends ServiceEntityRepository<KonfigurasiJadwalAbsensi>
 * @author Indonesian Developer
 */
class KonfigurasiJadwalAbsensiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KonfigurasiJadwalAbsensi::class);
    }

    /**
     * Mencari jadwal absensi yang aktif dan tersedia untuk hari tertentu
     * 
     * @param int $hari Hari dalam format ISO (1=Senin, 7=Minggu)
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findJadwalTersediaUntukHari(int $hari): array
    {
        $qb = $this->createQueryBuilder('j');
        
        // Kondisi dasar: jadwal harus aktif
        $qb->where('j.isAktif = :aktif')
           ->setParameter('aktif', true);

        // Kondisi hari: jadwal tersedia untuk hari yang diminta
        // Case 1: Rentang hari normal (hariMulai <= hariSelesai)
        // Case 2: Rentang hari melintasi minggu (hariMulai > hariSelesai)
        $qb->andWhere(
            $qb->expr()->orX(
                // Case 1: Hari normal (Senin-Jumat = 1-5)
                $qb->expr()->andX(
                    'j.hariMulai <= j.hariSelesai',
                    ':hari BETWEEN j.hariMulai AND j.hariSelesai'
                ),
                // Case 2: Melintasi minggu (Sabtu-Senin = 6-1)
                $qb->expr()->andX(
                    'j.hariMulai > j.hariSelesai',
                    $qb->expr()->orX(
                        ':hari >= j.hariMulai',
                        ':hari <= j.hariSelesai'
                    )
                )
            )
        );

        $qb->setParameter('hari', $hari)
           ->orderBy('j.jamMulai', 'ASC'); // Urutkan berdasarkan jam mulai

        return $qb->getQuery()->getResult();
    }

    /**
     * Mencari jadwal absensi yang sedang terbuka saat ini
     * (berdasarkan hari dan jam saat ini)
     * 
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findJadwalTerbukaSaatIni(): array
    {
        $sekarang = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
        $hariIni = (int)$sekarang->format('N');
        $jamSekarang = $sekarang->format('H:i:s');

        $qb = $this->createQueryBuilder('j');
        
        // Kondisi dasar: jadwal aktif dan hari tersedia
        $qb->where('j.isAktif = :aktif')
           ->setParameter('aktif', true);

        // Filter berdasarkan hari (sama seperti method di atas)
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->andX(
                    'j.hariMulai <= j.hariSelesai',
                    ':hari BETWEEN j.hariMulai AND j.hariSelesai'
                ),
                $qb->expr()->andX(
                    'j.hariMulai > j.hariSelesai',
                    $qb->expr()->orX(
                        ':hari >= j.hariMulai',
                        ':hari <= j.hariSelesai'
                    )
                )
            )
        );

        // Filter berdasarkan jam: jadwal yang sedang terbuka
        $qb->andWhere(
            $qb->expr()->orX(
                // Jam normal dalam satu hari (08:00 - 17:00)
                $qb->expr()->andX(
                    'j.jamMulai <= j.jamSelesai',
                    ':jam BETWEEN j.jamMulai AND j.jamSelesai'
                ),
                // Jam melintasi tengah malam (22:00 - 05:00)
                $qb->expr()->andX(
                    'j.jamMulai > j.jamSelesai',
                    $qb->expr()->orX(
                        ':jam >= j.jamMulai',
                        ':jam <= j.jamSelesai'
                    )
                )
            )
        );

        $qb->setParameter('hari', $hariIni)
           ->setParameter('jam', $jamSekarang)
           ->orderBy('j.jamMulai', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Mencari jadwal berdasarkan QR Code
     *
     * @param string $qrCode QR Code yang di-scan pegawai
     * @return KonfigurasiJadwalAbsensi|null
     */
    public function findByQrCode(string $qrCode): ?KonfigurasiJadwalAbsensi
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.qrCode = :qrCode')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('aktif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mencari jadwal berdasarkan QR Code dengan validasi fleksibel
     * Mendukung matching yang lebih longgar untuk compatibility
     *
     * @param string $qrCode QR Code yang di-scan pegawai
     * @return KonfigurasiJadwalAbsensi|null
     */
    public function findValidQrCode(string $qrCode): ?KonfigurasiJadwalAbsensi
    {
        // Coba exact match terlebih dahulu
        $exactMatch = $this->findByQrCode($qrCode);
        if ($exactMatch) {
            return $exactMatch;
        }

        // Jika tidak ada exact match, coba partial match
        // Untuk mengatasi masalah format QR yang sedikit berbeda
        $trimmedQr = trim($qrCode);

        // Coba match dengan QR yang sudah di-trim
        if ($trimmedQr !== $qrCode) {
            $trimMatch = $this->findByQrCode($trimmedQr);
            if ($trimMatch) {
                return $trimMatch;
            }
        }

        // Coba match dengan pattern yang fleksibel untuk QR yang mirip
        $qb = $this->createQueryBuilder('j');
        $qb->andWhere('j.isAktif = :aktif')
           ->andWhere('j.perluQrCode = :perluQr')
           ->setParameter('aktif', true)
           ->setParameter('perluQr', true);

        // Jika QR mengandung pattern JDW_, coba cari berdasarkan pattern
        if (strpos($qrCode, 'JDW_') === 0) {
            // Extract nama jadwal dari QR code
            $parts = explode('_', $qrCode);
            if (count($parts) >= 2) {
                $possibleName = $parts[1];
                $qb->andWhere('UPPER(REPLACE(j.namaJadwal, \' \', \'_\')) LIKE :namaPattern')
                   ->setParameter('namaPattern', '%' . strtoupper($possibleName) . '%');

                $results = $qb->getQuery()->getResult();
                if (count($results) === 1) {
                    return $results[0];
                }
            }
        }

        return null;
    }

    /**
     * Validasi apakah QR Code valid untuk jadwal tertentu dan masih bisa digunakan
     *
     * @param string $qrCode QR Code yang di-scan
     * @param KonfigurasiJadwalAbsensi $jadwal Jadwal yang dimaksud
     * @return array ['valid' => bool, 'alasan' => string]
     */
    public function validateQrCodeForSchedule(string $qrCode, KonfigurasiJadwalAbsensi $jadwal): array
    {
        // Cek apakah jadwal aktif
        if (!$jadwal->isAktif()) {
            return [
                'valid' => false,
                'alasan' => 'Jadwal absensi "' . $jadwal->getNamaJadwal() . '" sudah tidak aktif'
            ];
        }

        // Cek apakah jadwal memerlukan QR Code
        if (!$jadwal->isPerluQrCode()) {
            return [
                'valid' => false,
                'alasan' => 'Jadwal "' . $jadwal->getNamaJadwal() . '" tidak memerlukan QR Code'
            ];
        }

        // Cek apakah jadwal masih dalam periode yang tersedia
        if (!$jadwal->isTersediaSaatIni()) {
            return [
                'valid' => false,
                'alasan' => 'Jadwal "' . $jadwal->getNamaJadwal() . '" tidak tersedia saat ini. ' .
                           'Waktu absensi: ' . ($jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '') .
                           ' - ' . ($jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '')
            ];
        }

        // Validasi QR Code dengan fleksibilitas
        $qrCodeJadwal = $jadwal->getQrCode();

        // Exact match
        if ($qrCode === $qrCodeJadwal) {
            return ['valid' => true, 'alasan' => ''];
        }

        // Trim match
        if (trim($qrCode) === trim($qrCodeJadwal)) {
            return ['valid' => true, 'alasan' => ''];
        }

        // Pattern match untuk QR yang di-generate otomatis
        if ($qrCodeJadwal && strpos($qrCode, 'JDW_') === 0 && strpos($qrCodeJadwal, 'JDW_') === 0) {
            $qrParts = explode('_', $qrCode);
            $dbParts = explode('_', $qrCodeJadwal);

            // Cek apakah nama jadwal cocok (bagian kedua dari QR)
            if (count($qrParts) >= 2 && count($dbParts) >= 2) {
                if (strtoupper($qrParts[1]) === strtoupper($dbParts[1])) {
                    return ['valid' => true, 'alasan' => ''];
                }
            }
        }

        return [
            'valid' => false,
            'alasan' => 'QR Code tidak sesuai dengan jadwal "' . $jadwal->getNamaJadwal() . '"'
        ];
    }

    /**
     * Mendapatkan semua jadwal aktif untuk ditampilkan di dashboard admin
     * 
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findAllAktif(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('aktif', true)
            ->orderBy('j.hariMulai', 'ASC')
            ->addOrderBy('j.jamMulai', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mendapatkan semua jadwal (aktif dan nonaktif) untuk manajemen admin
     * 
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findAllWithStatus(): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.isAktif', 'DESC') // Aktif duluan
            ->addOrderBy('j.hariMulai', 'ASC')
            ->addOrderBy('j.jamMulai', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mencari jadwal berdasarkan nama (untuk pencarian admin)
     * 
     * @param string $nama Nama jadwal yang dicari
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findByNama(string $nama): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.namaJadwal LIKE :nama')
            ->setParameter('nama', '%' . $nama . '%')
            ->orderBy('j.namaJadwal', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Menghitung jumlah jadwal yang perlu QR Code
     * (untuk statistik admin)
     * 
     * @return int
     */
    public function countJadwalPerluQr(): int
    {
        return $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.perluQrCode = :perlu')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('perlu', true)
            ->setParameter('aktif', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Menghitung jumlah jadwal yang perlu kamera
     * (untuk statistik admin)
     * 
     * @return int
     */
    public function countJadwalPerluKamera(): int
    {
        return $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.perluKamera = :perlu')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('perlu', true)
            ->setParameter('aktif', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Mendapatkan jadwal yang dibuat oleh admin tertentu
     * 
     * @param int $adminId ID admin
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findByAdmin(int $adminId): array
    {
        return $this->createQueryBuilder('j')
            ->join('j.dibuatOleh', 'admin')
            ->andWhere('admin.id = :adminId')
            ->setParameter('adminId', $adminId)
            ->orderBy('j.dibuat', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Soft delete: nonaktifkan jadwal tanpa menghapus data
     * 
     * @param int $jadwalId ID jadwal yang akan dinonaktifkan
     * @return bool
     */
    public function nonaktifkanJadwal(int $jadwalId): bool
    {
        $affected = $this->createQueryBuilder('j')
            ->update()
            ->set('j.isAktif', ':nonaktif')
            ->set('j.diubah', ':sekarang')
            ->where('j.id = :id')
            ->setParameter('nonaktif', false)
            ->setParameter('sekarang', new \DateTime())
            ->setParameter('id', $jadwalId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Reaktifkan jadwal yang sudah dinonaktifkan
     * 
     * @param int $jadwalId ID jadwal yang akan diaktifkan
     * @return bool
     */
    public function aktifkanJadwal(int $jadwalId): bool
    {
        $affected = $this->createQueryBuilder('j')
            ->update()
            ->set('j.isAktif', ':aktif')
            ->set('j.diubah', ':sekarang')
            ->where('j.id = :id')
            ->setParameter('aktif', true)
            ->setParameter('sekarang', new \DateTime())
            ->setParameter('id', $jadwalId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Generate QR Code unik untuk jadwal
     * 
     * @param int $jadwalId ID jadwal
     * @param string $qrCode QR Code yang akan disimpan
     * @return bool
     */
    public function updateQrCode(int $jadwalId, string $qrCode): bool
    {
        $affected = $this->createQueryBuilder('j')
            ->update()
            ->set('j.qrCode', ':qrCode')
            ->set('j.diubah', ':sekarang')
            ->where('j.id = :id')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('sekarang', new \DateTime())
            ->setParameter('id', $jadwalId)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    /**
     * Cek apakah QR Code sudah digunakan jadwal lain
     * 
     * @param string $qrCode QR Code yang akan dicek
     * @param int|null $excludeId ID jadwal yang dikecualikan (untuk update)
     * @return bool
     */
    public function isQrCodeExists(string $qrCode, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.qrCode = :qrCode');

        if ($excludeId) {
            $qb->andWhere('j.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $count = $qb->setParameter('qrCode', $qrCode)
                    ->getQuery()
                    ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Mencari jadwal yang memerlukan validasi admin
     * (digunakan untuk fitur validasi absensi)
     * 
     * @return KonfigurasiJadwalAbsensi[]
     */
    public function findJadwalYangPerluValidasi(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.perluValidasiAdmin = :perluValidasi')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('perluValidasi', true)
            ->setParameter('aktif', true)
            ->orderBy('j.namaJadwal', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Menghitung jumlah jadwal yang perlu validasi admin
     * (untuk statistik admin)
     * 
     * @return int
     */
    public function countJadwalPerluValidasi(): int
    {
        return $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.perluValidasiAdmin = :perluValidasi')
            ->andWhere('j.isAktif = :aktif')
            ->setParameter('perluValidasi', true)
            ->setParameter('aktif', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Method untuk migrasi data lama ke sistem baru
     * (bisa dihapus setelah migrasi selesai)
     */
    public function migrasiDataLama(): array
    {
        // Method ini bisa digunakan untuk memindahkan data
        // dari tabel jadwal_absensi lama ke konfigurasi_jadwal_absensi
        // Implementasi tergantung struktur data lama
        
        return [
            'status' => 'siap_migrasi',
            'pesan' => 'Method ini siap digunakan untuk migrasi data lama'
        ];
    }
}