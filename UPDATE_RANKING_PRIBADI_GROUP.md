# ✅ UPDATE LENGKAP: RANKING PRIBADI & GROUP BERDASARKAN SKOR BULANAN

## 📊 Problem yang Diselesaikan

**SEBELUM**:
- Ranking Pribadi: Menampilkan persentase kehadiran (100%)
- Ranking Group: Menampilkan persentase kehadiran
- Top 10 Global: Sudah menggunakan akumulasi skor ✅

**SESUDAH**:
- ✅ Ranking Pribadi: Menampilkan total skor akumulasi bulanan + rata-rata skor per hari
- ✅ Ranking Group: Menampilkan rata-rata skor unit + posisi unit
- ✅ Top 10 Global: Tetap menggunakan akumulasi skor (sudah benar)

---

## 🔧 PERUBAHAN YANG DILAKUKAN

### 1. **RankingService.php** - Method Baru
File: [src/Service/RankingService.php:1272-1405](src/Service/RankingService.php#L1272-L1405)

#### a. Method `getRankingPribadiByMonthlyScore()`
```php
/**
 * Dapatkan ranking pribadi pegawai berdasarkan AKUMULASI SKOR BULANAN
 *
 * @return array [
 *   'posisi' => int,           // Peringkat pegawai (1 = terbaik)
 *   'total_pegawai' => int,    // Total pegawai yang memiliki ranking
 *   'total_skor' => int,       // Total akumulasi skor dari awal bulan
 *   'rata_rata_skor' => float, // Rata-rata skor per hari
 *   'status' => string         // Badge status (Excellent, Sangat Baik, dll)
 * ]
 */
```

**Cara Kerja**:
1. Ambil data dari tabel `ranking_bulanan` berdasarkan periode saat ini
2. Return posisi pegawai dan total skor akumulasi
3. Jika belum ada data (belum absen bulan ini), return posisi 0

#### b. Method `getRankingGroupByMonthlyScore()`
```php
/**
 * Dapatkan ranking unit kerja berdasarkan AKUMULASI SKOR BULANAN
 *
 * @return array [
 *   'posisi' => int,           // Peringkat unit (1 = terbaik)
 *   'nama_unit' => string,     // Nama unit kerja
 *   'rata_rata_skor' => float, // Rata-rata skor unit
 *   'total_pegawai' => int,    // Jumlah pegawai di unit ini
 *   'total_unit' => int        // Total unit yang memiliki ranking
 * ]
 */
```

**Cara Kerja**:
1. Ambil semua ranking bulanan periode ini
2. Group by unit kerja
3. Hitung rata-rata skor per unit (dari rata-rata skor pegawai)
4. Urutkan berdasarkan rata-rata tertinggi
5. Return posisi unit kerja pegawai

---

### 2. **AbsensiController.php** - Update Dashboard Method
File: [src/Controller/AbsensiController.php:90-107](src/Controller/AbsensiController.php#L90-L107)

**SEBELUM**:
```php
// Menggunakan method lama berbasis persentase
$rankingPribadi = $this->rankingService->getRankingPribadi($pegawai);
$rankingGroup = $this->rankingService->getRankingGroup($pegawai);
```

**SESUDAH**:
```php
// Menggunakan method baru berbasis akumulasi skor bulanan
$rankingPribadi = $this->rankingService->getRankingPribadiByMonthlyScore($pegawai);
$rankingGroup = $this->rankingService->getRankingGroupByMonthlyScore($pegawai);
```

---

### 3. **AbsensiController.php** - Update API Endpoint
File: [src/Controller/AbsensiController.php:231-264](src/Controller/AbsensiController.php#L231-L264)

**Perubahan**:
- API endpoint `/absensi/api/ranking-update` sekarang mengembalikan data skor bulanan
- Menghapus data ranking persentase dan skor harian (tidak digunakan lagi)

**Response JSON**:
```json
{
  "success": true,
  "ranking_pribadi": {
    "posisi": 106,
    "total_pegawai": 254,
    "total_skor": 450,
    "rata_rata_skor": 64.3,
    "status": "🥈 Baik"
  },
  "ranking_group": {
    "posisi": 43,
    "nama_unit": "Bagian Tata Usaha",
    "rata_rata_skor": 62.5,
    "total_pegawai": 8,
    "total_unit": 92
  },
  "top_10_pegawai": [ ... ],
  "timestamp": "2025-10-27 14:30:00"
}
```

---

### 4. **flexible.html.twig** - Update Template Display
File: [templates/dashboard/flexible.html.twig:97-129](templates/dashboard/flexible.html.twig#L97-L129)

**SEBELUM**:
```twig
🏅 Ranking Anda: #106 dari 254 (100%) 🟢
```

**SESUDAH**:
```twig
🏅 Ranking Anda: #106 dari 254 (450 poin) 🥈 Baik
Rata-rata skor: 64.3 poin/hari

📊 Ranking Group
Bagian Bagian Tata Usaha: #43 dari 92 Unit (Rata-rata: 62.5 poin)
```

---

### 5. **flexible.html.twig** - Update JavaScript AJAX
File: [templates/dashboard/flexible.html.twig:538-556](templates/dashboard/flexible.html.twig#L538-L556)

**Perubahan**:
- Update function `updateRankingDisplay()` untuk handle field `total_skor` dan `rata_rata_skor`
- Update display Ranking Group untuk menampilkan `total_unit` dan `rata_rata_skor`

---

## 📊 CONTOH OUTPUT

### Dashboard Pegawai

```
┌───────────────────────────────────────────────────────────┐
│  🏅 Ranking Anda: #106 dari 254 (450 poin) 🥈 Baik       │
│  Rata-rata skor: 64.3 poin/hari                           │
│                                                             │
│  📊 Ranking Group                                          │
│  Bagian Tata Usaha: #43 dari 92 Unit (Rata-rata: 62.5    │
│  poin)                                                      │
└───────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────┐
│  🏆 Top 10 Pegawai (Global)                                │
│                                                             │
│  #   Nama              Unit Kerja      Total Skor  Badge   │
│  ──────────────────────────────────────────────────────── │
│  1.  Ahmad Faisal     IT Development   520         🏆       │
│  2.  Siti Rahma       HRD              515         🏆       │
│  3.  Budi Santoso     Finance          510         🏆       │
│  4.  Dewi Lestari     Marketing        495         🥇       │
│  5.  Eko Prasetyo     IT Development   490         🥇       │
│  ...                                                        │
└───────────────────────────────────────────────────────────┘
```

---

## 🧪 TESTING

### Test Case 1: Verifikasi Ranking Pribadi Menampilkan Skor
**Langkah**:
1. Login sebagai pegawai
2. Lihat dashboard bagian "Ranking Anda"

**Expected Result**:
```
🏅 Ranking Anda: #X dari Y (ZZZ poin) Badge
Rata-rata skor: XX.X poin/hari
```

✅ **TIDAK LAGI MENAMPILKAN PERSENTASE (%)**

---

### Test Case 2: Verifikasi Ranking Group Menampilkan Skor
**Langkah**:
1. Login sebagai pegawai
2. Lihat dashboard bagian "Ranking Group"

**Expected Result**:
```
📊 Ranking Group
Bagian [Nama Unit]: #X dari Y Unit (Rata-rata: ZZ.Z poin)
```

✅ **TIDAK LAGI MENAMPILKAN "dari X Pegawai"**

---

### Test Case 3: Verifikasi Auto-Update (AJAX)
**Langkah**:
1. Login sebagai pegawai
2. Tunggu 5 menit (auto-refresh berjalan setiap 5 menit)
3. Atau buka Console browser → Jalankan: `updateRankingData()`

**Expected Result**:
- Ranking Pribadi dan Group ter-update dengan data terbaru
- Format tetap menampilkan skor (bukan persentase)

---

### Test Case 4: Verifikasi API Response
**Langkah**:
1. Buka browser Console (F12)
2. Jalankan:
```javascript
fetch('/absensi/api/ranking-update')
  .then(r => r.json())
  .then(d => console.log(d));
```

**Expected Result**:
```json
{
  "success": true,
  "ranking_pribadi": {
    "posisi": 106,
    "total_skor": 450,      ← ADA FIELD INI
    "rata_rata_skor": 64.3  ← ADA FIELD INI
  },
  "ranking_group": {
    "rata_rata_skor": 62.5, ← ADA FIELD INI
    "total_unit": 92        ← ADA FIELD INI
  }
}
```

---

## 🗄️ SQL VERIFIKASI

### 1. Cek Data Ranking Pribadi
```sql
-- Cek ranking bulanan pegawai
SELECT
    p.nama,
    rb.peringkat,
    rb.total_durasi AS total_skor,
    rb.rata_rata_durasi AS rata_rata_skor,
    rb.periode
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
WHERE p.id = 1  -- Ganti dengan ID pegawai yang ingin dicek
  AND rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m');
```

**Expected**:
- `total_skor` = akumulasi skor dari awal bulan
- `rata_rata_skor` = rata-rata skor per hari

---

### 2. Cek Ranking Group/Unit
```sql
-- Hitung rata-rata skor per unit
SELECT
    u.nama_unit,
    COUNT(rb.id) AS total_pegawai,
    AVG(rb.rata_rata_durasi) AS rata_rata_skor_unit
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
JOIN unit_kerja u ON u.id = p.unit_kerja_entity_id
WHERE rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
GROUP BY u.id, u.nama_unit
ORDER BY rata_rata_skor_unit DESC;
```

**Expected**:
- Unit dengan rata-rata skor tertinggi di posisi teratas
- Sesuai dengan ranking yang ditampilkan di dashboard

---

## 🔍 TROUBLESHOOTING

### Problem 1: Ranking Pribadi Masih Menampilkan Persentase
**Kemungkinan Penyebab**:
- Cache browser belum di-clear
- Template file belum ter-update

**Solusi**:
```bash
# Clear Symfony cache
php bin/console cache:clear

# Hard refresh browser: Ctrl+Shift+R (Windows) atau Cmd+Shift+R (Mac)
```

---

### Problem 2: Total Skor Menampilkan 0
**Kemungkinan Penyebab**:
- Pegawai belum absen bulan ini
- Tabel `ranking_bulanan` belum ter-update

**Solusi**:
```bash
# Manual trigger update ranking
php bin/console app:update-ranking-harian

# Atau lakukan absensi manual untuk trigger auto-update
```

---

### Problem 3: Ranking Group Tidak Muncul
**Kemungkinan Penyebab**:
- Pegawai tidak memiliki unit kerja (field `unit_kerja_entity_id` NULL)

**Solusi**:
```sql
-- Cek unit kerja pegawai
SELECT id, nama, unit_kerja_entity_id
FROM pegawai
WHERE id = [ID_PEGAWAI];

-- Jika NULL, assign unit kerja
UPDATE pegawai
SET unit_kerja_entity_id = [ID_UNIT]
WHERE id = [ID_PEGAWAI];
```

---

## ✅ CHECKLIST UPDATE

- [x] Buat method `getRankingPribadiByMonthlyScore()` di RankingService
- [x] Buat method `getRankingGroupByMonthlyScore()` di RankingService
- [x] Update `AbsensiController::dashboardAbsensi()` untuk gunakan method baru
- [x] Update `AbsensiController::apiRankingUpdate()` untuk return data baru
- [x] Update template `flexible.html.twig` untuk display skor (bukan persentase)
- [x] Update JavaScript `updateRankingDisplay()` untuk handle field baru
- [ ] **Testing manual**: Verifikasi ranking pribadi dan group menampilkan skor
- [ ] **Clear cache**: `php bin/console cache:clear`

---

## 📝 CATATAN PENTING

1. **Backward Compatibility**: Method lama (`getRankingPribadi()` dan `getRankingGroup()`) masih ada di RankingService untuk backward compatibility dengan fitur lain (laporan, admin dashboard, dll)

2. **Field Naming**:
   - Field `total_durasi` di tabel `ranking_bulanan` menyimpan **TOTAL SKOR** (bukan durasi)
   - Field `rata_rata_durasi` menyimpan **RATA-RATA SKOR** (bukan durasi)
   - Naming ini dipertahankan untuk backward compatibility

3. **Auto-Update**:
   - Ranking ter-update otomatis setiap kali ada absensi baru
   - AJAX refresh berjalan setiap 5 menit untuk update display

4. **Konsistensi**:
   - Semua ranking di dashboard pegawai sekarang berbasis **AKUMULASI SKOR BULANAN**
   - Ranking Pribadi, Ranking Group, dan Top 10 Global semua menggunakan sistem yang sama

---

## 🎉 DONE!

Sekarang **Ranking Pribadi**, **Ranking Group**, dan **Top 10 Global** semuanya menampilkan data berdasarkan **akumulasi skor bulanan** yang ter-update otomatis! 🚀

**Refresh browser Anda dan lihat hasilnya!** ✅
