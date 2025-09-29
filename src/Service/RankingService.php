<?php

namespace App\Service;

use App\Entity\Pegawai;
use App\Repository\AbsensiRepository;
use App\Repository\PegawaiRepository;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use App\Service\AttendanceCalculationService;
use App\Service\UiHelper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service untuk menghitung ranking absensi pegawai
 *
 * Berdasarkan aturan:
 * 1. Hanya absensi rutin (KonfigurasiJadwalAbsensi) yang dihitung
 * 2. Event tidak masuk hitungan ranking
 * 3. Persentase = (Jumlah Hadir ÷ Jumlah Target) × 100
 * 4. Ranking diurutkan: Persentase (DESC), lalu Waktu absen pertama (ASC)
 *
 * @author Indonesian Developer
 */
class RankingService
{
    public function __construct(
        private AbsensiRepository $absensiRepo,
        private PegawaiRepository $pegawaiRepo,
        private KonfigurasiJadwalAbsensiRepository $jadwalRepo,
        private EntityManagerInterface $entityManager,
        private AttendanceCalculationService $attendanceService
    ) {}

    /**
     * Mendapatkan ranking pribadi pegawai (posisi global dari seluruh pegawai)
     *
     * @param Pegawai $pegawai
     * @param \DateTime|null $mulai Tanggal mulai perhitungan (default: awal bulan ini)
     * @param \DateTime|null $selesai Tanggal akhir perhitungan (default: hari ini)
     * @return array ['posisi' => int, 'total_pegawai' => int, 'persentase' => float, 'status' => string]
     */
    public function getRankingPribadi(Pegawai $pegawai, ?\DateTime $mulai = null, ?\DateTime $selesai = null): array
    {
        // Set default periode jika tidak ada parameter
        if (!$mulai || !$selesai) {
            $periode = $this->getDefaultPeriode();
            $mulai = $periode['mulai'];
            $selesai = $periode['selesai'];
        }

        // Dapatkan data ranking semua pegawai
        $rankingData = $this->calculateAllRankings($mulai, $selesai);

        // Cari posisi pegawai yang diminta
        $posisiPegawai = null;
        foreach ($rankingData as $index => $data) {
            if ($data['pegawai_id'] === $pegawai->getId()) {
                $posisiPegawai = $index + 1; // Index dimulai dari 0, ranking dari 1
                $persentase = $data['persentase'];
                break;
            }
        }

        // Jika pegawai tidak ditemukan dalam ranking, kemungkinan belum ada data absensi
        if ($posisiPegawai === null) {
            return [
                'posisi' => 0,
                'total_pegawai' => count($rankingData),
                'persentase' => 0.0,
                'status' => UiHelper::getStatusBadge(0.0)
            ];
        }

        return [
            'posisi' => $posisiPegawai,
            'total_pegawai' => count($rankingData),
            'persentase' => $persentase,
            'status' => UiHelper::getStatusBadge($persentase)
        ];
    }

    /**
     * Mendapatkan ranking dalam unit kerja pegawai
     *
     * @param Pegawai $pegawai
     * @param \DateTime|null $mulai
     * @param \DateTime|null $selesai
     * @return array ['posisi' => int, 'total_pegawai' => int, 'persentase' => float, 'nama_unit' => string]
     */
    public function getRankingGroup(Pegawai $pegawai, ?\DateTime $mulai = null, ?\DateTime $selesai = null): array
    {
        // Set default periode jika tidak ada parameter
        if (!$mulai || !$selesai) {
            $periode = $this->getDefaultPeriode();
            $mulai = $periode['mulai'];
            $selesai = $periode['selesai'];
        }

        $unitKerja = $pegawai->getUnitKerjaEntity();
        $namaUnit = $unitKerja ? $unitKerja->getNamaUnit() : 'Unit Tidak Diketahui';

        // Jika pegawai tidak memiliki unit kerja, return data kosong
        if (!$unitKerja) {
            return [
                'posisi' => 0,
                'total_pegawai' => 0,
                'persentase' => 0.0,
                'nama_unit' => $namaUnit
            ];
        }

        // Dapatkan ranking hanya untuk pegawai dalam unit kerja yang sama
        $rankingData = $this->calculateRankingsByUnit($unitKerja->getId(), $mulai, $selesai);

        // Cari posisi pegawai dalam unit kerjanya
        $posisiPegawai = null;
        foreach ($rankingData as $index => $data) {
            if ($data['pegawai_id'] === $pegawai->getId()) {
                $posisiPegawai = $index + 1;
                $persentase = $data['persentase'];
                break;
            }
        }

        if ($posisiPegawai === null) {
            return [
                'posisi' => 0,
                'total_pegawai' => count($rankingData),
                'persentase' => 0.0,
                'nama_unit' => $namaUnit
            ];
        }

        return [
            'posisi' => $posisiPegawai,
            'total_pegawai' => count($rankingData),
            'persentase' => $persentase,
            'nama_unit' => $namaUnit
        ];
    }

    /**
     * Mendapatkan daftar 10 pegawai terbaik
     *
     * @param \DateTime|null $mulai
     * @param \DateTime|null $selesai
     * @return array Array of ['nama' => string, 'persentase' => float, 'status' => string, 'unit_kerja' => string]
     */
    public function getTop10(?\DateTime $mulai = null, ?\DateTime $selesai = null): array
    {
        // Set default periode jika tidak ada parameter
        if (!$mulai || !$selesai) {
            $periode = $this->getDefaultPeriode();
            $mulai = $periode['mulai'];
            $selesai = $periode['selesai'];
        }

        // Dapatkan ranking semua pegawai
        $rankingData = $this->calculateAllRankings($mulai, $selesai);

        // Ambil 10 teratas saja
        $top10Data = array_slice($rankingData, 0, 10);

        $result = [];
        foreach ($top10Data as $data) {
            // Dapatkan data pegawai lengkap untuk mendapatkan NIP
            $pegawai = $this->pegawaiRepo->find($data['pegawai_id']);
            $nip = $pegawai ? $pegawai->getNip() : 'N/A';

            $result[] = [
                'nip' => $nip,
                'nama' => $data['nama_pegawai'],
                'unit_kerja' => $data['unit_kerja'] ?? 'Unit Tidak Diketahui',
                'persentase' => $data['persentase'],
                'status' => UiHelper::getStatusBadge($data['persentase'])
            ];
        }

        return $result;
    }

    /**
     * Menghitung ranking semua pegawai menggunakan AttendanceCalculationService (KONSISTEN)
     *
     * @param \DateTime $mulai
     * @param \DateTime $selesai
     * @return array Array ranking dengan data pegawai
     */
    private function calculateAllRankings(\DateTime $mulai, \DateTime $selesai): array
    {
        // Ambil semua pegawai aktif
        $pegawaiList = $this->pegawaiRepo->findBy(
            ['statusKepegawaian' => 'aktif'],
            ['nama' => 'ASC']
        );

        $rankingData = [];
        $tahun = (int) $mulai->format('Y');
        $bulan = (int) $mulai->format('n');

        foreach ($pegawaiList as $pegawai) {
            // GUNAKAN AttendanceCalculationService untuk konsistensi
            $dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
                $pegawai,
                $tahun,
                $bulan
            );

            // Skip pegawai yang tidak ada data absensi
            if ($dataKehadiran['data_absensi']['total_absen_tercatat'] === 0) {
                continue;
            }

            // Ambil waktu absen pertama untuk tie-breaking
            $waktuAbsenPertama = $this->getWaktuAbsenPertama($pegawai, $mulai, $selesai);

            $rankingData[] = [
                'pegawai_id' => $pegawai->getId(),
                'nama_pegawai' => $pegawai->getNama(),
                'unit_kerja' => $pegawai->getNamaUnitKerja(),
                'total_hadir' => $dataKehadiran['data_absensi']['total_hadir'],
                'total_kehadiran' => $dataKehadiran['data_absensi']['total_kehadiran'],
                'total_absen_tercatat' => $dataKehadiran['data_absensi']['total_absen_tercatat'],
                'persentase' => $dataKehadiran['perhitungan']['persentase_kehadiran'],
                'waktu_absen_pertama' => $waktuAbsenPertama
            ];
        }

        // Urutkan berdasarkan aturan ranking:
        // 1. Persentase tertinggi (DESC)
        // 2. Waktu absen pertama terdekat (ASC) untuk tie-breaking
        usort($rankingData, function($a, $b) {
            // Persentase tertinggi dulu
            if ($a['persentase'] !== $b['persentase']) {
                return $b['persentase'] <=> $a['persentase'];
            }

            // Jika persentase sama, waktu absen pertama yang paling awal menang
            if ($a['waktu_absen_pertama'] && $b['waktu_absen_pertama']) {
                return $a['waktu_absen_pertama'] <=> $b['waktu_absen_pertama'];
            }

            // Jika salah satu tidak memiliki waktu absen, prioritaskan yang memiliki waktu
            if ($a['waktu_absen_pertama'] && !$b['waktu_absen_pertama']) {
                return -1;
            }
            if (!$a['waktu_absen_pertama'] && $b['waktu_absen_pertama']) {
                return 1;
            }

            return 0;
        });

        return $rankingData;
    }

    /**
     * Menghitung ranking pegawai dalam unit kerja tertentu menggunakan AttendanceCalculationService
     *
     * @param int $unitKerjaId
     * @param \DateTime $mulai
     * @param \DateTime $selesai
     * @return array
     */
    private function calculateRankingsByUnit(int $unitKerjaId, \DateTime $mulai, \DateTime $selesai): array
    {
        // Ambil pegawai aktif dari unit kerja tertentu
        $pegawaiList = $this->pegawaiRepo->findBy([
            'statusKepegawaian' => 'aktif',
            'unitKerjaEntity' => $unitKerjaId
        ], ['nama' => 'ASC']);

        $rankingData = [];
        $tahun = (int) $mulai->format('Y');
        $bulan = (int) $mulai->format('n');

        foreach ($pegawaiList as $pegawai) {
            // GUNAKAN AttendanceCalculationService untuk konsistensi
            $dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
                $pegawai,
                $tahun,
                $bulan
            );

            // Skip pegawai yang tidak ada data absensi
            if ($dataKehadiran['data_absensi']['total_absen_tercatat'] === 0) {
                continue;
            }

            // Ambil waktu absen pertama untuk tie-breaking
            $waktuAbsenPertama = $this->getWaktuAbsenPertama($pegawai, $mulai, $selesai);

            $rankingData[] = [
                'pegawai_id' => $pegawai->getId(),
                'nama_pegawai' => $pegawai->getNama(),
                'unit_kerja' => $pegawai->getNamaUnitKerja(),
                'total_hadir' => $dataKehadiran['data_absensi']['total_hadir'],
                'total_kehadiran' => $dataKehadiran['data_absensi']['total_kehadiran'],
                'total_absen_tercatat' => $dataKehadiran['data_absensi']['total_absen_tercatat'],
                'persentase' => $dataKehadiran['perhitungan']['persentase_kehadiran'],
                'waktu_absen_pertama' => $waktuAbsenPertama
            ];
        }

        // Urutkan dengan aturan yang sama
        usort($rankingData, function($a, $b) {
            if ($a['persentase'] !== $b['persentase']) {
                return $b['persentase'] <=> $a['persentase'];
            }

            if ($a['waktu_absen_pertama'] && $b['waktu_absen_pertama']) {
                return $a['waktu_absen_pertama'] <=> $b['waktu_absen_pertama'];
            }

            if ($a['waktu_absen_pertama'] && !$b['waktu_absen_pertama']) {
                return -1;
            }
            if (!$a['waktu_absen_pertama'] && $b['waktu_absen_pertama']) {
                return 1;
            }

            return 0;
        });

        return $rankingData;
    }

    /**
     * Helper method untuk mendapatkan waktu absen pertama pegawai (untuk tie-breaking)
     */
    private function getWaktuAbsenPertama(Pegawai $pegawai, \DateTime $mulai, \DateTime $selesai): ?\DateTime
    {
        $absensi = $this->entityManager->createQueryBuilder('a')
            ->select('a.waktuAbsensi')
            ->from('App\Entity\Absensi', 'a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal BETWEEN :mulai AND :selesai')
            ->andWhere('a.status IN (:statusValid)')
            ->andWhere('a.waktuAbsensi IS NOT NULL')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->setParameter('statusValid', ['hadir'])
            ->orderBy('a.waktuAbsensi', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $absensi ? $absensi['waktuAbsensi'] : null;
    }

    /**
     * Menghitung jumlah target absensi dalam periode tertentu
     * Berdasarkan jumlah hari kerja dan jadwal absensi rutin yang aktif
     *
     * @param \DateTime $mulai
     * @param \DateTime $selesai
     * @return int
     */
    private function calculateTargetAbsensi(\DateTime $mulai, \DateTime $selesai): int
    {
        // Dapatkan semua jadwal absensi rutin yang aktif
        $jadwalAktif = $this->jadwalRepo->findAllAktif();

        if (empty($jadwalAktif)) {
            return 0;
        }

        $totalTarget = 0;
        $current = clone $mulai;

        // Hitung hari per hari dalam periode
        while ($current <= $selesai) {
            $hariIni = (int)$current->format('N'); // 1=Senin, 7=Minggu

            // Cek apakah ada jadwal yang tersedia untuk hari ini
            foreach ($jadwalAktif as $jadwal) {
                if ($jadwal->isHariTersedia($hariIni)) {
                    $totalTarget++;
                    break; // Satu hari hanya dihitung satu kali, meskipun ada multiple jadwal
                }
            }

            $current->add(new \DateInterval('P1D'));
        }

        return $totalTarget;
    }

    /**
     * Mendapatkan periode default untuk perhitungan ranking (awal bulan ini sampai hari ini)
     *
     * @return array ['mulai' => \DateTime, 'selesai' => \DateTime]
     */
    private function getDefaultPeriode(): array
    {
        $timezone = new \DateTimeZone('Asia/Makassar');
        $sekarang = new \DateTime('now', $timezone);

        // Awal bulan ini
        $mulai = new \DateTime($sekarang->format('Y-m-01'), $timezone);

        // Hari ini
        $selesai = clone $sekarang;

        return [
            'mulai' => $mulai,
            'selesai' => $selesai
        ];
    }

    /**
     * REFACTOR NOTE: getStatusBadge() method dipindahkan ke UiHelper::getStatusBadge()
     * untuk menghindari duplikasi di multiple services/entities
     */

    /**
     * Mendapatkan statistik detail untuk pegawai tertentu menggunakan AttendanceCalculationService
     *
     * @param Pegawai $pegawai
     * @param \DateTime|null $mulai
     * @param \DateTime|null $selesai
     * @return array Detail statistik absensi pegawai
     */
    public function getDetailStatistik(Pegawai $pegawai, ?\DateTime $mulai = null, ?\DateTime $selesai = null): array
    {
        if (!$mulai || !$selesai) {
            $periode = $this->getDefaultPeriode();
            $mulai = $periode['mulai'];
            $selesai = $periode['selesai'];
        }

        $tahun = (int) $mulai->format('Y');
        $bulan = (int) $mulai->format('n');

        // GUNAKAN AttendanceCalculationService untuk konsistensi
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
            $pegawai,
            $tahun,
            $bulan
        );

        // Ambil waktu absen pertama dan terakhir untuk informasi tambahan
        $waktuAbsenPertama = $this->getWaktuAbsenPertama($pegawai, $mulai, $selesai);
        $waktuAbsenTerakhir = $this->getWaktuAbsenTerakhir($pegawai, $mulai, $selesai);

        return [
            'total_absensi' => $dataKehadiran['data_absensi']['total_absen_tercatat'],
            'total_hadir' => $dataKehadiran['data_absensi']['total_hadir'],
            'total_tidak_hadir' => $dataKehadiran['data_absensi']['total_tidak_hadir'],
            'total_izin' => $dataKehadiran['data_absensi']['total_izin'],
            'total_sakit' => $dataKehadiran['data_absensi']['total_sakit'],
            'total_kehadiran' => $dataKehadiran['data_absensi']['total_kehadiran'],
            'persentase_hadir' => $dataKehadiran['perhitungan']['persentase_kehadiran'],
            'status_kehadiran' => $dataKehadiran['perhitungan']['status_kehadiran'],
            'waktu_absen_pertama' => $waktuAbsenPertama,
            'waktu_absen_terakhir' => $waktuAbsenTerakhir
        ];
    }

    /**
     * Helper method untuk mendapatkan waktu absen terakhir pegawai
     */
    private function getWaktuAbsenTerakhir(Pegawai $pegawai, \DateTime $mulai, \DateTime $selesai): ?\DateTime
    {
        $absensi = $this->entityManager->createQueryBuilder('a')
            ->select('a.waktuAbsensi')
            ->from('App\Entity\Absensi', 'a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal BETWEEN :mulai AND :selesai')
            ->andWhere('a.status IN (:statusValid)')
            ->andWhere('a.waktuAbsensi IS NOT NULL')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->setParameter('statusValid', ['hadir'])
            ->orderBy('a.waktuAbsensi', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $absensi ? $absensi['waktuAbsensi'] : null;
    }
}