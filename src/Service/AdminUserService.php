<?php

namespace App\Service;

use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Repository\AdminRepository;
use App\Repository\PegawaiRepository;
use App\Repository\UnitKerjaRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * AdminUserService
 *
 * Service untuk menangani business logic manajemen admin user:
 * - CRUD operations untuk admin
 * - Password hashing dan validation
 * - Import/export admin dari Excel
 * - Status toggle dan bulk operations
 * - Sync admin ke pegawai
 *
 * REFACTOR: Dipindahkan dari fat AdminUserController (1152 lines)
 *
 * @author Refactor Assistant
 */
class AdminUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminRepository $adminRepository,
        private PegawaiRepository $pegawaiRepository,
        private UnitKerjaRepository $unitKerjaRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Validate admin data for create/update
     */
    public function validateAdminData(array $data, ?Admin $existingAdmin = null): array
    {
        $errors = [];

        // Required fields validation
        if (empty($data['username'])) {
            $errors[] = 'Username tidak boleh kosong';
        }

        if (empty($data['namaLengkap'])) {
            $errors[] = 'Nama Lengkap tidak boleh kosong';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email tidak boleh kosong';
        }

        // Email format validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid';
        }

        // Username uniqueness check
        if (!empty($data['username'])) {
            $queryBuilder = $this->adminRepository->createQueryBuilder('a')
                ->where('a.username = :username')
                ->setParameter('username', $data['username']);

            if ($existingAdmin) {
                $queryBuilder->andWhere('a.id != :id')
                           ->setParameter('id', $existingAdmin->getId());
            }

            $existingByUsername = $queryBuilder->getQuery()->getOneOrNullResult();
            if ($existingByUsername) {
                $errors[] = 'Username sudah digunakan';
            }
        }

        // Email uniqueness check
        if (!empty($data['email'])) {
            $queryBuilder = $this->adminRepository->createQueryBuilder('a')
                ->where('a.email = :email')
                ->setParameter('email', $data['email']);

            if ($existingAdmin) {
                $queryBuilder->andWhere('a.id != :id')
                           ->setParameter('id', $existingAdmin->getId());
            }

            $existingByEmail = $queryBuilder->getQuery()->getOneOrNullResult();
            if ($existingByEmail) {
                $errors[] = 'Email sudah digunakan';
            }
        }

        return $errors;
    }

    /**
     * Create new admin
     */
    public function createAdmin(array $data): array
    {
        $errors = $this->validateAdminData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Password validation for new admin
        if (empty($data['password'])) {
            return ['success' => false, 'errors' => ['Password tidak boleh kosong']];
        }

        try {
            $admin = new Admin();
            $admin->setUsername($data['username']);
            $admin->setNamaLengkap($data['namaLengkap']);
            $admin->setEmail($data['email']);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($admin, $data['password']);
            $admin->setPassword($hashedPassword);

            // Set unit kerja if provided
            if (!empty($data['unitKerjaId'])) {
                $unitKerja = $this->unitKerjaRepository->find($data['unitKerjaId']);
                if ($unitKerja) {
                    $admin->setUnitKerja($unitKerja);
                }
            }

            // Set role
            $admin->setRole($data['role'] ?? 'admin');
            $admin->setStatus($data['status'] ?? 'aktif');

            $this->entityManager->persist($admin);
            $this->entityManager->flush();

            return ['success' => true, 'data' => $admin];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal membuat admin: ' . $e->getMessage()]];
        }
    }

    /**
     * Update existing admin
     */
    public function updateAdmin(Admin $admin, array $data): array
    {
        $errors = $this->validateAdminData($data, $admin);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $admin->setUsername($data['username']);
            $admin->setNamaLengkap($data['namaLengkap']);
            $admin->setEmail($data['email']);

            // Update password only if provided
            if (!empty($data['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($admin, $data['password']);
                $admin->setPassword($hashedPassword);
            }

            // Update unit kerja
            if (!empty($data['unitKerjaId'])) {
                $unitKerja = $this->unitKerjaRepository->find($data['unitKerjaId']);
                $admin->setUnitKerja($unitKerja);
            } else {
                $admin->setUnitKerja(null);
            }

            // Update role and status
            $admin->setRole($data['role'] ?? $admin->getRole());
            $admin->setStatus($data['status'] ?? $admin->getStatus());

            $this->entityManager->flush();

            return ['success' => true, 'data' => $admin];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal mengupdate admin: ' . $e->getMessage()]];
        }
    }

    /**
     * Delete admin with safety checks
     */
    public function deleteAdmin(Admin $admin): array
    {
        try {
            // Check if admin is the only super admin
            if ($admin->isSuperAdmin()) {
                $superAdminCount = $this->adminRepository->count(['role' => 'super_admin', 'status' => 'aktif']);
                if ($superAdminCount <= 1) {
                    return ['success' => false, 'errors' => ['Tidak dapat menghapus Super Admin terakhir']];
                }
            }

            $this->entityManager->remove($admin);
            $this->entityManager->flush();

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal menghapus admin: ' . $e->getMessage()]];
        }
    }

    /**
     * Toggle admin status
     */
    public function toggleAdminStatus(Admin $admin): array
    {
        try {
            // Check if trying to deactivate the only super admin
            if ($admin->isSuperAdmin() && $admin->getStatus() === 'aktif') {
                $activeSuperAdminCount = $this->adminRepository->count(['role' => 'super_admin', 'status' => 'aktif']);
                if ($activeSuperAdminCount <= 1) {
                    return ['success' => false, 'errors' => ['Tidak dapat menonaktifkan Super Admin terakhir']];
                }
            }

            $newStatus = ($admin->getStatus() === 'aktif') ? 'nonaktif' : 'aktif';
            $admin->setStatus($newStatus);

            $this->entityManager->flush();

            return ['success' => true, 'newStatus' => $newStatus];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal mengubah status: ' . $e->getMessage()]];
        }
    }

    /**
     * Generate Excel template for admin import
     */
    public function generateAdminTemplate(): array
    {
        try {
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new \Exception('Library PhpSpreadsheet tidak tersedia');
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Template Import Admin');

            // Headers with formatting
            $headers = [
                'A1' => 'Username *',
                'B1' => 'Password *',
                'C1' => 'Nama Lengkap *',
                'D1' => 'Email *',
                'E1' => 'Role',
                'F1' => 'Unit Kerja',
                'G1' => 'Status'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFE6E6FA');
            }

            // Sample data
            $sheet->setCellValue('A2', 'admin_unit1');
            $sheet->setCellValue('B2', 'password123');
            $sheet->setCellValue('C2', 'Admin Unit Kerja 1');
            $sheet->setCellValue('D2', 'admin1@example.com');
            $sheet->setCellValue('E2', 'admin');
            $sheet->setCellValue('F2', 'IT');
            $sheet->setCellValue('G2', 'aktif');

            // Auto-size columns
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instructions
            $this->addAdminTemplateInstructions($sheet);

            return ['success' => true, 'spreadsheet' => $spreadsheet];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Preview Excel import data for admin
     */
    public function previewAdminImport(string $filePath): array
    {
        try {
            if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                return ['success' => false, 'message' => 'PhpSpreadsheet tidak tersedia'];
            }

            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Skip header row
            array_shift($data);

            $validData = [];
            $errors = [];

            foreach ($data as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $adminData = [
                    'username' => trim($row[0] ?? ''),
                    'password' => trim($row[1] ?? ''),
                    'namaLengkap' => trim($row[2] ?? ''),
                    'email' => trim($row[3] ?? ''),
                    'role' => trim($row[4] ?? '') ?: 'admin',
                    'unitKerja' => trim($row[5] ?? ''),
                    'status' => trim($row[6] ?? '') ?: 'aktif'
                ];

                $rowErrors = $this->validateImportAdminData($adminData, $rowNumber);
                if (!empty($rowErrors)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                $validData[] = $adminData;
            }

            return [
                'success' => true,
                'validData' => $validData,
                'errors' => $errors,
                'totalValid' => count($validData),
                'totalErrors' => count($errors)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error reading file: ' . $e->getMessage()];
        }
    }

    /**
     * Import admin data from validated array
     */
    public function importAdminData(array $validData): array
    {
        $imported = 0;
        $errors = [];

        foreach ($validData as $adminData) {
            try {
                $result = $this->createAdmin($adminData);
                if ($result['success']) {
                    $imported++;
                } else {
                    $errors[] = "Username {$adminData['username']}: " . implode(', ', $result['errors']);
                }
            } catch (\Exception $e) {
                $errors[] = "Error importing username {$adminData['username']}: " . $e->getMessage();
            }
        }

        return ['success' => true, 'imported' => $imported, 'errors' => $errors];
    }

    /**
     * Sync admin data to pegawai table
     */
    public function syncAdminToPegawai(): array
    {
        try {
            $syncedCount = 0;
            $errors = [];

            $admins = $this->adminRepository->findBy(['status' => 'aktif']);

            foreach ($admins as $admin) {
                try {
                    // Check if pegawai already exists
                    $existingPegawai = $this->pegawaiRepository->findOneBy(['email' => $admin->getEmail()]);
                    if ($existingPegawai) {
                        continue; // Skip if already exists
                    }

                    // Create pegawai from admin data
                    $pegawai = new Pegawai();
                    $pegawai->setNamaLengkap($admin->getNamaLengkap());
                    $pegawai->setEmail($admin->getEmail());
                    $pegawai->setNip($admin->getUsername()); // Use username as NIP
                    $pegawai->setJabatan('Administrator');
                    $pegawai->setStatusKepegawaian('aktif');

                    if ($admin->getUnitKerjaEntity()) {
                        $pegawai->setUnitKerja($admin->getUnitKerjaEntity());
                    }

                    // Hash password (same as admin)
                    $pegawai->setPassword($admin->getPassword());

                    $this->entityManager->persist($pegawai);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error syncing {$admin->getUsername()}: " . $e->getMessage();
                }
            }

            $this->entityManager->flush();

            return ['success' => true, 'synced' => $syncedCount, 'errors' => $errors];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync error: ' . $e->getMessage()];
        }
    }

    /**
     * Add instructions to admin template
     */
    private function addAdminTemplateInstructions($sheet): void
    {
        $sheet->setCellValue('A4', 'PETUNJUK PENGISIAN:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);

        $instructions = [
            'A5' => '1. Kolom dengan tanda (*) WAJIB diisi',
            'A6' => '2. Username: Unik, akan digunakan untuk login',
            'A7' => '3. Password: Minimal 6 karakter',
            'A8' => '4. Role: Pilihan "admin" atau "super_admin"',
            'A9' => '5. Status: Pilihan "aktif" atau "nonaktif"',
            'A10' => '6. Hapus baris contoh sebelum import!'
        ];

        foreach ($instructions as $cell => $instruction) {
            $sheet->setCellValue($cell, $instruction);
            $sheet->mergeCells($cell . ':G' . substr($cell, 1));
        }
    }

    /**
     * Validate admin data for import
     */
    private function validateImportAdminData(array $data, int $rowNumber): array
    {
        $errors = [];

        // Required fields
        if (empty($data['username'])) {
            $errors[] = "Baris {$rowNumber}: Username tidak boleh kosong";
        }

        if (empty($data['password'])) {
            $errors[] = "Baris {$rowNumber}: Password tidak boleh kosong";
        }

        if (empty($data['namaLengkap'])) {
            $errors[] = "Baris {$rowNumber}: Nama Lengkap tidak boleh kosong";
        }

        if (empty($data['email'])) {
            $errors[] = "Baris {$rowNumber}: Email tidak boleh kosong";
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Baris {$rowNumber}: Format email tidak valid";
        }

        // Role validation
        $allowedRoles = ['admin', 'super_admin'];
        if (!in_array($data['role'], $allowedRoles)) {
            $errors[] = "Baris {$rowNumber}: Role tidak valid (harus: admin atau super_admin)";
        }

        // Status validation
        $allowedStatus = ['aktif', 'nonaktif'];
        if (!in_array($data['status'], $allowedStatus)) {
            $errors[] = "Baris {$rowNumber}: Status tidak valid (harus: aktif atau nonaktif)";
        }

        return $errors;
    }
}