<?php

namespace App\Repository;

use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Entity\UnitKerja;
use App\Entity\KonfigurasiJadwalAbsensi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absensi>
 */
class AbsensiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absensi::class);
    }

    /**
     * Cari absensi berdasarkan pegawai dan tanggal
     */
    public function findByPegawaiAndDate(Pegawai $pegawai, \DateTime $tanggal): array
    {
        $startOfDay = clone $tanggal;
        $startOfDay->setTime(0, 0, 0);
        
        $endOfDay = clone $tanggal;
        $endOfDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->andWhere('a.pegawai = :pegawai')
            ->andWhere('a.waktuMasuk >= :start')
            ->andWhere('a.waktuMasuk <= :end')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('a.waktuMasuk', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistik kehadiran untuk dashboard admin
     */
    public function getStatistikHariIni(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        $qb = $this->createQueryBuilder('a')
            ->select('a.statusKehadiran, COUNT(a.id) as jumlah')
            ->where('a.waktuMasuk >= :today')
            ->andWhere('a.waktuMasuk < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->groupBy('a.statusKehadiran');

        $results = $qb->getQuery()->getResult();
        
        $stats = ['hadir' => 0, 'alpha' => 0];
        foreach ($results as $result) {
            $stats[$result['statusKehadiran']] = (int)$result['jumlah'];
        }
        
        return $stats;
    }

    /**
     * Absensi yang perlu validasi admin
     */
    public function findPendingValidation(int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->addSelect('p')
            ->where('a.validatedBy IS NULL')
            ->orderBy('a.waktuMasuk', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Rekap kehadiran per bulan
     */
    public function getRekapBulanan(int $tahun, int $bulan): array
    {
        $startDate = new \DateTime("$tahun-$bulan-01");
        $endDate = clone $startDate;
        $endDate->add(new \DateInterval('P1M'));

        return $this->createQueryBuilder('a')
            ->select('p.nama, p.nip, COUNT(a.id) as total_absensi,
                     SUM(CASE WHEN a.statusKehadiran = \'hadir\' THEN 1 ELSE 0 END) as hadir')
            ->leftJoin('a.pegawai', 'p')
            ->where('a.waktuMasuk >= :start')
            ->andWhere('a.waktuMasuk < :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('p.id')
            ->orderBy('p.nama', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Absensi terbaru untuk dashboard
     */
    public function getRecentAbsensi(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->addSelect('p')
            ->orderBy('a.waktuMasuk', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // === METHOD UNTUK SISTEM ABSENSI FLEKSIBEL BARU ===

    /**
     * Cari absensi pegawai hari ini untuk konfigurasi jadwal tertentu
     * 
     * @param Pegawai $pegawai
     * @param KonfigurasiJadwalAbsensi $konfigurasiJadwal  
     * @param \DateTime|null $tanggal
     * @return Absensi|null
     */
    public function findAbsensiHariIni(Pegawai $pegawai, KonfigurasiJadwalAbsensi $konfigurasiJadwal, ?\DateTime $tanggal = null): ?Absensi
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('today', new \DateTimeZone('Asia/Makassar'));
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.pegawai = :pegawai')
            ->andWhere('a.konfigurasiJadwal = :konfigurasiJadwal')
            ->andWhere('a.tanggal = :tanggal')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('konfigurasiJadwal', $konfigurasiJadwal)
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->orderBy('a.waktuAbsensi', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Riwayat absensi pegawai untuk bulan tertentu (sistem baru)
     * 
     * @param Pegawai $pegawai
     * @param string $bulan Format: '01' - '12'
     * @param string $tahun Format: '2025'
     * @return Absensi[]
     */
    public function findRiwayatPegawaiBulan(Pegawai $pegawai, string $bulan, string $tahun): array
    {
        $startDate = new \DateTime("$tahun-$bulan-01 00:00:00");
        $endDate = clone $startDate;
        $endDate->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'));

        return $this->createQueryBuilder('a')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->addSelect('kj')
            ->andWhere('a.pegawai = :pegawai')
            ->andWhere('a.tanggal BETWEEN :startDate AND :endDate')
            ->andWhere('a.konfigurasiJadwal IS NOT NULL') // Hanya absensi sistem baru
            ->setParameter('pegawai', $pegawai)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('a.waktuAbsensi', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistik absensi pegawai untuk konfigurasi jadwal tertentu
     * 
     * @param Pegawai $pegawai
     * @param KonfigurasiJadwalAbsensi $konfigurasiJadwal
     * @param \DateTime $mulai
     * @param \DateTime $selesai
     * @return array
     */
    public function getStatistikAbsensiPegawai(Pegawai $pegawai, KonfigurasiJadwalAbsensi $konfigurasiJadwal, \DateTime $mulai, \DateTime $selesai): array
    {
        $qb = $this->createQueryBuilder('a');
        
        $result = $qb->select([
                'COUNT(a.id) as total_absensi',
                'SUM(CASE WHEN a.status = \'hadir\' THEN 1 ELSE 0 END) as total_hadir',
                'SUM(CASE WHEN a.status = \'tidak_hadir\' THEN 1 ELSE 0 END) as total_tidak_hadir',
                'SUM(CASE WHEN a.status = \'izin\' THEN 1 ELSE 0 END) as total_izin',
                'SUM(CASE WHEN a.status = \'sakit\' THEN 1 ELSE 0 END) as total_sakit'
            ])
            ->andWhere('a.pegawai = :pegawai')
            ->andWhere('a.konfigurasiJadwal = :konfigurasiJadwal')  
            ->andWhere('a.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('konfigurasiJadwal', $konfigurasiJadwal)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();

        // Convert result ke format yang mudah dibaca
        return [
            'total_absensi' => (int)$result['total_absensi'],
            'total_hadir' => (int)$result['total_hadir'],
            'total_tidak_hadir' => (int)$result['total_tidak_hadir'],
            'total_izin' => (int)$result['total_izin'],
            'total_sakit' => (int)$result['total_sakit'],
            'persentase_hadir' => $result['total_absensi'] > 0 ? round(($result['total_hadir'] / $result['total_absensi']) * 100, 2) : 0
        ];
    }

    /**
     * Absensi terbaru menggunakan sistem baru (untuk dashboard admin)
     * 
     * @param int $limit
     * @return Absensi[]
     */
    public function getRecentAbsensiSistemBaru(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->addSelect('p', 'kj')
            ->andWhere('a.konfigurasiJadwal IS NOT NULL') // Hanya sistem baru
            ->orderBy('a.waktuAbsensi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Laporan absensi berdasarkan konfigurasi jadwal untuk admin
     * 
     * @param KonfigurasiJadwalAbsensi $konfigurasiJadwal
     * @param \DateTime $mulai
     * @param \DateTime $selesai
     * @return array
     */
    public function getLaporanAbsensiByKonfigurasi(KonfigurasiJadwalAbsensi $konfigurasiJadwal, \DateTime $mulai, \DateTime $selesai): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->addSelect('p')
            ->andWhere('a.konfigurasiJadwal = :konfigurasiJadwal')
            ->andWhere('a.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('konfigurasiJadwal', $konfigurasiJadwal)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('p.nama', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ambil absensi terbaru berdasarkan unit kerja tertentu (untuk Admin Unit)
     *
     * @param int $limit
     * @param UnitKerja $unitKerja
     * @return array
     */
    public function getRecentAbsensiByUnitKerja(int $limit = 10, UnitKerja $unitKerja): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->addSelect('p', 'kj')
            ->andWhere('a.konfigurasiJadwal IS NOT NULL') // Hanya sistem baru
            ->andWhere('p.unitKerjaEntity = :unitKerja')   // Filter unit kerja
            ->setParameter('unitKerja', $unitKerja)
            ->orderBy('a.waktuAbsensi', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}