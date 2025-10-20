# 🏆 Sistem Ranking GEMBIRA v2.0 - Berdasarkan Skor Harian (07:00-08:15 WITA)

## 📋 Overview Perubahan

Sistem ranking telah diperbarui dari **perhitungan berbasis persentase kehadiran** menjadi **perhitungan berbasis skor harian** dengan logika:

- **Check-in Only**: Tanpa check-out
- **Jam Buka**: 07:00 WITA
- **Jam Tutup**: 08:15 WITA
- **Skor Maksimal**: 75 poin
- **Acuan Waktu**: 07:00 WITA (semakin cepat = skor semakin tinggi)

---

## ✅ FILE YANG TELAH DIUPDATE

### **1. Entity & Repository**

| File | Status | Perubahan |
|------|--------|-----------|
| `src/Entity/RankingHarian.php` | ✅ Updated | Tambah field `jam_masuk`, `skor_harian` |
| `src/Repository/RankingHarianRepository.php` | ✅ Existing | Tidak perlu perubahan (sudah ada method yang dibutuhkan) |
| `src/Repository/RankingBulananRepository.php` | ✅ Existing | Tidak perlu perubahan |

### **2. Service Layer**

| File | Status | Method Baru |
|------|--------|-------------|
| `src/Service/AttendanceCalculationService.php` | ✅ Updated | `hitungSkorHarian()`, `isWaktuAbsenValid()`, `getInfoSkorHarian()` |
| `src/Service/RankingService.php` | ✅ Updated | `recalculateRankingHarianBySkor()`, `getAllDailyRanking()`, `getAllMonthlyRanking()`, `getAllGroupRanking()`, `calculateMonthlyAccumulationBySkor()` |

### **3. Controller**

| File | Status | Fungsi |
|------|--------|--------|
| `src/Controller/AdminRankingController.php` | ✅ Created | Controller baru untuk halaman "Lihat Ranking" admin |
| `src/Controller/AbsensiController.php` | ✅ Existing | Sudah memanggil `updateDailyRanking()` setelah absensi |

### **4. Migration**

| File | Status | Fungsi |
|------|--------|--------|
| `migrations/Version20250120100000_UpdateRankingHarianAddSkor.php` | ✅ Created | Tambah field `jam_masuk` dan `skor_harian` ke tabel `ranking_harian` |

---

## 🚀 CARA INSTALASI & SETUP

### **Step 1: Jalankan Migration Database**

```bash
# Jalankan migration untuk update tabel ranking_harian
php bin/console doctrine:migrations:migrate

# Verifikasi tabel sudah terupdate
php bin/console doctrine:schema:validate
```

**Atau via SQL Manual** (jika tidak menggunakan Doctrine):

```sql
-- Tambah field jam_masuk
ALTER TABLE ranking_harian
ADD COLUMN jam_masuk TIME NULL AFTER tanggal;

-- Tambah field skor_harian
ALTER TABLE ranking_harian
ADD COLUMN skor_harian INT NOT NULL DEFAULT 0 AFTER jam_masuk
COMMENT 'Skor harian (maksimal 75, berdasarkan kecepatan absen 07:00-08:15)';

-- Ubah total_durasi menjadi nullable
ALTER TABLE ranking_harian
MODIFY COLUMN total_durasi INT NULL;
```

### **Step 2: Clear Cache Symfony**

```bash
php bin/console cache:clear
```

### **Step 3: Testing Sistem**

1. Login sebagai pegawai
2. Lakukan absensi antara jam 07:00-08:15 WITA
3. Cek dashboard - ranking harus update otomatis dengan skor harian
4. Login sebagai admin → Klik menu "Lihat Ranking"
5. Verifikasi semua ranking terkalkulasi dengan benar

---

## 📊 LOGIKA SISTEM RANKING BARU

### **1. Perhitungan Skor Harian**

```
Acuan Waktu = 07:00 WITA
Skor Maksimal = 75

Formula:
skor = 75 - (selisih menit dari 07:00)

Contoh:
- Absen jam 07:00 → Skor = 75 (maksimal)
- Absen jam 07:15 → Skor = 60
- Absen jam 07:30 → Skor = 45
- Absen jam 08:00 → Skor = 15
- Absen jam 08:15 → Skor = 0
- Absen sebelum 07:00 → Skor = 75 (bonus)
```

### **2. Ranking Harian**

- **Trigger**: Setiap kali pegawai melakukan absensi
- **Urutan**:
  1. Skor tertinggi (DESC)
  2. Jam masuk tercepat (ASC) - untuk tie-breaking
- **Method**: `RankingService::recalculateRankingHarianBySkor()`

### **3. Ranking Bulanan**

- **Trigger**: Cron job setiap tanggal 1 pukul 01:00 WITA
- **Perhitungan**: Total skor semua hari dalam sebulan
- **Urutan**: Total skor tertinggi (DESC)
- **Method**: `RankingService::calculateMonthlyAccumulationBySkor()`

### **4. Ranking Unit Kerja**

- **Trigger**: Real-time setiap request
- **Perhitungan**: Rata-rata skor pegawai per unit kerja
- **Urutan**: Rata-rata tertinggi (DESC)
- **Method**: `RankingService::getAllGroupRanking()`

---

## 🎯 API METHODS YANG TERSEDIA

### **RankingService Methods**

```php
// 1. Dapatkan semua ranking harian (untuk admin)
$rankingHarian = $rankingService->getAllDailyRanking($tanggal);
// Return: Array of RankingHarian entities

// 2. Dapatkan semua ranking bulanan (untuk admin)
$rankingBulanan = $rankingService->getAllMonthlyRanking($periode);
// Return: Array of RankingBulanan entities

// 3. Dapatkan ranking per unit kerja (untuk admin)
$rankingGroup = $rankingService->getAllGroupRanking($tanggal);
// Return: Array of ['nama_unit', 'rata_rata_skor', 'total_pegawai', 'peringkat']

// 4. Update ranking setelah absensi (otomatis dipanggil di AbsensiController)
$result = $rankingService->updateDailyRanking($pegawai, $waktuAbsensi);

// 5. Hitung akumulasi bulanan berdasarkan skor
$result = $rankingService->calculateMonthlyAccumulationBySkor($tahun, $bulan);
```

### **AttendanceCalculationService Methods**

```php
// 1. Hitung skor harian dari jam masuk
$skor = $attendanceService->hitungSkorHarian($jamMasuk);
// Return: int (0-75)

// 2. Validasi apakah waktu absen valid (07:00-08:15)
$valid = $attendanceService->isWaktuAbsenValid($jamMasuk);
// Return: bool

// 3. Dapatkan info lengkap skor
$info = $attendanceService->getInfoSkorHarian($jamMasuk);
// Return: ['skor', 'selisih_menit', 'status', 'valid', 'jam_masuk', 'jam_acuan']
```

---

## 🌐 ROUTES ADMIN - "Lihat Ranking"

### **Halaman Utama**

```
URL: /admin/ranking
Route Name: admin_ranking_index
Method: GET
Parameters:
  - tanggal (optional): Format Y-m-d (default: hari ini)
  - periode (optional): Format Y-m (default: bulan ini)
```

### **API Endpoints**

```
1. Ranking Harian
   URL: /admin/ranking/api/harian
   Method: GET
   Parameters: tanggal (optional)

2. Ranking Bulanan
   URL: /admin/ranking/api/bulanan
   Method: GET
   Parameters: periode (optional)

3. Ranking Unit Kerja
   URL: /admin/ranking/api/group
   Method: GET
   Parameters: tanggal (optional)
```

---

## 📝 FILE YANG MASIH PERLU DIBUAT

### **1. Template Admin Ranking** ❌ (Belum dibuat)

File: `templates/admin/ranking/index.html.twig`

Template ini perlu menampilkan 3 tabel:
- Tabel Ranking Harian
- Tabel Ranking Bulanan
- Tabel Ranking Unit Kerja

**Struktur yang dibutuhkan**:

```twig
{% extends 'admin/base.html.twig' %}

{% block title %}{{ page_title }}{% endblock %}

{% block content %}
<div class="container-fluid">
    <!-- Header dengan filter tanggal/periode -->

    <!-- Tabel 1: Ranking Harian -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>🥇 Ranking Harian - {{ tanggal_dipilih|date('d/m/Y') }}</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>Unit Kerja</th>
                        <th>Jam Masuk</th>
                        <th>Skor</th>
                    </tr>
                </thead>
                <tbody>
                    {% for ranking in ranking_harian %}
                    <tr>
                        <td>{{ ranking.peringkatBadge }}</td>
                        <td>{{ ranking.pegawai.nama }}</td>
                        <td>{{ ranking.pegawai.nip }}</td>
                        <td>{{ ranking.pegawai.namaUnitKerja }}</td>
                        <td>{{ ranking.jamMasuk|date('H:i') }}</td>
                        <td><strong>{{ ranking.skorHarian }}</strong></td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel 2: Ranking Bulanan -->
    <!-- ... sama seperti di atas ... -->

    <!-- Tabel 3: Ranking Unit Kerja -->
    <!-- ... sama seperti di atas ... -->
</div>
{% endblock %}
```

### **2. Command Update Ranking Harian** ❌ (Belum dibuat)

File: `src/Command/UpdateRankingHarianCommand.php`

Command ini dijadwalkan via cron untuk jalan setiap hari pukul 08:20 WITA (setelah jam absen tutup).

**Fungsi**: Recalculate semua ranking harian untuk hari ini

**Cron Setup**:
```bash
20 8 * * * cd /path/to/gembira && php bin/console app:ranking:update-harian
```

### **3. Update Command Reset Ranking Bulanan** ⚠️ (Perlu di-update)

File: `src/Command/ResetRankingCommand.php` (sudah ada, perlu update)

**Perubahan yang diperlukan**:
- Gunakan method `calculateMonthlyAccumulationBySkor()` instead of `calculateMonthlyAccumulation()`
- Sudah dijadwalkan via cron: Tanggal 1 pukul 01:00

---

## 🧪 TESTING & VERIFIKASI

### **Test 1: Hitung Skor Harian**

```php
use App\Service\AttendanceCalculationService;

$attendanceService = $container->get(AttendanceCalculationService::class);

$jam0700 = new \DateTime('2025-01-20 07:00:00');
$skor1 = $attendanceService->hitungSkorHarian($jam0700);
// Expected: 75

$jam0715 = new \DateTime('2025-01-20 07:15:00');
$skor2 = $attendanceService->hitungSkorHarian($jam0715);
// Expected: 60

$jam0815 = new \DateTime('2025-01-20 08:15:00');
$skor3 = $attendanceService->hitungSkorHarian($jam0815);
// Expected: 0
```

### **Test 2: Ranking Harian**

```bash
# Login sebagai 3 pegawai berbeda
# Pegawai A absen jam 07:05 → Skor 70
# Pegawai B absen jam 07:10 → Skor 65
# Pegawai C absen jam 07:00 → Skor 75

# Expected Ranking:
# 1. Pegawai C (skor 75)
# 2. Pegawai A (skor 70)
# 3. Pegawai B (skor 65)
```

### **Test 3: Validasi Waktu**

```php
$jam0645 = new \DateTime('2025-01-20 06:45:00');
$valid1 = $attendanceService->isWaktuAbsenValid($jam0645);
// Expected: false (terlalu awal)

$jam0700 = new \DateTime('2025-01-20 07:00:00');
$valid2 = $attendanceService->isWaktuAbsenValid($jam0700);
// Expected: true

$jam0820 = new \DateTime('2025-01-20 08:20:00');
$valid3 = $attendanceService->isWaktuAbsenValid($jam0820);
// Expected: false (terlalu terlambat)
```

---

## 📌 CATATAN PENTING

1. **Field `total_durasi` di `ranking_harian`** sekarang nullable dan tidak digunakan lagi (untuk backward compatibility).

2. **Field `total_durasi` di `ranking_bulanan`** sekarang menyimpan **TOTAL SKOR** (bukan durasi menit).

3. **Jam ideal** hardcoded di `AttendanceCalculationService` = 07:00 WITA.

4. **Skor maksimal** = 75 poin.

5. **Absen sebelum 07:00** tetap mendapat skor maksimal (75).

6. **Absen setelah 08:15** akan ditolak (diluar waktu valid).

---

## 🎉 **SISTEM SUDAH 90% SELESAI!**

Yang sudah selesai:
- ✅ Entity & Repository
- ✅ Service Layer (logic perhitungan)
- ✅ Controller (AdminRankingController)
- ✅ Migration database
- ✅ API Endpoints

Yang masih perlu dibuat:
- ❌ Template `admin/ranking/index.html.twig`
- ❌ Command `UpdateRankingHarianCommand`
- ❌ Update `ResetRankingCommand` untuk gunakan method baru

Silakan lanjutkan dengan membuat 3 file terakhir tersebut, atau saya bisa membuatnya jika Anda minta! 🚀
