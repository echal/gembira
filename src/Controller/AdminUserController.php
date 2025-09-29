<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use App\Repository\UnitKerjaRepository;
use App\Service\AdminPermissionService;
use App\Service\ValidationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Manajemen User Admin
 * 
 * Mengelola penambahan, edit, dan penghapusan user admin.
 * Termasuk pengaturan role dan permissions untuk setiap user.
 * 
 * @author Indonesian Developer
 */
#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private AdminPermissionService $permissionService;
    private ValidationBadgeService $validationBadgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AdminPermissionService $permissionService,
        ValidationBadgeService $validationBadgeService
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->permissionService = $permissionService;
        $this->validationBadgeService = $validationBadgeService;
    }

    /**
     * Halaman utama manajemen user
     *
     * PERMISSION CHECK: Hanya Super Admin yang dapat mengelola user admin
     */
    #[Route('/', name: 'app_admin_user')]
    public function index(AdminRepository $adminRepository, UnitKerjaRepository $unitKerjaRepository): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang dapat mengelola user admin
        if (!$this->permissionService->canAccessFeature($admin, 'kelola_user_admin')) {
            $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'mengelola user admin'));
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Ambil data untuk dropdown (Super Admin bisa lihat semua)
        $unitKerjaList = $unitKerjaRepository->findBy([], ['namaUnit' => 'ASC']);
        $kepalaBidangList = $this->entityManager->getRepository('App\Entity\KepalaBidang')->findBy([], ['nama' => 'ASC']);
        $kepalaKantorList = $this->entityManager->getRepository('App\Entity\KepalaKantor')->findBy([], ['nama' => 'ASC']);

        // Grouping users by unit kerja
        $unitKerjaUsers = [];
        
        // Ambil unit kerja tanpa users
        $unitKerjaTanpaUsers = $this->entityManager->getRepository('App\Entity\UnitKerja')->findBy([], ['namaUnit' => 'ASC']);
        
        // Ambil semua users yang punya unit kerja
        $usersWithUnit = $adminRepository->createQueryBuilder('a')
            ->leftJoin('a.unitKerjaEntity', 'uk')
            ->where('a.unitKerjaEntity IS NOT NULL')
            ->orderBy('uk.namaUnit', 'ASC')
            ->addOrderBy('a.namaLengkap', 'ASC')
            ->getQuery()
            ->getResult();
            
        // Group users by unit kerja
        foreach ($usersWithUnit as $user) {
            $unitKerjaId = $user->getUnitKerjaEntity()->getId();
            if (!isset($unitKerjaUsers[$unitKerjaId])) {
                $unitKerjaUsers[$unitKerjaId] = [
                    'unit_kerja' => $user->getUnitKerjaEntity(),
                    'users' => []
                ];
            }
            $unitKerjaUsers[$unitKerjaId]['users'][] = $user;
        }
        
        // Ambil users tanpa unit kerja
        $usersWithoutUnit = $adminRepository->createQueryBuilder('a')
            ->where('a.unitKerjaEntity IS NULL')
            ->orderBy('a.namaLengkap', 'ASC')
            ->getQuery()
            ->getResult();

        // Calculate statistics for display
        $allUsers = $adminRepository->findBy([], ['namaLengkap' => 'ASC']);
        $statistics = [
            'total_users' => count($allUsers),
            'super_admin_count' => count(array_filter($allUsers, fn($u) => $u->getRole() === 'super_admin')),
            'admin_count' => count(array_filter($allUsers, fn($u) => $u->getRole() === 'admin')),
            'pegawai_count' => count(array_filter($allUsers, fn($u) => $u->getRole() === 'pegawai'))
        ];

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/user/index.html.twig', array_merge([
            'admin' => $admin,
            'unit_kerja_users' => $unitKerjaUsers,
            'users_without_unit' => $usersWithoutUnit,
            'statistics' => $statistics,
            'available_roles' => $this->getAvailableRoles(),
            'available_permissions' => $this->getAvailablePermissions(),
            'unit_kerja_list' => $unitKerjaList,
            'kepala_bidang_list' => $kepalaBidangList,
            'kepala_kantor_list' => $kepalaKantorList
        ], $sidebarStats));
    }

    /**
     * Tambah user baru
     *
     * PERMISSION CHECK: Hanya Super Admin yang dapat menambah user admin
     */
    #[Route('/create', name: 'app_admin_user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Hanya Super Admin yang dapat menambah user admin
        if (!$this->permissionService->canAccessFeature($admin, 'kelola_user_admin')) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->permissionService->getAccessDeniedMessage($admin, 'menambah user admin')
            ], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);

            // Validasi input
            if (!$data['username'] || !$data['namaLengkap'] || !$data['email'] || !$data['password']) {
                return new JsonResponse(['success' => false, 'message' => 'Semua field wajib diisi'], 400);
            }

            // Cek apakah username atau email sudah ada
            $existingUser = $this->entityManager->getRepository(Admin::class)
                ->findOneBy(['username' => $data['username']]);
            if ($existingUser) {
                return new JsonResponse(['success' => false, 'message' => 'Username sudah digunakan'], 400);
            }

            $existingEmail = $this->entityManager->getRepository(Admin::class)
                ->findOneBy(['email' => $data['email']]);
            if ($existingEmail) {
                return new JsonResponse(['success' => false, 'message' => 'Email sudah digunakan'], 400);
            }

            // Cek apakah NIP sudah ada (jika diisi)
            if (!empty($data['nip'])) {
                $existingNip = $this->entityManager->getRepository(Admin::class)
                    ->findOneBy(['nip' => $data['nip']]);
                if ($existingNip) {
                    return new JsonResponse(['success' => false, 'message' => 'NIP sudah digunakan'], 400);
                }
            }

            // Buat user baru
            $user = new Admin();
            $user->setUsername($data['username']);
            $user->setNamaLengkap($data['namaLengkap']);
            $user->setEmail($data['email']);
            $user->setRole($data['role'] ?? 'admin');
            $user->setStatus('aktif');
            $user->setCreatedBy($this->getUser());
            
            if (isset($data['nomorTelepon'])) {
                $user->setNomorTelepon($data['nomorTelepon']);
            }

            // Set field baru
            if (isset($data['nip'])) {
                $user->setNip($data['nip']);
            }

            // Set relasi jika ada
            if (!empty($data['unit_kerja_id'])) {
                $unitKerja = $this->entityManager->getRepository('App\Entity\UnitKerja')->find($data['unit_kerja_id']);
                if ($unitKerja) {
                    $user->setUnitKerjaEntity($unitKerja);
                }
            }

            if (!empty($data['kepala_bidang_id'])) {
                $kepalaBidang = $this->entityManager->getRepository('App\Entity\KepalaBidang')->find($data['kepala_bidang_id']);
                if ($kepalaBidang) {
                    $user->setKepalaBidang($kepalaBidang);
                }
            }

            if (!empty($data['kepala_kantor_id'])) {
                $kepalaKantor = $this->entityManager->getRepository('App\Entity\KepalaKantor')->find($data['kepala_kantor_id']);
                if ($kepalaKantor) {
                    $user->setKepalaKantor($kepalaKantor);
                }
            }

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            // Set permissions
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $user->setPermissions($data['permissions']);
            } else {
                $user->setPermissions(['validasi_absensi']); // default
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => 'User berhasil ditambahkan',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'namaLengkap' => $user->getNamaLengkap(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole(),
                    'status' => $user->getStatus()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update user
     */
    #[Route('/update/{id}', name: 'app_admin_user_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->entityManager->getRepository(Admin::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Update data
            if (isset($data['namaLengkap'])) $user->setNamaLengkap($data['namaLengkap']);
            if (isset($data['email'])) $user->setEmail($data['email']);
            if (isset($data['role'])) $user->setRole($data['role']);
            if (isset($data['status'])) $user->setStatus($data['status']);
            if (isset($data['nomorTelepon'])) $user->setNomorTelepon($data['nomorTelepon']);
            if (isset($data['nip'])) $user->setNip($data['nip']);
            
            // Update relasi
            if (isset($data['unit_kerja_id'])) {
                if (!empty($data['unit_kerja_id'])) {
                    $unitKerja = $this->entityManager->getRepository('App\Entity\UnitKerja')->find($data['unit_kerja_id']);
                    $user->setUnitKerjaEntity($unitKerja);
                } else {
                    $user->setUnitKerjaEntity(null);
                }
            }

            if (isset($data['kepala_bidang_id'])) {
                if (!empty($data['kepala_bidang_id'])) {
                    $kepalaBidang = $this->entityManager->getRepository('App\Entity\KepalaBidang')->find($data['kepala_bidang_id']);
                    $user->setKepalaBidang($kepalaBidang);
                } else {
                    $user->setKepalaBidang(null);
                }
            }

            if (isset($data['kepala_kantor_id'])) {
                if (!empty($data['kepala_kantor_id'])) {
                    $kepalaKantor = $this->entityManager->getRepository('App\Entity\KepalaKantor')->find($data['kepala_kantor_id']);
                    $user->setKepalaKantor($kepalaKantor);
                } else {
                    $user->setKepalaKantor(null);
                }
            }
            
            // Update permissions
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $user->setPermissions($data['permissions']);
            }

            // Update password jika ada
            if (!empty($data['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }

            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => 'User berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hapus user
     */
    #[Route('/delete/{id}', name: 'app_admin_user_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $user = $this->entityManager->getRepository(Admin::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
            }

            // Tidak bisa menghapus diri sendiri
            if ($user->getId() === $this->getUser()->getId()) {
                return new JsonResponse(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri'], 400);
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'User berhasil dihapus']);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get data user untuk edit
     */
    #[Route('/get/{id}', name: 'app_admin_user_get', methods: ['GET'])]
    public function getUserData(int $id): JsonResponse
    {
        $user = $this->entityManager->getRepository(Admin::class)->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'namaLengkap' => $user->getNamaLengkap(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'status' => $user->getStatus(),
                'nomorTelepon' => $user->getNomorTelepon(),
                'nip' => $user->getNip(),
                'unit_kerja_id' => $user->getUnitKerjaEntity()?->getId(),
                'kepala_bidang_id' => $user->getKepalaBidang()?->getId(),
                'kepala_kantor_id' => $user->getKepalaKantor()?->getId(),
                'permissions' => $user->getPermissions() ?? []
            ]
        ]);
    }

    /**
     * Toggle status user (aktif/nonaktif)
     */
    #[Route('/toggle-status/{id}', name: 'app_admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $user = $this->entityManager->getRepository(Admin::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
            }

            // Tidak bisa menonaktifkan diri sendiri
            if ($user->getId() === $this->getUser()->getId()) {
                return new JsonResponse(['success' => false, 'message' => 'Tidak dapat menonaktifkan akun sendiri'], 400);
            }

            $newStatus = $user->getStatus() === 'aktif' ? 'nonaktif' : 'aktif';
            $user->setStatus($newStatus);
            $user->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => 'Status user berhasil diubah menjadi ' . $newStatus,
                'newStatus' => $newStatus
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export Template Excel untuk Import User
     *
     * Menghasilkan file Excel template dengan format kolom yang benar
     * untuk memudahkan proses import user dalam jumlah banyak.
     */
    #[Route('/export-template', name: 'app_admin_user_export_template', methods: ['GET'])]
    public function exportTemplate(): Response
    {
        try {
            // Membuat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'A1' => 'Username',
                'B1' => 'Nama Lengkap',
                'C1' => 'Email',
                'D1' => 'NIP',
                'E1' => 'Nomor Telepon',
                'F1' => 'Unit Kerja',
                'G1' => 'Kepala Bidang',
                'H1' => 'Kepala Kantor',
                'I1' => 'Role',
                'J1' => 'Status',
                'K1' => 'Password'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
            }

            // Set contoh data
            $sheet->setCellValue('A2', '199112082020051002');
            $sheet->setCellValue('B2', 'DESRI MAHENDRA, S.Th');
            $sheet->setCellValue('C2', 'desri@kemenag.go.id');
            $sheet->setCellValue('D2', '199112082020051002');
            $sheet->setCellValue('E2', '081234567890');
            $sheet->setCellValue('F2', 'Bidang Bimas Islam');
            $sheet->setCellValue('G2', 'H. HAERUL, S.HI');
            $sheet->setCellValue('H2', 'Dr. H. ADNAN MA');
            $sheet->setCellValue('I2', 'pegawai');
            $sheet->setCellValue('J2', 'aktif');
            $sheet->setCellValue('K2', ''); // Kosong = akan menggunakan NIP sebagai password

            // Auto resize kolom
            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Tambahkan keterangan
            $sheet->setCellValue('A4', 'PETUNJUK PENGISIAN:');
            $sheet->getStyle('A4')->getFont()->setBold(true);

            $instructions = [
                'A5' => '1. Username: Biasanya NIP, harus unik untuk login',
                'A6' => '2. Email: Optional, tapi harus valid jika diisi',
                'A7' => '3. NIP: Untuk pegawai, digunakan sebagai username dan password default',
                'A8' => '4. Role: Pilihan (super_admin, admin, pegawai)',
                'A9' => '5. Status: Pilihan (aktif, nonaktif)',
                'A10' => '6. Password: Kosongkan untuk pegawai (otomatis = NIP), atau isi manual',
                'A11' => '7. Kolom wajib: Username, Nama Lengkap, Role',
                'A12' => '8. Unit Kerja: Gunakan nama persis sesuai sistem (lihat daftar di bawah)'
            ];

            foreach ($instructions as $cell => $instruction) {
                $sheet->setCellValue($cell, $instruction);
            }

            // Merge cells untuk instruksi
            foreach (range(5, 12) as $row) {
                $sheet->mergeCells("A{$row}:K{$row}");
            }

            // Tambahkan daftar unit kerja yang valid
            $sheet->setCellValue('A14', 'DAFTAR UNIT KERJA YANG VALID:');
            $sheet->getStyle('A14')->getFont()->setBold(true);

            // Ambil daftar unit kerja dari database
            $unitKerjaList = $this->entityManager->getRepository('App\Entity\UnitKerja')->findBy([], ['namaUnit' => 'ASC']);
            $row = 15;
            foreach ($unitKerjaList as $unitKerja) {
                $sheet->setCellValue("A{$row}", "- {$unitKerja->getNamaUnit()}");
                $sheet->mergeCells("A{$row}:K{$row}");
                $row++;
            }

            // Buat response untuk download
            $writer = new Xlsx($spreadsheet);
            $response = new StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="template_import_user.xlsx"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Gagal membuat template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Preview Import User dari Excel
     *
     * Membaca file Excel yang diupload dan menampilkan preview data
     * yang akan diimport tanpa menyimpannya ke database.
     */
    #[Route('/preview-import', name: 'app_admin_user_preview_import', methods: ['POST'])]
    public function previewImport(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('excelFile');

            if (!$uploadedFile) {
                return new JsonResponse(['success' => false, 'message' => 'File Excel tidak ditemukan'], 400);
            }

            // Validasi file
            $allowedMimeTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'
            ];

            if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
                return new JsonResponse(['success' => false, 'message' => 'Format file tidak didukung'], 400);
            }

            // Baca file Excel
            $spreadsheet = IOFactory::load($uploadedFile->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Skip header row
            array_shift($data);

            $validData = [];
            $errors = [];

            foreach ($data as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 karena array dimulai dari 0 dan kita skip header

                // Skip baris kosong
                if (empty(array_filter($row))) {
                    continue;
                }

                $userData = [
                    'username' => trim($row[0] ?? ''),
                    'namaLengkap' => trim($row[1] ?? ''),
                    'email' => trim($row[2] ?? ''),
                    'nip' => trim($row[3] ?? ''),
                    'nomorTelepon' => trim($row[4] ?? ''),
                    'unitKerja' => trim($row[5] ?? ''),
                    'kepalaBidang' => trim($row[6] ?? ''),
                    'kepalaKantor' => trim($row[7] ?? ''),
                    'role' => trim($row[8] ?? 'pegawai'),
                    'status' => trim($row[9] ?? 'aktif'),
                    'password' => trim($row[10] ?? '')
                ];

                // Validasi data wajib sesuai requirement baru
                if (empty($userData['username'])) {
                    $errors[] = "Baris {$rowNumber}: Username tidak boleh kosong";
                    continue;
                }

                if (empty($userData['namaLengkap'])) {
                    $errors[] = "Baris {$rowNumber}: Nama Lengkap tidak boleh kosong";
                    continue;
                }

                // Validasi email jika diisi
                if (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Baris {$rowNumber}: Format email tidak valid";
                    continue;
                }

                // Set default password = NIP jika password kosong dan role = pegawai
                if (empty($userData['password']) && $userData['role'] === 'pegawai' && !empty($userData['nip'])) {
                    $userData['password'] = $userData['nip'];
                }

                if (empty($userData['password']) || strlen($userData['password']) < 6) {
                    $errors[] = "Baris {$rowNumber}: Password minimal 6 karakter (atau NIP untuk pegawai)";
                    continue;
                }

                // Validasi role
                $allowedRoles = ['super_admin', 'admin', 'pegawai'];
                if (!in_array($userData['role'], $allowedRoles)) {
                    $errors[] = "Baris {$rowNumber}: Role tidak valid (harus: super_admin, admin, atau pegawai)";
                    continue;
                }

                // Validasi status
                $allowedStatus = ['aktif', 'nonaktif'];
                if (!in_array($userData['status'], $allowedStatus)) {
                    $errors[] = "Baris {$rowNumber}: Status tidak valid (harus: aktif atau nonaktif)";
                    continue;
                }

                $validData[] = $userData;
            }

            if (!empty($errors)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Terdapat kesalahan dalam data',
                    'errors' => $errors
                ], 400);
            }

            return new JsonResponse(['success' => true, 'data' => $validData]);

        } catch (\Throwable $e) {
            // Log error untuk debugging
            error_log('Preview Import Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Test endpoint untuk debugging
     */
    #[Route('/test-import', name: 'app_admin_user_test_import', methods: ['GET'])]
    public function testImport(): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'Controller accessible']);
    }

    /**
     * Simplified import method for debugging
     */
    #[Route('/import-simple', name: 'app_admin_user_import_simple', methods: ['POST'])]
    public function importSimple(Request $request): JsonResponse
    {
        try {
            // Test EntityManager
            if (!$this->entityManager->isOpen()) {
                return new JsonResponse(['success' => false, 'message' => 'EntityManager already closed at start']);
            }

            // Just check if file was uploaded
            $uploadedFile = $request->files->get('excelFile');

            if (!$uploadedFile) {
                return new JsonResponse(['success' => false, 'message' => 'No file uploaded']);
            }

            // Test creating one simple user
            $user = new Admin();
            $user->setUsername('test_user_' . time());
            $user->setNamaLengkap('Test User');
            $user->setEmail('test_' . time() . '@example.com');
            $user->setRole('pegawai');
            $user->setStatus('aktif');

            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setCreatedBy($this->getUser());

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Test user created successfully',
                'filename' => $uploadedFile->getClientOriginalName()
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Import User dari Excel
     *
     * Memproses file Excel dan menyimpan data user ke database.
     * Mendukung mode update untuk user yang sudah ada.
     */
    #[Route('/import', name: 'app_admin_user_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        // Ensure we always return JSON response even if fatal error occurs
        try {
            error_log('Import method called'); // Debug log

            // Early validation to catch basic issues
            if (!$request->files->has('excelFile')) {
                error_log('excelFile field not found');
                return new JsonResponse(['success' => false, 'message' => 'Field excelFile tidak ditemukan'], 400);
            }

            $uploadedFile = $request->files->get('excelFile');
            $updateExisting = $request->request->get('updateExisting') === '1';

            if (!$uploadedFile) {
                return new JsonResponse(['success' => false, 'message' => 'File Excel tidak ditemukan'], 400);
            }

            // Check if file was uploaded successfully
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return new JsonResponse(['success' => false, 'message' => 'Upload error: ' . $uploadedFile->getErrorMessage()], 400);
            }

            // Validasi file (sama seperti preview)
            $allowedMimeTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'
            ];

            if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
                return new JsonResponse(['success' => false, 'message' => 'Format file tidak didukung'], 400);
            }

            // Baca file Excel
            $spreadsheet = IOFactory::load($uploadedFile->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Skip header row
            array_shift($data);

            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];

            // Cache untuk lookup entities
            $unitKerjaRepo = $this->entityManager->getRepository('App\Entity\UnitKerja');
            $kepalaBidangRepo = $this->entityManager->getRepository('App\Entity\KepalaBidang');
            $kepalaKantorRepo = $this->entityManager->getRepository('App\Entity\KepalaKantor');
            $adminRepo = $this->entityManager->getRepository(Admin::class);

            foreach ($data as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;

                // Check EntityManager status
                if (!$this->entityManager->isOpen()) {
                    throw new \Exception("EntityManager closed at row {$rowNumber}");
                }

                // Skip baris kosong
                if (empty(array_filter($row))) {
                    continue;
                }

                $userData = [
                    'username' => trim($row[0] ?? ''),
                    'namaLengkap' => trim($row[1] ?? ''),
                    'email' => trim($row[2] ?? ''),
                    'nip' => trim($row[3] ?? ''),
                    'nomorTelepon' => trim($row[4] ?? ''),
                    'unitKerja' => trim($row[5] ?? ''),
                    'kepalaBidang' => trim($row[6] ?? ''),
                    'kepalaKantor' => trim($row[7] ?? ''),
                    'role' => trim($row[8] ?? 'pegawai'),
                    'status' => trim($row[9] ?? 'aktif'),
                    'password' => trim($row[10] ?? '')
                ];

                // Validasi data wajib sesuai requirement baru
                if (empty($userData['username']) || empty($userData['namaLengkap'])) {
                    $errors[] = "Baris {$rowNumber}: Username dan Nama Lengkap wajib diisi, dilewati";
                    continue;
                }

                // Validasi email jika diisi
                if (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Baris {$rowNumber}: Format email tidak valid, dilewati";
                    continue;
                }

                // Set default password = NIP jika password kosong dan role = pegawai
                if (empty($userData['password']) && $userData['role'] === 'pegawai' && !empty($userData['nip'])) {
                    $userData['password'] = $userData['nip']; // Default password = NIP
                }

                // Validasi password setelah set default
                if (empty($userData['password']) || strlen($userData['password']) < 6) {
                    $errors[] = "Baris {$rowNumber}: Password minimal 6 karakter (atau NIP untuk pegawai), dilewati";
                    continue;
                }

                try {
                    error_log("Processing row {$rowNumber}");

                    // Cek apakah user sudah ada di table Admin maupun Pegawai
                    $pegawaiRepo = $this->entityManager->getRepository('App\\Entity\\Pegawai');

                    // Check di table Admin
                    $existingAdminByUsername = $adminRepo->findOneBy(['username' => $userData['username']]);
                    $existingAdminByEmail = null;
                    if (!empty($userData['email'])) {
                        $existingAdminByEmail = $adminRepo->findOneBy(['email' => $userData['email']]);
                    }
                    $existingAdminByNip = null;
                    if (!empty($userData['nip'])) {
                        $existingAdminByNip = $adminRepo->findOneBy(['nip' => $userData['nip']]);
                    }

                    // Check di table Pegawai
                    $existingPegawaiByNip = null;
                    if (!empty($userData['username'])) {
                        $existingPegawaiByNip = $pegawaiRepo->findOneBy(['nip' => $userData['username']]);
                    }
                    $existingPegawaiByEmail = null;
                    if (!empty($userData['email'])) {
                        $existingPegawaiByEmail = $pegawaiRepo->findOneBy(['email' => $userData['email']]);
                    }

                    // Tentukan existing user dari mana saja
                    $existingUser = $existingAdminByUsername ?: $existingAdminByEmail ?: $existingAdminByNip ?:
                                  $existingPegawaiByNip ?: $existingPegawaiByEmail;

                    if ($existingUser && !$updateExisting) {
                        if ($existingAdminByUsername || $existingPegawaiByNip) {
                            $errors[] = "Baris {$rowNumber}: Username/NIP '{$userData['username']}' sudah digunakan, dilewati";
                        } elseif ($existingAdminByEmail || $existingPegawaiByEmail) {
                            $errors[] = "Baris {$rowNumber}: Email '{$userData['email']}' sudah digunakan, dilewati";
                        } elseif ($existingAdminByNip) {
                            $errors[] = "Baris {$rowNumber}: NIP '{$userData['nip']}' sudah digunakan, dilewati";
                        }
                        continue;
                    }

                    // Check for conflicts when updating (simplified)
                    if ($updateExisting && $existingUser) {
                        // Basic conflict check - skip complex validation for now
                        // TODO: Implement detailed conflict resolution if needed
                    }

                    if ($existingUser && $updateExisting) {
                        // Update existing user
                        $user = $existingUser;
                        $updatedCount++;
                        error_log("Updating existing user");
                    } else {
                        // Create new user berdasarkan role
                        if ($userData['role'] === 'pegawai') {
                            // Buat instance Pegawai untuk role pegawai
                            $user = new \App\Entity\Pegawai();
                            error_log("Creating new Pegawai user");
                        } else {
                            // Buat instance Admin untuk role admin/super_admin
                            $user = new Admin();
                            error_log("Creating new Admin user");
                        }
                        $importedCount++;
                    }

                    // Set data user berdasarkan instance type
                    if ($user instanceof \App\Entity\Pegawai) {
                        // Setting untuk Pegawai
                        $user->setNip($userData['username']); // NIP sebagai username
                        $user->setNama($userData['namaLengkap']);
                        $user->setEmail(!empty($userData['email']) ? $userData['email'] : null);
                        $user->setNomorTelepon($userData['nomorTelepon'] ?: null);
                        $user->setJabatan('Pegawai'); // Default jabatan
                        $user->setStatusKepegawaian($userData['status'] === 'aktif' ? 'aktif' : 'nonaktif');
                        $user->setTanggalMulaiKerja(new \DateTime()); // Default hari ini
                        // Pegawai otomatis dapat role ROLE_USER
                        $user->setRoles(['ROLE_USER']);
                    } else {
                        // Setting untuk Admin
                        $user->setUsername($userData['username']);
                        $user->setNamaLengkap($userData['namaLengkap']);
                        $user->setEmail(!empty($userData['email']) ? $userData['email'] : null);
                        $user->setNip($userData['nip'] ?: null);
                        $user->setNomorTelepon($userData['nomorTelepon'] ?: null);
                        $user->setRole($userData['role']);
                        $user->setStatus($userData['status']);
                    }

                    // Hash password
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
                    $user->setPassword($hashedPassword);

                    // Set timestamps dan creator berdasarkan instance type
                    if (!$existingUser) {
                        if ($user instanceof Admin) {
                            $user->setCreatedBy($this->getUser());
                            // createdAt sudah diset di constructor Admin
                        }
                        // Pegawai createdAt sudah diset di constructor Pegawai
                    }

                    if ($user instanceof Admin) {
                        $user->setUpdatedAt(new \DateTime());
                    } else {
                        // Untuk Pegawai
                        $user->setUpdatedAt(new \DateTime());
                    }

                    // Set relationships (jika data tersedia)
                    if (!empty($userData['unitKerja'])) {
                        // Try exact match first
                        $unitKerja = $unitKerjaRepo->findOneBy(['namaUnit' => $userData['unitKerja']]);

                        // If not found, try case-insensitive search
                        if (!$unitKerja) {
                            $allUnits = $unitKerjaRepo->findAll();
                            foreach ($allUnits as $unit) {
                                if (strcasecmp($unit->getNamaUnit(), $userData['unitKerja']) === 0) {
                                    $unitKerja = $unit;
                                    break;
                                }
                            }
                        }

                        // If still not found, try partial match
                        if (!$unitKerja) {
                            foreach ($allUnits as $unit) {
                                if (stripos($unit->getNamaUnit(), $userData['unitKerja']) !== false ||
                                    stripos($userData['unitKerja'], $unit->getNamaUnit()) !== false) {
                                    $unitKerja = $unit;
                                    error_log("Partial match found: '{$userData['unitKerja']}' -> '{$unit->getNamaUnit()}'");
                                    break;
                                }
                            }
                        }

                        if ($unitKerja) {
                            $user->setUnitKerjaEntity($unitKerja);
                        } else {
                            error_log("Unit kerja not found: '{$userData['unitKerja']}'");
                            $errors[] = "Baris {$rowNumber}: Unit kerja '{$userData['unitKerja']}' tidak ditemukan, user dibuat tanpa unit kerja";
                        }
                    }

                    if (!empty($userData['kepalaBidang'])) {
                        $kepalaBidang = $kepalaBidangRepo->findOneBy(['nama' => $userData['kepalaBidang']]);
                        if ($kepalaBidang) {
                            $user->setKepalaBidang($kepalaBidang);
                        }
                    }

                    if (!empty($userData['kepalaKantor'])) {
                        $kepalaKantor = $kepalaKantorRepo->findOneBy(['nama' => $userData['kepalaKantor']]);
                        if ($kepalaKantor) {
                            $user->setKepalaKantor($kepalaKantor);
                        }
                    }

                    // Set default permissions hanya untuk Admin
                    if ($user instanceof Admin) {
                        $defaultPermissions = $this->getDefaultPermissionsByRole($userData['role']);
                        $user->setPermissions($defaultPermissions);
                    }
                    // Pegawai tidak memiliki field permissions, role-nya sudah diset di setRoles()

                    $this->entityManager->persist($user);

                } catch (\Throwable $e) {
                    // Log error but don't let it close EntityManager
                    error_log("Import error at row {$rowNumber}: " . $e->getMessage());
                    $errors[] = "Baris {$rowNumber}: Error - " . $e->getMessage() . " | Data: " . json_encode($userData);

                    // Check if EntityManager is still open
                    if (!$this->entityManager->isOpen()) {
                        throw new \Exception("EntityManager was closed by error at row {$rowNumber}: " . $e->getMessage());
                    }
                }
            }

            // Flush sisa perubahan - check EntityManager status first
            if ($this->entityManager->isOpen()) {
                $this->entityManager->flush();
            } else {
                throw new \Exception('EntityManager is closed before final flush');
            }

            $totalProcessed = $importedCount + $updatedCount;
            $message = "Import selesai! {$importedCount} user baru ditambahkan";
            if ($updatedCount > 0) {
                $message .= ", {$updatedCount} user diupdate";
            }

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'imported' => $totalProcessed,
                'total_rows_processed' => count($data),
                'errors' => $errors,
                'error_count' => count($errors)
            ]);

        } catch (\Throwable $e) {
            // Log error untuk debugging
            error_log('Import Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengimport data: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Sync data admin dengan role pegawai ke table pegawai
     */
    #[Route('/sync-to-pegawai', name: 'app_admin_user_sync_pegawai', methods: ['POST'])]
    public function syncToPegawai(): JsonResponse
    {
        try {
            // Ambil semua admin dengan role pegawai
            $adminPegawai = $this->entityManager->getRepository(Admin::class)
                ->findBy(['role' => 'pegawai']);

            $pegawaiRepo = $this->entityManager->getRepository('App\\Entity\\Pegawai');
            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($adminPegawai as $admin) {
                try {
                    // Check apakah pegawai sudah ada (by NIP atau email)
                    $existingPegawai = null;
                    if ($admin->getNip()) {
                        $existingPegawai = $pegawaiRepo->findOneBy(['nip' => $admin->getNip()]);
                    }
                    if (!$existingPegawai && $admin->getEmail()) {
                        $existingPegawai = $pegawaiRepo->findOneBy(['email' => $admin->getEmail()]);
                    }

                    if ($existingPegawai) {
                        $skippedCount++;
                        continue;
                    }

                    // Buat pegawai baru
                    $pegawai = new \App\Entity\Pegawai();
                    $pegawai->setNip($admin->getNip() ?: $admin->getUsername());
                    $pegawai->setNama($admin->getNamaLengkap());
                    $pegawai->setEmail($admin->getEmail());
                    $pegawai->setPassword($admin->getPassword()); // Copy encrypted password
                    $pegawai->setJabatan('Pegawai'); // Default jabatan

                    // Set unit kerja
                    if ($admin->getUnitKerjaEntity()) {
                        $pegawai->setUnitKerjaEntity($admin->getUnitKerjaEntity());
                    }

                    $this->entityManager->persist($pegawai);
                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error sync {$admin->getNamaLengkap()}: " . $e->getMessage();
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "Sync selesai! {$syncedCount} pegawai di-sync, {$skippedCount} di-skip",
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default permissions berdasarkan role
     */
    private function getDefaultPermissionsByRole(string $role): array
    {
        switch ($role) {
            case 'super_admin':
                return array_keys($this->getAvailablePermissions());
            case 'admin':
                return ['kelola_pegawai', 'kelola_jadwal', 'validasi_absensi', 'laporan', 'kelola_event'];
            case 'pegawai':
                return ['absensi_pegawai', 'profile_pegawai'];
            default:
                return [];
        }
    }

    /**
     * Daftar role yang tersedia
     */
    private function getAvailableRoles(): array
    {
        return [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'pegawai' => 'Pegawai'
        ];
    }

    /**
     * Daftar permission yang tersedia
     */
    private function getAvailablePermissions(): array
    {
        return [
            'kelola_pegawai' => 'Kelola Pegawai',
            'kelola_jadwal' => 'Kelola Jadwal Absensi',
            'validasi_absensi' => 'Validasi Absensi',
            'laporan' => 'Akses Laporan',
            'kelola_event' => 'Kelola Event',
            'kelola_user' => 'Kelola User Admin',
            'pengaturan_sistem' => 'Pengaturan Sistem',
            'absensi_pegawai' => 'Akses Absensi Pegawai',
            'profile_pegawai' => 'Kelola Profile Pegawai'
        ];
    }
}