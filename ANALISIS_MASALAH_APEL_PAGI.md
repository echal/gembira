# Analisis Masalah: Duplikasi "Apel Pagi" & Data Tidak Terekam

## 🔍 Masalah yang Dilaporkan

1. **Di halaman /admin/jadwal-absensi**: Terlihat 2 jadwal yang sama
   - "Apel Pagi Senin"
   - "Absen Apel Pagi"

2. **Di laporan PDF bulanan**:
   - Kolom "Absen Apel Pagi" menunjukkan Total = 4, tapi Hadir = 0
   - Data tidak terekam meskipun ada absensi di database

## 🔎 Root Cause Analysis

### 1. Database Structure

Ada **2 tabel jadwal** yang berbeda:
- `jadwal_absensi` (tabel lama, hanya 1 record)
- `konfigurasi_jadwal_absensi` (tabel baru, 26 records)

### 2. Data di `konfigurasi_jadwal_absensi`

```sql
+----+-------------------+--------+-------------+
| ID | Nama Jadwal       | Aktif  | Total Data  |
+----+-------------------+--------+-------------+
| 19 | Apel Pagi         | ❌ 0   | 252 absensi |
| 23 | Apel Pagi Senin   | ❌ 0   | 65 absensi  |
| 24 | Absen Apel Pagi   | ✅ 1   | 432 absensi |
| 25 | Apel Pagii (typo) | ❌ 0   | 1 absensi   |
+----+-------------------+--------+-------------+
```

### 3. Masalah di Controller

**File**: `src/Controller/AdminLaporanBulananController.php:995`

```php
$jadwalApelPagi = $jadwalRepo->findOneBy([
    'namaJadwal' => 'Apel Pagi',  // ❌ TIDAK DITEMUKAN (yang aktif namanya "Absen Apel Pagi")
    'isAktif' => true
]);
```

**Akibat**:
- `$jadwalApelPagi` = NULL (karena "Apel Pagi" tidak aktif)
- Saat perhitungan di line 1078-1082, kondisi tidak pernah TRUE
- Semua data absensi dengan jadwal "Absen Apel Pagi" (ID 24) diabaikan
- Laporan menunjukkan Total = 4 (expected hari Senin), Hadir = 0 (tidak ada yang dihitung)

### 4. Data Absensi NULL

Dari 2451 total absensi:
- **2446 records** memiliki `jenis_absensi = NULL` dan `konfigurasi_jadwal_id = NULL`
- Data ini dari backup production lama
- Tidak terhubung ke jadwal manapun

## ✅ Solusi

### Solusi 1: Update Nama Jadwal (Recommended)
Rename jadwal "Absen Apel Pagi" menjadi "Apel Pagi" agar sesuai dengan kode:

```sql
UPDATE konfigurasi_jadwal_absensi
SET nama_jadwal = 'Apel Pagi'
WHERE id = 24;
```

### Solusi 2: Nonaktifkan Jadwal Duplikat
Nonaktifkan jadwal "Absen Apel Pagi" dan aktifkan yang "Apel Pagi":

```sql
-- Nonaktifkan "Absen Apel Pagi"
UPDATE konfigurasi_jadwal_absensi
SET is_aktif = 0
WHERE id = 24;

-- Aktifkan "Apel Pagi" asli
UPDATE konfigurasi_jadwal_absensi
SET is_aktif = 1
WHERE id = 19;

-- Pindahkan semua absensi dari ID 24 ke ID 19
UPDATE absensi
SET konfigurasi_jadwal_id = 19
WHERE konfigurasi_jadwal_id = 24;
```

### Solusi 3: Update Controller (Fallback)
Tambahkan fallback di controller untuk mencari "Absen Apel Pagi":

```php
$jadwalApelPagi = $jadwalRepo->findOneBy([
    'namaJadwal' => 'Apel Pagi',
    'isAktif' => true
]);

// FALLBACK: Coba cari dengan nama alternatif
if (!$jadwalApelPagi) {
    $jadwalApelPagi = $jadwalRepo->findOneBy([
        'namaJadwal' => 'Absen Apel Pagi',
        'isAktif' => true
    ]);
}
```

## 📋 Data Statistik

- Total jadwal Apel Pagi (semua varian): 4
- Total absensi terkait: 750 records
  - ID 24 "Absen Apel Pagi": 432 (57.6%)
  - ID 19 "Apel Pagi": 252 (33.6%)
  - ID 23 "Apel Pagi Senin": 65 (8.7%)
  - ID 25 "Apel Pagii": 1 (0.1%)

- Absensi tanpa jadwal: 2446 records (jenis_absensi = NULL)

## 🎯 Rekomendasi

**Pilih Solusi 1** (paling sederhana):
1. Rename "Absen Apel Pagi" → "Apel Pagi"
2. Hapus jadwal duplikat yang tidak aktif
3. Migrate data lama (jenis_absensi=NULL) ke jadwal yang sesuai

Ini akan membuat:
- Tampilan UI konsisten (hanya 1 "Apel Pagi")
- Laporan PDF berfungsi normal (data terhitung)
- Tidak perlu ubah kode controller
