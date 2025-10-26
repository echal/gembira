<?php

namespace App\Service;

use App\Entity\Pegawai;
use App\Entity\AbsensiDurasi;
use App\Entity\RankingHarian;
use App\Entity\RankingBulanan;
use App\Repository\AbsensiRepository;
use App\Repository\PegawaiRepository;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use App\Repository\AbsensiDurasiRepository;
use App\Repository\RankingHarianRepository;
use App\Repository\RankingBulananRepository;
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
    // Jam ideal untuk absensi (default: 07:00)
    private const JAM_IDEAL = '07:00';

    public function __construct(
        private AbsensiRepository $absensiRepo,
        private PegawaiRepository $pegawaiRepo,
        private KonfigurasiJadwalAbsensiRepository $jadwalRepo,
        private EntityManagerInterface $entityManager,
        private AttendanceCalculationService $attendanceService,
        private AbsensiDurasiRepository $absensiDurasiRepo,
        private RankingHarianRepository $rankingHarianRepo,
        private RankingBulananRepository $rankingBulananRepo
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

    // ============================================================
    // SISTEM RANKING DINAMIS BARU
    // ============================================================

    /**
     * Update ranking harian secara dinamis setelah absensi baru
     *
     * Method ini dipanggil setiap kali pegawai melakukan absensi.
     * Alur:
     * 1. Hitung SKOR harian berdasarkan waktu absensi (07:00-08:15)
     * 2. Simpan/update ke tabel ranking_harian
     * 3. Recalculate ranking harian untuk hari ini (berdasarkan skor)
     * 4. Update ranking bulanan (akumulasi skor)
     *
     * @param Pegawai $pegawai Pegawai yang baru melakukan absensi
     * @param \DateTimeInterface|null $waktuAbsensi Waktu absensi (default: sekarang)
     * @return array ['success' => bool, 'message' => string, 'skor_harian' => int, 'peringkat_harian' => int]
     */
    public function updateDailyRanking(Pegawai $pegawai, ?\DateTimeInterface $waktuAbsensi = null): array
    {
        try {
            $timezone = new \DateTimeZone('Asia/Makassar');

            // Default waktu absensi adalah sekarang jika tidak ada parameter
            if (!$waktuAbsensi) {
                $waktuAbsensi = new \DateTime('now', $timezone);
            }

            $tanggal = (clone $waktuAbsensi)->setTime(0, 0, 0);

            // 1. Hitung SKOR harian (bukan durasi)
            $skorHarian = $this->attendanceService->hitungSkorHarian($waktuAbsensi);

            // 2. Cek apakah sudah ada record ranking harian untuk hari ini
            $rankingHarian = $this->rankingHarianRepo->findByPegawaiAndTanggal($pegawai, $tanggal);

            if (!$rankingHarian) {
                // Buat record baru
                $rankingHarian = new RankingHarian();
                $rankingHarian->setPegawai($pegawai);
                $rankingHarian->setTanggal($tanggal);
            }

            // Update jam masuk dan skor harian
            $rankingHarian->setJamMasuk($waktuAbsensi);
            $rankingHarian->setSkorHarian($skorHarian);
            $rankingHarian->setUpdatedAt(new \DateTime());

            $this->rankingHarianRepo->save($rankingHarian, true);

            // 3. Recalculate ranking harian untuk hari ini berdasarkan SKOR
            $this->recalculateRankingHarianBySkor($tanggal);

            // 4. Update ranking bulanan berdasarkan AKUMULASI SKOR
            $this->calculateMonthlyAccumulationBySkor((int)$waktuAbsensi->format('Y'), (int)$waktuAbsensi->format('n'));

            // Dapatkan peringkat pegawai setelah update
            $rankingHarian = $this->rankingHarianRepo->findByPegawaiAndTanggal($pegawai, $tanggal);
            $peringkat = $rankingHarian ? $rankingHarian->getPeringkat() : 0;

            return [
                'success' => true,
                'message' => 'Ranking berhasil diupdate',
                'skor_harian' => $skorHarian,
                'peringkat_harian' => $peringkat,
                'jam_masuk' => $waktuAbsensi->format('H:i')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal update ranking: ' . $e->getMessage(),
                'skor_harian' => 0,
                'peringkat_harian' => 0
            ];
        }
    }

    /**
     * Hitung durasi absensi dalam menit (selisih dari jam ideal)
     *
     * Nilai positif = terlambat
     * Nilai negatif = lebih awal dari jam ideal
     *
     * @param \DateTimeInterface $waktuAbsensi
     * @return int Durasi dalam menit
     */
    private function hitungDurasiAbsensi(\DateTimeInterface $waktuAbsensi): int
    {
        $timezone = new \DateTimeZone('Asia/Makassar');

        // Buat waktu ideal pada tanggal yang sama dengan absensi
        $jamIdeal = \DateTime::createFromFormat(
            'Y-m-d H:i',
            $waktuAbsensi->format('Y-m-d') . ' ' . self::JAM_IDEAL,
            $timezone
        );

        // Hitung selisih dalam menit
        $interval = $jamIdeal->diff($waktuAbsensi);
        $totalMenit = ($interval->h * 60) + $interval->i;

        // Jika absensi lebih awal dari jam ideal, beri nilai negatif
        if ($waktuAbsensi < $jamIdeal) {
            $totalMenit = -$totalMenit;
        }

        return $totalMenit;
    }

    /**
     * Recalculate ranking harian untuk tanggal tertentu
     *
     * Method ini menghitung ulang peringkat semua pegawai yang absen pada tanggal tersebut.
     * Diurutkan berdasarkan total durasi (yang paling kecil/paling awal mendapat peringkat 1)
     *
     * @param \DateTimeInterface $tanggal
     * @return int Jumlah pegawai yang di-rank
     */
    private function recalculateRankingHarian(\DateTimeInterface $tanggal): int
    {
        // Ambil semua data absensi durasi untuk tanggal tersebut
        $daftarAbsensiDurasi = $this->absensiDurasiRepo->findByTanggal($tanggal);

        if (empty($daftarAbsensiDurasi)) {
            return 0;
        }

        // Urutkan berdasarkan durasi (yang terkecil/paling awal di ranking 1)
        usort($daftarAbsensiDurasi, function($a, $b) {
            return $a->getDurasiMenit() <=> $b->getDurasiMenit();
        });

        // Update atau create ranking harian
        $peringkat = 1;
        foreach ($daftarAbsensiDurasi as $absensiDurasi) {
            $pegawai = $absensiDurasi->getPegawai();

            // Cari atau buat ranking harian
            $rankingHarian = $this->rankingHarianRepo->findByPegawaiAndTanggal($pegawai, $tanggal);

            if (!$rankingHarian) {
                $rankingHarian = new RankingHarian();
                $rankingHarian->setPegawai($pegawai);
                $rankingHarian->setTanggal($tanggal);
            }

            // Update data
            $rankingHarian->setTotalDurasi($absensiDurasi->getDurasiMenit());
            $rankingHarian->setPeringkat($peringkat);
            $rankingHarian->setUpdatedAt(new \DateTime());

            $this->rankingHarianRepo->save($rankingHarian, false);

            $peringkat++;
        }

        // Flush semua perubahan sekaligus untuk performa
        $this->entityManager->flush();

        return count($daftarAbsensiDurasi);
    }

    /**
     * Hitung dan update ranking bulanan (akumulasi dari ranking harian)
     *
     * Method ini menghitung total durasi dari semua ranking harian dalam satu bulan,
     * lalu membuat/update ranking bulanan untuk semua pegawai.
     *
     * @param int $tahun
     * @param int $bulan
     * @return array ['total_pegawai' => int, 'periode' => string]
     */
    public function calculateMonthlyAccumulation(int $tahun, int $bulan): array
    {
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        // Hitung tanggal mulai dan selesai bulan tersebut
        $mulai = new \DateTime("{$tahun}-{$bulan}-01");
        $selesai = (clone $mulai)->modify('last day of this month');

        // Ambil semua pegawai aktif
        $pegawaiList = $this->pegawaiRepo->findBy(
            ['statusKepegawaian' => 'aktif'],
            ['nama' => 'ASC']
        );

        $dataRanking = [];

        foreach ($pegawaiList as $pegawai) {
            // Dapatkan agregasi data dari ranking harian
            $agregasi = $this->rankingHarianRepo->getAgregasiByPeriode($pegawai, $mulai, $selesai);

            // Skip jika pegawai tidak punya data absensi bulan ini
            if ($agregasi['jumlah_hari'] === 0) {
                continue;
            }

            $dataRanking[] = [
                'pegawai' => $pegawai,
                'total_durasi' => $agregasi['total_durasi'],
                'rata_rata' => $agregasi['rata_rata'],
                'jumlah_hari' => $agregasi['jumlah_hari']
            ];
        }

        // Urutkan berdasarkan total durasi (yang terkecil ranking 1)
        usort($dataRanking, function($a, $b) {
            return $a['total_durasi'] <=> $b['total_durasi'];
        });

        // Update atau create ranking bulanan
        $peringkat = 1;
        foreach ($dataRanking as $data) {
            $pegawai = $data['pegawai'];

            // Cari atau buat ranking bulanan
            $rankingBulanan = $this->rankingBulananRepo->findByPegawaiAndPeriode($pegawai, $periode);

            if (!$rankingBulanan) {
                $rankingBulanan = new RankingBulanan();
                $rankingBulanan->setPegawai($pegawai);
                $rankingBulanan->setPeriode($periode);
            }

            // Update data
            $rankingBulanan->setTotalDurasi($data['total_durasi']);
            $rankingBulanan->setRataRataDurasi($data['rata_rata']);
            $rankingBulanan->setPeringkat($peringkat);
            $rankingBulanan->setUpdatedAt(new \DateTime());

            $this->rankingBulananRepo->save($rankingBulanan, false);

            $peringkat++;
        }

        // Flush semua perubahan
        $this->entityManager->flush();

        return [
            'total_pegawai' => count($dataRanking),
            'periode' => $periode
        ];
    }

    /**
     * Reset ranking bulanan (untuk awal bulan baru)
     *
     * Method ini TIDAK menghapus data lama, hanya mempersiapkan untuk bulan baru.
     * Data bulan lalu tetap tersimpan untuk history.
     *
     * @param int|null $tahun Tahun yang akan direset (default: tahun sekarang)
     * @param int|null $bulan Bulan yang akan direset (default: bulan sekarang)
     * @return array ['message' => string, 'periode' => string]
     */
    public function resetMonthlyRanking(?int $tahun = null, ?int $bulan = null): array
    {
        $now = new \DateTime();
        $tahun = $tahun ?? (int)$now->format('Y');
        $bulan = $bulan ?? (int)$now->format('n');

        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        // Hitung ulang ranking bulanan untuk periode ini BERDASARKAN SKOR HARIAN
        // Ini akan membuat data baru untuk bulan ini berdasarkan data harian yang ada
        $result = $this->calculateMonthlyAccumulationBySkor($tahun, $bulan);

        return [
            'message' => "Ranking bulanan berhasil direset untuk periode {$periode}",
            'periode' => $periode,
            'total_pegawai' => $result['total_pegawai']
        ];
    }

    /**
     * Format durasi menit ke format yang mudah dibaca
     *
     * @param int $menit
     * @return string
     */
    private function formatDurasi(int $menit): string
    {
        $absMenit = abs($menit);
        $jam = floor($absMenit / 60);
        $sisa = $absMenit % 60;

        $status = $menit > 0 ? 'Terlambat' : ($menit < 0 ? 'Lebih Awal' : 'Tepat Waktu');

        if ($jam > 0) {
            return "{$status} {$jam} jam {$sisa} menit";
        }

        if ($sisa > 0) {
            return "{$status} {$sisa} menit";
        }

        return $status;
    }

    /**
     * Set jam ideal untuk absensi (untuk testing atau konfigurasi custom)
     *
     * @param string $jamIdeal Format: HH:MM
     * @return void
     */
    private $customJamIdeal = null;

    public function setJamIdeal(string $jamIdeal): void
    {
        $this->customJamIdeal = $jamIdeal;
    }

    private function getJamIdeal(): string
    {
        return $this->customJamIdeal ?? self::JAM_IDEAL;
    }

    // ============================================================
    // METHOD BARU UNTUK SISTEM RANKING BERDASARKAN SKOR (07:00-08:15)
    // ============================================================

    /**
     * Recalculate ranking harian berdasarkan skor (BUKAN durasi)
     *
     * Method ini menghitung ulang peringkat semua pegawai yang absen pada tanggal tersebut.
     * Diurutkan berdasarkan SKOR TERTINGGI dan JAM MASUK TERCEPAT (untuk tie-breaking)
     *
     * @param \DateTimeInterface $tanggal
     * @return int Jumlah pegawai yang di-rank
     */
    private function recalculateRankingHarianBySkor(\DateTimeInterface $tanggal): int
    {
        // Ambil semua ranking harian untuk tanggal tersebut
        $daftarRanking = $this->rankingHarianRepo->findByTanggal($tanggal);

        if (empty($daftarRanking)) {
            return 0;
        }

        // Urutkan berdasarkan:
        // 1. Skor tertinggi (DESC)
        // 2. Jam masuk tercepat (ASC) untuk tie-breaking
        usort($daftarRanking, function($a, $b) {
            // Skor tertinggi dulu
            if ($a->getSkorHarian() !== $b->getSkorHarian()) {
                return $b->getSkorHarian() <=> $a->getSkorHarian();
            }

            // Jika skor sama, jam masuk tercepat menang
            if ($a->getJamMasuk() && $b->getJamMasuk()) {
                return $a->getJamMasuk() <=> $b->getJamMasuk();
            }

            return 0;
        });

        // Update peringkat
        $peringkat = 1;
        foreach ($daftarRanking as $ranking) {
            $ranking->setPeringkat($peringkat);
            $ranking->setUpdatedAt(new \DateTime());
            $this->rankingHarianRepo->save($ranking, false);
            $peringkat++;
        }

        // Flush semua perubahan
        $this->entityManager->flush();

        return count($daftarRanking);
    }

    /**
     * Dapatkan semua ranking harian untuk tanggal tertentu (untuk admin)
     *
     * @param \DateTimeInterface|null $tanggal Default: hari ini
     * @return array Array of RankingHarian entities
     */
    public function getAllDailyRanking(?\DateTimeInterface $tanggal = null): array
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
            $tanggal->setTime(0, 0, 0);
        }

        return $this->rankingHarianRepo->findByTanggal($tanggal);
    }

    /**
     * Dapatkan semua ranking bulanan untuk periode tertentu (untuk admin)
     *
     * @param string|null $periode Format: YYYY-MM (default: bulan ini)
     * @return array Array of RankingBulanan entities
     */
    public function getAllMonthlyRanking(?string $periode = null): array
    {
        if (!$periode) {
            $now = new \DateTime();
            $periode = $now->format('Y-m');
        }

        return $this->rankingBulananRepo->findByPeriode($periode);
    }

    /**
     * Dapatkan Top 10 pegawai berdasarkan AKUMULASI SKOR BULANAN
     *
     * Method ini untuk dashboard pegawai - menampilkan ranking berdasarkan
     * total akumulasi skor dari awal bulan sampai hari ini.
     *
     * Auto-update setiap ada absensi baru karena ranking bulanan
     * di-recalculate setiap kali ada absensi.
     *
     * @param int|null $tahun Default: tahun ini
     * @param int|null $bulan Default: bulan ini
     * @return array Top 10 pegawai dengan total skor tertinggi
     */
    public function getTop10ByMonthlyScore(?int $tahun = null, ?int $bulan = null): array
    {
        $now = new \DateTime();
        $tahun = $tahun ?? (int)$now->format('Y');
        $bulan = $bulan ?? (int)$now->format('n');
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        // Ambil ranking bulanan untuk periode ini
        $rankingBulananList = $this->rankingBulananRepo->findByPeriode($periode);

        // Ambil 10 teratas saja
        $top10 = array_slice($rankingBulananList, 0, 10);

        $result = [];
        foreach ($top10 as $ranking) {
            $pegawai = $ranking->getPegawai();

            // Hitung persentase kehadiran untuk display
            $dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai, $tahun, $bulan);
            $persentase = $dataKehadiran['perhitungan']['persentase_kehadiran'];

            $result[] = [
                'peringkat' => $ranking->getPeringkat(),
                'nip' => $pegawai->getNip(),
                'nama' => $pegawai->getNama(),
                'unit_kerja' => $pegawai->getNamaUnitKerja(),
                'total_skor' => $ranking->getTotalDurasi(), // Ini sebenarnya total skor
                'rata_rata_skor' => $ranking->getRataRataDurasi(),
                'persentase' => $persentase, // Untuk kompatibilitas template
                'status' => $this->getStatusBySkor((int)$ranking->getRataRataDurasi())
            ];
        }

        return $result;
    }

    /**
     * Dapatkan ranking per unit kerja untuk tanggal tertentu (untuk admin)
     *
     * Menghitung rata-rata skor harian per unit kerja
     *
     * @param \DateTimeInterface|null $tanggal Default: hari ini
     * @return array Array of ['unit_kerja' => string, 'rata_rata_skor' => float, 'total_pegawai' => int, 'peringkat' => int]
     */
    public function getAllGroupRanking(?\DateTimeInterface $tanggal = null): array
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
            $tanggal->setTime(0, 0, 0);
        }

        // Ambil semua ranking harian untuk tanggal tersebut
        $rankingHarian = $this->rankingHarianRepo->findByTanggal($tanggal);

        // Group by unit kerja dan hitung rata-rata
        $groupData = [];

        foreach ($rankingHarian as $ranking) {
            $pegawai = $ranking->getPegawai();
            $unitKerja = $pegawai->getUnitKerjaEntity();

            if (!$unitKerja) {
                continue;
            }

            $unitKerjaId = $unitKerja->getId();
            $namaUnit = $unitKerja->getNamaUnit();

            if (!isset($groupData[$unitKerjaId])) {
                $groupData[$unitKerjaId] = [
                    'unit_kerja_id' => $unitKerjaId,
                    'nama_unit' => $namaUnit,
                    'total_skor' => 0,
                    'total_pegawai' => 0,
                    'rata_rata_skor' => 0
                ];
            }

            $groupData[$unitKerjaId]['total_skor'] += $ranking->getSkorHarian();
            $groupData[$unitKerjaId]['total_pegawai']++;
        }

        // Hitung rata-rata per unit
        foreach ($groupData as $key => $data) {
            if ($data['total_pegawai'] > 0) {
                $groupData[$key]['rata_rata_skor'] = round($data['total_skor'] / $data['total_pegawai'], 2);
            }
        }

        // Urutkan berdasarkan rata-rata skor tertinggi
        usort($groupData, function($a, $b) {
            return $b['rata_rata_skor'] <=> $a['rata_rata_skor'];
        });

        // Tambahkan peringkat
        $peringkat = 1;
        foreach ($groupData as &$data) {
            $data['peringkat'] = $peringkat++;
        }

        return array_values($groupData);
    }

    /**
     * Hitung ranking bulanan berdasarkan total skor harian (BUKAN durasi)
     *
     * @param int $tahun
     * @param int $bulan
     * @return array ['total_pegawai' => int, 'periode' => string]
     */
    public function calculateMonthlyAccumulationBySkor(int $tahun, int $bulan): array
    {
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        // Hitung tanggal mulai dan selesai bulan tersebut
        $mulai = new \DateTime("{$tahun}-{$bulan}-01");
        $selesai = (clone $mulai)->modify('last day of this month');

        // Ambil semua pegawai aktif
        $pegawaiList = $this->pegawaiRepo->findBy(
            ['statusKepegawaian' => 'aktif'],
            ['nama' => 'ASC']
        );

        $dataRanking = [];

        foreach ($pegawaiList as $pegawai) {
            // Ambil semua ranking harian pegawai ini dalam sebulan
            $rankingHarianList = $this->rankingHarianRepo->findByPegawaiAndPeriode($pegawai, $mulai, $selesai);

            if (empty($rankingHarianList)) {
                continue;
            }

            // Hitung total skor
            $totalSkor = 0;
            $jumlahHari = count($rankingHarianList);

            foreach ($rankingHarianList as $ranking) {
                $totalSkor += $ranking->getSkorHarian();
            }

            $rataRata = $jumlahHari > 0 ? round($totalSkor / $jumlahHari, 2) : 0;

            $dataRanking[] = [
                'pegawai' => $pegawai,
                'total_skor' => $totalSkor,
                'rata_rata' => $rataRata,
                'jumlah_hari' => $jumlahHari
            ];
        }

        // Urutkan berdasarkan total skor tertinggi
        usort($dataRanking, function($a, $b) {
            return $b['total_skor'] <=> $a['total_skor'];
        });

        // Update atau create ranking bulanan
        $peringkat = 1;
        foreach ($dataRanking as $data) {
            $pegawai = $data['pegawai'];

            // Cari atau buat ranking bulanan
            $rankingBulanan = $this->rankingBulananRepo->findByPegawaiAndPeriode($pegawai, $periode);

            if (!$rankingBulanan) {
                $rankingBulanan = new RankingBulanan();
                $rankingBulanan->setPegawai($pegawai);
                $rankingBulanan->setPeriode($periode);
            }

            // Update data dengan TOTAL SKOR (bukan durasi)
            $rankingBulanan->setTotalDurasi($data['total_skor']); // Field ini digunakan untuk total skor
            $rankingBulanan->setRataRataDurasi($data['rata_rata']);
            $rankingBulanan->setPeringkat($peringkat);
            $rankingBulanan->setUpdatedAt(new \DateTime());

            $this->rankingBulananRepo->save($rankingBulanan, false);

            $peringkat++;
        }

        // Flush semua perubahan
        $this->entityManager->flush();

        return [
            'total_pegawai' => count($dataRanking),
            'periode' => $periode
        ];
    }

    // ============================================================
    // METHOD UNTUK FRONTEND PEGAWAI (GABUNGAN SKOR + PERSENTASE)
    // ============================================================

    /**
     * Dapatkan ranking pribadi pegawai berdasarkan SKOR hari ini
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface|null $tanggal Default: hari ini
     * @return array ['posisi' => int, 'total_pegawai' => int, 'skor' => int, 'jam_masuk' => string|null]
     */
    public function getRankingPribadiByScore(Pegawai $pegawai, ?\DateTimeInterface $tanggal = null): array
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
            $tanggal->setTime(0, 0, 0);
        }

        // Ambil ranking harian pegawai
        $rankingHarian = $this->rankingHarianRepo->findByPegawaiAndTanggal($pegawai, $tanggal);

        if (!$rankingHarian) {
            // Pegawai belum absen hari ini
            $totalPegawaiHariIni = count($this->rankingHarianRepo->findByTanggal($tanggal));

            return [
                'posisi' => 0,
                'total_pegawai' => $totalPegawaiHariIni,
                'skor' => 0,
                'jam_masuk' => null,
                'status' => 'Belum Absen'
            ];
        }

        $totalPegawaiHariIni = count($this->rankingHarianRepo->findByTanggal($tanggal));

        return [
            'posisi' => $rankingHarian->getPeringkat(),
            'total_pegawai' => $totalPegawaiHariIni,
            'skor' => $rankingHarian->getSkorHarian(),
            'jam_masuk' => $rankingHarian->getJamMasuk() ? $rankingHarian->getJamMasuk()->format('H:i') : null,
            'status' => $this->getStatusBySkor($rankingHarian->getSkorHarian())
        ];
    }

    /**
     * Dapatkan top 10 pegawai berdasarkan SKOR hari ini
     *
     * @param \DateTimeInterface|null $tanggal Default: hari ini
     * @return array Array of ranking data
     */
    public function getTop10ByScore(?\DateTimeInterface $tanggal = null): array
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
            $tanggal->setTime(0, 0, 0);
        }

        // Ambil semua ranking harian untuk hari ini
        $rankingList = $this->rankingHarianRepo->findByTanggal($tanggal);

        // Ambil 10 teratas saja
        $top10 = array_slice($rankingList, 0, 10);

        $result = [];
        foreach ($top10 as $ranking) {
            $pegawai = $ranking->getPegawai();

            $result[] = [
                'peringkat' => $ranking->getPeringkat(),
                'nip' => $pegawai->getNip(),
                'nama' => $pegawai->getNama(),
                'unit_kerja' => $pegawai->getNamaUnitKerja(),
                'skor' => $ranking->getSkorHarian(),
                'jam_masuk' => $ranking->getJamMasuk() ? $ranking->getJamMasuk()->format('H:i') : '-',
                'status' => $this->getStatusBySkor($ranking->getSkorHarian())
            ];
        }

        return $result;
    }

    /**
     * Dapatkan ranking unit kerja berdasarkan SKOR hari ini
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface|null $tanggal Default: hari ini
     * @return array ['posisi' => int, 'nama_unit' => string, 'rata_rata_skor' => float, 'total_pegawai' => int]
     */
    public function getRankingGroupByScore(Pegawai $pegawai, ?\DateTimeInterface $tanggal = null): array
    {
        if (!$tanggal) {
            $tanggal = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
            $tanggal->setTime(0, 0, 0);
        }

        $unitKerja = $pegawai->getUnitKerjaEntity();
        $namaUnit = $unitKerja ? $unitKerja->getNamaUnit() : 'Unit Tidak Diketahui';

        if (!$unitKerja) {
            return [
                'posisi' => 0,
                'nama_unit' => $namaUnit,
                'rata_rata_skor' => 0.0,
                'total_pegawai' => 0,
                'total_unit' => 0
            ];
        }

        // Dapatkan semua ranking unit
        $allGroupRanking = $this->getAllGroupRanking($tanggal);

        // Cari posisi unit kerja pegawai
        $posisi = 0;
        $rataRataSkor = 0.0;
        $totalPegawai = 0;

        foreach ($allGroupRanking as $group) {
            if ($group['unit_kerja_id'] === $unitKerja->getId()) {
                $posisi = $group['peringkat'];
                $rataRataSkor = $group['rata_rata_skor'];
                $totalPegawai = $group['total_pegawai'];
                break;
            }
        }

        return [
            'posisi' => $posisi,
            'nama_unit' => $namaUnit,
            'rata_rata_skor' => $rataRataSkor,
            'total_pegawai' => $totalPegawai,
            'total_unit' => count($allGroupRanking)
        ];
    }

    /**
     * Helper method untuk mendapatkan status berdasarkan skor
     *
     * @param int $skor
     * @return string
     */
    private function getStatusBySkor(int $skor): string
    {
        if ($skor >= 70) {
            return '🏆 Excellent';
        } elseif ($skor >= 60) {
            return '🥇 Sangat Baik';
        } elseif ($skor >= 45) {
            return '🥈 Baik';
        } elseif ($skor >= 30) {
            return '🥉 Cukup';
        } else {
            return '⚠️ Perlu Perbaikan';
        }
    }

    // ============================================================
    // METHOD BARU: RANKING PRIBADI & GROUP BERDASARKAN SKOR BULANAN
    // ============================================================

    /**
     * Dapatkan ranking pribadi pegawai berdasarkan AKUMULASI SKOR BULANAN
     *
     * @param Pegawai $pegawai
     * @param int|null $tahun Default: tahun ini
     * @param int|null $bulan Default: bulan ini
     * @return array ['posisi' => int, 'total_pegawai' => int, 'total_skor' => int, 'rata_rata_skor' => float]
     */
    public function getRankingPribadiByMonthlyScore(Pegawai $pegawai, ?int $tahun = null, ?int $bulan = null): array
    {
        $now = new \DateTime();
        $tahun = $tahun ?? (int)$now->format('Y');
        $bulan = $bulan ?? (int)$now->format('n');
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        // Ambil ranking bulanan pegawai
        $rankingBulanan = $this->rankingBulananRepo->findByPegawaiAndPeriode($pegawai, $periode);

        if (!$rankingBulanan) {
            // Pegawai belum ada di ranking bulanan (belum absen bulan ini)
            $totalPegawai = count($this->rankingBulananRepo->findByPeriode($periode));

            return [
                'posisi' => 0,
                'total_pegawai' => $totalPegawai,
                'total_skor' => 0,
                'rata_rata_skor' => 0.0,
                'status' => '⚠️ Belum Ada Data'
            ];
        }

        $totalPegawai = count($this->rankingBulananRepo->findByPeriode($periode));

        return [
            'posisi' => $rankingBulanan->getPeringkat(),
            'total_pegawai' => $totalPegawai,
            'total_skor' => $rankingBulanan->getTotalDurasi(), // Field ini menyimpan total skor
            'rata_rata_skor' => round($rankingBulanan->getRataRataDurasi(), 2),
            'status' => $this->getStatusBySkor((int)$rankingBulanan->getRataRataDurasi())
        ];
    }

    /**
     * Dapatkan ranking unit kerja berdasarkan AKUMULASI SKOR BULANAN
     *
     * @param Pegawai $pegawai
     * @param int|null $tahun Default: tahun ini
     * @param int|null $bulan Default: bulan ini
     * @return array ['posisi' => int, 'nama_unit' => string, 'rata_rata_skor' => float, 'total_pegawai' => int, 'total_unit' => int]
     */
    public function getRankingGroupByMonthlyScore(Pegawai $pegawai, ?int $tahun = null, ?int $bulan = null): array
    {
        $now = new \DateTime();
        $tahun = $tahun ?? (int)$now->format('Y');
        $bulan = $bulan ?? (int)$now->format('n');
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        $unitKerja = $pegawai->getUnitKerjaEntity();
        $namaUnit = $unitKerja ? $unitKerja->getNamaUnit() : 'Unit Tidak Diketahui';

        if (!$unitKerja) {
            return [
                'posisi' => 0,
                'nama_unit' => $namaUnit,
                'rata_rata_skor' => 0.0,
                'total_pegawai' => 0,
                'total_unit' => 0
            ];
        }

        // Ambil semua ranking bulanan untuk periode ini
        $rankingBulananList = $this->rankingBulananRepo->findByPeriode($periode);

        // Group by unit kerja dan hitung rata-rata
        $groupData = [];

        foreach ($rankingBulananList as $ranking) {
            $pegawaiRanking = $ranking->getPegawai();
            $unitKerjaPegawai = $pegawaiRanking->getUnitKerjaEntity();

            if (!$unitKerjaPegawai) {
                continue;
            }

            $unitKerjaId = $unitKerjaPegawai->getId();
            $namaUnitPegawai = $unitKerjaPegawai->getNamaUnit();

            if (!isset($groupData[$unitKerjaId])) {
                $groupData[$unitKerjaId] = [
                    'unit_kerja_id' => $unitKerjaId,
                    'nama_unit' => $namaUnitPegawai,
                    'total_skor' => 0,
                    'total_pegawai' => 0,
                    'rata_rata_skor' => 0
                ];
            }

            // Gunakan rata-rata skor pegawai untuk menghitung rata-rata unit
            $groupData[$unitKerjaId]['total_skor'] += $ranking->getRataRataDurasi();
            $groupData[$unitKerjaId]['total_pegawai']++;
        }

        // Hitung rata-rata per unit
        foreach ($groupData as $key => $data) {
            if ($data['total_pegawai'] > 0) {
                $groupData[$key]['rata_rata_skor'] = round($data['total_skor'] / $data['total_pegawai'], 2);
            }
        }

        // Urutkan berdasarkan rata-rata skor tertinggi
        usort($groupData, function($a, $b) {
            return $b['rata_rata_skor'] <=> $a['rata_rata_skor'];
        });

        // Tambahkan peringkat
        $peringkat = 1;
        foreach ($groupData as &$data) {
            $data['peringkat'] = $peringkat++;
        }

        // Cari posisi unit kerja pegawai
        $posisi = 0;
        $rataRataSkor = 0.0;
        $totalPegawai = 0;

        foreach ($groupData as $group) {
            if ($group['unit_kerja_id'] === $unitKerja->getId()) {
                $posisi = $group['peringkat'];
                $rataRataSkor = $group['rata_rata_skor'];
                $totalPegawai = $group['total_pegawai'];
                break;
            }
        }

        return [
            'posisi' => $posisi,
            'nama_unit' => $namaUnit,
            'rata_rata_skor' => $rataRataSkor,
            'total_pegawai' => $totalPegawai,
            'total_unit' => count($groupData)
        ];
    }
}
