# Contoh Refactoring: Mengganti Perhitungan Lama dengan AttendanceCalculationService

## File: LaporanBulananService.php

### SEBELUM (Kode Lama - Tidak Konsisten)

```php
// File: src/Service/LaporanBulananService.php
private function getAbsensiPegawai(Pegawai $pegawai, int $year, int $month, string $kategoriAbsensi): array
{
    // MASALAH: Menghitung berdasarkan jumlah hari kerja dalam bulan
    $jumlahHariKerja = $this->getJumlahHariKerja($year, $month); // 30 hari

    $startDate = new \DateTime("$year-$month-01");
    $endDate = new \DateTime("$year-$month-01");
    $endDate->modify('last day of this month');

    $query = $this->entityManager->createQueryBuilder()
        ->select('COUNT(a.id) as jumlah_hadir')
        ->from(Absensi::class, 'a')
        ->where('a.pegawai = :pegawai')
        ->andWhere('a.tanggalAbsen >= :startDate')
        ->andWhere('a.tanggalAbsen <= :endDate')
        ->andWhere('a.status = :status')
        ->setParameter('pegawai', $pegawai)
        ->setParameter('startDate', $startDate)
        ->setParameter('endDate', $endDate)
        ->setParameter('status', 'hadir');

    $result = $query->getQuery()->getOneOrNullResult();
    $jumlahHadir = (int) $result['jumlah_hadir'];

    // MASALAH: Perhitungan tidak konsisten dengan user dashboard
    $jumlahTidakAbsen = $jumlahHariKerja - $jumlahHadir; // Hari kosong dianggap alpha
    $persentaseKehadiran = $jumlahHariKerja > 0 ? ($jumlahHadir / $jumlahHariKerja) * 100 : 0;

    return [
        'jumlah_hadir' => $jumlahHadir,
        'jumlah_tidak_absen' => $jumlahTidakAbsen,
        'persentase_kehadiran' => $persentaseKehadiran
    ];
}
```

### SESUDAH (Menggunakan AttendanceCalculationService - Konsisten)

```php
// File: src/Service/LaporanBulananService.php

use App\Service\AttendanceCalculationService;

class LaporanBulananService
{
    private EntityManagerInterface $entityManager;
    private AttendanceCalculationService $attendanceService; // TAMBAH SERVICE

    public function __construct(
        EntityManagerInterface $entityManager,
        AttendanceCalculationService $attendanceService // INJECT SERVICE
    ) {
        $this->entityManager = $entityManager;
        $this->attendanceService = $attendanceService; // ASSIGN SERVICE
    }

    private function getAbsensiPegawai(Pegawai $pegawai, int $year, int $month, string $kategoriAbsensi): array
    {
        // SOLUSI: Gunakan service untuk konsistensi
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai, $year, $month);

        return [
            'jumlah_hadir' => $dataKehadiran['data_absensi']['total_hadir'],
            'jumlah_terlambat' => $dataKehadiran['data_absensi']['total_terlambat'],
            'jumlah_kehadiran' => $dataKehadiran['data_absensi']['total_kehadiran'], // hadir + terlambat
            'total_absen_tercatat' => $dataKehadiran['data_absensi']['total_absen_tercatat'],
            'persentase_kehadiran' => $dataKehadiran['perhitungan']['persentase_kehadiran'],
            'status_kehadiran' => $dataKehadiran['perhitungan']['status_kehadiran']
        ];
    }

    // HAPUS method getJumlahHariKerja() karena tidak diperlukan lagi
    // private function getJumlahHariKerja(int $year, int $month): int { ... }
}
```

## File: UserLaporanController.php

### SEBELUM (Kode Lama)

```php
// File: src/Controller/UserLaporanController.php

// MASALAH: Perhitungan manual di controller
$totalHariKerja = $this->hitungHariKerjaBerdasarkanJadwalAdmin($tanggalAwalBulan, $tanggalAkhirBulan);
$totalHadir = 0;
$totalTerlambat = 0;

foreach ($riwayatAbsensi as $absensi) {
    $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
    if ($status === 'hadir') {
        $totalHadir++;
    } elseif ($status === 'terlambat') {
        $totalTerlambat++;
    }
}

$totalKehadiran = $totalHadir + $totalTerlambat;
$totalAlpha = max(0, $totalHariKerja - $totalKehadiran);
$persentaseKehadiran = $totalHariKerja > 0 ? round(($totalKehadiran / $totalHariKerja) * 100, 1) : 0;
```

### SESUDAH (Menggunakan AttendanceCalculationService)

```php
// File: src/Controller/UserLaporanController.php

use App\Service\AttendanceCalculationService;

class UserLaporanController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttendanceCalculationService $attendanceService // INJECT SERVICE
    ) {}

    #[Route('/', name: 'app_user_laporan')]
    public function riwayatAbsensi(Request $request): Response
    {
        $pengguna = $this->getUser();

        if (!$pengguna instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat mengakses riwayat absensi.');
        }

        $tahun = (int) date('Y');
        $bulan = (int) date('n');

        // SOLUSI: Gunakan service untuk perhitungan yang konsisten
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pengguna, $tahun, $bulan);

        // Ambil riwayat absensi untuk tampilan
        $riwayatAbsensi = $this->getRiwayatAbsensiPegawai($pengguna, $tahun, $bulan);

        return $this->render('user/laporan/riwayat.html.twig', [
            'riwayat_absensi' => $riwayatAbsensi,
            'pegawai' => $pengguna,
            'data_kehadiran' => $dataKehadiran, // Data konsisten dengan admin
        ]);
    }

    // HAPUS method hitungHariKerjaBerdasarkanJadwalAdmin() karena tidak diperlukan lagi
}
```

## File: AdminLaporanBulananController.php

### SEBELUM (Kode Lama)

```php
// File: src/Controller/AdminLaporanBulananController.php

foreach ($pegawaiList as $pegawai) {
    // MASALAH: Perhitungan manual berbeda dengan user
    $absensiData = $this->getAbsensiPegawai($pegawai, $year, $month, $kategoriAbsensi);
    $jumlahHadir = $absensiData['jumlah_hadir'];
    $jumlahTidakAbsen = $jumlahHariKerja - $jumlahHadir;
    $persentaseKehadiran = $jumlahHariKerja > 0 ? ($jumlahHadir / $jumlahHariKerja) * 100 : 0;

    $laporanData[] = [
        'pegawai' => $pegawai,
        'total_kehadiran' => $jumlahHadir,
        'persentase_kehadiran' => round($persentaseKehadiran, 1),
    ];
}
```

### SESUDAH (Menggunakan AttendanceCalculationService)

```php
// File: src/Controller/AdminLaporanBulananController.php

use App\Service\AttendanceCalculationService;

class AdminLaporanBulananController extends AbstractController
{
    public function __construct(
        private AttendanceCalculationService $attendanceService // INJECT SERVICE
    ) {}

    #[Route('/', name: 'app_admin_laporan_bulanan')]
    public function index(Request $request): Response
    {
        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));
        $unitKerjaId = $request->query->get('unit_kerja', null);

        // SOLUSI: Gunakan service untuk konsistensi dengan user dashboard
        $statistikKehadiran = $this->attendanceService->getStatistikKehadiranUnitKerja(
            $unitKerjaId,
            $year,
            $month
        );

        return $this->render('admin/laporan_bulanan/index.html.twig', [
            'statistik' => $statistikKehadiran,
            'filter' => [
                'year' => $year,
                'month' => $month,
                'unit_kerja' => $unitKerjaId
            ]
        ]);
    }
}
```

## Perbandingan Hasil

### Skenario: Pegawai dengan 15 hari hadir dari 20 absen yang tercatat admin

#### SEBELUM (Tidak Konsisten):
- **Admin melihat**: 15/30 hari = 50% (menghitung seluruh hari dalam bulan)
- **User melihat**: 15/20 absen = 75% (menghitung absen tercatat)
- **MASALAH**: Data tidak sama! ❌

#### SESUDAH (Konsisten):
- **Admin melihat**: 15/20 absen = 75% (menggunakan AttendanceCalculationService)
- **User melihat**: 15/20 absen = 75% (menggunakan AttendanceCalculationService)
- **SOLUSI**: Data sama! ✅

## Langkah-langkah Refactoring

### 1. Update Composer Dependencies (jika perlu)
```bash
composer require doctrine/orm
```

### 2. Inject Service ke Constructor
```php
public function __construct(
    private AttendanceCalculationService $attendanceService
) {}
```

### 3. Ganti Perhitungan Manual
```php
// HAPUS ini:
$persentaseKehadiran = ($jumlahHadir / $jumlahHariKerja) * 100;

// GANTI dengan ini:
$dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai);
$persentaseKehadiran = $dataKehadiran['perhitungan']['persentase_kehadiran'];
```

### 4. Update Template/View
```twig
{# SEBELUM #}
{{ (total_hadir / total_hari_kerja * 100)|round(1) }}%

{# SESUDAH #}
{{ data_kehadiran.perhitungan.persentase_kehadiran }}%
```

### 5. Testing
```bash
# Test untuk memastikan konsistensi
php bin/phpunit tests/Service/AttendanceCalculationServiceTest.php
```

## Checklist Refactoring

- [ ] ✅ Buat AttendanceCalculationService
- [ ] ✅ Update LaporanBulananService
- [ ] ✅ Update UserLaporanController
- [ ] ✅ Update AdminLaporanBulananController
- [ ] ✅ Update AdminLaporanKehadiranController
- [ ] ✅ Update template Twig
- [ ] ✅ Hapus method perhitungan manual lama
- [ ] ✅ Test konsistensi data admin vs user
- [ ] ✅ Update dokumentasi

## Manfaat Setelah Refactoring

1. **Konsistensi Data**: Admin dan user melihat persentase yang sama
2. **Single Source of Truth**: Semua perhitungan ada di satu tempat
3. **Easier Maintenance**: Perubahan logic cukup di satu file
4. **Better Testing**: Service bisa di-unit test secara terpisah
5. **More Accurate**: Berdasarkan data yang benar-benar tercatat