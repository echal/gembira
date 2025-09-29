# Panduan Penggunaan AttendanceCalculationService

## Masalah yang Diselesaikan

Sebelumnya, terdapat **perbedaan logika perhitungan persentase kehadiran** antara sisi admin dan dashboard user:

- **Di sisi admin**: Persentase dihitung berdasarkan range penuh (jumlah hari kerja dalam 1 bulan)
- **Di sisi dashboard user**: Persentase dihitung berdasarkan jumlah absen yang sudah dibuat admin

Hal ini menyebabkan **ketidakkonsistenan** data antara admin dan user.

## Solusi

Dibuat `AttendanceCalculationService` dengan logika perhitungan yang **konsisten**:

```
% Kehadiran = (Jumlah Hadir / Total Absen yang dicatat admin) * 100
```

### Prinsip Utama:
1. **TIDAK** menghitung berdasarkan jumlah hari kerja dalam 1 bulan
2. **HANYA** menghitung berdasarkan absen yang benar-benar dicatat oleh admin
3. Jika admin belum membuat data absen pada hari tertentu, hari tersebut **tidak dihitung** sama sekali

## Cara Penggunaan

### 1. Penggunaan Dasar

```php
<?php
// Inject service ke controller
public function __construct(
    private AttendanceCalculationService $attendanceService
) {}

// Dapatkan persentase kehadiran pegawai
$pegawai = $this->entityManager->getRepository(Pegawai::class)->find(1);
$dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai);

echo $dataKehadiran['perhitungan']['persentase_kehadiran']; // 85.5
```

### 2. Dengan Parameter Tanggal

```php
<?php
// Untuk bulan dan tahun tertentu
$dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
    $pegawai,
    2024,  // tahun
    3      // bulan (Maret)
);
```

### 3. Fungsi Sederhana

```php
<?php
// Jika hanya perlu persentase saja
$persentase = $this->attendanceService->getSimplePersentaseKehadiran($pegawai);
echo $persentase; // 85.5
```

### 4. Laporan Multiple Pegawai

```php
<?php
$pegawaiList = $this->entityManager->getRepository(Pegawai::class)
    ->findBy(['statusKepegawaian' => 'aktif']);

$laporanKehadiran = $this->attendanceService->getLaporanKehadiranMultiplePegawai(
    $pegawaiList,
    2024,
    3
);

foreach ($laporanKehadiran as $laporan) {
    echo $laporan['pegawai_nama'] . ': ' . $laporan['perhitungan']['persentase_kehadiran'] . '%' . PHP_EOL;
}
```

### 5. Statistik Unit Kerja

```php
<?php
$statistik = $this->attendanceService->getStatistikKehadiranUnitKerja(
    $unitKerjaId,  // atau null untuk semua unit
    2024,
    3
);

echo "Rata-rata kehadiran unit kerja: " . $statistik['statistik_agregat']['rata_rata_persentase'] . '%';
```

## Struktur Data yang Dikembalikan

### getPersentaseKehadiran()

```php
[
    'pegawai_id' => 1,
    'pegawai_nama' => 'John Doe',
    'pegawai_nip' => '123456789',
    'periode' => [
        'tahun' => 2024,
        'bulan' => 3,
        'nama_bulan' => 'Maret'
    ],
    'data_absensi' => [
        'total_absen_tercatat' => 20,  // Hanya absen yang dicatat admin
        'total_hadir' => 15,
        'total_terlambat' => 2,
        'total_izin' => 1,
        'total_sakit' => 0,
        'total_tidak_hadir' => 2,
        'total_kehadiran' => 17,  // hadir + terlambat
    ],
    'perhitungan' => [
        'persentase_kehadiran' => 85.0,  // (17/20) * 100
        'status_kehadiran' => 'Baik',
        'keterangan' => "Berdasarkan 17 dari 20 absen yang tercatat"
    ]
]
```

## Contoh Implementasi di Controller

### Controller Admin

```php
<?php
namespace App\Controller;

use App\Service\AttendanceCalculationService;

#[Route('/admin/laporan')]
class AdminLaporanController extends AbstractController
{
    public function __construct(
        private AttendanceCalculationService $attendanceService
    ) {}

    #[Route('/kehadiran', name: 'app_admin_laporan_kehadiran')]
    public function laporanKehadiran(Request $request): Response
    {
        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));

        // Gunakan service untuk konsistensi
        $statistik = $this->attendanceService->getStatistikKehadiranUnitKerja(
            null, // semua unit kerja
            $tahun,
            $bulan
        );

        return $this->render('admin/laporan_kehadiran.html.twig', [
            'statistik' => $statistik
        ]);
    }
}
```

### Controller User

```php
<?php
namespace App\Controller;

use App\Service\AttendanceCalculationService;

#[Route('/user/dashboard')]
class UserDashboardController extends AbstractController
{
    public function __construct(
        private AttendanceCalculationService $attendanceService
    ) {}

    #[Route('/', name: 'app_user_dashboard')]
    public function dashboard(): Response
    {
        $pegawai = $this->getUser(); // Instance of Pegawai

        // Gunakan service yang sama seperti admin
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai);

        return $this->render('user/dashboard.html.twig', [
            'kehadiran' => $dataKehadiran
        ]);
    }
}
```

## Refactoring Kode Lama

### Sebelum (Tidak Konsisten)

```php
// Di AdminController
$jumlahHariKerja = $this->getJumlahHariKerja($year, $month); // 30 hari
$persentaseKehadiran = ($jumlahHadir / $jumlahHariKerja) * 100; // 15/30 = 50%

// Di UserController
$totalAbsenTercatat = count($riwayatAbsensi); // 20 absen
$persentaseKehadiran = ($jumlahHadir / $totalAbsenTercatat) * 100; // 15/20 = 75%
```

### Sesudah (Konsisten)

```php
// Di AdminController dan UserController (SAMA)
$dataKehadiran = $this->attendanceService->getPersentaseKehadiran($pegawai);
$persentaseKehadiran = $dataKehadiran['perhitungan']['persentase_kehadiran']; // 75%
```

## Keuntungan

1. **Konsistensi**: Admin dan user melihat persentase yang sama
2. **Akurat**: Berdasarkan data yang benar-benar ada
3. **Mudah Maintain**: Satu tempat untuk logika perhitungan
4. **Flexible**: Bisa digunakan untuk berbagai keperluan
5. **Testable**: Service terpisah mudah di-unit test

## Registrasi Service (Otomatis)

Service ini menggunakan autowiring Symfony, jadi tidak perlu konfigurasi manual di `services.yaml`.

## Status Kehadiran

Service menentukan status berdasarkan persentase:

- **≥ 95%**: Sangat Baik
- **≥ 85%**: Baik
- **≥ 75%**: Cukup
- **≥ 60%**: Kurang
- **< 60%**: Sangat Kurang

## Tips Penggunaan

1. **Selalu gunakan service ini** untuk perhitungan persentase kehadiran
2. **Jangan buat perhitungan manual** di controller
3. **Cache hasil** jika diperlukan untuk performa
4. **Validasi input** pegawai dan tanggal sebelum memanggil service

## Troubleshooting

### Persentase 0% padahal ada absensi
- Periksa field `tanggal` dan `tanggalAbsen` di database
- Pastikan status absensi menggunakan nilai yang benar ('hadir', 'terlambat', dll)

### Data tidak konsisten
- Pastikan semua controller menggunakan service ini
- Jangan ada perhitungan manual yang tersisa

### Performance lambat
- Tambahkan index database pada field yang sering di-query
- Pertimbangkan caching untuk data yang jarang berubah