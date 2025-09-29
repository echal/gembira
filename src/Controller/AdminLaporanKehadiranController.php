<?php

namespace App\Controller;

use App\Entity\Absensi;
use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Service\AttendanceCalculationService;
use App\Service\ValidationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Laporan Kehadiran Admin
 * 
 * Menampilkan laporan kehadiran semua pegawai dengan filter bulan, tanggal, dan unit kerja.
 * Fitur: tabel sederhana, ringkasan statistik, modal foto, dan peta GPS.
 * 
 * @author Indonesian Developer
 */
#[Route('/admin/laporan-kehadiran')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLaporanKehadiranController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AttendanceCalculationService $attendanceService;
    private ValidationBadgeService $validationBadgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AttendanceCalculationService $attendanceService,
        ValidationBadgeService $validationBadgeService
    ) {
        $this->entityManager = $entityManager;
        $this->attendanceService = $attendanceService;
        $this->validationBadgeService = $validationBadgeService;
    }

    /**
     * Halaman utama laporan kehadiran
     * Menampilkan tabel absensi dengan filter dan ringkasan
     */
    #[Route('/', name: 'app_admin_laporan_kehadiran')]
    public function index(Request $request): Response
    {
        // Ambil parameter filter dari request
        $tanggal = $request->query->get('tanggal', date('Y-m-d'));
        $bulan = $request->query->get('bulan', date('Y-m'));
        $unitKerja = $request->query->get('unit_kerja', '');
        $filter = $request->query->get('filter', 'hari'); // hari atau bulan

        // Siapkan tanggal untuk query
        if ($filter === 'bulan') {
            $tanggalAwal = new \DateTime($bulan . '-01 00:00:00');
            $tanggalAkhir = new \DateTime($bulan . '-' . date('t', strtotime($bulan . '-01')) . ' 23:59:59');
        } else {
            $tanggalAwal = new \DateTime($tanggal . ' 00:00:00');
            $tanggalAkhir = new \DateTime($tanggal . ' 23:59:59');
        }

        // Query data absensi dengan filter
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $queryBuilder = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('a.jadwalAbsensi', 'ja')  // Sistem lama
            ->leftJoin('a.konfigurasiJadwal', 'kj')  // Sistem baru
            ->where('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir);

        // Filter unit kerja jika dipilih
        if (!empty($unitKerja)) {
            $queryBuilder->andWhere('uk.id = :unit_kerja')
                        ->setParameter('unit_kerja', $unitKerja);
        }

        $dataAbsensi = $queryBuilder
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('p.nama', 'ASC')
            ->getQuery()
            ->getResult();

        // Hitung ringkasan statistik menggunakan AttendanceCalculationService
        $totalHadir = 0;
        $totalTidakHadir = 0;
        $persentaseKehadiranData = [];

        // Identifikasi pegawai unik dari data absensi
        $pegawaiUnik = [];
        foreach ($dataAbsensi as $absensi) {
            $pegawaiId = $absensi->getPegawai()->getId();
            $pegawaiUnik[$pegawaiId] = $absensi->getPegawai();

            // Tetap hitung total untuk compatibility
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            if ($status === 'hadir') {
                $totalHadir++;
            } else {
                $totalTidakHadir++;
            }
        }

        // Hitung persentase kehadiran per pegawai menggunakan service yang sama seperti ranking
        foreach ($pegawaiUnik as $pegawaiId => $pegawai) {
            $tahun = $filter === 'bulan' ? (int)date('Y', strtotime($bulan.'-01')) : (int)date('Y', strtotime($tanggal));
            $bulanFilter = $filter === 'bulan' ? (int)date('n', strtotime($bulan.'-01')) : (int)date('n', strtotime($tanggal));

            $perhitunganKehadiran = $this->attendanceService->getPersentaseKehadiran(
                $pegawai,
                $tahun,
                $bulanFilter
            );

            $persentaseKehadiranData[$pegawaiId] = $perhitunganKehadiran['perhitungan']['persentase_kehadiran'];
        }

        // Ambil daftar unit kerja untuk filter
        $unitKerjaRepo = $this->entityManager->getRepository('App\Entity\UnitKerja');
        $unitKerjaList = $unitKerjaRepo->findBy([], ['namaUnit' => 'ASC']);

        // Siapkan nama bulan Indonesia
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        // Ambil data admin yang sedang login
        $admin = $this->getUser();
        
        // Ambil stats untuk badge sidebar menggunakan service yang konsisten
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/laporan_kehadiran/index.html.twig', array_merge([
            'admin' => $admin,
            'data_absensi' => $dataAbsensi,
            'total_hadir' => $totalHadir,
            'total_tidak_hadir' => $totalTidakHadir,
            'persentase_kehadiran_data' => $persentaseKehadiranData, // Data persentase yang konsisten dengan ranking
            'unit_kerja_list' => $unitKerjaList,
            'filter' => [
                'tanggal' => $tanggal,
                'bulan' => $bulan,
                'unit_kerja' => $unitKerja,
                'type' => $filter
            ],
            'nama_bulan' => $namaBulan,
        ], $sidebarStats));
    }

    /**
     * Detail kehadiran pegawai untuk periode tertentu
     */
    #[Route('/detail/{pegawaiId}', name: 'app_admin_laporan_kehadiran_detail_pegawai')]
    public function detailKehadiran(int $pegawaiId, Request $request): Response
    {
        $periode = $request->query->get('periode');
        $filter = $request->query->get('filter', 'hari');

        // Debug logging
        error_log("=== DEBUG DETAIL KEHADIRAN ===");
        error_log("Pegawai ID: " . $pegawaiId);
        error_log("Periode: " . $periode);
        error_log("Filter: " . $filter);

        // Validasi pegawai
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        $pegawai = $pegawaiRepo->find($pegawaiId);

        if (!$pegawai) {
            error_log("Pegawai tidak ditemukan dengan ID: " . $pegawaiId);
            throw $this->createNotFoundException('Pegawai tidak ditemukan');
        }

        error_log("Pegawai ditemukan: " . $pegawai->getNama() . " (NIP: " . $pegawai->getNip() . ")");

        // Debug: cari semua pegawai dengan nama yang sama
        $allPegawaiSameName = $pegawaiRepo->createQueryBuilder('p')
            ->where('p.nama LIKE :nama')
            ->setParameter('nama', '%' . $pegawai->getNama() . '%')
            ->getQuery()
            ->getResult();

        error_log("Total pegawai dengan nama serupa: " . count($allPegawaiSameName));
        foreach ($allPegawaiSameName as $pg) {
            error_log("- Pegawai ID: {$pg->getId()}, Nama: {$pg->getNama()}, NIP: {$pg->getNip()}");
        }

        // Siapkan range tanggal berdasarkan periode dan filter
        if ($filter === 'bulan') {
            // Handle format YYYY-MM or fallback to current month
            if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
                $tanggalAwal = new \DateTime($periode . '-01 00:00:00');
                $tanggalAkhir = new \DateTime($periode . '-' . date('t', strtotime($periode . '-01')) . ' 23:59:59');
            } else {
                // Fallback to current month if format is not correct
                $currentMonth = date('Y-m');
                $tanggalAwal = new \DateTime($currentMonth . '-01 00:00:00');
                $tanggalAkhir = new \DateTime($currentMonth . '-' . date('t') . ' 23:59:59');
                error_log("Invalid periode format, using current month: " . $currentMonth);
            }
        } else {
            // Handle format YYYY-MM-DD or fallback to today
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $periode)) {
                $tanggalAwal = new \DateTime($periode . ' 00:00:00');
                $tanggalAkhir = new \DateTime($periode . ' 23:59:59');
            } else {
                // Fallback to today if format is not correct
                $today = date('Y-m-d');
                $tanggalAwal = new \DateTime($today . ' 00:00:00');
                $tanggalAkhir = new \DateTime($today . ' 23:59:59');
                error_log("Invalid periode format, using today: " . $today);
            }
        }

        error_log("Tanggal Awal: " . $tanggalAwal->format('Y-m-d H:i:s'));
        error_log("Tanggal Akhir: " . $tanggalAkhir->format('Y-m-d H:i:s'));

        // Query absensi pegawai untuk periode tersebut
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);

        // Debug: cek semua absensi untuk pegawai ini
        $allAbsensi = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->where('a.pegawai = :pegawai')
            ->setParameter('pegawai', $pegawai)
            ->getQuery()
            ->getResult();

        error_log("Total semua absensi untuk pegawai ini: " . count($allAbsensi));

        // Cek juga berdasarkan NIP (mungkin ada masalah relasi)
        $absensiByNip = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->where('p.nip = :nip')
            ->setParameter('nip', $pegawai->getNip())
            ->getQuery()
            ->getResult();

        error_log("Total absensi berdasarkan NIP: " . count($absensiByNip));

        // Coba query dengan multiple approaches
        $queryBuilder = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('a.jadwalAbsensi', 'ja')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->andWhere('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir);

        // Try multiple matching approaches
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                'a.pegawai = :pegawai',
                'p.nip = :nip',
                'p.nama = :nama'
            )
        )
        ->setParameter('pegawai', $pegawai)
        ->setParameter('nip', $pegawai->getNip())
        ->setParameter('nama', $pegawai->getNama());

        $dataAbsensi = $queryBuilder
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        error_log("Data absensi ditemukan untuk periode: " . count($dataAbsensi));

        // Jika tidak ada data untuk periode, coba ambil semua data pegawai ini
        if (count($dataAbsensi) === 0) {
            error_log("Tidak ada data untuk periode, mencoba semua data pegawai");

            $fallbackBuilder = $absensiRepo->createQueryBuilder('a')
                ->leftJoin('a.pegawai', 'p')
                ->leftJoin('a.jadwalAbsensi', 'ja')
                ->leftJoin('a.konfigurasiJadwal', 'kj');

            $fallbackBuilder->andWhere(
                $fallbackBuilder->expr()->orX(
                    'a.pegawai = :pegawai',
                    'p.nip = :nip',
                    'p.nama = :nama'
                )
            )
            ->setParameter('pegawai', $pegawai)
            ->setParameter('nip', $pegawai->getNip())
            ->setParameter('nama', $pegawai->getNama());

            $dataAbsensi = $fallbackBuilder
                ->orderBy('a.tanggal', 'DESC')
                ->addOrderBy('a.createdAt', 'DESC')
                ->setMaxResults(50) // Limit untuk testing
                ->getQuery()
                ->getResult();

            error_log("Total semua data pegawai (fallback): " . count($dataAbsensi));
        }

        // Hitung statistik
        $totalHadir = 0;
        $totalTerlambat = 0;
        $totalTidakHadir = 0;
        $totalIzin = 0;

        foreach ($dataAbsensi as $absensi) {
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            switch ($status) {
                case 'hadir':
                    $totalHadir++;
                    break;
                case 'terlambat':
                    $totalTerlambat++;
                    break;
                case 'izin':
                case 'sakit':
                    $totalIzin++;
                    break;
                default:
                    $totalTidakHadir++;
                    break;
            }
        }

        return $this->render('admin/laporan_kehadiran/detail.html.twig', [
            'pegawai' => $pegawai,
            'data_absensi' => $dataAbsensi,
            'periode' => $periode,
            'filter' => $filter,
            'statistik' => [
                'total_hadir' => $totalHadir,
                'total_terlambat' => $totalTerlambat,
                'total_tidak_hadir' => $totalTidakHadir,
                'total_izin' => $totalIzin,
                'total_hari' => count($dataAbsensi)
            ]
        ]);
    }

    /**
     * Detail individual absensi untuk modal
     */
    #[Route('/absensi-detail/{absensiId}', name: 'app_admin_laporan_kehadiran_absensi_detail')]
    public function detailAbsensi(int $absensiId): Response
    {
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $absensi = $absensiRepo->find($absensiId);

        if (!$absensi) {
            throw $this->createNotFoundException('Data absensi tidak ditemukan');
        }

        // Format data untuk template detail modal
        $detailData = $this->formatAbsensiDetail($absensi);

        return $this->render('admin/laporan_kehadiran/detail_modal.html.twig', [
            'detail' => $detailData
        ]);
    }

    /**
     * Format data absensi untuk detail modal
     */
    private function formatAbsensiDetail(Absensi $absensi): array
    {
        $pegawai = $absensi->getPegawai();
        $jadwal = $absensi->getJadwalAbsensi() ?? $absensi->getKonfigurasiJadwal();

        // Format status kehadiran
        $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
        $statusInfo = $this->getStatusInfo($status);

        // Format lokasi GPS
        $lokasiInfo = $this->getLokasiInfo($absensi);

        // Format informasi jadwal
        $jadwalInfo = $this->getJadwalInfo($jadwal);

        // Format QR Code info
        $qrInfo = $this->getQrCodeInfo($absensi);

        // Format data teknis
        $teknisInfo = $this->getTeknisInfo($absensi);

        // Format validasi info
        $validasiInfo = $this->getValidasiInfo($absensi);

        return [
            'id' => $absensi->getId(),
            'pegawai' => [
                'nama' => $pegawai->getNama(),
                'nip' => $pegawai->getNip(),
                'jabatan' => $pegawai->getJabatan(),
                'unit_kerja' => $pegawai->getUnitKerjaEntity() ? $pegawai->getUnitKerjaEntity()->getNamaUnit() : 'Tidak terdaftar'
            ],
            'absensi' => [
                'tanggal' => $absensi->getTanggal()->format('d F Y'),
                'waktu_absensi' => [
                    'waktu_formatted' => $absensi->getWaktuAbsensi() ? $absensi->getWaktuAbsensi()->format('H:i:s') : 'Tidak tercatat'
                ],
                'status_kehadiran' => $statusInfo,
                'keterangan' => $absensi->getKeterangan() ?? 'Tidak ada keterangan',
                'jadwal_info' => $jadwalInfo,
                'qr_code_info' => $qrInfo,
                'foto_tersedia' => !empty($absensi->getFotoPath()) || !empty($absensi->getFotoSelfie()),
                'foto_path' => $this->generateFotoUrl($absensi)
            ],
            'lokasi' => $lokasiInfo,
            'teknis' => $teknisInfo,
            'validasi' => $validasiInfo
        ];
    }

    private function getStatusInfo(string $status): array
    {
        switch ($status) {
            case 'hadir':
                return [
                    'icon' => '✅',
                    'text' => 'Hadir',
                    'class' => 'bg-green-100 text-green-800',
                    'keterangan' => 'Pegawai hadir tepat waktu'
                ];
            case 'izin':
                return [
                    'icon' => '📋',
                    'text' => 'Izin',
                    'class' => 'bg-blue-100 text-blue-800',
                    'keterangan' => 'Pegawai tidak hadir dengan izin'
                ];
            case 'sakit':
                return [
                    'icon' => '🏥',
                    'text' => 'Sakit',
                    'class' => 'bg-blue-100 text-blue-800',
                    'keterangan' => 'Pegawai tidak hadir karena sakit'
                ];
            default:
                return [
                    'icon' => '❌',
                    'text' => 'Tidak Hadir',
                    'class' => 'bg-red-100 text-red-800',
                    'keterangan' => 'Pegawai tidak hadir tanpa keterangan'
                ];
        }
    }

    private function getLokasiInfo(Absensi $absensi): array
    {
        $latitude = $absensi->getLatitude();
        $longitude = $absensi->getLongitude();

        if ($latitude && $longitude) {
            return [
                'gps_tersedia' => true,
                'koordinat' => "{$latitude}, {$longitude}",
                'koordinat_array' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'google_maps_url' => "https://www.google.com/maps?q={$latitude},{$longitude}"
                ]
            ];
        }

        return [
            'gps_tersedia' => false,
            'koordinat' => 'GPS tidak tersedia'
        ];
    }

    private function getJadwalInfo($jadwal): array
    {
        if (!$jadwal) {
            return [
                'emoji' => '❓',
                'nama' => 'Tidak ada jadwal',
                'jam_mulai' => '-',
                'jam_selesai' => '-',
                'sistem' => 'Tidak terdefinisi',
                'keterangan' => 'Jadwal kerja tidak ditemukan'
            ];
        }

        // Handle different jadwal types
        if (method_exists($jadwal, 'getNamaJadwal')) {
            // JadwalAbsensi (sistem lama)
            return [
                'emoji' => '📅',
                'nama' => $jadwal->getNamaJadwal(),
                'jam_mulai' => $jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '-',
                'jam_selesai' => $jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '-',
                'sistem' => 'Jadwal Absensi (Lama)',
                'keterangan' => 'Menggunakan sistem jadwal absensi lama'
            ];
        } else {
            // KonfigurasiJadwalAbsensi (sistem baru)
            return [
                'emoji' => '⚙️',
                'nama' => method_exists($jadwal, 'getNama') ? $jadwal->getNama() : 'Konfigurasi Jadwal',
                'jam_mulai' => method_exists($jadwal, 'getJamMasuk') ? $jadwal->getJamMasuk()->format('H:i') : '-',
                'jam_selesai' => method_exists($jadwal, 'getJamPulang') ? $jadwal->getJamPulang()->format('H:i') : '-',
                'sistem' => 'Konfigurasi Jadwal (Baru)',
                'keterangan' => 'Menggunakan sistem konfigurasi jadwal baru'
            ];
        }
    }

    private function getQrCodeInfo(Absensi $absensi): array
    {
        $qrCodeScanned = $absensi->getQrCodeScanned();
        $qrCodeUsed = $absensi->getQrCodeUsed();
        $qrCode = $qrCodeUsed ?: $qrCodeScanned;

        return [
            'icon' => $qrCode ? '📱' : '❌',
            'menggunakan_qr' => !empty($qrCode),
            'kode_qr' => $qrCode ?? 'Tidak menggunakan QR',
            'status' => $qrCode ? 'QR Code tersedia' : 'Absensi manual',
            'keterangan' => $qrCode ? 'Absensi menggunakan QR Code' : 'Absensi dilakukan secara manual'
        ];
    }

    private function getTeknisInfo(Absensi $absensi): array
    {
        return [
            'system_type' => 'Web Absensi Gembira',
            'ip_address' => $absensi->getIpAddress() ?? 'Tidak tercatat',
            'created_at' => $absensi->getCreatedAt() ? $absensi->getCreatedAt()->format('d/m/Y H:i:s') : 'Tidak tercatat',
            'user_agent' => $absensi->getUserAgent() ?? 'Tidak tercatat'
        ];
    }

    private function getValidasiInfo(Absensi $absensi): array
    {
        // Check if absensi needs validation
        $perluValidasi = method_exists($absensi, 'getPerluValidasiAdmin') ? $absensi->getPerluValidasiAdmin() : false;
        $divalidasi = method_exists($absensi, 'getDivalidasiAdmin') ? $absensi->getDivalidasiAdmin() : false;

        if ($perluValidasi && !$divalidasi) {
            $status = [
                'icon' => '⏳',
                'text' => 'Menunggu Validasi',
                'class' => 'bg-yellow-100 text-yellow-800'
            ];
        } elseif ($divalidasi) {
            $status = [
                'icon' => '✅',
                'text' => 'Tervalidasi',
                'class' => 'bg-green-100 text-green-800'
            ];
        } else {
            $status = [
                'icon' => '✅',
                'text' => 'Valid',
                'class' => 'bg-green-100 text-green-800'
            ];
        }

        return [
            'status' => $status,
            'validator' => method_exists($absensi, 'getValidatorAdmin') && $absensi->getValidatorAdmin() ?
                $absensi->getValidatorAdmin()->getNamaLengkap() : 'Sistem otomatis',
            'tanggal_validasi' => method_exists($absensi, 'getTanggalValidasiAdmin') && $absensi->getTanggalValidasiAdmin() ?
                $absensi->getTanggalValidasiAdmin()->format('d/m/Y H:i:s') : 'Otomatis saat absen',
            'catatan_admin' => method_exists($absensi, 'getCatatanAdmin') ?
                ($absensi->getCatatanAdmin() ?? 'Tidak ada catatan') : 'Tidak ada catatan'
        ];
    }

    /**
     * Generate URL foto absensi yang benar
     */
    private function generateFotoUrl(Absensi $absensi): ?string
    {
        // Cek foto dari field fotoPath terlebih dahulu (sistem baru)
        $fotoPath = $absensi->getFotoPath();
        if (!empty($fotoPath)) {
            // Jika path sudah lengkap dengan domain, return as is
            if (str_contains($fotoPath, 'http')) {
                return $fotoPath;
            }
            // Jika path relatif, buat URL langsung ke public/uploads
            if (str_starts_with($fotoPath, '/uploads/')) {
                return $fotoPath; // Path sudah benar untuk public folder
            }
            return '/uploads/absensi/' . basename($fotoPath);
        }

        // Fallback ke fotoSelfie (sistem lama)
        $fotoSelfie = $absensi->getFotoSelfie();
        if (!empty($fotoSelfie)) {
            if (str_contains($fotoSelfie, 'http')) {
                return $fotoSelfie;
            }
            if (str_starts_with($fotoSelfie, '/uploads/')) {
                return $fotoSelfie;
            }
            return '/uploads/absensi/' . basename($fotoSelfie);
        }

        return null;
    }

    /**
     * Download rekap data kehadiran dalam format Excel
     */
    #[Route('/download', name: 'app_admin_laporan_kehadiran_download')]
    public function downloadRekapData(Request $request): Response
    {
        // Ambil parameter filter dari request
        $tanggal = $request->query->get('tanggal', date('Y-m-d'));
        $bulan = $request->query->get('bulan', date('Y-m'));
        $unitKerja = $request->query->get('unit_kerja', '');
        $filter = $request->query->get('filter', 'hari');

        // Siapkan tanggal untuk query
        if ($filter === 'bulan') {
            $tanggalAwal = new \DateTime($bulan . '-01 00:00:00');
            $tanggalAkhir = new \DateTime($bulan . '-' . date('t', strtotime($bulan . '-01')) . ' 23:59:59');
            $namaFile = 'Rekap_Kehadiran_' . date('Y_m', strtotime($bulan . '-01'));
        } else {
            $tanggalAwal = new \DateTime($tanggal . ' 00:00:00');
            $tanggalAkhir = new \DateTime($tanggal . ' 23:59:59');
            $namaFile = 'Rekap_Kehadiran_' . date('Y_m_d', strtotime($tanggal));
        }

        // Query data absensi dengan filter
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $queryBuilder = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('a.jadwalAbsensi', 'ja')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->where('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir);

        if (!empty($unitKerja)) {
            $queryBuilder->andWhere('uk.id = :unit_kerja')
                        ->setParameter('unit_kerja', $unitKerja);
        }

        $dataAbsensi = $queryBuilder
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('p.nama', 'ASC')
            ->getQuery()
            ->getResult();

        // Siapkan header CSV
        $csvData = [];
        $csvData[] = ['Tanggal', 'Nama Pegawai', 'NIP', 'Unit Kerja', 'Waktu Absensi', 'Status', 'Menggunakan QR', 'Lokasi GPS'];

        // Tambahkan data
        foreach ($dataAbsensi as $absensi) {
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            $waktuAbsensi = $absensi->getWaktuMasuk() ?? $absensi->getWaktuAbsensi();
            $qrUsed = ($absensi->getQrCodeUsed() || $absensi->getQrCodeScanned()) ? 'Ya' : 'Tidak';
            
            $csvData[] = [
                $absensi->getTanggal()->format('d/m/Y'),
                $absensi->getPegawai()->getNama(),
                $absensi->getPegawai()->getNip(),
                $absensi->getPegawai()->getNamaUnitKerja(),
                $waktuAbsensi ? $waktuAbsensi->format('H:i:s') : '-',
                ucfirst($status ?? 'Tidak Hadir'),
                $qrUsed,
                $absensi->getLokasiAbsensi() ?? '-'
            ];
        }

        // Buat response dengan CSV
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $namaFile . '.csv"');
        
        $output = fopen('php://temp', 'r+');
        
        // Tambahkan BOM untuk UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // Tulis data CSV
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    /**
     * API untuk mengambil detail absensi
     * Digunakan untuk modal detail pada tabel laporan kehadiran
     * 
     * Menampilkan informasi lengkap absensi dalam bahasa Indonesia yang mudah dipahami
     * oleh admin untuk keperluan maintenance dan validasi data.
     */
    #[Route('/api/detail/{id}', name: 'app_admin_laporan_kehadiran_detail', methods: ['GET'])]
    public function getAbsensiDetail(int $id): Response
    {
        // Ambil data absensi berdasarkan ID dengan eager loading untuk performa
        $absensi = $this->entityManager->getRepository(Absensi::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('a.jadwalAbsensi', 'ja') // Sistem lama
            ->leftJoin('a.konfigurasiJadwal', 'kj') // Sistem baru
            ->leftJoin('a.validatedBy', 'v')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$absensi) {
            return new Response('Data absensi tidak ditemukan', 404);
        }

        // Siapkan data detail dalam bahasa Indonesia yang mudah dipahami
        $detailData = [
            'id' => $absensi->getId(),
            // Data Pegawai
            'pegawai' => [
                'nama' => $absensi->getPegawai()->getNama(),
                'nip' => $absensi->getPegawai()->getNip(),
                'jabatan' => $absensi->getPegawai()->getJabatan() ?? 'Tidak Diketahui',
                'unit_kerja' => $absensi->getPegawai()->getNamaUnitKerja() ?? 'Tidak Ada Unit'
            ],
            // Data Absensi
            'absensi' => [
                'tanggal' => $this->formatTanggalIndonesia($absensi->getTanggal()), // Format: Senin, 15 September 2025
                'waktu_absensi' => $this->getWaktuAbsensiFormatted($absensi),
                'status_kehadiran' => $this->getStatusFormatted($absensi),
                'jadwal_info' => $this->getJadwalInfoFormatted($absensi),
                'foto_tersedia' => !empty($absensi->getFotoPath()),
                'foto_path' => $absensi->getFotoPath(),
                'qr_code_info' => $this->getQrCodeInfo($absensi),
                'keterangan' => $absensi->getKeterangan() ?? 'Tidak ada keterangan'
            ],
            // Data GPS dan Lokasi
            'lokasi' => [
                'gps_tersedia' => !empty($absensi->getLokasiAbsensi()),
                'koordinat' => $absensi->getLokasiAbsensi(),
                'koordinat_array' => $this->parseGpsCoordinates($absensi->getLokasiAbsensi())
            ],
            // Data Teknis (untuk maintenance admin)
            'teknis' => [
                'ip_address' => $absensi->getIpAddress() ?? 'Tidak Tercatat',
                'user_agent' => $absensi->getUserAgent() ?? 'Tidak Tercatat',
                'created_at' => $absensi->getCreatedAt()->format('d/m/Y H:i:s'),
                'system_type' => $this->getSystemType($absensi)
            ],
            // Data Validasi
            'validasi' => [
                'status' => $this->getValidationStatusFormatted($absensi),
                'catatan_admin' => $absensi->getCatatanAdmin() ?? 'Tidak ada catatan',
                'validator' => $absensi->getValidatedBy() ? $absensi->getValidatedBy()->getNamaLengkap() : 'Belum Divalidasi',
                'tanggal_validasi' => $absensi->getTanggalValidasi() ? 
                    $absensi->getTanggalValidasi()->format('d/m/Y H:i') : 'Belum Divalidasi'
            ]
        ];

        return $this->render('admin/laporan_kehadiran/detail_modal.html.twig', [
            'detail' => $detailData
        ]);
    }

    /**
     * Helper: Format waktu absensi dengan keterangan lengkap
     */
    private function getWaktuAbsensiFormatted(Absensi $absensi): array
    {
        $waktu = $absensi->getWaktuMasuk() ?? $absensi->getWaktuAbsensi();
        
        return [
            'waktu_raw' => $waktu,
            'waktu_formatted' => $waktu ? $waktu->format('H:i:s') : 'Tidak Tercatat',
            'keterangan' => $waktu ? 
                'Absen pada ' . $waktu->format('H:i') . ' WITA' : 
                'Waktu absensi tidak tercatat dalam sistem'
        ];
    }

    /**
     * Helper: Format status kehadiran dengan warna dan keterangan
     */
    private function getStatusFormatted(Absensi $absensi): array
    {
        $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran() ?? 'tidak_hadir';
        
        $statusMapping = [
            'hadir' => [
                'text' => 'Hadir',
                'class' => 'bg-green-100 text-green-800',
                'icon' => '✅',
                'keterangan' => 'Pegawai hadir tepat waktu'
            ],
            'terlambat' => [
                'text' => 'Terlambat', 
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => '⏰',
                'keterangan' => 'Pegawai hadir tetapi terlambat'
            ],
            'tidak_hadir' => [
                'text' => 'Tidak Hadir',
                'class' => 'bg-red-100 text-red-800', 
                'icon' => '❌',
                'keterangan' => 'Pegawai tidak melakukan absensi'
            ],
            'izin' => [
                'text' => 'Izin',
                'class' => 'bg-blue-100 text-blue-800',
                'icon' => '📄',
                'keterangan' => 'Pegawai mendapat izin tidak masuk'
            ]
        ];

        return $statusMapping[$status] ?? $statusMapping['tidak_hadir'];
    }

    /**
     * Helper: Format informasi jadwal dengan sistem yang digunakan
     */
    private function getJadwalInfoFormatted(Absensi $absensi): array
    {
        // Sistem baru (KonfigurasiJadwalAbsensi) - lebih fleksibel
        if ($absensi->getKonfigurasiJadwal()) {
            $jadwal = $absensi->getKonfigurasiJadwal();
            return [
                'nama' => $jadwal->getNamaJadwal(),
                'jam_mulai' => $jadwal->getJamMulai()->format('H:i'),
                'jam_selesai' => $jadwal->getJamSelesai()->format('H:i'),
                'sistem' => 'Sistem Jadwal Fleksibel (Baru)',
                'keterangan' => 'Menggunakan sistem jadwal baru yang lebih fleksibel',
                'emoji' => $jadwal->getEmoji() ?? '📅'
            ];
        }

        // Sistem lama (JadwalAbsensi) - fixed schedule
        if ($absensi->getJadwalAbsensi()) {
            $jadwal = $absensi->getJadwalAbsensi();
            return [
                'nama' => $jadwal->getNamaJadwal(),
                'jam_mulai' => $jadwal->getJamMasuk()->format('H:i'),
                'jam_selesai' => $jadwal->getJamKeluar()->format('H:i'),
                'sistem' => 'Sistem Jadwal Tetap (Lama)',
                'keterangan' => 'Menggunakan sistem jadwal lama dengan jam tetap',
                'emoji' => '🕐'
            ];
        }

        return [
            'nama' => 'Manual / Tidak Menggunakan Jadwal',
            'jam_mulai' => '-',
            'jam_selesai' => '-',
            'sistem' => 'Absensi Manual',
            'keterangan' => 'Absensi dilakukan secara manual tanpa jadwal sistem',
            'emoji' => '📝'
        ];
    }


    /**
     * Helper: Parse koordinat GPS menjadi array latitude dan longitude
     */
    private function parseGpsCoordinates(?string $koordinat): ?array
    {
        if (!$koordinat) {
            return null;
        }

        $coords = explode(',', $koordinat);
        if (count($coords) === 2) {
            return [
                'latitude' => trim($coords[0]),
                'longitude' => trim($coords[1]),
                'google_maps_url' => "https://www.google.com/maps?q=" . trim($coords[0]) . "," . trim($coords[1])
            ];
        }

        return null;
    }

    /**
     * Helper: Tentukan sistem yang digunakan untuk absensi
     */
    private function getSystemType(Absensi $absensi): string
    {
        if ($absensi->getKonfigurasiJadwal()) {
            return 'Sistem Baru (KonfigurasiJadwalAbsensi)';
        }
        
        if ($absensi->getJadwalAbsensi()) {
            return 'Sistem Lama (JadwalAbsensi)';
        }

        return 'Manual/Legacy';
    }

    /**
     * Helper: Format status validasi admin
     */
    private function getValidationStatusFormatted(Absensi $absensi): array
    {
        $status = $absensi->getStatusValidasi() ?? 'pending';
        
        $statusMapping = [
            'pending' => [
                'text' => 'Menunggu Validasi',
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => '⏳'
            ],
            'disetujui' => [
                'text' => 'Disetujui',
                'class' => 'bg-green-100 text-green-800',
                'icon' => '✅'
            ],
            'ditolak' => [
                'text' => 'Ditolak',
                'class' => 'bg-red-100 text-red-800',
                'icon' => '❌'
            ]
        ];

        return $statusMapping[$status] ?? $statusMapping['pending'];
    }

    /**
     * Helper: Format tanggal ke bahasa Indonesia
     * Contoh output: "Senin, 15 September 2025"
     */
    private function formatTanggalIndonesia(\DateTimeInterface $tanggal): string
    {
        // Format ke bahasa Inggris dulu
        $formatInggris = $tanggal->format('l, d F Y');
        
        // Replace hari ke bahasa Indonesia
        $formatIndonesia = str_replace([
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 
            'Friday', 'Saturday', 'Sunday'
        ], [
            'Senin', 'Selasa', 'Rabu', 'Kamis', 
            'Jumat', 'Sabtu', 'Minggu'
        ], $formatInggris);
        
        // Replace bulan ke bahasa Indonesia
        $formatIndonesia = str_replace([
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ], [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ], $formatIndonesia);
        
        return $formatIndonesia;
    }

}