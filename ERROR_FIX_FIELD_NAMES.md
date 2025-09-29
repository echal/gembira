# PERBAIKAN ERROR: Field Names di Entity Absensi

## 🚨 Error yang Ditemukan

```
[Semantical Error] line 0, col 656 near 'tanggalAbsen': Error: Class App\Entity\Absensi has no field or association named tanggalAbsen
```

## 🔍 Analisis

Setelah memeriksa `src/Entity/Absensi.php`, field yang benar adalah:

### ✅ Field yang BENAR:
- `tanggal` (bukan `tanggalAbsen`)
- `fotoPath` dan `fotoSelfie` (bukan `fotoAbsensi`)
- `qrCodeUsed` dan `qrCodeScanned` (bukan `qrCodeData`)
- `lokasiAbsensi` (bukan `lokasiGps`)

## 🔧 Perbaikan yang Dilakukan

### 1. **AttendanceCalculationService.php**
```php
// SEBELUM (ERROR):
->andWhere('a.tanggal >= :startDate OR a.tanggalAbsen >= :startDate')
->andWhere('a.tanggal <= :endDate OR a.tanggalAbsen <= :endDate')

// SESUDAH (FIXED):
->andWhere('a.tanggal >= :startDate')
->andWhere('a.tanggal <= :endDate')
```

### 2. **LaporanBulananService.php**
```php
// SEBELUM (ERROR):
->select('COUNT(CASE WHEN a.fotoAbsensi IS NOT NULL THEN 1 END) as has_foto')
->addSelect('COUNT(CASE WHEN a.qrCodeData IS NOT NULL THEN 1 END) as has_qr')
->addSelect('COUNT(CASE WHEN a.lokasiGps IS NOT NULL THEN 1 END) as has_gps')
->andWhere('a.tanggal >= :startDate OR a.tanggalAbsen >= :startDate')

// SESUDAH (FIXED):
->select('COUNT(CASE WHEN (a.fotoPath IS NOT NULL OR a.fotoSelfie IS NOT NULL) THEN 1 END) as has_foto')
->addSelect('COUNT(CASE WHEN (a.qrCodeUsed IS NOT NULL OR a.qrCodeScanned IS NOT NULL) THEN 1 END) as has_qr')
->addSelect('COUNT(CASE WHEN a.lokasiAbsensi IS NOT NULL THEN 1 END) as has_gps')
->andWhere('a.tanggal >= :startDate')
```

## ✅ Status

**ERROR SUDAH DIPERBAIKI**

Sekarang semua query menggunakan field names yang benar sesuai dengan Entity Absensi:
- ✅ `tanggal` untuk tanggal absensi
- ✅ `fotoPath` dan `fotoSelfie` untuk foto
- ✅ `qrCodeUsed` dan `qrCodeScanned` untuk QR code
- ✅ `lokasiAbsensi` untuk GPS location

## 🚀 Testing

Setelah perbaikan ini, sistem seharusnya tidak menampilkan error semantical lagi saat mengakses:
- Ranking user
- Laporan bulanan admin
- Dashboard user dengan AttendanceCalculationService