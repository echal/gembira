<?php

namespace App\Service;

use App\Entity\UnitKerja;
use App\Entity\KepalaBidang;
use App\Entity\KepalaKantor;
use App\Entity\Pegawai;
use App\Repository\UnitKerjaRepository;
use App\Repository\KepalaBidangRepository;
use App\Repository\KepalaKantorRepository;
use App\Repository\PegawaiRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * OrganizationalStructureService
 *
 * Service untuk menangani business logic terkait struktur organisasi:
 * - Unit Kerja operations
 * - Kepala Bidang operations
 * - Kepala Kantor operations
 * - Pegawai import/export operations
 * - Excel template generation
 * - Data validation logic
 *
 * REFACTOR: Dipindahkan dari fat StrukturOrganisasiController (1955 lines)
 *
 * @author Refactor Assistant
 */
class OrganizationalStructureService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UnitKerjaRepository $unitKerjaRepository,
        private KepalaBidangRepository $kepalaBidangRepository,
        private KepalaKantorRepository $kepalaKantorRepository,
        private PegawaiRepository $pegawaiRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Validate Unit Kerja data
     */
    public function validateUnitKerjaData(array $data): array
    {
        $errors = [];

        if (empty($data['nama'])) {
            $errors[] = 'Nama Unit Kerja tidak boleh kosong';
        }

        if (strlen($data['nama'] ?? '') > 100) {
            $errors[] = 'Nama Unit Kerja maksimal 100 karakter';
        }

        return $errors;
    }

    /**
     * Create Unit Kerja with validation
     */
    public function createUnitKerja(array $data): array
    {
        $errors = $this->validateUnitKerjaData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if unit already exists
        $existing = $this->unitKerjaRepository->findOneBy(['nama' => $data['nama']]);
        if ($existing) {
            return ['success' => false, 'errors' => ['Unit Kerja dengan nama tersebut sudah ada']];
        }

        $unitKerja = new UnitKerja();
        $unitKerja->setNama($data['nama']);
        $unitKerja->setDeskripsi($data['deskripsi'] ?? '');

        try {
            $this->entityManager->persist($unitKerja);
            $this->entityManager->flush();
            return ['success' => true, 'data' => $unitKerja];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal menyimpan data: ' . $e->getMessage()]];
        }
    }

    /**
     * Update Unit Kerja with validation
     */
    public function updateUnitKerja(UnitKerja $unitKerja, array $data): array
    {
        $errors = $this->validateUnitKerjaData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if another unit with same name exists
        $existing = $this->unitKerjaRepository->createQueryBuilder('u')
            ->where('u.nama = :nama AND u.id != :id')
            ->setParameter('nama', $data['nama'])
            ->setParameter('id', $unitKerja->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            return ['success' => false, 'errors' => ['Unit Kerja dengan nama tersebut sudah ada']];
        }

        $unitKerja->setNama($data['nama']);
        $unitKerja->setDeskripsi($data['deskripsi'] ?? '');

        try {
            $this->entityManager->flush();
            return ['success' => true, 'data' => $unitKerja];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal mengupdate data: ' . $e->getMessage()]];
        }
    }

    /**
     * Delete Unit Kerja with dependency check
     */
    public function deleteUnitKerja(UnitKerja $unitKerja): array
    {
        // Check if unit has associated kepala bidang
        $kepalaBidangCount = $this->kepalaBidangRepository->count(['unitKerja' => $unitKerja]);
        if ($kepalaBidangCount > 0) {
            return ['success' => false, 'errors' => ['Tidak dapat menghapus Unit Kerja yang masih memiliki Kepala Bidang']];
        }

        // Check if unit has associated pegawai
        $pegawaiCount = $this->pegawaiRepository->count(['unitKerja' => $unitKerja]);
        if ($pegawaiCount > 0) {
            return ['success' => false, 'errors' => ['Tidak dapat menghapus Unit Kerja yang masih memiliki Pegawai']];
        }

        try {
            $this->entityManager->remove($unitKerja);
            $this->entityManager->flush();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['Gagal menghapus data: ' . $e->getMessage()]];
        }
    }

    /**
     * Generate Excel template for Pegawai import with complete formatting and instructions
     */
    public function generatePegawaiTemplate(): array
    {
        try {
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new \Exception('Library PhpSpreadsheet tidak tersedia. Silakan install melalui Composer.');
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Template Import Pegawai');

            // Set headers with formatting
            $headers = [
                'A1' => 'NIP *',
                'B1' => 'Nama Lengkap *',
                'C1' => 'Email',
                'D1' => 'Jabatan',
                'E1' => 'Nomor Telepon',
                'F1' => 'Unit Kerja',
                'G1' => 'Status Kepegawaian',
                'H1' => 'Password'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFE6E6FA');
            }

            // Sample data
            $sheet->setCellValue('A2', '199112082020051002');
            $sheet->setCellValue('B2', 'DESRI MAHENDRA, S.Th');
            $sheet->setCellValue('C2', 'desri@kemenag.go.id');
            $sheet->setCellValue('D2', 'Kasubag Administrasi Umum');
            $sheet->setCellValue('E2', '081234567890');
            $sheet->setCellValue('F2', 'Bagian Tata Usaha');
            $sheet->setCellValue('G2', 'aktif');
            $sheet->setCellValue('H2', '');

            // Auto-size columns
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instructions
            $this->addTemplateInstructions($sheet);

            // Add unit kerja list
            $this->addUnitKerjaList($sheet);

            return ['success' => true, 'spreadsheet' => $spreadsheet];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add instructions to template
     */
    private function addTemplateInstructions($sheet): void
    {
        $sheet->setCellValue('A4', 'PETUNJUK PENGISIAN:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4')->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFFFFF00');

        $instructions = [
            'A5' => '1. Kolom dengan tanda (*) WAJIB diisi',
            'A6' => '2. NIP: 18 digit angka, akan digunakan sebagai username login',
            'A7' => '3. Email: Format harus valid (contoh: nama@domain.com)',
            'A8' => '4. Jabatan: Kosongkan untuk default "Pegawai"',
            'A9' => '5. Status Kepegawaian: Pilihan "aktif" atau "nonaktif"',
            'A10' => '6. Password: Kosongkan untuk menggunakan NIP sebagai password',
            'A11' => '7. Unit Kerja: Gunakan nama PERSIS sesuai daftar di bawah',
            'A12' => '8. Hapus baris contoh sebelum import!'
        ];

        foreach ($instructions as $cell => $instruction) {
            $sheet->setCellValue($cell, $instruction);
            $sheet->mergeCells($cell . ':H' . substr($cell, 1));
        }
    }

    /**
     * Add unit kerja list to template
     */
    private function addUnitKerjaList($sheet): void
    {
        $sheet->setCellValue('A14', 'DAFTAR UNIT KERJA YANG VALID:');
        $sheet->getStyle('A14')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A14')->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FF90EE90');

        $unitKerjaList = $this->unitKerjaRepository->findBy([], ['nama' => 'ASC']);

        $row = 15;
        if (empty($unitKerjaList)) {
            $sheet->setCellValue("A{$row}", '- Belum ada unit kerja yang terdaftar');
        } else {
            foreach ($unitKerjaList as $unitKerja) {
                $sheet->setCellValue("A{$row}", "- {$unitKerja->getNama()}");
                $sheet->mergeCells("A{$row}:H{$row}");
                $row++;
            }
        }

        // Add important notes
        $noteRow = $row + 2;
        $sheet->setCellValue("A{$noteRow}", 'CATATAN PENTING:');
        $sheet->getStyle("A{$noteRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');

        $notes = [
            "A" . ($noteRow + 1) => '• Simpan file dalam format .xlsx atau .xls',
            "A" . ($noteRow + 2) => '• Pastikan tidak ada baris kosong di tengah data',
            "A" . ($noteRow + 3) => '• Import hanya memproses data mulai baris 2',
            "A" . ($noteRow + 4) => '• Backup data pegawai sebelum import massal'
        ];

        foreach ($notes as $cell => $note) {
            $sheet->setCellValue($cell, $note);
            $sheet->mergeCells($cell . ':H' . substr($cell, 1));
        }
    }

    /**
     * Preview Excel import data with validation
     */
    public function previewExcelImport(string $filePath): array
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

                $pegawaiData = [
                    'nip' => trim($row[0] ?? ''),
                    'namaLengkap' => trim($row[1] ?? ''),
                    'email' => trim($row[2] ?? ''),
                    'jabatan' => trim($row[3] ?? '') ?: 'Pegawai',
                    'nomorTelepon' => trim($row[4] ?? ''),
                    'unitKerja' => trim($row[5] ?? ''),
                    'statusKepegawaian' => trim($row[6] ?? '') ?: 'aktif',
                    'password' => trim($row[7] ?? '')
                ];

                $rowErrors = $this->validatePegawaiData($pegawaiData, $rowNumber);
                if (!empty($rowErrors)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                // Set default password = NIP if empty
                if (empty($pegawaiData['password'])) {
                    $pegawaiData['password'] = $pegawaiData['nip'];
                }

                $validData[] = $pegawaiData;
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
     * Validate pegawai data for import
     */
    private function validatePegawaiData(array $data, int $rowNumber): array
    {
        $errors = [];

        // Required fields validation
        if (empty($data['nip'])) {
            $errors[] = "Baris {$rowNumber}: NIP tidak boleh kosong";
        }

        if (empty($data['namaLengkap'])) {
            $errors[] = "Baris {$rowNumber}: Nama Lengkap tidak boleh kosong";
        }

        // Email validation if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Baris {$rowNumber}: Format email tidak valid";
        }

        // Status validation
        $allowedStatus = ['aktif', 'nonaktif'];
        if (!in_array($data['statusKepegawaian'], $allowedStatus)) {
            $errors[] = "Baris {$rowNumber}: Status kepegawaian tidak valid (harus: aktif atau nonaktif)";
        }

        return $errors;
    }

    /**
     * Import pegawai data from validated array
     */
    public function importPegawaiData(array $validData): array
    {
        $imported = 0;
        $errors = [];

        foreach ($validData as $pegawaiData) {
            try {
                // Check if NIP already exists
                $existingPegawai = $this->pegawaiRepository->findOneBy(['nip' => $pegawaiData['nip']]);
                if ($existingPegawai) {
                    $errors[] = "NIP {$pegawaiData['nip']} sudah ada";
                    continue;
                }

                // Find or create unit kerja
                $unitKerja = null;
                if (!empty($pegawaiData['unitKerja'])) {
                    $unitKerja = $this->unitKerjaRepository->findOneBy(['nama' => $pegawaiData['unitKerja']]);
                    if (!$unitKerja) {
                        // Auto-create unit kerja if not exists
                        $unitKerja = new UnitKerja();
                        $unitKerja->setNama($pegawaiData['unitKerja']);
                        $this->entityManager->persist($unitKerja);
                    }
                }

                // Create pegawai
                $pegawai = new Pegawai();
                $pegawai->setNip($pegawaiData['nip']);
                $pegawai->setNamaLengkap($pegawaiData['namaLengkap']);
                $pegawai->setEmail($pegawaiData['email'] ?: null);
                $pegawai->setJabatan($pegawaiData['jabatan']);
                $pegawai->setNomorTelepon($pegawaiData['nomorTelepon'] ?: null);
                $pegawai->setStatusKepegawaian($pegawaiData['statusKepegawaian']);

                if ($unitKerja) {
                    $pegawai->setUnitKerja($unitKerja);
                }

                // Hash password
                $hashedPassword = $this->passwordHasher->hashPassword($pegawai, $pegawaiData['password']);
                $pegawai->setPassword($hashedPassword);

                $this->entityManager->persist($pegawai);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Error importing NIP {$pegawaiData['nip']}: " . $e->getMessage();
            }
        }

        try {
            $this->entityManager->flush();
            return ['success' => true, 'imported' => $imported, 'errors' => $errors];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get organizational structure overview
     */
    public function getOrganizationalOverview(): array
    {
        return [
            'unitKerja' => $this->unitKerjaRepository->count([]),
            'kepalaBidang' => $this->kepalaBidangRepository->count([]),
            'kepalaKantor' => $this->kepalaKantorRepository->count([]),
            'totalPegawai' => $this->pegawaiRepository->count([]),
            'activePegawai' => $this->pegawaiRepository->count(['statusKepegawaian' => 'aktif'])
        ];
    }
}