<?php

namespace App\Service;

use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Repository\AbsensiRepository;
use App\Repository\PegawaiRepository;
use App\Repository\UnitKerjaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * MonthlyReportService
 *
 * Service untuk menangani business logic laporan bulanan:
 * - Perhitungan statistik kehadiran bulanan
 * - Generate data laporan per pegawai
 * - Filter laporan berdasarkan unit kerja
 * - Agregasi data kehadiran per bulan
 *
 * REFACTOR: Dipindahkan dari fat AdminLaporanBulananController (1264 lines)
 *
 * @author Refactor Assistant
 */
class MonthlyReportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AbsensiRepository $absensiRepository,
        private PegawaiRepository $pegawaiRepository,
        private UnitKerjaRepository $unitKerjaRepository
    ) {}

    /**
     * Get monthly attendance statistics for admin dashboard
     */
    public function getMonthlyStatistics(?\DateTimeInterface $monthYear, ?int $unitKerjaId): array
    {
        $startDate = $monthYear ? clone $monthYear : new \DateTime('first day of this month');
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $queryBuilder = $this->absensiRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as totalAbsensi')
            ->addSelect('SUM(CASE WHEN a.status = \'hadir\' THEN 1 ELSE 0 END) as totalHadir')
            ->addSelect('SUM(CASE WHEN a.status = \'sakit\' THEN 1 ELSE 0 END) as totalSakit')
            ->addSelect('SUM(CASE WHEN a.status = \'izin\' THEN 1 ELSE 0 END) as totalIzin')
            ->addSelect('SUM(CASE WHEN a.status = \'alpha\' THEN 1 ELSE 0 END) as totalAlpha')
            ->where('a.tanggal BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'));

        if ($unitKerjaId) {
            $queryBuilder->join('a.pegawai', 'p')
                        ->andWhere('p.unitKerjaEntity = :unitKerja')
                        ->setParameter('unitKerja', $unitKerjaId);
        }

        $result = $queryBuilder->getQuery()->getSingleResult();

        $totalAbsensi = $result['totalAbsensi'] ?? 0;
        $persentaseKehadiran = $totalAbsensi > 0
            ? round(($result['totalHadir'] / $totalAbsensi) * 100, 1)
            : 0;

        return [
            'totalAbsensi' => $totalAbsensi,
            'totalHadir' => $result['totalHadir'] ?? 0,
            'totalSakit' => $result['totalSakit'] ?? 0,
            'totalIzin' => $result['totalIzin'] ?? 0,
            'totalAlpha' => $result['totalAlpha'] ?? 0,
            'persentaseKehadiran' => $persentaseKehadiran,
            'periode' => [
                'bulan' => $startDate->format('F Y'),
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d')
            ]
        ];
    }

    /**
     * Get employee attendance data for monthly report
     */
    public function getEmployeeMonthlyData(?\DateTimeInterface $monthYear, ?int $unitKerjaId): array
    {
        $startDate = $monthYear ? clone $monthYear : new \DateTime('first day of this month');
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        // Use the working pattern: Get all employees and LEFT JOIN with their absensi records
        // This ensures we show all employees even if they have no absensi records
        $queryBuilder = $this->pegawaiRepository->createQueryBuilder('p')
            ->select('p.id, p.nama, p.nip')
            ->addSelect('uk.namaUnit as unitKerjaNama')
            ->addSelect('COUNT(a.id) as totalAbsensi')
            ->addSelect('SUM(CASE WHEN a.status = \'hadir\' THEN 1 ELSE 0 END) as totalHadir')
            ->addSelect('SUM(CASE WHEN a.status = \'sakit\' THEN 1 ELSE 0 END) as totalSakit')
            ->addSelect('SUM(CASE WHEN a.status = \'izin\' THEN 1 ELSE 0 END) as totalIzin')
            ->addSelect('SUM(CASE WHEN a.status = \'alpha\' THEN 1 ELSE 0 END) as totalAlpha')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('App\\Entity\\Absensi', 'a', 'WITH',
                      'a.pegawai = p.id AND a.tanggal BETWEEN :startDate AND :endDate')
            ->where('p.statusKepegawaian = :status')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('status', 'aktif')
            ->groupBy('p.id, p.nama, p.nip, uk.namaUnit');

        if ($unitKerjaId) {
            $queryBuilder->andWhere('p.unitKerjaEntity = :unitKerja')
                        ->setParameter('unitKerja', $unitKerjaId);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Calculate attendance percentage for each employee
        return array_map(function ($employee) {
            $totalAbsensi = $employee['totalAbsensi'];
            $totalHadir = $employee['totalHadir'];
            $persentaseKehadiran = $totalAbsensi > 0
                ? round(($totalHadir / $totalAbsensi) * 100, 1)
                : 0;

            return [
                'pegawai_id' => $employee['id'],
                'nama' => $employee['nama'],
                'nip' => $employee['nip'],
                'unit_kerja' => $employee['unitKerjaNama'] ?? 'Tidak Ada Unit',
                'hadir' => $totalHadir,
                'sakit' => $employee['totalSakit'],
                'izin' => $employee['totalIzin'],
                'alpha' => $employee['totalAlpha'],
                'tidak_hadir' => $employee['totalAlpha'],
                'total_absen_tercatat' => $totalAbsensi,
                'total_kehadiran' => $totalHadir,
                'persentase_kehadiran' => $persentaseKehadiran
            ];
        }, $results);
    }

    /**
     * Get detailed attendance data for specific employee
     */
    public function getEmployeeDetailData(int $pegawaiId, ?\DateTimeInterface $monthYear): array
    {
        $pegawai = $this->pegawaiRepository->find($pegawaiId);
        if (!$pegawai) {
            throw new \InvalidArgumentException('Pegawai tidak ditemukan');
        }

        $startDate = $monthYear ? clone $monthYear : new \DateTime('first day of this month');
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $absensiRecords = $this->absensiRepository->createQueryBuilder('a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal BETWEEN :startDate AND :endDate')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('a.tanggal', 'DESC')
            ->getQuery()
            ->getResult();

        // Calculate statistics
        $totalRecords = count($absensiRecords);
        $statusCount = [];
        foreach ($absensiRecords as $record) {
            $status = $record->getStatus();
            $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
        }

        $persentaseKehadiran = $totalRecords > 0
            ? round((($statusCount['hadir'] ?? 0) / $totalRecords) * 100, 1)
            : 0;

        return [
            'pegawai' => [
                'id' => $pegawai->getId(),
                'namaLengkap' => $pegawai->getNama(),
                'nip' => $pegawai->getNip(),
                'unitKerja' => $pegawai->getUnitKerjaEntity()?->getNamaUnit()
            ],
            'periode' => [
                'bulan' => $startDate->format('F Y'),
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d')
            ],
            'statistik' => [
                'totalAbsensi' => $totalRecords,
                'totalHadir' => $statusCount['hadir'] ?? 0,
                'totalSakit' => $statusCount['sakit'] ?? 0,
                'totalIzin' => $statusCount['izin'] ?? 0,
                'totalAlpha' => $statusCount['alpha'] ?? 0,
                'persentaseKehadiran' => $persentaseKehadiran
            ],
            'absensiRecords' => $absensiRecords
        ];
    }

    /**
     * Generate report data for export (Excel/PDF)
     */
    public function generateExportData(?\DateTimeInterface $monthYear, ?int $unitKerjaId): array
    {
        $statistics = $this->getMonthlyStatistics($monthYear, $unitKerjaId);
        $employeeData = $this->getEmployeeMonthlyData($monthYear, $unitKerjaId);

        // Add unit kerja filter info
        $unitKerjaInfo = null;
        if ($unitKerjaId) {
            $unitKerja = $this->unitKerjaRepository->find($unitKerjaId);
            $unitKerjaInfo = $unitKerja ? $unitKerja->getNama() : null;
        }

        return [
            'periode' => $statistics['periode'],
            'unitKerja' => $unitKerjaInfo,
            'statistikUmum' => $statistics,
            'dataPegawai' => $employeeData,
            'generatedAt' => new \DateTime()
        ];
    }

    /**
     * Get available unit kerja for filtering
     */
    public function getAvailableUnitKerja(): array
    {
        return $this->unitKerjaRepository->findBy([], ['nama' => 'ASC']);
    }

    /**
     * Validate month year parameter
     */
    public function validateMonthYear(?string $monthYearString): ?\DateTimeInterface
    {
        if (!$monthYearString) {
            return null;
        }

        try {
            return \DateTime::createFromFormat('Y-m', $monthYearString);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Format bulan tidak valid. Gunakan format Y-m (contoh: 2024-01)');
        }
    }
}