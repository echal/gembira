<?php

namespace App\Repository;

use App\Entity\JadwalAbsensi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JadwalAbsensi>
 */
class JadwalAbsensiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JadwalAbsensi::class);
    }

    /**
     * Mendapatkan jadwal absensi yang aktif berdasarkan jenis dan hari
     */
    public function findActiveScheduleByTypeAndDay(string $jenisAbsensi, int $hari): ?JadwalAbsensi
    {
        // Gunakan native SQL query karena JSON_CONTAINS tidak didukung DQL
        $sql = "SELECT * FROM jadwal_absensi 
                WHERE jenis_absensi = :jenis 
                AND is_aktif = 1 
                AND JSON_CONTAINS(hari_diizinkan, :hari) = 1";
        
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, [
            'jenis' => $jenisAbsensi,
            'hari' => '"' . $hari . '"'
        ])->fetchAssociative();
        
        if (!$result) {
            return null;
        }
        
        // Convert hasil ke entity
        return $this->find($result['id']);
    }

    /**
     * Mendapatkan semua jadwal yang aktif untuk hari tertentu
     */
    public function findActiveSchedulesByDay(int $hari): array
    {
        // Gunakan native SQL query karena JSON_CONTAINS tidak didukung DQL
        $sql = "SELECT id FROM jadwal_absensi 
                WHERE is_aktif = 1 
                AND JSON_CONTAINS(hari_diizinkan, :hari) = 1
                ORDER BY jam_mulai ASC";
        
        $conn = $this->getEntityManager()->getConnection();
        $hariParam = '"' . $hari . '"';
        
        // DEBUG: Log query dan parameter
        error_log("DEBUG findActiveSchedulesByDay(): Hari = $hari, Parameter = $hariParam");
        
        $results = $conn->executeQuery($sql, [
            'hari' => $hariParam
        ])->fetchAllAssociative();
        
        error_log("DEBUG findActiveSchedulesByDay(): SQL results = " . json_encode($results));
        
        if (empty($results)) {
            error_log("DEBUG findActiveSchedulesByDay(): No results found, returning empty array");
            return [];
        }
        
        // Convert hasil ke entities
        $ids = array_column($results, 'id');
        error_log("DEBUG findActiveSchedulesByDay(): Converting IDs = " . json_encode($ids));
        
        $entities = $this->createQueryBuilder('ja')
            ->andWhere('ja.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('ja.jamMulai', 'ASC')
            ->getQuery()
            ->getResult();
            
        error_log("DEBUG findActiveSchedulesByDay(): Final entities count = " . count($entities));
        return $entities;
    }

    /**
     * Alias untuk mendapatkan semua jadwal aktif untuk hari tertentu (untuk mobile controller)
     */
    public function findActiveSchedulesForDay(int $hari): array
    {
        return $this->findActiveSchedulesByDay($hari);
    }

    /**
     * Mendapatkan jadwal berdasarkan QR Code
     */
    public function findByQrCode(string $qrCode): ?JadwalAbsensi
    {
        return $this->createQueryBuilder('ja')
            ->andWhere('ja.qrCode = :qrCode')
            ->andWhere('ja.isAktif = :aktif')
            ->setParameter('qrCode', $qrCode)
            ->setParameter('aktif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mendapatkan jadwal upacara nasional berdasarkan tanggal
     */
    public function findUpacaraNasionalByDate(\DateTimeInterface $tanggal): ?JadwalAbsensi
    {
        return $this->createQueryBuilder('ja')
            ->andWhere('ja.jenisAbsensi = :jenis')
            ->andWhere('ja.tanggalKhusus = :tanggal')
            ->andWhere('ja.isAktif = :aktif')
            ->setParameter('jenis', 'upacara_nasional')
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->setParameter('aktif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mendapatkan semua jadwal absensi yang aktif
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('ja')
            ->andWhere('ja.isAktif = :aktif')
            ->setParameter('aktif', true)
            ->orderBy('ja.jenisAbsensi', 'ASC')
            ->addOrderBy('ja.jamMulai', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mendapatkan semua jadwal absensi (aktif dan tidak aktif)
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('ja')
            ->orderBy('ja.jenisAbsensi', 'ASC')
            ->addOrderBy('ja.jamMulai', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cek apakah jadwal absensi sedang berlangsung sekarang
     */
    public function findCurrentActiveSchedules(): array
    {
        $now = new \DateTime();
        $hari = (int)$now->format('N'); // 1 = Senin, 7 = Minggu
        $jam = $now->format('H:i:s');

        // Gunakan native SQL query karena JSON_CONTAINS tidak didukung DQL
        $sql = "SELECT id FROM jadwal_absensi 
                WHERE is_aktif = 1 
                AND JSON_CONTAINS(hari_diizinkan, :hari) = 1
                AND jam_mulai <= :jam
                AND jam_selesai >= :jam";
        
        $conn = $this->getEntityManager()->getConnection();
        $results = $conn->executeQuery($sql, [
            'hari' => '"' . $hari . '"',
            'jam' => $jam
        ])->fetchAllAssociative();
        
        if (empty($results)) {
            return [];
        }
        
        // Convert hasil ke entities
        $ids = array_column($results, 'id');
        return $this->createQueryBuilder('ja')
            ->andWhere('ja.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate QR Code unique untuk jenis absensi tertentu
     */
    public function generateUniqueQrCode(string $jenisAbsensi): string
    {
        $prefix = match($jenisAbsensi) {
            'apel_pagi' => 'AP',
            'upacara_nasional' => 'UN',
            'sholat_malam' => 'SM',
            'sholat_tahajud' => 'ST', 
            'dzikir_malam' => 'DM',
            default => 'AB'
        };

        do {
            $qrCode = $prefix . '_' . date('Ymd') . '_' . substr(md5(uniqid()), 0, 8);
            $exists = $this->findOneBy(['qrCode' => $qrCode]);
        } while ($exists);

        return $qrCode;
    }

}