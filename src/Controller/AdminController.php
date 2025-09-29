<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\KonfigurasiJadwalAbsensi;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use App\Repository\SliderRepository;
use App\Repository\AbsensiRepository;
use App\Service\MaintenanceService;
use App\Service\BackupService;
use App\Service\AdminPermissionService;
use App\Service\ValidationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller Admin Utama - Sistem Baru Sepenuhnya
 * 
 * Controller ini telah direfactor untuk menggunakan sistem jadwal absensi
 * yang sepenuhnya fleksibel dan dapat dikonfigurasi oleh admin.
 * 
 * TIDAK ADA LAGI logika hardcoded untuk jenis absensi tertentu:
 * - Semua jadwal disimpan di database
 * - Admin dapat membuat jadwal kapan saja
 * - Konfigurasi hari, jam, QR, dan kamera sepenuhnya fleksibel
 * 
 * @author Indonesian Developer
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KonfigurasiJadwalAbsensiRepository $jadwalRepository,
        private SliderRepository $sliderRepository,
        private AbsensiRepository $absensiRepository,
        private MaintenanceService $maintenanceService,
        private BackupService $backupService,
        private AdminPermissionService $permissionService,
        private ValidationBadgeService $validationBadgeService
    ) {}

    /**
     * Dashboard admin utama
     */
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // Statistik untuk dashboard (filter berdasarkan unit kerja admin)
        $statistik = $this->generateDashboardStats($admin);

        // Ambil slider aktif
        $activeSliders = $this->sliderRepository->findActiveSliders();

        // FILTER AKTIVITAS BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin lihat semua aktivitas
            $aktivitasTerbaru = $this->absensiRepository->getRecentAbsensiSistemBaru(5);
        } else {
            // Admin Unit hanya lihat aktivitas unit kerjanya
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if ($adminUnitKerja) {
                $aktivitasTerbaru = $this->absensiRepository->getRecentAbsensiByUnitKerja(5, $adminUnitKerja);
            } else {
                $aktivitasTerbaru = []; // Tidak ada aktivitas jika admin belum di-assign unit kerja
            }
        }

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/index.html.twig', array_merge([
            'admin' => $admin,
            'tanggal_hari_ini' => new \DateTime(),
            'statistik' => $statistik,
            'sliders' => $activeSliders,
            'aktivitas_terbaru' => $aktivitasTerbaru
        ], $sidebarStats));
    }

    /**
     * Halaman pengaturan admin - hanya untuk konfigurasi jadwal baru
     */
    #[Route('/pengaturan', name: 'app_admin_pengaturan')]
    public function pengaturan(): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();
        
        // Get server timezone information
        $serverTimezone = date_default_timezone_get();
        $currentDateTime = new \DateTime('now', new \DateTimeZone($serverTimezone));
        
        // Get backup information
        $latestBackup = $this->backupService->getLatestBackup();
        $backupInfo = $this->backupService->getBackupDirectoryInfo();
        $mysqldumpAvailable = $this->backupService->checkMysqldumpAvailable();
        
        return $this->render('admin/pengaturan.html.twig', [
            'admin' => $admin,
            'server_timezone' => $serverTimezone,
            'current_time' => $currentDateTime->format('Y-m-d H:i:s T'),
            'timezone_offset' => $currentDateTime->format('P'),
            'maintenance_mode_enabled' => $this->maintenanceService->isMaintenanceModeEnabled(),
            'maintenance_message' => $this->maintenanceService->getMaintenanceMessage(),
            'latest_backup' => $latestBackup,
            'backup_info' => $backupInfo,
            'mysqldump_available' => $mysqldumpAvailable
        ]);
    }

    /**
     * Halaman khusus untuk kelola jadwal absensi - Hanya untuk SUPER ADMIN
     */
    #[Route('/jadwal-absensi', name: 'app_admin_jadwal_absensi')]
    public function jadwalAbsensi(): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa mengatur jadwal absensi
        if (!$admin->isSuperAdmin()) {
            $this->addFlash('error', 'Akses ditolak. Hanya Super Admin yang dapat mengatur jadwal absensi.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Ambil semua jadwal yang sudah dibuat admin
        $semuaJadwal = $this->jadwalRepository->findAllWithStatus();
        
        
        return $this->render('admin/jadwal_absensi.html.twig', [
            'admin' => $admin,
            'jadwal_list' => $semuaJadwal
        ]);
    }

    /**
     * Halaman form untuk membuat jadwal absensi baru - Hanya untuk SUPER ADMIN
     */
    #[Route('/jadwal-absensi/new', name: 'app_admin_jadwal_absensi_new')]
    public function newJadwalAbsensi(): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa membuat jadwal absensi baru
        if (!$admin->isSuperAdmin()) {
            $this->addFlash('error', 'Akses ditolak. Hanya Super Admin yang dapat membuat jadwal absensi baru.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/jadwal_absensi_new.html.twig', [
            'admin' => $admin
        ]);
    }

    /**
     * API untuk membuat jadwal absensi baru - Hanya untuk SUPER ADMIN
     * Sistem sepenuhnya fleksibel berdasarkan input admin
     */
    #[Route('/jadwal-absensi/create', name: 'app_admin_create_jadwal', methods: ['POST'])]
    public function createJadwalAbsensi(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        if (!$admin instanceof Admin) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses tidak diizinkan'
            ]);
        }

        // PERMISSION CHECK: Hanya Super Admin yang bisa membuat jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat membuat jadwal absensi.'
            ]);
        }

        try {
            // Ambil data dari request
            $namaJadwal = trim($request->request->get('nama_jadwal', ''));
            $deskripsi = trim($request->request->get('deskripsi', ''));
            $hariMulai = (int) $request->request->get('hari_mulai', 1);
            $hariSelesai = (int) $request->request->get('hari_selesai', 1);
            $jamMulai = $request->request->get('jam_mulai', '');
            $jamSelesai = $request->request->get('jam_selesai', '');
            $perluQr = $request->request->get('perlu_qr_code', false) ? true : false;
            $perluKamera = $request->request->get('perlu_kamera', false) ? true : false;
            $emoji = $request->request->get('emoji', '📅');
            $warna = $request->request->get('warna_kartu', '#3B82F6');

            // Validasi input wajib
            if (empty($namaJadwal)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Nama jadwal harus diisi'
                ]);
            }

            if (empty($jamMulai) || empty($jamSelesai)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jam mulai dan jam selesai harus diisi'
                ]);
            }

            // Validasi hari (1-7)
            if ($hariMulai < 1 || $hariMulai > 7 || $hariSelesai < 1 || $hariSelesai > 7) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Hari tidak valid (harus 1-7)'
                ]);
            }

            // Parse jam
            $jamMulaiObj = \DateTime::createFromFormat('H:i', $jamMulai);
            $jamSelesaiObj = \DateTime::createFromFormat('H:i', $jamSelesai);

            if (!$jamMulaiObj || !$jamSelesaiObj) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Format jam tidak valid (gunakan HH:MM)'
                ]);
            }

            // Cek duplikasi nama jadwal
            $existingJadwal = $this->jadwalRepository->findOneBy(['namaJadwal' => $namaJadwal]);
            if ($existingJadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Jadwal dengan nama '{$namaJadwal}' sudah ada"
                ]);
            }

            // Buat jadwal baru
            $jadwal = new KonfigurasiJadwalAbsensi();
            $jadwal->setNamaJadwal($namaJadwal);
            $jadwal->setDeskripsi($deskripsi);
            $jadwal->setHariMulai($hariMulai);
            $jadwal->setHariSelesai($hariSelesai);
            $jadwal->setJamMulai($jamMulaiObj);
            $jadwal->setJamSelesai($jamSelesaiObj);
            $jadwal->setPerluQrCode($perluQr);
            $jadwal->setPerluKamera($perluKamera);
            $jadwal->setEmoji($emoji);
            $jadwal->setWarnaKartu($warna);
            $jadwal->setIsAktif(true);
            $jadwal->setDibuatOleh($admin);

            // Generate QR Code jika diperlukan
            if ($perluQr) {
                $qrCode = 'JDW_' . strtoupper(str_replace(' ', '_', $namaJadwal)) . '_' . date('Ymd_His');
                $jadwal->setQrCode($qrCode);
            }

            // Simpan ke database
            $this->entityManager->persist($jadwal);
            $this->entityManager->flush();

            // Log activity
            error_log("ADMIN CREATE JADWAL: '{$namaJadwal}' by {$admin->getNamaLengkap()}");

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Jadwal '{$namaJadwal}' berhasil dibuat",
                'jadwal' => [
                    'id' => $jadwal->getId(),
                    'nama' => $jadwal->getNamaJadwal(),
                    'hari_range' => $jadwal->getNamaHariTersedia(),
                    'jam_range' => $jamMulai . ' - ' . $jamSelesai,
                    'fitur' => [
                        'qr_code' => $perluQr,
                        'kamera' => $perluKamera
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            error_log("ERROR CREATE JADWAL: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat jadwal: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk toggle status aktif/nonaktif jadwal
     */
    #[Route('/jadwal-absensi/{id}/toggle', name: 'app_admin_toggle_jadwal', methods: ['POST'])]
    public function toggleJadwalStatus(int $id): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa toggle jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengubah status jadwal absensi.'
            ]);
        }

        try {
            // Manual entity loading dengan error handling
            $jadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->find($id);
            
            if (!$jadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ]);
            }
            
            $statusLama = $jadwal->isAktif();
            $jadwal->setIsAktif(!$statusLama);
            
            $this->entityManager->flush();

            $statusBaru = $jadwal->isAktif() ? 'diaktifkan' : 'dinonaktifkan';
            $icon = $jadwal->isAktif() ? '✅' : '❌';

            return new JsonResponse([
                'success' => true,
                'message' => "{$icon} Jadwal '{$jadwal->getNamaJadwal()}' berhasil {$statusBaru}",
                'new_status' => $jadwal->isAktif(),
                'is_aktif' => $jadwal->isAktif() // Tambahkan untuk konsistensi dengan JavaScript
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengubah status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk hapus jadwal (hard delete dengan cascade)
     */
    #[Route('/jadwal-absensi/{id}/delete', name: 'app_admin_delete_jadwal_new', methods: ['DELETE'])]
    public function deleteJadwalAbsensi(int $id): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa delete jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat menghapus jadwal absensi.'
            ]);
        }

        try {
            // Manual entity loading dengan error handling
            $jadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->find($id);

            if (!$jadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ]);
            }

            $namaJadwal = $jadwal->getNamaJadwal();
            $jumlahAbsensi = count($jadwal->getDaftarAbsensi());

            // Log untuk audit trail
            error_log("🗑️ ADMIN HARD DELETE AUDIT:");
            error_log("   Jadwal: '{$namaJadwal}' (ID: {$jadwal->getId()})");
            error_log("   Admin: " . $this->getUser()->getNamaLengkap());
            error_log("   Absensi terkait: {$jumlahAbsensi} records");
            error_log("   Timestamp: " . date('Y-m-d H:i:s'));

            // Hapus file foto absensi terkait sebelum cascade delete
            $fotoCount = 0;
            foreach ($jadwal->getDaftarAbsensi() as $absensi) {
                if ($absensi->getFotoPath()) {
                    $fotoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/absensi/' . $absensi->getFotoPath();
                    if (file_exists($fotoPath)) {
                        @unlink($fotoPath);
                        $fotoCount++;
                        error_log("   Foto dihapus: " . $absensi->getFotoPath());
                    }
                }
            }
            error_log("   Total foto dihapus: {$fotoCount} files");

            // Hapus jadwal (cascade akan menghapus semua absensi terkait)
            error_log("🗑️ DELETING JADWAL: " . $jadwal->getNamaJadwal() . " (ID: " . $jadwal->getId() . ")");
            $this->entityManager->remove($jadwal);
            $this->entityManager->flush();
            error_log("✅ JADWAL AND RELATED DATA DELETED SUCCESSFULLY");

            $message = $jumlahAbsensi > 0
                ? "🗑️ HAPUS PERMANEN BERHASIL!\n\n✅ Yang telah dihapus:\n   • Jadwal '{$namaJadwal}'\n   • {$jumlahAbsensi} riwayat absensi pegawai\n   • {$fotoCount} foto absensi\n   • Semua data validasi terkait\n\n💀 Data tidak dapat dipulihkan!"
                : "🗑️ Jadwal '{$namaJadwal}' berhasil dihapus";

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'jadwal_id' => $jadwal->getId(),
                'deleted_absensi' => $jumlahAbsensi,
                'deleted_photos' => $fotoCount
            ]);

        } catch (\Exception $e) {
            error_log("ERROR DELETE JADWAL: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus jadwal: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk generate QR Code baru untuk jadwal
     */
    #[Route('/jadwal-absensi/{id}/generate-qr', name: 'app_admin_generate_qr', methods: ['POST'])]
    public function generateQrCode(int $id): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa generate QR Code jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat generate QR Code jadwal absensi.'
            ]);
        }

        try {
            $jadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->find($id);
            
            if (!$jadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ]);
            }

            if (!$jadwal->isPerluQrCode()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal ini tidak memerlukan QR Code'
                ]);
            }

            // Generate QR Code baru
            $qrCode = 'JDW_' . strtoupper(str_replace([' ', '-'], '_', $jadwal->getNamaJadwal())) . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
            
            $jadwal->setQrCode($qrCode);
            $jadwal->setDiubah(new \DateTime());
            
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ QR Code baru berhasil dibuat untuk jadwal '{$jadwal->getNamaJadwal()}'",
                'qr_code' => $qrCode
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal membuat QR Code: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk mengambil data jadwal untuk edit
     */
    #[Route('/jadwal-absensi/{id}/edit', name: 'app_admin_edit_jadwal', methods: ['GET'])]
    public function editJadwal(int $id): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa edit jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengedit jadwal absensi.'
            ]);
        }

        try {
            $jadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->find($id);
            
            if (!$jadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'jadwal' => [
                    'id' => $jadwal->getId(),
                    'nama_jadwal' => $jadwal->getNamaJadwal(),
                    'nama_jenis_absensi' => $jadwal->getNamaJadwal(),
                    'jam_mulai' => $jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '',
                    'jam_selesai' => $jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '',
                    'keterangan' => $jadwal->getKeterangan(),
                    'emoji' => $jadwal->getEmoji(),
                    'warna_kartu' => $jadwal->getWarnaKartu(),
                    'perlu_qr_code' => $jadwal->isPerluQrCode(),
                    'perlu_kamera' => $jadwal->isPerluKamera(),
                    'hari_mulai' => $jadwal->getHariMulai(),
                    'hari_selesai' => $jadwal->getHariSelesai()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengambil data jadwal: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk update jadwal
     */
    #[Route('/jadwal-absensi/{id}/update', name: 'app_admin_update_jadwal', methods: ['POST'])]
    public function updateJadwal(int $id, Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang bisa update jadwal absensi
        if (!$admin->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengupdate jadwal absensi.'
            ]);
        }

        try {
            $jadwal = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class)->find($id);
            
            if (!$jadwal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ]);
            }

            // Update data jadwal
            $jamMulai = $request->request->get('jam_mulai');
            $jamSelesai = $request->request->get('jam_selesai');
            $keterangan = $request->request->get('keterangan');

            if ($jamMulai) {
                $jadwal->setJamMulai(\DateTime::createFromFormat('H:i', $jamMulai));
            }
            if ($jamSelesai) {
                $jadwal->setJamSelesai(\DateTime::createFromFormat('H:i', $jamSelesai));
            }
            if ($keterangan !== null) {
                $jadwal->setKeterangan($keterangan);
            }

            $jadwal->setDiubah(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Jadwal '{$jadwal->getNamaJadwal()}' berhasil diupdate",
                'jadwal' => [
                    'id' => $jadwal->getId(),
                    'nama_jadwal' => $jadwal->getNamaJadwal(),
                    'jam_mulai' => $jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '',
                    'jam_selesai' => $jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '',
                    'keterangan' => $jadwal->getKeterangan()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengupdate jadwal: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Halaman manajemen QR codes
     */
    #[Route('/qr-codes', name: 'app_admin_qr_codes')]
    public function qrCodes(): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();
        
        // Ambil semua jadwal yang menggunakan QR Code
        $jadwalDenganQr = $this->jadwalRepository->findBy([
            'perluQrCode' => true,
            'isAktif' => true
        ]);
        
        // Format data QR codes untuk template
        $qrCodes = [];
        foreach ($jadwalDenganQr as $jadwal) {
            // Generate QR code if not exists
            if (!$jadwal->getQrCode()) {
                $qrCodeData = 'GEMBIRA_' . strtoupper(str_replace([' ', '-'], '_', $jadwal->getNamaJadwal())) . '_' . $jadwal->getId();
                $jadwal->setQrCode($qrCodeData);
                $this->entityManager->flush();
            }
            
            $qrCodes[] = [
                'id' => $jadwal->getId(),
                'nama' => $jadwal->getNamaJadwal(),
                'jenis' => strtolower(str_replace([' ', '-'], '_', $jadwal->getNamaJadwal())),
                'emoji' => $jadwal->getEmoji(),
                'jam_mulai' => $jadwal->getJamMulai() ? $jadwal->getJamMulai()->format('H:i') : '00:00',
                'jam_selesai' => $jadwal->getJamSelesai() ? $jadwal->getJamSelesai()->format('H:i') : '23:59',
                'hari' => $this->getHariText($jadwal->getHariMulai(), $jadwal->getHariSelesai()),
                'qr_code' => $jadwal->getQrCode(),
                'url' => $this->generateUrl('app_absensi_generate_qr_by_schedule', ['id' => $jadwal->getId()]),
                'keterangan' => $jadwal->getKeterangan()
            ];
        }
        
        return $this->render('admin/qr_codes.html.twig', [
            'admin' => $admin,
            'tanggal_hari_ini' => new \DateTime(),
            'qr_codes' => $qrCodes
        ]);
    }

    /**
     * Helper untuk mengkonversi hari ke text
     */
    private function getHariText(?int $hariMulai, ?int $hariSelesai): string
    {
        if ($hariMulai === null || $hariSelesai === null) {
            return 'Tidak diatur';
        }
        
        $namaHari = [
            1 => 'Senin',
            2 => 'Selasa', 
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu'
        ];
        
        if ($hariMulai === $hariSelesai) {
            return $namaHari[$hariMulai] ?? 'Tidak diketahui';
        }
        
        $hariText = [];
        // Handle range of days
        if ($hariMulai <= $hariSelesai) {
            for ($i = $hariMulai; $i <= $hariSelesai; $i++) {
                if (isset($namaHari[$i])) {
                    $hariText[] = $namaHari[$i];
                }
            }
        } else {
            // Handle wrap-around (e.g., Sabtu to Senin)
            for ($i = $hariMulai; $i <= 7; $i++) {
                if (isset($namaHari[$i])) {
                    $hariText[] = $namaHari[$i];
                }
            }
            for ($i = 1; $i <= $hariSelesai; $i++) {
                if (isset($namaHari[$i])) {
                    $hariText[] = $namaHari[$i];
                }
            }
        }
        
        return implode(', ', $hariText);
    }

    /**
     * Generate statistik untuk dashboard admin
     */
    private function generateDashboardStats(Admin $admin): array
    {
        // Filter statistik berdasarkan role admin
        if ($admin->isSuperAdmin()) {
            // Super Admin lihat semua statistik
            $totalJadwal = $this->jadwalRepository->count([]);
            $jadwalAktif = $this->jadwalRepository->count(['isAktif' => true]);
            $jadwalPerluQr = $this->jadwalRepository->countJadwalPerluQr();
            $jadwalPerluKamera = $this->jadwalRepository->countJadwalPerluKamera();

            // Statistik pegawai
            $pegawaiRepo = $this->entityManager->getRepository('App\Entity\Pegawai');
            $totalPegawai = $pegawaiRepo->count([]);

            // Statistik absensi hari ini
            $absensiHariIni = $this->absensiRepository->count([
                'tanggal' => new \DateTime('today')
            ]);

        } else {
            // Admin Unit hanya lihat statistik unit kerjanya
            $adminUnitKerja = $admin->getUnitKerjaEntity();

            if ($adminUnitKerja) {
                // Jadwal untuk unit kerja ini (asumsi ada relasi)
                $totalJadwal = $this->jadwalRepository->count([]);
                $jadwalAktif = $this->jadwalRepository->count(['isAktif' => true]);
                $jadwalPerluQr = $this->jadwalRepository->countJadwalPerluQr();
                $jadwalPerluKamera = $this->jadwalRepository->countJadwalPerluKamera();

                // Pegawai di unit kerja ini
                $pegawaiRepo = $this->entityManager->getRepository('App\Entity\Pegawai');
                $totalPegawai = $pegawaiRepo->count(['unitKerjaEntity' => $adminUnitKerja]);

                // Absensi hari ini dari pegawai unit kerja ini
                $absensiHariIni = $this->absensiRepository->createQueryBuilder('a')
                    ->leftJoin('a.pegawai', 'p')
                    ->where('a.tanggal = :today')
                    ->andWhere('p.unitKerjaEntity = :unitKerja')
                    ->setParameter('today', new \DateTime('today'))
                    ->setParameter('unitKerja', $adminUnitKerja)
                    ->select('COUNT(a.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
            } else {
                // Admin belum di-assign unit kerja
                $totalJadwal = 0;
                $jadwalAktif = 0;
                $jadwalPerluQr = 0;
                $jadwalPerluKamera = 0;
                $totalPegawai = 0;
                $absensiHariIni = 0;
            }
        }

        return [
            'total_jadwal' => $totalJadwal,
            'jadwal_aktif' => $jadwalAktif,
            'jadwal_nonaktif' => $totalJadwal - $jadwalAktif,
            'jadwal_perlu_qr' => $jadwalPerluQr,
            'jadwal_perlu_kamera' => $jadwalPerluKamera,
            'total_pegawai' => $totalPegawai ?? 0,
            'absensi_hari_ini' => (int)$absensiHariIni,
            'unit_kerja_admin' => $admin->isSuperAdmin() ? 'Semua Unit Kerja' : ($admin->getUnitKerjaEntity()?->getNamaUnit() ?? 'Belum di-assign')
        ];
    }

    /**
     * API untuk clear cache sistem
     */
    #[Route('/clear-cache', name: 'app_admin_clear_cache', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            // Clear Symfony cache
            $cacheDir = $this->getParameter('kernel.cache_dir');
            
            if (is_dir($cacheDir)) {
                $this->clearDirectory($cacheDir . '/pools');
                $this->clearDirectory($cacheDir . '/profiler');
            }
            
            // Clear Twig cache if exists
            $twigCacheDir = $cacheDir . '/../twig';
            if (is_dir($twigCacheDir)) {
                $this->clearDirectory($twigCacheDir);
            }
            
            // Get cache size info
            $cacheSize = $this->getDirectorySize($cacheDir);
            $cacheSizeFormatted = $this->formatBytes($cacheSize);
            
            return new JsonResponse([
                'success' => true,
                'message' => '✅ Cache berhasil dibersihkan',
                'cache_size' => $cacheSizeFormatted,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal membersihkan cache: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk clean old files
     */
    #[Route('/clean-old-files', name: 'app_admin_clean_old_files', methods: ['POST'])]
    public function cleanOldFiles(): JsonResponse
    {
        try {
            $deletedFiles = 0;
            $freedSpace = 0;
            $publicDir = $this->getParameter('kernel.project_dir') . '/public';
            
            // Clean old uploaded photos (older than 6 months)
            $photoDir = $publicDir . '/uploads/photos';
            if (is_dir($photoDir)) {
                $result = $this->cleanOldFilesInDirectory($photoDir, 180); // 6 months
                $deletedFiles += $result['files'];
                $freedSpace += $result['size'];
            }
            
            // Clean temporary files
            $tempDir = $publicDir . '/uploads/temp';
            if (is_dir($tempDir)) {
                $result = $this->cleanOldFilesInDirectory($tempDir, 7); // 1 week
                $deletedFiles += $result['files'];
                $freedSpace += $result['size'];
            }
            
            // Clean log files older than 1 month
            $logDir = $this->getParameter('kernel.project_dir') . '/var/log';
            if (is_dir($logDir)) {
                $result = $this->cleanOldFilesInDirectory($logDir, 30, '*.log');
                $deletedFiles += $result['files'];
                $freedSpace += $result['size'];
            }
            
            return new JsonResponse([
                'success' => true,
                'message' => "✅ Berhasil membersihkan {$deletedFiles} file lama",
                'deleted_files' => $deletedFiles,
                'freed_space' => $this->formatBytes($freedSpace),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal membersihkan file lama: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Helper untuk membersihkan direktori
     */
    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }
    }

    /**
     * Helper untuk membersihkan file lama dalam direktori
     */
    private function cleanOldFilesInDirectory(string $dir, int $daysOld, string $pattern = '*'): array
    {
        $deletedFiles = 0;
        $freedSpace = 0;
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);

        if (!is_dir($dir)) {
            return ['files' => 0, 'size' => 0];
        }

        $files = glob($dir . '/' . $pattern);
        if ($files === false) {
            return ['files' => 0, 'size' => 0];
        }

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                $fileSize = filesize($file);
                if (@unlink($file)) {
                    $deletedFiles++;
                    $freedSpace += $fileSize;
                }
            }
        }

        return ['files' => $deletedFiles, 'size' => $freedSpace];
    }

    /**
     * Helper untuk menghitung ukuran direktori
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        if (!is_dir($dir)) {
            return 0;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Helper untuk format ukuran file
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    /**
     * API untuk toggle maintenance mode
     */
    #[Route('/maintenance/toggle', name: 'app_admin_maintenance_toggle', methods: ['POST'])]
    public function toggleMaintenanceMode(Request $request): JsonResponse
    {
        try {
            /** @var Admin $admin */
            $admin = $this->getUser();

            $enable = $request->request->getBoolean('enable');
            $message = $request->request->get('message', 'Sistem sedang dalam pemeliharaan. Mohon coba beberapa saat lagi.');

            if ($enable) {
                $this->maintenanceService->enableMaintenanceMode($message, $admin);
                $statusMessage = '🔧 Mode maintenance berhasil diaktifkan';
            } else {
                $this->maintenanceService->disableMaintenanceMode($admin);
                $statusMessage = '✅ Mode maintenance berhasil dinonaktifkan';
            }

            return new JsonResponse([
                'success' => true,
                'message' => $statusMessage,
                'enabled' => $enable,
                'maintenance_message' => $this->maintenanceService->getMaintenanceMessage(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengubah mode maintenance: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk update maintenance message
     */
    #[Route('/maintenance/message', name: 'app_admin_maintenance_message', methods: ['POST'])]
    public function updateMaintenanceMessage(Request $request): JsonResponse
    {
        try {
            /** @var Admin $admin */
            $admin = $this->getUser();

            $message = $request->request->get('message', '');

            if (empty(trim($message))) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pesan maintenance tidak boleh kosong'
                ]);
            }

            $this->maintenanceService->updateMaintenanceMessage($message, $admin);

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Pesan maintenance berhasil diupdate',
                'new_message' => $message,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengupdate pesan maintenance: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk create database backup
     */
    #[Route('/backup/create', name: 'app_admin_backup_create', methods: ['POST'])]
    public function createBackup(): JsonResponse
    {
        try {
            /** @var Admin $admin */
            $admin = $this->getUser();

            $result = $this->backupService->createBackup();

            if ($result['success']) {
                // Log backup activity
                error_log("ADMIN BACKUP: Database backup created by {$admin->getNamaLengkap()}: {$result['filename']}");

                return new JsonResponse([
                    'success' => true,
                    'message' => '✅ Backup database berhasil dibuat',
                    'filename' => $result['filename'],
                    'size' => $this->formatBytes($result['size']),
                    'timestamp' => date('d/m/Y H:i', strtotime($result['timestamp']))
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Gagal membuat backup: ' . $result['error']
                ]);
            }

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal membuat backup database: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API untuk download database backup
     */
    #[Route('/backup/download/{filename}', name: 'app_admin_backup_download', methods: ['GET'])]
    public function downloadBackup(string $filename): Response
    {
        try {
            $filepath = $this->backupService->downloadBackup($filename);

            if (!$filepath) {
                throw $this->createNotFoundException('Backup file not found');
            }

            /** @var Admin $admin */
            $admin = $this->getUser();
            error_log("ADMIN DOWNLOAD BACKUP: {$filename} by {$admin->getNamaLengkap()}");

            return $this->file($filepath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mendownload backup: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * API untuk get backup list
     */
    #[Route('/backup/list', name: 'app_admin_backup_list', methods: ['GET'])]
    public function getBackupList(): JsonResponse
    {
        try {
            $backups = $this->backupService->getBackupList();
            $backupInfo = $this->backupService->getBackupDirectoryInfo();

            return new JsonResponse([
                'success' => true,
                'backups' => $backups,
                'info' => $backupInfo
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengambil daftar backup: ' . $e->getMessage()
            ]);
        }
    }
}