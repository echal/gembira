<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Absensi;
use App\Entity\KonfigurasiJadwalAbsensi;
use App\Repository\AbsensiRepository;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use App\Service\AdminPermissionService;
use App\Service\ValidationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Validasi Absensi Admin
 * 
 * Controller ini menghandle semua proses validasi absensi yang memerlukan
 * persetujuan admin sebelum dianggap sah. Fitur ini dirancang khusus
 * untuk jadwal-jadwal penting yang perlu kontrol ekstra.
 * 
 * FITUR UTAMA:
 * - Lihat daftar absensi yang pending validasi
 * - Review foto selfie dan lokasi absensi
 * - Approve atau reject absensi dengan alasan
 * - Dashboard statistik validasi
 * - Filter dan pencarian absensi
 * - Bulk actions untuk efisiensi admin
 * 
 * WORKFLOW VALIDASI:
 * 1. Pegawai absen pada jadwal yang perlu validasi
 * 2. Absensi masuk dengan status "pending"
 * 3. Admin review foto, lokasi, dan waktu
 * 4. Admin approve/reject dengan alasan jika perlu
 * 5. Status absensi berubah, pegawai dapat notifikasi
 * 
 * @author Indonesian Developer
 * @version 1.0
 * @since 2024
 */
#[Route('/admin/validasi-absen')]
#[IsGranted('ROLE_ADMIN')]
class AdminValidasiAbsenController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AbsensiRepository $absensiRepository,
        private AdminPermissionService $permissionService,
        private ValidationBadgeService $validationBadgeService,
        private KonfigurasiJadwalAbsensiRepository $jadwalRepository
    ) {}

    /**
     * Halaman Utama Validasi Absensi
     *
     * PERMISSION CHECK: Admin hanya bisa validasi absensi dari unit kerjanya
     * Super Admin bisa validasi semua absensi
     *
     * Menampilkan dashboard validasi dengan:
     * - Statistik jumlah pending, approved, rejected (berdasarkan unit kerja admin)
     * - Daftar absensi yang perlu divalidasi (filter otomatis berdasarkan unit kerja)
     * - Filter berdasarkan status, jadwal, tanggal
     * - Search berdasarkan nama pegawai atau NIP
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/', name: 'app_admin_validasi_absen')]
    public function index(Request $request): Response
    {
        // Ambil admin yang sedang login untuk log aktivitas
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Pastikan admin dapat mengakses fitur validasi absensi
        if (!$this->permissionService->canAccessFeature($admin, 'validasi_absensi_unit') &&
            !$admin->isSuperAdmin()) {
            $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'mengakses validasi absensi'));
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Ambil parameter filter dari request
        $filterStatus = $request->query->get('status', 'pending'); // Default: pending
        $filterJadwal = $request->query->get('jadwal', '');
        // PERBAIKAN: Jangan gunakan default tanggal, biarkan kosong agar menampilkan semua
        $filterTanggal = $request->query->get('tanggal', '');
        $searchQuery = $request->query->get('search', '');

        // Hitung statistik validasi untuk dashboard (berdasarkan unit kerja admin)
        $statistik = $this->hitungStatistikValidasi($admin, $filterTanggal);

        // Ambil daftar absensi berdasarkan filter (otomatis filter berdasarkan unit kerja admin)
        $daftarAbsensi = $this->ambilDaftarAbsensiDenganFilter(
            $admin,
            $filterStatus,
            $filterJadwal,
            $filterTanggal,
            $searchQuery
        );

        // Ambil daftar jadwal untuk dropdown filter
        $daftarJadwal = $this->jadwalRepository->findJadwalYangPerluValidasi();

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/validasi_absen.html.twig', array_merge([
            'admin' => $admin,
            'stats' => $statistik,
            'daftarAbsensi' => $daftarAbsensi,
            'daftarJadwal' => $daftarJadwal,
            'filter_status' => $filterStatus,
            'filter_jadwal' => $filterJadwal,
            'filter_tanggal' => $filterTanggal,
            'search_query' => $searchQuery
        ], $sidebarStats));
    }

    /**
     * API untuk Approve Absensi
     * 
     * Endpoint ini digunakan untuk menyetujui absensi yang pending.
     * Setelah di-approve, absensi akan dianggap sah dan masuk ke laporan.
     * 
     * @param Request $request
     * @param int $id ID absensi yang akan di-approve
     * @return JsonResponse
     */
    #[Route('/approve/{id}', name: 'app_admin_approve_absensi', methods: ['POST'])]
    public function approveAbsensi(Request $request, int $id): JsonResponse
    {
        try {
            // Cari absensi berdasarkan ID
            $absensi = $this->absensiRepository->find($id);
            
            if (!$absensi) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Absensi tidak ditemukan'
                ], 404);
            }

            // Validasi: hanya absensi pending yang bisa di-approve
            if ($absensi->getStatusValidasi() !== 'pending') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Hanya absensi dengan status pending yang dapat di-approve'
                ], 400);
            }

            // Ambil admin yang melakukan approve
            /** @var Admin $admin */
            $admin = $this->getUser();

            // PERMISSION CHECK: Pastikan admin dapat mengelola absensi pegawai ini
            if (!$this->permissionService->canManagePegawai($admin, $absensi->getPegawai())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->permissionService->getAccessDeniedMessage($admin, 'menyetujui absensi pegawai dari unit kerja lain')
                ], 403);
            }

            // Update status absensi menjadi approved
            $absensi->setStatusValidasi('disetujui');
            $absensi->setValidatedBy($admin);
            $absensi->setValidatedAt(new \DateTime());
            $absensi->setKeteranganValidasi('Absensi disetujui oleh admin');

            // Simpan perubahan ke database
            $this->entityManager->flush();

            // Log aktivitas admin (opsional - bisa ditambahkan ke sistem log)
            $this->logAktivitasAdmin($admin, 'approve_absensi', $absensi);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf(
                    '✅ Absensi %s berhasil disetujui!',
                    $absensi->getPegawai()->getNama()
                )
            ]);

        } catch (\Exception $e) {
            // Log error untuk debugging
            error_log('Error approve absensi: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat approve absensi'
            ], 500);
        }
    }

    /**
     * API untuk Reject Absensi
     * 
     * Endpoint ini digunakan untuk menolak absensi yang pending.
     * Absensi yang di-reject tidak akan masuk ke laporan kehadiran.
     * 
     * @param Request $request
     * @param int $id ID absensi yang akan di-reject
     * @return JsonResponse
     */
    #[Route('/reject/{id}', name: 'app_admin_reject_absensi', methods: ['POST'])]
    public function rejectAbsensi(Request $request, int $id): JsonResponse
    {
        try {
            // Ambil data JSON dari request body
            $data = json_decode($request->getContent(), true);
            $alasanPenolakan = $data['alasan'] ?? '';

            // Validasi: alasan penolakan harus ada
            if (empty($alasanPenolakan)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Alasan penolakan harus diisi'
                ], 400);
            }

            // Cari absensi berdasarkan ID
            $absensi = $this->absensiRepository->find($id);
            
            if (!$absensi) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Absensi tidak ditemukan'
                ], 404);
            }

            // Validasi: hanya absensi pending yang bisa di-reject
            if ($absensi->getStatusValidasi() !== 'pending') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Hanya absensi dengan status pending yang dapat di-reject'
                ], 400);
            }

            // Ambil admin yang melakukan reject
            /** @var Admin $admin */
            $admin = $this->getUser();

            // PERMISSION CHECK: Pastikan admin dapat mengelola absensi pegawai ini
            if (!$this->permissionService->canManagePegawai($admin, $absensi->getPegawai())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->permissionService->getAccessDeniedMessage($admin, 'menolak absensi pegawai dari unit kerja lain')
                ], 403);
            }

            // Update status absensi menjadi rejected
            $absensi->setStatusValidasi('ditolak');
            $absensi->setValidatedBy($admin);
            $absensi->setValidatedAt(new \DateTime());
            $absensi->setKeteranganValidasi($alasanPenolakan);

            // Simpan perubahan ke database
            $this->entityManager->flush();

            // Log aktivitas admin
            $this->logAktivitasAdmin($admin, 'reject_absensi', $absensi, $alasanPenolakan);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf(
                    '❌ Absensi %s ditolak. Alasan: %s',
                    $absensi->getPegawai()->getNama(),
                    $alasanPenolakan
                )
            ]);

        } catch (\Exception $e) {
            // Log error untuk debugging
            error_log('Error reject absensi: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat reject absensi'
            ], 500);
        }
    }

    /**
     * API untuk Bulk Actions
     * 
     * Endpoint untuk melakukan approve/reject pada multiple absensi sekaligus.
     * Berguna untuk efisiensi admin saat banyak absensi yang perlu divalidasi.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/bulk-action', name: 'app_admin_bulk_validation', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            // Ambil data dari request
            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';
            $absensiIds = $data['absensi_ids'] ?? [];
            $alasan = $data['alasan'] ?? '';

            // Validasi input
            if (!in_array($action, ['approve', 'reject'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Action tidak valid. Gunakan "approve" atau "reject"'
                ], 400);
            }

            if (empty($absensiIds)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Pilih minimal satu absensi untuk diproses'
                ], 400);
            }

            if ($action === 'reject' && empty($alasan)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Alasan penolakan harus diisi untuk bulk reject'
                ], 400);
            }

            // Ambil admin yang melakukan bulk action
            /** @var Admin $admin */
            $admin = $this->getUser();

            $berhasil = 0;
            $gagal = 0;

            // Proses setiap absensi
            foreach ($absensiIds as $absensiId) {
                try {
                    $absensi = $this->absensiRepository->find($absensiId);
                    
                    if (!$absensi || $absensi->getStatusValidasi() !== 'pending') {
                        $gagal++;
                        continue;
                    }

                    // Update status berdasarkan action
                    if ($action === 'approve') {
                        $absensi->setStatusValidasi('disetujui');
                        $absensi->setKeteranganValidasi('Bulk approve oleh admin');
                    } else {
                        $absensi->setStatusValidasi('ditolak');
                        $absensi->setKeteranganValidasi($alasan);
                    }

                    $absensi->setValidatedBy($admin);
                    $absensi->setValidatedAt(new \DateTime());

                    $berhasil++;

                } catch (\Exception $e) {
                    $gagal++;
                    continue;
                }
            }

            // Simpan semua perubahan
            $this->entityManager->flush();

            // Log bulk action
            $this->logAktivitasAdmin($admin, 'bulk_' . $action, null, 
                "Processed: {$berhasil} berhasil, {$gagal} gagal");

            return new JsonResponse([
                'success' => true,
                'message' => sprintf(
                    '📊 Bulk %s selesai: %d berhasil, %d gagal',
                    $action === 'approve' ? 'approval' : 'rejection',
                    $berhasil,
                    $gagal
                ),
                'berhasil' => $berhasil,
                'gagal' => $gagal
            ]);

        } catch (\Exception $e) {
            error_log('Error bulk action: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat bulk action'
            ], 500);
        }
    }

    /**
     * API untuk mendapatkan detail absensi
     * 
     * Mengembalikan informasi lengkap absensi termasuk foto, lokasi,
     * dan informasi pegawai untuk modal detail.
     * 
     * @param int $id ID absensi
     * @return JsonResponse
     */
    #[Route('/detail/{id}', name: 'app_admin_detail_absensi', methods: ['GET'])]
    public function detailAbsensi(int $id): JsonResponse
    {
        try {
            $absensi = $this->absensiRepository->find($id);
            
            if (!$absensi) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Absensi tidak ditemukan'
                ], 404);
            }

            // Siapkan data detail absensi
            $detail = [
                'id' => $absensi->getId(),
                'pegawai' => [
                    'nama' => $absensi->getPegawai()->getNama(),
                    'nip' => $absensi->getPegawai()->getNip(),
                    'unit_kerja' => $absensi->getPegawai()->getUnitKerja(),
                    'jabatan' => $absensi->getPegawai()->getJabatan()
                ],
                'jadwal' => [
                    'nama' => $absensi->getKonfigurasiJadwal() ? 
                             $absensi->getKonfigurasiJadwal()->getNamaJadwal() : 
                             ($absensi->getJadwalAbsensi() ? $absensi->getJadwalAbsensi()->getNamaJadwal() : 'N/A'),
                    'jam_mulai' => $absensi->getKonfigurasiJadwal() ? 
                                  $absensi->getKonfigurasiJadwal()->getJamMulai()->format('H:i') : 
                                  ($absensi->getJadwalAbsensi() ? $absensi->getJadwalAbsensi()->getJamMulai()->format('H:i') : 'N/A'),
                    'jam_selesai' => $absensi->getKonfigurasiJadwal() ? 
                                    $absensi->getKonfigurasiJadwal()->getJamSelesai()->format('H:i') : 
                                    ($absensi->getJadwalAbsensi() ? $absensi->getJadwalAbsensi()->getJamSelesai()->format('H:i') : 'N/A'),
                    'emoji' => $absensi->getKonfigurasiJadwal() ? 
                              $absensi->getKonfigurasiJadwal()->getEmoji() : 
                              ($absensi->getJadwalAbsensi() ? $absensi->getJadwalAbsensi()->getEmoji() : '✅')
                ],
                'absensi' => [
                    'waktu' => $absensi->getWaktuAbsensi()->format('Y-m-d H:i:s'),
                    'status_validasi' => $absensi->getStatusValidasi(),
                    'foto_path' => $absensi->getFotoPath(),
                    'latitude' => $absensi->getLatitude(),
                    'longitude' => $absensi->getLongitude(),
                    'keterangan' => $absensi->getKeterangan(),
                    'keterangan_validasi' => $absensi->getKeteranganValidasi()
                ]
            ];

            // Tambahkan informasi validator jika sudah divalidasi
            if ($absensi->getValidatedBy()) {
                $detail['validator'] = [
                    'nama' => $absensi->getValidatedBy()->getNamaLengkap(),
                    'waktu' => $absensi->getValidatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $detail
            ]);

        } catch (\Exception $e) {
            error_log('Error get detail absensi: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail absensi'
            ], 500);
        }
    }

    /**
     * Hitung Statistik Validasi untuk Dashboard
     * 
     * Menghitung jumlah absensi berdasarkan status validasi
     * untuk ditampilkan di dashboard admin.
     * 
     * @param string $tanggal Tanggal untuk filter statistik
     * @return array Statistik validasi
     */
    private function hitungStatistikValidasi(Admin $admin, string $tanggal = ''): array
    {
        try {
            // Konversi string tanggal ke DateTime jika ada
            $targetDate = !empty($tanggal) ? new \DateTime($tanggal) : new \DateTime();

            // Base query builder
            $baseQuery = $this->absensiRepository->createQueryBuilder('a')
                ->leftJoin('a.pegawai', 'p');

            // Filter berdasarkan unit kerja jika admin bukan super admin
            if (!$admin->isSuperAdmin() && $admin->getUnitKerjaEntity()) {
                $baseQuery->andWhere('p.unitKerjaEntity = :unitKerja')
                         ->setParameter('unitKerja', $admin->getUnitKerjaEntity());
            }

            // PERBAIKAN: Hitung pending dengan filter unit kerja admin
            $pending = (clone $baseQuery)
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_pending')
                ->setParameter('status_pending', 'pending')
                ->getQuery()
                ->getSingleScalarResult();

            // Hitung approved hari ini dengan filter unit kerja
            $approved = (clone $baseQuery)
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_approved')
                ->andWhere('DATE(a.validatedAt) = :tanggal')
                ->setParameter('status_approved', 'disetujui')
                ->setParameter('tanggal', $targetDate->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult();

            // Hitung rejected hari ini dengan filter unit kerja
            $rejected = (clone $baseQuery)
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_rejected')
                ->andWhere('DATE(a.validatedAt) = :tanggal')
                ->setParameter('status_rejected', 'ditolak')
                ->setParameter('tanggal', $targetDate->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult();

            // Hitung approval rate (persentase yang di-approve)
            $totalValidated = $approved + $rejected;
            $approvalRate = $totalValidated > 0 ? round(($approved / $totalValidated) * 100) : 0;

            return [
                'pending' => (int)$pending,
                'approved_today' => (int)$approved,
                'rejected_today' => (int)$rejected,
                'approval_rate' => $approvalRate
            ];

        } catch (\Exception $e) {
            error_log('Error hitung statistik validasi: ' . $e->getMessage());
            
            // Return default values jika error
            return [
                'pending' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'approval_rate' => 0
            ];
        }
    }

    /**
     * Ambil Daftar Absensi dengan Filter
     * 
     * Mengambil daftar absensi dari database berdasarkan filter
     * yang dipilih admin (status, jadwal, tanggal, search).
     * 
     * @param string $status Status validasi (pending, approved, rejected)
     * @param string $jadwal ID jadwal untuk filter
     * @param string $tanggal Tanggal untuk filter
     * @param string $search Query pencarian nama/NIP
     * @return array Daftar absensi yang sesuai filter
     */
    private function ambilDaftarAbsensiDenganFilter(
        Admin $admin,
        string $status,
        string $jadwal,
        string $tanggal,
        string $search
    ): array {
        try {
            $queryBuilder = $this->absensiRepository->createQueryBuilder('a')
                ->select('a', 'p', 'kj', 'j')
                ->join('a.pegawai', 'p')
                ->leftJoin('a.konfigurasiJadwal', 'kj')
                ->leftJoin('a.jadwalAbsensi', 'j');

            // PERMISSION FILTER: Filter berdasarkan unit kerja admin
            if (!$admin->isSuperAdmin() && $admin->getUnitKerjaEntity()) {
                $queryBuilder->andWhere('p.unitKerjaEntity = :adminUnitKerja')
                           ->setParameter('adminUnitKerja', $admin->getUnitKerjaEntity());
            }

            // PERBAIKAN: Filter KONSISTEN dengan AdminStatsSubscriber
            // Tampilkan semua absensi yang sudah masuk sistem validasi
            // Jika status adalah pending/disetujui/ditolak, berarti perlu/sudah divalidasi
            $queryBuilder->andWhere('a.statusValidasi IN (:status_validasi)')
                ->setParameter('status_validasi', ['pending', 'disetujui', 'ditolak']);

            // Filter berdasarkan status
            if (!empty($status)) {
                $queryBuilder
                    ->andWhere('a.statusValidasi = :status')
                    ->setParameter('status', $status);
            }

            // Filter berdasarkan jadwal
            if (!empty($jadwal)) {
                $queryBuilder
                    ->andWhere('kj.id = :jadwal_id OR j.id = :jadwal_id')
                    ->setParameter('jadwal_id', $jadwal);
            }

            // Filter berdasarkan tanggal - hanya jika admin memilih tanggal tertentu
            if (!empty($tanggal)) {
                $targetDate = new \DateTime($tanggal);
                $queryBuilder
                    ->andWhere('DATE(a.waktuAbsensi) = :tanggal OR DATE(a.tanggal) = :tanggal')
                    ->setParameter('tanggal', $targetDate->format('Y-m-d'));
            }

            // Search berdasarkan nama atau NIP
            if (!empty($search)) {
                $queryBuilder
                    ->andWhere('p.nama LIKE :search OR p.nip LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            // Urutkan berdasarkan waktu absen terbaru
            $queryBuilder->orderBy('a.waktuAbsensi', 'DESC');

            // Batasi hasil untuk performa (bisa disesuaikan)
            $queryBuilder->setMaxResults(100);

            $query = $queryBuilder->getQuery();
            $results = $query->getResult();

            // DEBUG: Log query yang dijalankan untuk troubleshooting
            if (count($results) === 0 && empty($tanggal) && $status === 'pending') {
                $dql = $query->getDQL();
                $parameters = [];
                foreach ($query->getParameters() as $param) {
                    $parameters[$param->getName()] = $param->getValue();
                }

                error_log(sprintf(
                    '[VALIDASI_DEBUG] Query tidak mengembalikan hasil. DQL: %s, Parameters: %s, Filter: status=%s, jadwal=%s, tanggal=%s, search=%s',
                    $dql,
                    json_encode($parameters),
                    $status,
                    $jadwal,
                    $tanggal,
                    $search
                ));

                // Query sederhana untuk debug
                $debugCount = $this->absensiRepository->createQueryBuilder('debug_a')
                    ->select('COUNT(debug_a.id)')
                    ->where('debug_a.statusValidasi = :debug_status')
                    ->setParameter('debug_status', 'pending')
                    ->getQuery()
                    ->getSingleScalarResult();

                error_log(sprintf(
                    '[VALIDASI_DEBUG] Total absensi dengan status pending di database: %d',
                    $debugCount
                ));
            }

            return $results;

        } catch (\Exception $e) {
            error_log('Error ambil daftar absensi: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Log Aktivitas Admin
     * 
     * Mencatat setiap aktivitas validasi yang dilakukan admin
     * untuk keperluan audit dan tracking.
     * 
     * @param Admin $admin Admin yang melakukan aktivitas
     * @param string $action Jenis aktivitas (approve, reject, bulk_approve, dll)
     * @param Absensi|null $absensi Absensi yang diproses (jika ada)
     * @param string|null $keterangan Keterangan tambahan
     */
    private function logAktivitasAdmin(
        Admin $admin,
        string $action,
        ?Absensi $absensi = null,
        ?string $keterangan = null
    ): void {
        try {
            // Format log message
            $logMessage = sprintf(
                '[VALIDASI ABSEN] Admin %s melakukan %s',
                $admin->getNamaLengkap(),
                $action
            );

            if ($absensi) {
                $jadwalNama = 'N/A';
                if ($absensi->getKonfigurasiJadwal()) {
                    $jadwalNama = $absensi->getKonfigurasiJadwal()->getNamaJadwal();
                } elseif ($absensi->getJadwalAbsensi()) {
                    $jadwalNama = $absensi->getJadwalAbsensi()->getNamaJadwal();
                }

                $logMessage .= sprintf(
                    ' pada absensi %s (%s)',
                    $absensi->getPegawai()->getNama(),
                    $jadwalNama
                );
            }

            if ($keterangan) {
                $logMessage .= '. Keterangan: ' . $keterangan;
            }

            // Log ke file (bisa disesuaikan dengan sistem logging yang ada)
            error_log($logMessage);

            // TODO: Implementasi log ke database jika diperlukan
            // Bisa ditambahkan tabel admin_activity_log untuk tracking yang lebih detail

        } catch (\Exception $e) {
            // Jangan sampai logging error mengganggu proses utama
            error_log('Error log aktivitas admin: ' . $e->getMessage());
        }
    }
}