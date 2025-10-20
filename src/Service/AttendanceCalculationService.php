<?php

namespace App\Service;

use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Entity\KonfigurasiJadwalAbsensi;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service untuk perhitungan persentase kehadiran yang konsisten
 *
 * Service ini memastikan bahwa perhitungan persentase kehadiran antara
 * admin dan dashboard user menggunakan logika yang sama:
 *
 * % Kehadiran = (Jumlah Hadir / Total Absen yang dicatat admin) * 100
 *
 * TIDAK menghitung berdasarkan jumlah hari kerja dalam 1 bulan,
 * tetapi berdasarkan jumlah absen yang benar-benar dibuat oleh admin.
 *
 * @author Indonesian Developer
 */
class AttendanceCalculationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Fungsi utama untuk menghitung persentase kehadiran pegawai
     *
     * @param Pegawai $pegawai Pegawai yang akan dihitung persentase kehadirannya
     * @param int $tahun Tahun (default: tahun berjalan)
     * @param int $bulan Bulan (default: bulan berjalan)
     * @return array Data lengkap perhitungan kehadiran
     */
    public function getPersentaseKehadiran(Pegawai $pegawai, ?int $tahun = null, ?int $bulan = null): array
    {
        if (!$tahun) {
            $tahun = (int) date('Y');
        }
        if (!$bulan) {
            $bulan = (int) date('n');
        }

        // Ambil data absensi pegawai untuk bulan/tahun tertentu
        $dataAbsensi = $this->getDataAbsensiPegawai($pegawai, $tahun, $bulan);

        // Hitung statistik
        $totalAbsenTercatat = $dataAbsensi['total_absen_tercatat'];
        $totalHadir = $dataAbsensi['total_hadir'];
        $totalIzin = $dataAbsensi['total_izin'];
        $totalSakit = $dataAbsensi['total_sakit'];
        $totalTidakHadir = $dataAbsensi['total_tidak_hadir'];

        // Perhitungan persentase kehadiran berdasarkan total absen yang tercatat
        $totalKehadiran = $totalHadir;
        $persentaseKehadiran = $totalAbsenTercatat > 0 ?
            round(($totalKehadiran / $totalAbsenTercatat) * 100, 2) : 0;

        return [
            'pegawai_id' => $pegawai->getId(),
            'pegawai_nama' => $pegawai->getNama(),
            'pegawai_nip' => $pegawai->getNip(),
            'periode' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'nama_bulan' => $this->getNamaBulan($bulan)
            ],
            'data_absensi' => [
                'total_absen_tercatat' => $totalAbsenTercatat,
                'total_hadir' => $totalHadir,
                'total_izin' => $totalIzin,
                'total_sakit' => $totalSakit,
                'total_tidak_hadir' => $totalTidakHadir,
                'total_kehadiran' => $totalKehadiran, // hadir
            ],
            'perhitungan' => [
                'persentase_kehadiran' => $persentaseKehadiran,
                'status_kehadiran' => $this->getStatusKehadiran($persentaseKehadiran),
                'keterangan' => "Berdasarkan {$totalKehadiran} dari {$totalAbsenTercatat} absen yang tercatat"
            ]
        ];
    }

    /**
     * Fungsi sederhana untuk mendapatkan persentase kehadiran saja
     *
     * @param Pegawai $pegawai
     * @param int|null $tahun
     * @param int|null $bulan
     * @return float Persentase kehadiran (0-100)
     */
    public function getSimplePersentaseKehadiran(Pegawai $pegawai, ?int $tahun = null, ?int $bulan = null): float
    {
        $hasil = $this->getPersentaseKehadiran($pegawai, $tahun, $bulan);
        return $hasil['perhitungan']['persentase_kehadiran'];
    }

    /**
     * Mendapatkan data absensi pegawai dari database
     *
     * @param Pegawai $pegawai
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    private function getDataAbsensiPegawai(Pegawai $pegawai, int $tahun, int $bulan): array
    {
        $startDate = new \DateTime("$tahun-$bulan-01");
        $endDate = new \DateTime("$tahun-$bulan-01");
        $endDate->modify('last day of this month');

        // Query untuk menghitung semua jenis status absensi
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select([
                'COUNT(a.id) as total_absen_tercatat',
                'SUM(CASE WHEN (a.status = \'hadir\' OR a.statusKehadiran = \'hadir\') THEN 1 ELSE 0 END) as total_hadir',
                'SUM(CASE WHEN (a.status = \'izin\' OR a.statusKehadiran = \'izin\') THEN 1 ELSE 0 END) as total_izin',
                'SUM(CASE WHEN (a.status = \'sakit\' OR a.statusKehadiran = \'sakit\') THEN 1 ELSE 0 END) as total_sakit',
                'SUM(CASE WHEN (a.status = \'tidak_hadir\' OR a.statusKehadiran = \'alpha\') THEN 1 ELSE 0 END) as total_tidak_hadir'
            ])
            ->from(Absensi::class, 'a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal >= :startDate')  // Field yang benar adalah 'tanggal'
            ->andWhere('a.tanggal <= :endDate')    // Field yang benar adalah 'tanggal'
            ->setParameter('pegawai', $pegawai)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return [
            'total_absen_tercatat' => (int) $result['total_absen_tercatat'],
            'total_hadir' => (int) $result['total_hadir'],
            'total_izin' => (int) $result['total_izin'],
            'total_sakit' => (int) $result['total_sakit'],
            'total_tidak_hadir' => (int) $result['total_tidak_hadir']
        ];
    }

    /**
     * Mendapatkan status kehadiran berdasarkan persentase
     *
     * @param float $persentase
     * @return string
     */
    private function getStatusKehadiran(float $persentase): string
    {
        if ($persentase >= 95) {
            return 'Sangat Baik';
        } elseif ($persentase >= 85) {
            return 'Baik';
        } elseif ($persentase >= 75) {
            return 'Cukup';
        } elseif ($persentase >= 60) {
            return 'Kurang';
        } else {
            return 'Sangat Kurang';
        }
    }

    /**
     * Mendapatkan nama bulan dalam bahasa Indonesia
     *
     * @param int $bulan
     * @return string
     */
    private function getNamaBulan(int $bulan): string
    {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $namaBulan[$bulan] ?? 'Unknown';
    }

    /**
     * Mendapatkan laporan kehadiran untuk beberapa pegawai
     *
     * @param array $pegawaiList Array of Pegawai entities
     * @param int|null $tahun
     * @param int|null $bulan
     * @return array
     */
    public function getLaporanKehadiranMultiplePegawai(array $pegawaiList, ?int $tahun = null, ?int $bulan = null): array
    {
        $laporan = [];

        foreach ($pegawaiList as $pegawai) {
            if (!$pegawai instanceof Pegawai) {
                continue;
            }

            $laporan[] = $this->getPersentaseKehadiran($pegawai, $tahun, $bulan);
        }

        return $laporan;
    }

    /**
     * Hitung skor harian berdasarkan jam masuk pegawai
     *
     * Logika:
     * - Acuan waktu: 07:00 WITA
     * - Skor maksimal: 75 poin
     * - Semakin cepat absen = skor semakin tinggi
     * - Rumus: 75 - (selisih menit dari 07:00)
     *
     * Contoh:
     * - Jam 07:00 = skor 75 (maksimal)
     * - Jam 07:15 = skor 60
     * - Jam 08:00 = skor 15
     * - Jam 08:15 = skor 0
     *
     * @param \DateTimeInterface $jamMasuk Waktu absen masuk pegawai
     * @return int Skor harian (0-75)
     */
    public function hitungSkorHarian(\DateTimeInterface $jamMasuk): int
    {
        $timezone = new \DateTimeZone('Asia/Makassar'); // WITA

        // Buat waktu acuan 07:00 pada tanggal yang sama
        $jamAcuan = \DateTime::createFromFormat(
            'Y-m-d H:i',
            $jamMasuk->format('Y-m-d') . ' 07:00',
            $timezone
        );

        // Hitung selisih dalam menit
        $selisihMenit = 0;
        if ($jamMasuk >= $jamAcuan) {
            // Pegawai terlambat dari 07:00
            $interval = $jamAcuan->diff($jamMasuk);
            $selisihMenit = ($interval->h * 60) + $interval->i;
        } else {
            // Pegawai lebih awal dari 07:00 (bonus: skor tetap maksimal)
            $selisihMenit = 0;
        }

        // Hitung skor (maksimal 75, minimal 0)
        $skor = 75 - $selisihMenit;

        return max(0, $skor); // Pastikan tidak negatif
    }

    /**
     * Validasi apakah waktu absen dalam rentang valid (07:00 - 08:15 WITA)
     *
     * @param \DateTimeInterface $jamMasuk
     * @return bool
     */
    public function isWaktuAbsenValid(\DateTimeInterface $jamMasuk): bool
    {
        $timezone = new \DateTimeZone('Asia/Makassar');

        // Buat batas waktu
        $jamBuka = \DateTime::createFromFormat(
            'Y-m-d H:i',
            $jamMasuk->format('Y-m-d') . ' 07:00',
            $timezone
        );

        $jamTutup = \DateTime::createFromFormat(
            'Y-m-d H:i',
            $jamMasuk->format('Y-m-d') . ' 08:15',
            $timezone
        );

        // Cek apakah jam masuk dalam rentang
        return ($jamMasuk >= $jamBuka && $jamMasuk <= $jamTutup);
    }

    /**
     * Dapatkan informasi lengkap skor harian
     *
     * @param \DateTimeInterface $jamMasuk
     * @return array ['skor' => int, 'selisih_menit' => int, 'status' => string, 'valid' => bool]
     */
    public function getInfoSkorHarian(\DateTimeInterface $jamMasuk): array
    {
        $timezone = new \DateTimeZone('Asia/Makassar');

        $jamAcuan = \DateTime::createFromFormat(
            'Y-m-d H:i',
            $jamMasuk->format('Y-m-d') . ' 07:00',
            $timezone
        );

        $selisihMenit = 0;
        $status = 'Tepat Waktu';

        if ($jamMasuk > $jamAcuan) {
            $interval = $jamAcuan->diff($jamMasuk);
            $selisihMenit = ($interval->h * 60) + $interval->i;
            $status = "Terlambat {$selisihMenit} menit";
        } elseif ($jamMasuk < $jamAcuan) {
            $interval = $jamMasuk->diff($jamAcuan);
            $selisihMenit = -(($interval->h * 60) + $interval->i);
            $status = "Lebih Awal " . abs($selisihMenit) . " menit";
        }

        $skor = $this->hitungSkorHarian($jamMasuk);
        $valid = $this->isWaktuAbsenValid($jamMasuk);

        return [
            'skor' => $skor,
            'selisih_menit' => $selisihMenit,
            'status' => $status,
            'valid' => $valid,
            'jam_masuk' => $jamMasuk->format('H:i'),
            'jam_acuan' => '07:00'
        ];
    }

    /**
     * Mendapatkan statistik kehadiran untuk unit kerja
     *
     * @param string $unitKerjaId
     * @param int|null $tahun
     * @param int|null $bulan
     * @return array
     */
    public function getStatistikKehadiranUnitKerja(?string $unitKerjaId = null, ?int $tahun = null, ?int $bulan = null): array
    {
        if (!$tahun) {
            $tahun = (int) date('Y');
        }
        if (!$bulan) {
            $bulan = (int) date('n');
        }

        // Ambil daftar pegawai dari unit kerja
        $pegawaiQueryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Pegawai::class, 'p')
            ->where('p.statusKepegawaian = :status')
            ->setParameter('status', 'aktif')
            ->orderBy('p.nama', 'ASC');

        if ($unitKerjaId) {
            $pegawaiQueryBuilder->andWhere('p.unitKerjaEntity = :unitKerja')
                               ->setParameter('unitKerja', $unitKerjaId);
        }

        $pegawaiList = $pegawaiQueryBuilder->getQuery()->getResult();

        // Hitung statistik untuk setiap pegawai
        $laporanPegawai = $this->getLaporanKehadiranMultiplePegawai($pegawaiList, $tahun, $bulan);

        // Hitung statistik agregat
        $totalPegawai = count($laporanPegawai);
        $totalPersentase = 0;
        $distribusiStatus = [
            'Sangat Baik' => 0,
            'Baik' => 0,
            'Cukup' => 0,
            'Kurang' => 0,
            'Sangat Kurang' => 0
        ];

        foreach ($laporanPegawai as $laporan) {
            $totalPersentase += $laporan['perhitungan']['persentase_kehadiran'];
            $status = $laporan['perhitungan']['status_kehadiran'];
            $distribusiStatus[$status]++;
        }

        $rataRataPersentase = $totalPegawai > 0 ? round($totalPersentase / $totalPegawai, 2) : 0;

        return [
            'periode' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'nama_bulan' => $this->getNamaBulan($bulan)
            ],
            'unit_kerja_id' => $unitKerjaId,
            'statistik_agregat' => [
                'total_pegawai' => $totalPegawai,
                'rata_rata_persentase' => $rataRataPersentase,
                'distribusi_status' => $distribusiStatus
            ],
            'detail_pegawai' => $laporanPegawai
        ];
    }
}