<?php

namespace App\Controller;

use App\Entity\KonfigurasiJadwalAbsensi;
use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use App\Repository\AbsensiRepository;
use App\Repository\SliderRepository;
use App\Service\RankingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Controller Absensi Pegawai dengan Sistem Fleksibel
 *
 * Menangani semua proses absensi pegawai berdasarkan konfigurasi admin.
 * Mendukung 4 mode absensi: QR+Kamera, QR saja, Kamera saja, atau Tombol sederhana.
 *
 * @author Tim Developer Indonesia
 */
#[Route('/absensi', name: 'app_absensi_')]
#[IsGranted('ROLE_USER')]
class AbsensiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KonfigurasiJadwalAbsensiRepository $repositoriJadwal,
        private AbsensiRepository $repositoriAbsensi,
        private SliderRepository $sliderRepository,
        private RankingService $rankingService
    ) {}

    /**
     * Halaman Dashboard Absensi Pegawai
     * 
     * Menampilkan grid icon jadwal absensi yang tersedia untuk hari ini
     * sesuai dengan konfigurasi yang dibuat oleh admin.
     */
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboardAbsensi(): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();
        
        // Validasi: pastikan yang login adalah pegawai
        if (!$pegawai instanceof Pegawai) {
            $this->addFlash('error', 'Akses ditolak. Silakan login sebagai pegawai.');
            return $this->redirectToRoute('app_login');
        }

        // Ambil waktu dan hari saat ini dengan timezone Indonesia Tengah
        $timezoneIndonesia = new \DateTimeZone('Asia/Makassar');
        $waktuSekarang = new \DateTime('now', $timezoneIndonesia);
        $hariIni = (int)$waktuSekarang->format('N'); // 1=Senin, 7=Minggu

        // Cari semua jadwal yang tersedia untuk hari ini
        $jadwalHariIni = $this->repositoriJadwal->findJadwalTersediaUntukHari($hariIni);
        
        // Cari jadwal yang sedang dalam jam buka absensi
        $jadwalSedangBuka = $this->repositoriJadwal->findJadwalTerbukaSaatIni();

        // Siapkan data untuk template dashboard
        $daftarKartuAbsensi = [];
        foreach ($jadwalHariIni as $jadwal) {
            // Cek status absensi pegawai untuk jadwal ini
            $infoAbsensi = $this->cekStatusAbsensiHariIni($pegawai, $jadwal, $waktuSekarang);
            
            // Format data untuk template
            $daftarKartuAbsensi[] = [
                'jadwal' => $jadwal,
                'sedang_buka' => in_array($jadwal, $jadwalSedangBuka),
                'sudah_absen' => $infoAbsensi['sudah_absen'],
                'data_absensi' => $infoAbsensi['data_absensi'],
                'bisa_absen' => $this->validasiBisaAbsen($jadwal, $infoAbsensi, $jadwalSedangBuka)
            ];
        }

        // Ambil banner aktif untuk slider
        $banners = $this->sliderRepository->findActiveSliders();

        // Ambil data ranking pegawai menggunakan RankingService
        // SISTEM LAMA - Berdasarkan PERSENTASE (untuk ranking bulanan)
        $rankingPribadi = $this->rankingService->getRankingPribadi($pegawai);
        $rankingGroup = $this->rankingService->getRankingGroup($pegawai);
        $top10Pegawai = $this->rankingService->getTop10();

        // SISTEM BARU - Berdasarkan SKOR HARIAN (connect dengan admin)
        $rankingPribadiSkor = $this->rankingService->getRankingPribadiByScore($pegawai);
        $rankingGroupSkor = $this->rankingService->getRankingGroupByScore($pegawai);
        $top10Skor = $this->rankingService->getTop10ByScore();

        return $this->render('dashboard/flexible.html.twig', [
            'pegawai' => $pegawai,
            'kartu_absensi' => $daftarKartuAbsensi,
            'hari_ini' => $this->getNamaHariIndonesia($hariIni),
            'waktu_sekarang' => $waktuSekarang,
            'banners' => $banners,
            'page_title' => 'Dashboard Absensi',
            // Ranking berdasarkan persentase (bulanan)
            'ranking_pribadi' => $rankingPribadi,
            'ranking_group' => $rankingGroup,
            'top_10_pegawai' => $top10Pegawai,
            // Ranking berdasarkan skor (harian) - KONEKSI KE ADMIN
            'ranking_pribadi_skor' => $rankingPribadiSkor,
            'ranking_group_skor' => $rankingGroupSkor,
            'top_10_skor' => $top10Skor
        ]);
    }

    /**
     * Proses Absensi Berdasarkan Konfigurasi Admin
     * 
     * Menentukan jenis absensi yang akan dijalankan berdasarkan pengaturan:
     * - QR Code + Kamera: Scan QR dan ambil foto
     * - QR Code saja: Hanya scan QR
     * - Kamera saja: Hanya ambil foto
     * - Tombol sederhana: Klik tombol saja
     */
    #[Route('/proses/{id}', name: 'proses', methods: ['GET', 'POST'])]
    public function prosesAbsensi(Request $request, KonfigurasiJadwalAbsensi $jadwal): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        // Validasi: pastikan jadwal aktif dan dalam jam buka
        if (!$jadwal->isAktif() || !$jadwal->isTersediaSaatIni()) {
            $this->addFlash('error', 
                'Jadwal absensi "' . $jadwal->getNamaJadwal() . '" tidak tersedia saat ini.'
            );
            return $this->redirectToRoute('absensi_dashboard');
        }

        // Validasi: cek apakah pegawai sudah absen hari ini untuk jadwal ini
        $sudahAbsenHariIni = $this->repositoriAbsensi->findAbsensiHariIni($pegawai, $jadwal);
        if ($sudahAbsenHariIni) {
            $this->addFlash('warning', 
                'Anda sudah melakukan absensi "' . $jadwal->getNamaJadwal() . '" hari ini pada pukul ' . 
                $sudahAbsenHariIni->getWaktuAbsensi()->format('H:i:s')
            );
            return $this->redirectToRoute('absensi_dashboard');
        }

        // Tentukan jenis proses absensi berdasarkan konfigurasi admin
        if ($jadwal->isPerluQrCode() && $jadwal->isPerluKamera()) {
            // Mode lengkap: QR Code + Kamera
            return $this->prosesAbsensiQrDanKamera($request, $jadwal, $pegawai);
        } elseif ($jadwal->isPerluQrCode()) {
            // Mode QR: Hanya QR Code
            return $this->prosesAbsensiQrSaja($request, $jadwal, $pegawai);
        } elseif ($jadwal->isPerluKamera()) {
            // Mode Kamera: Hanya foto/selfie
            return $this->prosesAbsensiKameraSaja($request, $jadwal, $pegawai);
        } else {
            // Mode sederhana: Hanya tombol klik
            return $this->prosesAbsensiSederhana($request, $jadwal, $pegawai);
        }
    }

    /**
     * Halaman Riwayat Absensi Pegawai
     * 
     * Menampilkan daftar absensi pegawai untuk bulan tertentu.
     */
    #[Route('/riwayat', name: 'riwayat', methods: ['GET'])]
    public function riwayatAbsensi(Request $request): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        $bulanDipilih = $request->query->get('bulan', date('m'));
        $tahunDipilih = $request->query->get('tahun', date('Y'));

        // Ambil riwayat absensi pegawai untuk bulan yang dipilih
        $riwayatAbsensi = $this->repositoriAbsensi->findRiwayatPegawaiBulan($pegawai, $bulanDipilih, $tahunDipilih);

        return $this->render('absensi/riwayat.html.twig', [
            'riwayat_absensi' => $riwayatAbsensi,
            'pegawai' => $pegawai,
            'bulan_dipilih' => $bulanDipilih,
            'tahun_dipilih' => $tahunDipilih,
            'page_title' => 'Riwayat Absensi'
        ]);
    }

    /**
     * API Status Absensi Real-time
     *
     * Digunakan untuk update status absensi secara real-time di dashboard.
     */
    #[Route('/api/status', name: 'api_status', methods: ['GET'])]
    public function apiStatusAbsensi(): JsonResponse
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        $timezoneIndonesia = new \DateTimeZone('Asia/Makassar');
        $waktuSekarang = new \DateTime('now', $timezoneIndonesia);
        $hariIni = (int)$waktuSekarang->format('N');

        // Ambil jadwal yang sedang terbuka saat ini
        $jadwalTerbuka = $this->repositoriJadwal->findJadwalTerbukaSaatIni();

        $statusJadwal = [];
        foreach ($jadwalTerbuka as $jadwal) {
            $infoAbsensi = $this->cekStatusAbsensiHariIni($pegawai, $jadwal, $waktuSekarang);

            $statusJadwal[] = [
                'id' => $jadwal->getId(),
                'nama' => $jadwal->getNamaJadwal(),
                'jam_tutup' => $jadwal->getJamSelesai()->format('H:i'),
                'sudah_absen' => $infoAbsensi['sudah_absen'],
                'emoji' => $jadwal->getEmoji(),
                'warna' => $jadwal->getWarnaKartu()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'waktu_sekarang' => $waktuSekarang->format('H:i:s'),
            'hari' => $this->getNamaHariIndonesia($hariIni),
            'jadwal_terbuka' => $statusJadwal,
            'total_jadwal' => count($statusJadwal)
        ]);
    }

    /**
     * API endpoint untuk mendapatkan data ranking pegawai secara real-time
     * Digunakan untuk auto-refresh ranking tanpa reload halaman
     */
    #[Route('/api/ranking-update', name: 'api_ranking_update', methods: ['GET'])]
    public function apiRankingUpdate(): JsonResponse
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        // Validasi pengguna harus pegawai
        if (!$pegawai instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses tidak diizinkan'
            ]);
        }

        try {
            // Ambil data ranking terbaru - SISTEM LAMA (Persentase)
            $rankingPribadi = $this->rankingService->getRankingPribadi($pegawai);
            $rankingGroup = $this->rankingService->getRankingGroup($pegawai);
            $top10Pegawai = $this->rankingService->getTop10();

            // Ambil data ranking terbaru - SISTEM BARU (Skor)
            $rankingPribadiSkor = $this->rankingService->getRankingPribadiByScore($pegawai);
            $rankingGroupSkor = $this->rankingService->getRankingGroupByScore($pegawai);
            $top10Skor = $this->rankingService->getTop10ByScore();

            return new JsonResponse([
                'success' => true,
                // Ranking berdasarkan persentase (bulanan)
                'ranking_pribadi' => $rankingPribadi,
                'ranking_group' => $rankingGroup,
                'top_10_pegawai' => $top10Pegawai,
                // Ranking berdasarkan skor (harian) - KONEKSI KE ADMIN
                'ranking_pribadi_skor' => $rankingPribadiSkor,
                'ranking_group_skor' => $rankingGroupSkor,
                'top_10_skor' => $top10Skor,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengambil data ranking: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk validasi QR Code dan absensi langsung
     *
     * Endpoint alternatif yang bisa mencari jadwal berdasarkan QR code yang di-scan
     */
    #[Route('/api/scan-qr', name: 'api_scan_qr', methods: ['POST'])]
    public function apiScanQr(Request $request): JsonResponse
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        $qrCode = $request->request->get('qr_code');
        $fotoBase64 = $request->request->get('foto');

        if (!$qrCode) {
            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'QR Code tidak boleh kosong'
            ]);
        }

        // Cari jadwal berdasarkan QR Code dengan validasi fleksibel
        $jadwal = $this->repositoriJadwal->findValidQrCode($qrCode);

        if (!$jadwal) {
            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'QR Code tidak ditemukan atau sudah tidak berlaku. Pastikan QR Code sesuai dengan jadwal absensi yang aktif.'
            ]);
        }

        // Validasi jadwal tersedia saat ini
        if (!$jadwal->isTersediaSaatIni()) {
            $jamBuka = $jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '00:00';
            $jamTutup = $jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '23:59';

            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'Jadwal "' . $jadwal->getNamaJadwal() . '" tidak tersedia saat ini. Jam absensi: ' . $jamBuka . ' - ' . $jamTutup
            ]);
        }

        // Cek apakah pegawai sudah absen hari ini untuk jadwal ini
        $sudahAbsenHariIni = $this->repositoriAbsensi->findAbsensiHariIni($pegawai, $jadwal);
        if ($sudahAbsenHariIni) {
            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'Anda sudah melakukan absensi "' . $jadwal->getNamaJadwal() . '" hari ini pada pukul ' .
                           $sudahAbsenHariIni->getWaktuAbsensi()->format('H:i:s')
            ]);
        }

        // Validasi foto jika diperlukan
        if ($jadwal->isPerluKamera() && !$fotoBase64) {
            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'Foto selfie diperlukan untuk absensi "' . $jadwal->getNamaJadwal() . '"'
            ]);
        }

        try {
            // Simpan data absensi
            $absensi = $this->simpanDataAbsensi($pegawai, $jadwal, $fotoBase64, $qrCode);

            return new JsonResponse([
                'berhasil' => true,
                'pesan' => 'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan!',
                'waktu_absensi' => $absensi->getWaktuAbsensi()->format('H:i:s'),
                'jadwal_nama' => $jadwal->getNamaJadwal(),
                'perlu_validasi' => $jadwal->isPerluValidasiAdmin()
            ]);

        } catch (\Exception $e) {
            error_log('ERROR SCAN QR ABSENSI: ' . $e->getMessage());
            return new JsonResponse([
                'berhasil' => false,
                'pesan' => 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage()
            ]);
        }
    }

    // ============================================================
    // PRIVATE METHODS - Helper functions untuk proses internal
    // ============================================================

    /**
     * Proses Absensi Mode QR Code + Kamera
     *
     * Pegawai harus scan QR Code dan ambil foto untuk bisa absen.
     */
    private function prosesAbsensiQrDanKamera(Request $request, KonfigurasiJadwalAbsensi $jadwal, Pegawai $pegawai): Response
    {
        if ($request->isMethod('POST')) {
            $qrCodeDiScan = $request->request->get('qr_code');
            $fotoBase64 = $request->request->get('foto');

            // Validasi QR Code dengan method yang lebih fleksibel
            $validasiQr = $this->repositoriJadwal->validateQrCodeForSchedule($qrCodeDiScan, $jadwal);
            if (!$validasiQr['valid']) {
                return new JsonResponse([
                    'berhasil' => false,
                    'pesan' => $validasiQr['alasan']
                ]);
            }

            // Validasi foto
            if (!$fotoBase64) {
                return new JsonResponse([
                    'berhasil' => false,
                    'pesan' => 'Foto selfie diperlukan untuk absensi ini.'
                ]);
            }

            // Simpan data absensi
            $absensi = $this->simpanDataAbsensi($pegawai, $jadwal, $fotoBase64, $qrCodeDiScan);

            return new JsonResponse([
                'berhasil' => true,
                'pesan' => 'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan!',
                'waktu_absensi' => $absensi->getWaktuAbsensi()->format('H:i:s')
            ]);
        }

        return $this->render('absensi/form_qr_kamera.html.twig', [
            'jadwal' => $jadwal,
            'pegawai' => $pegawai,
            'page_title' => 'Absensi: ' . $jadwal->getNamaJadwal()
        ]);
    }

    /**
     * Proses Absensi Mode QR Code Saja
     *
     * Pegawai hanya perlu scan QR Code untuk absen.
     */
    private function prosesAbsensiQrSaja(Request $request, KonfigurasiJadwalAbsensi $jadwal, Pegawai $pegawai): Response
    {
        if ($request->isMethod('POST')) {
            $qrCodeDiScan = $request->request->get('qr_code');

            // Validasi QR Code dengan method yang lebih fleksibel
            $validasiQr = $this->repositoriJadwal->validateQrCodeForSchedule($qrCodeDiScan, $jadwal);
            if (!$validasiQr['valid']) {
                return new JsonResponse([
                    'berhasil' => false,
                    'pesan' => $validasiQr['alasan']
                ]);
            }

            // Simpan data absensi tanpa foto
            $absensi = $this->simpanDataAbsensi($pegawai, $jadwal, null, $qrCodeDiScan);

            return new JsonResponse([
                'berhasil' => true,
                'pesan' => 'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan!',
                'waktu_absensi' => $absensi->getWaktuAbsensi()->format('H:i:s')
            ]);
        }

        return $this->render('absensi/form_qr.html.twig', [
            'jadwal' => $jadwal,
            'pegawai' => $pegawai,
            'page_title' => 'Scan QR - ' . $jadwal->getNamaJadwal()
        ]);
    }

    /**
     * Proses Absensi Mode Kamera Saja
     * 
     * Pegawai hanya perlu ambil foto/selfie untuk absen.
     */
    private function prosesAbsensiKameraSaja(Request $request, KonfigurasiJadwalAbsensi $jadwal, Pegawai $pegawai): Response
    {
        if ($request->isMethod('POST')) {
            $fotoBase64 = $request->request->get('foto');

            // Validasi foto
            if (!$fotoBase64) {
                return new JsonResponse([
                    'berhasil' => false,
                    'pesan' => 'Foto selfie diperlukan untuk absensi ini.'
                ]);
            }

            // Simpan data absensi dengan foto
            $absensi = $this->simpanDataAbsensi($pegawai, $jadwal, $fotoBase64);

            return new JsonResponse([
                'berhasil' => true,
                'pesan' => 'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan!',
                'waktu_absensi' => $absensi->getWaktuAbsensi()->format('H:i:s')
            ]);
        }

        return $this->render('absensi/form_kamera.html.twig', [
            'jadwal' => $jadwal,
            'pegawai' => $pegawai,
            'page_title' => 'Foto Absensi - ' . $jadwal->getNamaJadwal()
        ]);
    }

    /**
     * Proses Absensi Mode Sederhana
     * 
     * Pegawai hanya perlu klik tombol untuk absen (sesuai desain awal).
     */
    private function prosesAbsensiSederhana(Request $request, KonfigurasiJadwalAbsensi $jadwal, Pegawai $pegawai): Response
    {
        if ($request->isMethod('POST')) {
            // Validasi CSRF token untuk keamanan
            if (!$this->isCsrfTokenValid('absensi_sederhana', $request->request->get('_token'))) {
                // Return JSON response jika AJAX request
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'berhasil' => false,
                        'pesan' => 'Token keamanan tidak valid.'
                    ]);
                }
                $this->addFlash('error', 'Token keamanan tidak valid.');
                return $this->redirectToRoute('absensi_dashboard');
            }

            try {
                // Simpan data absensi tanpa foto dan QR
                $absensi = $this->simpanDataAbsensi($pegawai, $jadwal);

                // Return JSON response jika AJAX request
                if ($request->isXmlHttpRequest()) {
                $pesan = 'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan!';

                // Tambahkan info validasi jika diperlukan
                if ($jadwal->isPerluValidasiAdmin()) {
                    $pesan .= ' (Menunggu validasi admin)';
                }

                return new JsonResponse([
                    'berhasil' => true,
                    'pesan' => $pesan,
                    'waktu_absensi' => $absensi->getWaktuAbsensi()->format('H:i:s'),
                    'perlu_validasi' => $jadwal->isPerluValidasiAdmin()
                ]);
            }

            $this->addFlash('success',
                'Absensi "' . $jadwal->getNamaJadwal() . '" berhasil disimpan pada pukul ' .
                $absensi->getWaktuAbsensi()->format('H:i:s') . '!'
            );

            return $this->redirectToRoute('absensi_dashboard');

            } catch (\Exception $e) {
                // Return JSON response jika AJAX request
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'berhasil' => false,
                        'pesan' => 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage()
                    ]);
                }

                $this->addFlash('error', 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage());
                return $this->redirectToRoute('absensi_dashboard');
            }
        }

        return $this->render('absensi/form_sederhana.html.twig', [
            'jadwal' => $jadwal,
            'pegawai' => $pegawai,
            'page_title' => 'Konfirmasi Absensi - ' . $jadwal->getNamaJadwal()
        ]);
    }

    /**
     * Simpan Data Absensi ke Database
     * 
     * Menyimpan record absensi pegawai dengan informasi yang diperlukan.
     */
    private function simpanDataAbsensi(
        Pegawai $pegawai, 
        KonfigurasiJadwalAbsensi $jadwal, 
        ?string $fotoBase64 = null, 
        ?string $qrCodeDigunakan = null
    ): Absensi {
        $timezoneIndonesia = new \DateTimeZone('Asia/Makassar');
        
        // Buat record absensi baru
        $absensi = new Absensi();
        $absensi->setPegawai($pegawai);
        $absensi->setKonfigurasiJadwal($jadwal);
        $absensi->setTanggal(new \DateTime('today', $timezoneIndonesia));
        $absensi->setWaktuAbsensi(new \DateTime('now', $timezoneIndonesia));
        $absensi->setStatus('hadir');

        // Tentukan status validasi berdasarkan konfigurasi jadwal
        if ($jadwal->isPerluValidasiAdmin()) {
            $absensi->setStatusValidasi('pending'); // Perlu validasi admin
        } else {
            $absensi->setStatusValidasi('disetujui'); // Langsung disetujui
        }
        
        // Simpan foto jika ada
        if ($fotoBase64) {
            $namaFileFoto = $this->simpanFotoAbsensi($fotoBase64, $pegawai, $jadwal);
            $absensi->setFotoPath($namaFileFoto);
        }

        // Simpan QR code yang digunakan jika ada
        if ($qrCodeDigunakan) {
            $absensi->setQrCodeUsed($qrCodeDigunakan);
        }

        // Simpan ke database
        $this->entityManager->persist($absensi);
        $this->entityManager->flush();

        // **SISTEM RANKING DINAMIS BARU**
        // Update ranking harian setelah absensi tersimpan
        try {
            $this->rankingService->updateDailyRanking($pegawai, $absensi->getWaktuAbsensi());
        } catch (\Exception $e) {
            // Log error tapi jangan hentikan proses absensi
            error_log('ERROR UPDATE RANKING: ' . $e->getMessage());
        }

        return $absensi;
    }

    /**
     * Simpan File Foto Absensi
     * 
     * Menyimpan foto absensi dari base64 ke file system.
     */
    private function simpanFotoAbsensi(string $fotoBase64, Pegawai $pegawai, KonfigurasiJadwalAbsensi $jadwal): string
    {
        // Decode base64 image
        $dataFoto = explode(',', $fotoBase64);
        $imageBinary = base64_decode($dataFoto[1] ?? $fotoBase64);

        // Generate nama file unik
        $namaFile = 'absensi_' . $pegawai->getId() . '_' . $jadwal->getId() . '_' . date('Y-m-d_H-i-s') . '.jpg';
        $pathFile = $this->getParameter('kernel.project_dir') . '/public/uploads/absensi/' . $namaFile;

        // Pastikan direktori upload ada
        $direktoriUpload = dirname($pathFile);
        if (!is_dir($direktoriUpload)) {
            mkdir($direktoriUpload, 0755, true);
        }

        // Simpan file gambar
        file_put_contents($pathFile, $imageBinary);

        return $namaFile;
    }

    /**
     * Cek Status Absensi Pegawai Hari Ini
     * 
     * Mengecek apakah pegawai sudah absen untuk jadwal tertentu hari ini.
     */
    private function cekStatusAbsensiHariIni(Pegawai $pegawai, KonfigurasiJadwalAbsensi $jadwal, \DateTime $tanggal): array
    {
        $absensiHariIni = $this->repositoriAbsensi->findAbsensiHariIni($pegawai, $jadwal, $tanggal);

        return [
            'sudah_absen' => $absensiHariIni !== null,
            'data_absensi' => $absensiHariIni
        ];
    }

    /**
     * Validasi Apakah Pegawai Bisa Melakukan Absensi
     * 
     * Menentukan apakah tombol absensi aktif atau tidak.
     */
    private function validasiBisaAbsen(KonfigurasiJadwalAbsensi $jadwal, array $infoAbsensi, array $jadwalSedangBuka): bool
    {
        // Jika sudah absen hari ini, tidak bisa absen lagi
        if ($infoAbsensi['sudah_absen']) {
            return false;
        }

        // Jika jadwal tidak sedang terbuka, tidak bisa absen
        if (!in_array($jadwal, $jadwalSedangBuka)) {
            return false;
        }

        return true;
    }

    /**
     * Get Nama Hari dalam Bahasa Indonesia
     * 
     * Mengkonversi nomor hari ISO ke nama hari dalam bahasa Indonesia.
     */
    private function getNamaHariIndonesia(int $nomorHari): string
    {
        $daftarHari = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];

        return $daftarHari[$nomorHari] ?? 'Tidak Valid';
    }

    /**
     * Generate QR Code untuk jadwal tertentu
     */
    #[Route('/qr/schedule/{id}', name: 'generate_qr_by_schedule')]
    public function generateQrBySchedule(KonfigurasiJadwalAbsensi $jadwal): Response
    {
        try {
            // Debug logging
            error_log("=== QR CODE GENERATION DEBUG ===");
            error_log("Jadwal ID: " . $jadwal->getId());
            error_log("Jadwal Nama: " . $jadwal->getNamaJadwal());
            error_log("Perlu QR Code: " . ($jadwal->isPerluQrCode() ? 'YES' : 'NO'));
            error_log("QR Code Data: " . ($jadwal->getQrCode() ?? 'NULL'));

            // Validasi jadwal memerlukan QR Code
            if (!$jadwal->isPerluQrCode()) {
                error_log("ERROR: Jadwal tidak memerlukan QR Code");
                throw $this->createNotFoundException('Jadwal ini tidak memerlukan QR Code');
            }

            // Generate QR Code data jika belum ada
            if (!$jadwal->getQrCode()) {
                $qrCodeData = 'GEMBIRA_' . strtoupper(str_replace([' ', '-'], '_', $jadwal->getNamaJadwal())) . '_' . $jadwal->getId();
                $jadwal->setQrCode($qrCodeData);
                $this->entityManager->flush();
                error_log("QR Code data generated: " . $qrCodeData);
            }

            // Generate QR Code image
            $qrCode = new QrCode(
                data: $jadwal->getQrCode(),
                size: 300,
                margin: 10
            );

            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            error_log("QR Code generated successfully. Size: " . strlen($result->getString()));

            return new Response($result->getString(), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=3600',
            ]);

        } catch (\Exception $e) {
            error_log("QR Code generation failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Generate a simple fallback QR Code with basic data
            try {
                $fallbackData = 'ERROR_JADWAL_' . $jadwal->getId();
                $fallbackQr = new QrCode(
                    data: $fallbackData,
                    size: 300,
                    margin: 10
                );

                $writer = new PngWriter();
                $result = $writer->write($fallbackQr);

                return new Response($result->getString(), 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'no-cache',
                ]);
            } catch (\Exception $fallbackError) {
                // If even fallback fails, return 404
                throw $this->createNotFoundException('QR Code generation completely failed');
            }
        }
    }
}