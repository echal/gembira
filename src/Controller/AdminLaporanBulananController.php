<?php

namespace App\Controller;

use App\Entity\Absensi;
use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Service\AdminPermissionService;
use App\Service\ValidationBadgeService;
use App\Service\MonthlyReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Laporan Bulanan Admin
 * 
 * Menampilkan laporan kehadiran bulanan dengan ringkasan per pegawai dan unit kerja.
 * Fitur: statistik bulanan, chart kehadiran, dan export laporan.
 * 
 * @author Indonesian Developer
 */
#[Route('/admin/laporan-bulanan')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLaporanBulananController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AdminPermissionService $permissionService;
    private ValidationBadgeService $validationBadgeService;
    private MonthlyReportService $monthlyReportService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AdminPermissionService $permissionService,
        ValidationBadgeService $validationBadgeService,
        MonthlyReportService $monthlyReportService
    ) {
        $this->entityManager = $entityManager;
        $this->permissionService = $permissionService;
        $this->validationBadgeService = $validationBadgeService;
        $this->monthlyReportService = $monthlyReportService;
    }

    /**
     * Halaman utama laporan bulanan
     *
     * PERMISSION CHECK: Admin hanya bisa lihat laporan unit kerjanya
     * Super Admin bisa lihat laporan semua unit kerja
     *
     * Menampilkan ringkasan kehadiran per bulan dan per pegawai
     */
    #[Route('/', name: 'app_admin_laporan_bulanan')]
    public function index(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Pastikan admin bisa akses laporan bulanan
        if (!$this->permissionService->canAccessFeature($admin, 'laporan_unit')) {
            $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'mengakses laporan bulanan'));
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Ambil parameter filter dari request
        $tahun = $request->query->get('tahun', date('Y'));
        $bulanNumber = $request->query->get('bulan', date('m'));
        $unitKerjaId = $request->query->get('unit_kerja', '');
        $exportFormat = $request->query->get('export', '');

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if (!$admin->isSuperAdmin()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }
            $unitKerjaId = $adminUnitKerja->getId();
        }

        // Parse month year
        $monthYearString = $tahun . '-' . $bulanNumber;
        try {
            $monthYear = $this->monthlyReportService->validateMonthYear($monthYearString);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_admin_laporan_bulanan');
        }

        // Get data from service
        $statistics = $this->monthlyReportService->getMonthlyStatistics($monthYear, $unitKerjaId ?: null);
        $employeeData = $this->monthlyReportService->getEmployeeMonthlyData($monthYear, $unitKerjaId ?: null);

        // Backward compatibility variables
        $bulan = $monthYearString;
        $statistikPegawaiArray = $employeeData;

        // KELOMPOKKAN DATA BERDASARKAN UNIT KERJA UNTUK TAMPILAN GRUP
        // Membuat struktur data yang dikelompokkan per unit kerja dengan statistik masing-masing
        $statistikPerUnitKerja = [];
        
        foreach ($statistikPegawaiArray as $dataPegawai) {
            $namaUnitKerja = $dataPegawai['unit_kerja'];
            
            // Inisialisasi unit kerja jika belum ada
            if (!isset($statistikPerUnitKerja[$namaUnitKerja])) {
                $statistikPerUnitKerja[$namaUnitKerja] = [
                    'nama_unit' => $namaUnitKerja,
                    'total_pegawai' => 0,
                    'pegawai_perlu_perhatian' => 0,  // < 75%
                    'pegawai_bagus' => 0,            // 75-89%
                    'pegawai_luar_biasa' => 0,       // >= 90%
                    'total_kehadiran_unit' => 0,
                    'total_hari_kerja_unit' => 0,
                    'daftar_pegawai' => []
                ];
            }
            
            // Tambahkan pegawai ke unit kerja yang sesuai
            $statistikPerUnitKerja[$namaUnitKerja]['daftar_pegawai'][] = $dataPegawai;
            $statistikPerUnitKerja[$namaUnitKerja]['total_pegawai']++;
            
            // Kategorikan berdasarkan persentase kehadiran
            $persentase = $dataPegawai['persentase_kehadiran'];
            if ($persentase >= 90) {
                $statistikPerUnitKerja[$namaUnitKerja]['pegawai_luar_biasa']++;
            } elseif ($persentase >= 75) {
                $statistikPerUnitKerja[$namaUnitKerja]['pegawai_bagus']++;
            } else {
                $statistikPerUnitKerja[$namaUnitKerja]['pegawai_perlu_perhatian']++;
            }
            
            // Akumulasi untuk rata-rata unit kerja
            $totalKehadiranPegawai = $dataPegawai['total_kehadiran'];
            $statistikPerUnitKerja[$namaUnitKerja]['total_kehadiran_unit'] += $totalKehadiranPegawai;
            $statistikPerUnitKerja[$namaUnitKerja]['total_hari_kerja_unit'] += $dataPegawai['total_absen_tercatat'];
        }
        
        // Hitung rata-rata persentase per unit kerja
        foreach ($statistikPerUnitKerja as $namaUnit => &$dataUnit) {
            if ($dataUnit['total_hari_kerja_unit'] > 0) {
                $rataRataPersentase = ($dataUnit['total_kehadiran_unit'] / $dataUnit['total_hari_kerja_unit']) * 100;
                $dataUnit['rata_rata_persentase'] = round($rataRataPersentase, 1);
            } else {
                $dataUnit['rata_rata_persentase'] = 0;
            }
        }
        
        // Urutkan berdasarkan nama unit kerja
        ksort($statistikPerUnitKerja);

        // Hitung ringkasan total
        $totalPegawai = count($statistikPegawaiArray);
        $totalKehadiran = 0;
        $totalAlpha = 0;
        $totalAbsenTercatat = 0;

        foreach ($statistikPegawaiArray as $data) {
            $totalKehadiran += $data['hadir'];
            $totalAlpha += $data['alpha'];
            $totalAbsenTercatat += $data['total_absen_tercatat'];
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

        // Filter daftar unit kerja berdasarkan role admin
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua unit kerja
            $availableUnitKerja = $unitKerjaList;
        } else {
            // Admin Unit hanya bisa lihat unit kerjanya sendiri
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            $availableUnitKerja = $adminUnitKerja ? [$adminUnitKerja] : [];
        }

        // PERBAIKAN: Handle export request jika ada parameter export
        if (!empty($exportFormat)) {
            return $this->handleExportRequest($exportFormat, $statistikPegawaiArray, $tahun, $bulanNumber, $unitKerjaId);
        }

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/laporan_bulanan/index.html.twig', array_merge([
            'admin' => $admin,
            'statistik_pegawai' => $statistikPegawaiArray,
            'statistik_per_unit_kerja' => $statistikPerUnitKerja,
            'unit_kerja_list' => $availableUnitKerja, // Filter berdasarkan role
            'filter' => [
                'bulan' => $bulan,
                'unit_kerja' => $unitKerjaId
            ],
            'ringkasan' => [
                'total_pegawai' => $totalPegawai,
                'total_kehadiran' => $totalKehadiran,
                'total_alpha' => $totalAlpha,
                'total_absen_tercatat' => $totalAbsenTercatat
            ],
            'nama_bulan' => $namaBulan,
            'bulan_terpilih' => $namaBulan[(int)date('n', strtotime($bulan . '-01'))] . ' ' . date('Y', strtotime($bulan . '-01'))
        ], $sidebarStats));
    }

    /**
     * Halaman detail absensi pegawai per bulan
     * Menampilkan semua catatan absensi pegawai berdasarkan ID dan periode bulan
     */
    #[Route('/detail/{pegawaiId}', name: 'app_admin_laporan_bulanan_detail')]
    public function detailPegawai(Request $request, int $pegawaiId): Response
    {
        // Ambil parameter bulan dari request (default bulan sekarang)
        $bulan = $request->query->get('bulan', date('Y-m'));
        
        // Ambil data pegawai
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        $pegawai = $pegawaiRepo->find($pegawaiId);
        
        if (!$pegawai) {
            $this->addFlash('error', 'Pegawai tidak ditemukan.');
            return $this->redirectToRoute('app_admin_laporan_bulanan');
        }

        // Siapkan tanggal untuk query detail
        $tanggalAwal = new \DateTime($bulan . '-01 00:00:00');
        $tanggalAkhir = new \DateTime($bulan . '-' . date('t', strtotime($bulan . '-01')) . ' 23:59:59');

        // QUERY DETAIL: Ambil semua absensi pegawai untuk bulan yang dipilih
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $absensiDetail = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('a.konfigurasiJadwal', 'kj')  // Sistem baru
            ->leftJoin('a.jadwalAbsensi', 'ja')     // Sistem lama
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir)
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('a.waktuAbsensi', 'DESC')
            ->getQuery()
            ->getResult();

        // Format data detail untuk template
        $detailAbsensi = [];
        $totalHariKerja = $this->getHariKerjaDalamBulan($tanggalAwal, $tanggalAkhir);
        $statistikBulan = [
            'total_hadir' => 0,
            'total_alpha' => 0,
        ];

        foreach ($absensiDetail as $absensi) {
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            $tanggal = $absensi->getTanggal();
            
            // Increment statistik
            if ($status === 'hadir') {
                $statistikBulan['total_hadir']++;
            }

            // Format data untuk tampilan
            $detailAbsensi[] = [
                'tanggal' => $tanggal,
                'hari_tanggal' => $this->formatHariTanggalIndonesia($tanggal),
                'unit_kerja' => $pegawai->getNamaUnitKerja(),
                'status' => $status,
                'waktu_absensi' => $absensi->getWaktuAbsensi(),
                'jadwal_nama' => $absensi->getKonfigurasiJadwal() ? 
                    $absensi->getKonfigurasiJadwal()->getNamaJadwal() :
                    ($absensi->getJadwalAbsensi() ? $absensi->getJadwalAbsensi()->getNamaJenisAbsensi() : 'Absensi Umum'),
                'foto_path' => $absensi->getFotoPath(),
                'lokasi_absensi' => $absensi->getLokasiAbsensi()
            ];
        }

        // Hitung alpha dari selisih hari kerja
        $totalKehadiran = $statistikBulan['total_hadir'];
        $statistikBulan['total_alpha'] = max(0, $totalHariKerja - $totalKehadiran);
        
        // Hitung persentase kehadiran
        $persentaseKehadiran = $totalHariKerja > 0 ? 
            round(($totalKehadiran / $totalHariKerja) * 100, 1) : 0;

        // Siapkan nama bulan Indonesia
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $bulanNama = $namaBulan[(int)date('n', strtotime($bulan . '-01'))];
        $tahun = (int)date('Y', strtotime($bulan . '-01'));

        return $this->render('admin/laporan_bulanan/detail.html.twig', [
            'pegawai' => $pegawai,
            'detail_absensi' => $detailAbsensi,
            'statistik_bulan' => $statistikBulan,
            'persentase_kehadiran' => $persentaseKehadiran,
            'status_kehadiran' => $this->tentukanStatusKehadiran($persentaseKehadiran),
            'total_hari_kerja' => $totalHariKerja,
            'bulan_nama' => $bulanNama,
            'tahun' => $tahun,
            'periode_filter' => $bulanNama . ' ' . $tahun,
            'admin' => $this->getUser()
        ]);
    }

    /**
     * Download laporan bulanan dalam format Excel atau PDF (implementasi sederhana)
     * Konsisten dengan AdminLaporanKehadiranController yang sudah bekerja
     */
    #[Route('/download-new', name: 'app_admin_laporan_bulanan_download_new', methods: ['POST'])]
    public function downloadLaporanBulananNew(Request $request): Response
    {
        try {
            // Validasi CSRF token untuk keamanan
            if (!$this->isCsrfTokenValid('download_laporan_bulanan', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
                return $this->redirectToRoute('app_admin_laporan_bulanan');
            }
            $year = (int) $request->request->get('year', date('Y'));
            $month = (int) $request->request->get('month', date('n'));
            $unitKerjaId = $request->request->get('unit_kerja_id') ? (int) $request->request->get('unit_kerja_id') : null;
            $format = $request->request->get('format', 'excel'); // excel atau pdf

            // Validasi input
            if ($month < 1 || $month > 12) {
                $this->addFlash('error', 'Bulan tidak valid');
                return $this->redirectToRoute('app_admin_laporan_bulanan');
            }
            if ($year < 2020 || $year > 2030) {
                $this->addFlash('error', 'Tahun tidak valid');
                return $this->redirectToRoute('app_admin_laporan_bulanan');
            }

            // Buat tanggal range
            $tanggalAwal = new \DateTime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
            $tanggalAkhir = new \DateTime($tanggalAwal->format('Y-m-t') . ' 23:59:59');
            
            // Query data absensi seperti di method index
            $absensiRepo = $this->entityManager->getRepository(Absensi::class);
            $queryBuilder = $absensiRepo->createQueryBuilder('a')
                ->leftJoin('a.pegawai', 'p')
                ->leftJoin('p.unitKerjaEntity', 'uk')
                ->where('a.tanggal >= :tanggal_awal')
                ->andWhere('a.tanggal <= :tanggal_akhir')
                ->setParameter('tanggal_awal', $tanggalAwal)
                ->setParameter('tanggal_akhir', $tanggalAkhir);

            if ($unitKerjaId) {
                $queryBuilder->andWhere('uk.id = :unit_kerja')
                            ->setParameter('unit_kerja', $unitKerjaId);
                
                // Ambil nama unit kerja
                $unitKerjaRepo = $this->entityManager->getRepository('App\Entity\UnitKerja');
                $unitKerjaEntity = $unitKerjaRepo->find($unitKerjaId);
                $namaUnit = $unitKerjaEntity ? $unitKerjaEntity->getNamaUnit() : 'Unit_' . $unitKerjaId;
            } else {
                $namaUnit = 'Semua_Unit';
            }

            $dataAbsensi = $queryBuilder
                ->orderBy('p.nama', 'ASC')
                ->addOrderBy('a.tanggal', 'ASC')
                ->getQuery()
                ->getResult();

            // DEBUG: Log jumlah data yang ditemukan
            error_log('Laporan Bulanan: Ditemukan ' . count($dataAbsensi) . ' record absensi untuk periode ' . $tanggalAwal->format('Y-m-d') . ' sampai ' . $tanggalAkhir->format('Y-m-d'));

            // Hitung statistik per pegawai
            $statistikPegawai = [];
            $totalHariKerja = $this->getHariKerjaDalamBulan($tanggalAwal, $tanggalAkhir);
            
            // DEBUG: Log total hari kerja
            error_log('Laporan Bulanan: Total hari kerja dalam periode = ' . $totalHariKerja);
            
            foreach ($dataAbsensi as $absensi) {
                $pegawaiId = $absensi->getPegawai()->getId();
                $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
                
                if (!isset($statistikPegawai[$pegawaiId])) {
                    $statistikPegawai[$pegawaiId] = [
                        'nama' => $absensi->getPegawai()->getNama(),
                        'nip' => $absensi->getPegawai()->getNip(),
                        'unit_kerja' => $absensi->getPegawai()->getNamaUnitKerja(),
                        'jabatan' => $absensi->getPegawai()->getJabatan(),
                        'hadir' => 0,
                        'alpha' => 0,
                        'total_hari_kerja' => $totalHariKerja
                    ];
                }

                if ($status === 'hadir') {
                    $statistikPegawai[$pegawaiId]['hadir']++;
                }
                // Alpha akan dihitung setelah loop selesai berdasarkan selisih hari kerja
            }

            // Hitung alpha untuk setiap pegawai (selisih hari kerja dengan total kehadiran)
            foreach ($statistikPegawai as $pegawaiId => &$data) {
                $totalHadir = $data['hadir'];
                $data['alpha'] = max(0, $data['total_hari_kerja'] - $totalHadir);
            }

            // Jika tidak ada data statistik, buat laporan kosong
            if (empty($statistikPegawai)) {
                $this->addFlash('warning', 'Tidak ada data absensi untuk periode yang dipilih. Membuat laporan kosong.');
                // Buat entry kosong untuk laporan
                $statistikPegawai['kosong'] = [
                    'nama' => 'Tidak ada data',
                    'nip' => '-',
                    'unit_kerja' => $namaUnit,
                    'jabatan' => '-',
                    'hadir' => 0,
                    'alpha' => 0,
                    'total_hari_kerja' => $totalHariKerja
                ];
            }

            // Siapkan nama file
            $namaBulan = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            
            $filename = sprintf(
                'Laporan_Bulanan_%s_%d_%s',
                $namaBulan[$month],
                $year,
                str_replace([' ', '/', ','], '_', $namaUnit)
            );

            // DEBUG: Log sebelum generate
            error_log('Laporan Bulanan: Akan generate format ' . $format . ' dengan ' . count($statistikPegawai) . ' pegawai');
            
            if ($format === 'pdf') {
                // Generate PDF sederhana menggunakan HTML
                return $this->generatePDFReport($statistikPegawai, $namaBulan[$month], $year, $namaUnit, $filename);
            } else {
                // Generate CSV seperti di AdminLaporanKehadiranController
                return $this->generateCSVReport($statistikPegawai, $filename);
            }

        } catch (\Exception $e) {
            // Log error untuk debugging dengan format konsisten
            error_log('Laporan Bulanan Download Error: ' . $e->getMessage() . ' - File: ' . $e->getFile() . ' - Line: ' . $e->getLine());
            
            $this->addFlash('error', 'Gagal menggenerate laporan: ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_laporan_bulanan');
        }
    }

    /**
     * Test download functionality (debug route)
     */
    #[Route('/test-download', name: 'app_admin_laporan_bulanan_test_download')]
    public function testDownload(Request $request): Response
    {
        try {
            // Test data sederhana
            $testData = [
                [
                    'nama' => 'Test Pegawai 1',
                    'nip' => '123456789',
                    'unit_kerja' => 'Test Unit',
                    'jabatan' => 'Test Jabatan',
                    'hadir' => 20,
                    'alpha' => 0,
                    'total_hari_kerja' => 22
                ],
                [
                    'nama' => 'Test Pegawai 2',
                    'nip' => '987654321',
                    'unit_kerja' => 'Test Unit 2',
                    'jabatan' => 'Test Jabatan 2',
                    'hadir' => 18,
                    'alpha' => 1,
                    'total_hari_kerja' => 22
                ]
            ];

            $format = $request->query->get('format', 'excel');

            if ($format === 'pdf') {
                return $this->generatePDFReport($testData, 'September', 2025, 'Test Unit', 'Test_Laporan_PDF');
            } else {
                return $this->generateCSVReport($testData, 'Test_Laporan_Excel');
            }

        } catch (\Exception $e) {
            return new Response('Error: ' . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString(), 500, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * Download laporan bulanan dalam format Excel (legacy)
     */
    #[Route('/download', name: 'app_admin_laporan_bulanan_download')]
    public function downloadLaporanBulanan(Request $request): Response
    {
        $bulan = $request->query->get('bulan', date('Y-m'));
        $unitKerja = $request->query->get('unit_kerja', '');

        // Siapkan tanggal untuk query
        $tanggalAwal = new \DateTime($bulan . '-01 00:00:00');
        $tanggalAkhir = new \DateTime($bulan . '-' . date('t', strtotime($bulan . '-01')) . ' 23:59:59');
        $namaFile = 'Laporan_Bulanan_' . date('Y_m', strtotime($bulan . '-01'));

        // Query data absensi
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $queryBuilder = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->where('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir);

        if (!empty($unitKerja)) {
            $queryBuilder->andWhere('uk.id = :unit_kerja')
                        ->setParameter('unit_kerja', $unitKerja);
        }

        $dataAbsensi = $queryBuilder
            ->orderBy('p.nama', 'ASC')
            ->addOrderBy('a.tanggal', 'ASC')
            ->getQuery()
            ->getResult();

        // Hitung statistik per pegawai untuk CSV
        $statistikPegawai = [];
        $totalHariKerja = $this->getHariKerjaDalamBulan($tanggalAwal, $tanggalAkhir);
        
        foreach ($dataAbsensi as $absensi) {
            $pegawaiId = $absensi->getPegawai()->getId();
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            
            if (!isset($statistikPegawai[$pegawaiId])) {
                $statistikPegawai[$pegawaiId] = [
                    'nama' => $absensi->getPegawai()->getNama(),
                    'nip' => $absensi->getPegawai()->getNip(),
                    'unit_kerja' => $absensi->getPegawai()->getNamaUnitKerja(),
                    'hadir' => 0,
                    'terlambat' => 0,
                    'alpha' => 0
                ];
            }

            if ($status === 'hadir') {
                $statistikPegawai[$pegawaiId]['hadir']++;
            } else {
                $statistikPegawai[$pegawaiId]['alpha']++;
            }
        }

        // Siapkan data CSV
        $csvData = [];
        $csvData[] = ['Nama Pegawai', 'NIP', 'Unit Kerja', 'Hadir', 'Terlambat', 'Alpha', 'Total Hari Kerja', 'Persentase Kehadiran'];

        foreach ($statistikPegawai as $data) {
            $totalHadir = $data['hadir'];
            $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100, 1) : 0;
            
            $csvData[] = [
                $data['nama'],
                $data['nip'],
                $data['unit_kerja'],
                $data['hadir'],
                0, // izin atau sakit
                $data['alpha'],
                $totalHariKerja,
                $persentase . '%'
            ];
        }

        // Buat response dengan CSV
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $namaFile . '.csv"');
        
        $output = fopen('php://temp', 'r+');
        fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8
        
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    /**
     * Hitung jumlah hari kerja dalam bulan (Senin-Jumat)
     */
    /**
     * PERBAIKAN: Hitung jumlah hari kerja berdasarkan jadwal admin (sama seperti UserLaporanController)
     *
     * Menggunakan jadwal rutin yang dibuat admin, bukan default Senin-Jumat
     * Ini memastikan konsistensi perhitungan antara laporan admin dan user
     */
    private function getHariKerjaDalamBulan(\DateTime $tanggalAwal, \DateTime $tanggalAkhir): int
    {
        $totalHariKerja = 0;
        $tanggalIterator = clone $tanggalAwal;

        // QUERY: Ambil semua jadwal rutin yang aktif (sama seperti UserLaporanController)
        $jadwalAbsensiRepo = $this->entityManager->getRepository(\App\Entity\KonfigurasiJadwalAbsensi::class);
        $daftarJadwalAktif = $jadwalAbsensiRepo->createQueryBuilder('j')
            ->where('j.isAktif = :aktif')
            ->setParameter('aktif', true)
            ->getQuery()
            ->getResult();

        // PERHITUNGAN: Loop setiap hari dalam bulan
        while ($tanggalIterator <= $tanggalAkhir) {
            $nomorHariSaatIni = (int)$tanggalIterator->format('N'); // 1=Senin, 7=Minggu

            // CEK: Apakah ada jadwal absensi untuk hari ini?
            foreach ($daftarJadwalAktif as $jadwal) {
                if ($this->cekHariMasukJadwal($nomorHariSaatIni, $jadwal)) {
                    $totalHariKerja++;
                    // PENTING: Multiple jadwal per hari dihitung terpisah
                    // Contoh: Apel Pagi + Rapat Sore = 2 target absensi
                }
            }

            // ITERASI: Pindah ke hari berikutnya
            $tanggalIterator->modify('+1 day');
        }

        return $totalHariKerja;
    }

    /**
     * HELPER METHOD: Cek apakah hari tertentu masuk dalam rentang jadwal
     * (sama seperti di UserLaporanController untuk konsistensi)
     */
    private function cekHariMasukJadwal(int $nomorHari, \App\Entity\KonfigurasiJadwalAbsensi $jadwal): bool
    {
        $hariMulai = $jadwal->getHariMulai();
        $hariSelesai = $jadwal->getHariSelesai();

        // CASE 1: Jadwal dalam seminggu normal (Senin-Jumat)
        if ($hariMulai <= $hariSelesai) {
            return $nomorHari >= $hariMulai && $nomorHari <= $hariSelesai;
        }

        // CASE 2: Jadwal lintas minggu (Jumat-Senin)
        return $nomorHari >= $hariMulai || $nomorHari <= $hariSelesai;
    }

    /**
     * Generate CSV report dengan format jadwal spesifik atau konsisten seperti AdminLaporanKehadiranController
     */
    private function generateCSVReport(array $statistikPegawai, string $filename): Response
    {
        try {
            // DEBUG: Log awal proses CSV
            error_log('CSV Generator: Memulai pembuatan CSV dengan ' . count($statistikPegawai) . ' pegawai');

            // Cek apakah data menggunakan format jadwal spesifik atau format lama
            $isJadwalSpesifik = !empty($statistikPegawai) && isset($statistikPegawai[0]['apel_pagi_total']);

            // Siapkan header CSV dengan BOM untuk UTF-8
            $csvData = [];

            if ($isJadwalSpesifik) {
                // Format baru dengan jadwal spesifik
                $csvData[] = [
                    'Nama Pegawai',
                    'NIP',
                    'Unit Kerja',
                    'Absen Apel Pagi (Total)',
                    'Absen Apel Pagi (Hadir)',
                    'Absen Ibadah Pagi (Total)',
                    'Absen Ibadah Pagi (Hadir)',
                    'Total Alpha',
                    'Total Jadwal',
                    'Total Hadir',
                    'Persentase Kehadiran (%)'
                ];

                // Tambahkan data statistik pegawai dengan format jadwal
                foreach ($statistikPegawai as $data) {
                    $csvData[] = [
                        $data['nama'],
                        $data['nip'],
                        $data['unit_kerja'] ?? 'Tidak Ada Unit',
                        $data['apel_pagi_total'],
                        $data['apel_pagi_hadir'],
                        $data['ibadah_pagi_total'],
                        $data['ibadah_pagi_hadir'],
                        $data['total_alpha'],
                        $data['total_jadwal'],
                        $data['total_hadir'],
                        $data['persentase_kehadiran'] . '%'
                    ];
                }
            } else {
                // Format lama - KONSISTEN DENGAN ATTENDANCECALCULATIONSERVICE
                $csvData[] = [
                    'Nama Pegawai',
                    'NIP',
                    'Unit Kerja',
                    'Jabatan',
                    'Hadir',
                    'Terlambat',
                    'Izin',
                    'Sakit',
                    'Tidak Hadir',
                    'Total Absen Tercatat',
                    'Persentase Kehadiran (%)',
                    'Status Kehadiran'
                ];

                // Tambahkan data statistik pegawai - KONSISTEN DENGAN ATTENDANCECALCULATIONSERVICE
                foreach ($statistikPegawai as $data) {
                    $csvData[] = [
                        $data['nama'],
                        $data['nip'],
                        $data['unit_kerja'] ?? 'Tidak Ada Unit',
                        $data['jabatan'] ?? 'Tidak Diketahui',
                        $data['hadir'],
                        0, // izin atau sakit
                        $data['izin'] ?? 0,
                        $data['sakit'] ?? 0,
                        $data['tidak_hadir'] ?? $data['alpha'] ?? 0,
                        $data['total_absen_tercatat'] ?? 0,
                        $data['persentase_kehadiran'] . '%',
                        $data['status']['text'] ?? 'Tidak Diketahui'
                    ];
                }
            }

            // DEBUG: Log jumlah baris yang akan dibuat
            error_log('CSV Generator: Akan membuat ' . count($csvData) . ' baris CSV (termasuk header)');

            // Gunakan output buffer untuk generate CSV
            $output = fopen('php://temp', 'r+');

            // Tambahkan BOM untuk UTF-8 (agar Excel bisa baca dengan benar)
            fputs($output, "\xEF\xBB\xBF");

            // Tulis data CSV
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }

            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            // DEBUG: Log ukuran file CSV
            error_log('CSV Generator: File CSV berhasil dibuat dengan ukuran ' . strlen($csvContent) . ' bytes');

            // Buat response CSV dengan encoding UTF-8
            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'application/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'public');

            return $response;

        } catch (\Exception $e) {
            error_log('CSV Generator Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate PDF report sederhana menggunakan HTML (konsisten dengan sistem yang ada)
     */
    private function generatePDFReport(array $statistikPegawai, string $bulan, int $tahun, string $unitKerja, string $filename): Response
    {
        try {
            // DEBUG: Log awal proses PDF
            error_log('PDF Generator: Memulai pembuatan PDF untuk ' . count($statistikPegawai) . ' pegawai');

            // Generate HTML untuk PDF (bisa di-print atau save as PDF dari browser)
            $htmlContent = $this->renderView('admin/laporan_bulanan/export_pdf.html.twig', [
                'statistik_pegawai' => $statistikPegawai,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'unit_kerja' => $unitKerja,
                'tanggal_export' => new \DateTime(),
                'total_pegawai' => count($statistikPegawai)
            ]);

            // DEBUG: Log ukuran HTML yang dihasilkan
            error_log('PDF Generator: HTML berhasil dibuat dengan ukuran ' . strlen($htmlContent) . ' bytes');

            $response = new Response($htmlContent);
            $response->headers->set('Content-Type', 'text/html; charset=utf-8');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '.html"');
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'public');

            return $response;

        } catch (\Exception $e) {
            error_log('PDF Generator Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper method: Tentukan status kehadiran berdasarkan persentase
     * 
     * @param float $persentase Persentase kehadiran (0-100)
     * @return array Status dengan informasi untuk tampilan
     */
    private function tentukanStatusKehadiran(float $persentase): array
    {
        if ($persentase >= 90) {
            return [
                'text' => 'Luar Biasa',
                'class' => 'bg-green-100 text-green-800',
                'emoji' => '🟢'
            ];
        } elseif ($persentase >= 75) {
            return [
                'text' => 'Bagus',
                'class' => 'bg-yellow-100 text-yellow-800',
                'emoji' => '🟡'
            ];
        } else {
            return [
                'text' => 'Perlu Perhatian',
                'class' => 'bg-red-100 text-red-800',
                'emoji' => '🔴'
            ];
        }
    }

    /**
     * Helper method: Format tanggal ke bahasa Indonesia
     *
     * @param \DateTime $tanggal Tanggal yang akan diformat
     * @return string Format: "Senin, 1 September 2025"
     */
    private function formatHariTanggalIndonesia(\DateTime $tanggal): string
    {
        $namaHari = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $hari = $namaHari[(int)$tanggal->format('N')];
        $tanggalNum = (int)$tanggal->format('j');
        $bulan = $namaBulan[(int)$tanggal->format('n')];
        $tahun = $tanggal->format('Y');

        return $hari . ', ' . $tanggalNum . ' ' . $bulan . ' ' . $tahun;
    }

    /**
     * FUNGSI HELPER BARU: Hitung jumlah hari tertentu dalam rentang bulan
     *
     * Fungsi ini digunakan untuk menghitung total jadwal absensi yang seharusnya ada
     * berdasarkan hari-hari spesifik dalam bulan tersebut.
     *
     * @param \DateTime $tanggalAwal Tanggal mulai periode (awal bulan)
     * @param \DateTime $tanggalAkhir Tanggal akhir periode (akhir bulan)
     * @param array $daftarHari Array nomor hari (1=Senin, 2=Selasa, dst)
     * @return int Jumlah hari yang cocok dalam periode
     *
     * Contoh penggunaan:
     * - hitungHariTertentuDalamBulan($awal, $akhir, [1]) // Hitung semua hari Senin
     * - hitungHariTertentuDalamBulan($awal, $akhir, [2,3,4]) // Hitung Selasa-Kamis
     */
    private function hitungHariTertentuDalamBulan(\DateTime $tanggalAwal, \DateTime $tanggalAkhir, array $daftarHari): int
    {
        $totalHari = 0;
        $tanggalIterator = clone $tanggalAwal;

        // PERULANGAN: Cek setiap hari dalam rentang periode
        while ($tanggalIterator <= $tanggalAkhir) {
            // Ambil nomor hari dalam seminggu (1=Senin, 7=Minggu)
            $nomorHariSaatIni = (int)$tanggalIterator->format('N');

            // CEK: Apakah hari ini termasuk dalam daftar hari yang dicari?
            if (in_array($nomorHariSaatIni, $daftarHari)) {
                $totalHari++;
            }

            // ITERASI: Pindah ke hari berikutnya
            $tanggalIterator->modify('+1 day');
        }

        return $totalHari;
    }

    /**
     * PERBAIKAN: Handle export request dengan role-based data yang konsisten
     */
    private function handleExportRequest(string $format, array $statistikPegawai, string $tahun, string $bulan, ?string $unitKerja): Response
    {
        try {
            // Ambil nama unit kerja untuk filename
            if (!empty($unitKerja)) {
                $unitKerjaRepo = $this->entityManager->getRepository('App\Entity\UnitKerja');
                $unitKerjaEntity = $unitKerjaRepo->find($unitKerja);
                $namaUnit = $unitKerjaEntity ? $unitKerjaEntity->getNamaUnit() : 'Unit_' . $unitKerja;
            } else {
                $namaUnit = 'Semua_Unit';
            }

            // PERBAIKAN BARU: Ambil data berdasarkan jadwal absensi spesifik
            $statistikJadwalSpesifik = $this->getStatistikByJadwalAbsensi($tahun, $bulan, $unitKerja);

            // Ambil data kepala kantor dan kepala bidang untuk tanda tangan
            $kepalaKantor = $this->entityManager->getRepository('App\Entity\KepalaKantor')
                ->findOneBy(['isAktif' => true]);
            $kepalaBidang = null;

            if (!empty($unitKerja)) {
                $kepalaBidang = $this->entityManager->getRepository('App\Entity\KepalaBidang')
                    ->findOneBy(['unitKerja' => $unitKerja]);
            }

            // Siapkan nama file
            $namaBulan = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
            ];

            $filename = sprintf(
                'Laporan_Bulanan_%s_%s_%s',
                $namaBulan[$bulan] ?? 'Unknown',
                $tahun,
                str_replace([' ', '/', ','], '_', $namaUnit)
            );

            if ($format === 'pdf') {
                return $this->generatePDFReportWithJadwal(
                    $statistikJadwalSpesifik,
                    $namaBulan[$bulan] ?? 'Unknown',
                    (int)$tahun,
                    $namaUnit,
                    $filename,
                    $kepalaKantor,
                    $kepalaBidang
                );
            } else {
                return $this->generateCSVReport($statistikJadwalSpesifik, $filename);
            }

        } catch (\Exception $e) {
            error_log('Export Error: ' . $e->getMessage());
            $this->addFlash('error', 'Gagal menggenerate laporan: ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_laporan_bulanan');
        }
    }

    /**
     * BARU: Ambil statistik berdasarkan jadwal absensi spesifik (Apel Pagi dan Ibadah Pagi)
     *
     * PERBAIKAN: Menghitung total jadwal berdasarkan hari spesifik
     * - Apel Pagi: hanya hari Senin
     * - Ibadah Pagi: hanya hari Selasa, Rabu, Kamis
     */
    private function getStatistikByJadwalAbsensi(string $tahun, string $bulan, ?string $unitKerja): array
    {
        // Buat tanggal range
        $tanggalAwal = new \DateTime($tahun . '-' . $bulan . '-01 00:00:00');
        $tanggalAkhir = new \DateTime($tahun . '-' . $bulan . '-' . date('t', strtotime($tahun . '-' . $bulan . '-01')) . ' 23:59:59');

        // Ambil jadwal "Apel Pagi" dan "Ibadah Pagi"
        $jadwalRepo = $this->entityManager->getRepository('App\Entity\KonfigurasiJadwalAbsensi');
        $jadwalApelPagi = $jadwalRepo->findOneBy(['namaJadwal' => 'Apel Pagi', 'isAktif' => true]);
        $jadwalIbadahPagi = $jadwalRepo->findOneBy(['namaJadwal' => 'Ibadah Pagi', 'isAktif' => true]);

        // Debug log untuk memastikan jadwal ditemukan
        error_log('Jadwal Apel Pagi: ' . ($jadwalApelPagi ? 'Found ID=' . $jadwalApelPagi->getId() : 'Not Found'));
        error_log('Jadwal Ibadah Pagi: ' . ($jadwalIbadahPagi ? 'Found ID=' . $jadwalIbadahPagi->getId() : 'Not Found'));

        // PERBAIKAN: Hitung jumlah hari spesifik dalam bulan
        $totalHariSenin = $this->hitungHariTertentuDalamBulan($tanggalAwal, $tanggalAkhir, [1]); // Senin = 1
        $totalHariSelasaKamis = $this->hitungHariTertentuDalamBulan($tanggalAwal, $tanggalAkhir, [2, 3, 4]); // Selasa=2, Rabu=3, Kamis=4

        // Debug log untuk perhitungan hari
        error_log("Total hari Senin dalam bulan {$bulan}/{$tahun}: {$totalHariSenin}");
        error_log("Total hari Selasa-Kamis dalam bulan {$bulan}/{$tahun}: {$totalHariSelasaKamis}");

        // Query dasar absensi
        $absensiRepo = $this->entityManager->getRepository('App\Entity\Absensi');
        $queryBuilder = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.pegawai', 'p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->leftJoin('a.konfigurasiJadwal', 'kj')
            ->where('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->setParameter('tanggal_awal', $tanggalAwal)
            ->setParameter('tanggal_akhir', $tanggalAkhir)
            ->orderBy('p.nama', 'ASC');

        // Filter berdasarkan unit kerja jika diperlukan
        if (!empty($unitKerja)) {
            $queryBuilder->andWhere('uk.id = :unit_kerja')
                        ->setParameter('unit_kerja', $unitKerja);
        }

        $dataAbsensi = $queryBuilder->getQuery()->getResult();

        // Ambil daftar unik pegawai
        $pegawaiUnik = [];
        foreach ($dataAbsensi as $absensi) {
            $pegawaiId = $absensi->getPegawai()->getId();
            if (!isset($pegawaiUnik[$pegawaiId])) {
                $pegawaiUnik[$pegawaiId] = $absensi->getPegawai();
            }
        }

        // Inisialisasi statistik per pegawai
        $statistikPegawai = [];
        foreach ($pegawaiUnik as $pegawaiId => $pegawai) {
            $statistikPegawai[$pegawaiId] = [
                'pegawai_id' => $pegawaiId,
                'nama' => $pegawai->getNama(),
                'nip' => $pegawai->getNip(),
                'unit_kerja' => $pegawai->getNamaUnitKerja() ?? 'Tidak Ada Unit',

                // Data Apel Pagi (hanya hari Senin)
                'apel_pagi_total' => $totalHariSenin,
                'apel_pagi_hadir' => 0,

                // Data Ibadah Pagi (hanya hari Selasa-Kamis)
                'ibadah_pagi_total' => $totalHariSelasaKamis,
                'ibadah_pagi_hadir' => 0,

                // Total alpha dan persentase
                'total_alpha' => 0,
                'total_jadwal' => $totalHariSenin + $totalHariSelasaKamis,
                'total_hadir' => 0,
                'persentase_kehadiran' => 0
            ];
        }

        // Hitung kehadiran per jadwal
        foreach ($dataAbsensi as $absensi) {
            $pegawaiId = $absensi->getPegawai()->getId();
            $jadwalAbsensi = $absensi->getKonfigurasiJadwal();

            if (!isset($statistikPegawai[$pegawaiId])) {
                continue;
            }

            // Cek status kehadiran
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            $isHadir = in_array($status, ['hadir']);

            // Kategorikan berdasarkan jadwal
            if ($jadwalAbsensi && $jadwalApelPagi && $jadwalAbsensi->getId() === $jadwalApelPagi->getId()) {
                // Absensi Apel Pagi
                if ($isHadir) {
                    $statistikPegawai[$pegawaiId]['apel_pagi_hadir']++;
                }
            } elseif ($jadwalAbsensi && $jadwalIbadahPagi && $jadwalAbsensi->getId() === $jadwalIbadahPagi->getId()) {
                // Absensi Ibadah Pagi
                if ($isHadir) {
                    $statistikPegawai[$pegawaiId]['ibadah_pagi_hadir']++;
                }
            }
        }

        // Finalisasi perhitungan
        foreach ($statistikPegawai as $pegawaiId => &$data) {
            $data['total_hadir'] = $data['apel_pagi_hadir'] + $data['ibadah_pagi_hadir'];
            $data['total_alpha'] = $data['total_jadwal'] - $data['total_hadir'];
            $data['persentase_kehadiran'] = $data['total_jadwal'] > 0 ?
                round(($data['total_hadir'] / $data['total_jadwal']) * 100, 1) : 0;
        }

        return array_values($statistikPegawai);
    }

    /**
     * BARU: Generate PDF report dengan format jadwal spesifik
     */
    private function generatePDFReportWithJadwal(
        array $statistikPegawai,
        string $bulan,
        int $tahun,
        string $unitKerja,
        string $filename,
        $kepalaKantor = null,
        $kepalaBidang = null
    ): Response {
        try {
            // Tentukan jabatan kepala bidang berdasarkan unit kerja
            $jabatanKepalaBidang = 'Kepala Bagian Tata Usaha'; // Default
            if ($kepalaBidang && $kepalaBidang->getJabatan()) {
                $jabatanKepalaBidang = $kepalaBidang->getJabatan();
            } elseif ($unitKerja !== 'Semua_Unit') {
                // Format jabatan berdasarkan nama unit kerja
                if (stripos($unitKerja, 'Bimas Islam') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Bimas Islam';
                } elseif (stripos($unitKerja, 'Bimas Kristen') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Bimas Kristen';
                } elseif (stripos($unitKerja, 'Bimas Katolik') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Bimas Katolik';
                } elseif (stripos($unitKerja, 'Bimas Hindu') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Bimas Hindu';
                } elseif (stripos($unitKerja, 'Bimas Buddha') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Bimas Buddha';
                } elseif (stripos($unitKerja, 'Pendidikan') !== false || stripos($unitKerja, 'Pendis') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Pendidikan Islam';
                } elseif (stripos($unitKerja, 'Haji') !== false || stripos($unitKerja, 'PHU') !== false) {
                    $jabatanKepalaBidang = 'Kepala Bidang Penyelenggaraan Haji dan Umrah';
                } else {
                    $jabatanKepalaBidang = 'Kepala Bidang ' . $unitKerja;
                }
            }

            // Generate HTML untuk PDF
            $htmlContent = $this->renderView('admin/laporan_bulanan/export_pdf_jadwal.html.twig', [
                'statistik_pegawai' => $statistikPegawai,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'unit_kerja' => $unitKerja,
                'tanggal_export' => new \DateTime(),
                'total_pegawai' => count($statistikPegawai),
                'kepala_kantor' => $kepalaKantor,
                'kepala_bidang' => $kepalaBidang,
                'jabatan_kepala_bidang' => $jabatanKepalaBidang
            ]);

            $response = new Response($htmlContent);
            $response->headers->set('Content-Type', 'text/html; charset=utf-8');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '.html"');
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'public');

            return $response;

        } catch (\Exception $e) {
            error_log('PDF Generator Error: ' . $e->getMessage());
            throw $e;
        }
    }
}