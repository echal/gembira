# Testing Sistem Akumulasi Skor Bulanan - Top 10 Global

## 🎯 Tujuan Testing
Memastikan bahwa Top 10 Global di dashboard menampilkan ranking berdasarkan **akumulasi skor bulanan** yang ter-update otomatis setiap kali ada absensi baru.

## 📋 Perubahan yang Telah Dilakukan

### 1. **RankingService.php**
- ✅ Update method `updateDailyRanking()` untuk:
  - Menghitung **skor harian** (bukan durasi) menggunakan `AttendanceCalculationService::hitungSkorHarian()`
  - Menyimpan skor ke tabel `ranking_harian`
  - Memanggil `recalculateRankingHarianBySkor()` untuk update ranking harian
  - Memanggil `calculateMonthlyAccumulationBySkor()` untuk update ranking bulanan

### 2. **Template (flexible.html.twig)**
- ✅ Update header tabel: "% Kehadiran" → "Total Skor"
- ✅ Update isi tabel: `{{ pegawai_top.persentase }}%` → `{{ pegawai_top.total_skor|default(0) }}`
- ✅ Update JavaScript AJAX: menampilkan `total_skor` bukan `persentase`
- ✅ Update footer: penjelasan tentang sistem akumulasi skor bulanan

## 🧪 Skenario Test

### Test Case 1: Akumulasi Skor Hari Pertama
**Tujuan**: Memastikan skor hari pertama tercatat dengan benar

**Langkah**:
1. Login sebagai Pegawai A
2. Absen pada jam 07:03 WITA
3. Cek dashboard → Top 10 Global

**Expected Result**:
- Pegawai A muncul dengan **Total Skor = 72**
  - Perhitungan: 75 - 3 menit = 72
- Peringkat: #1 (jika tidak ada pegawai lain)

---

### Test Case 2: Akumulasi Skor Hari Kedua (Same Pegawai)
**Tujuan**: Memastikan skor terakumulasi dari hari ke hari

**Langkah**:
1. Hari berikutnya, Pegawai A absen lagi pada jam 07:10 WITA
2. Cek dashboard → Top 10 Global

**Expected Result**:
- Pegawai A: **Total Skor = 137**
  - Hari 1: 72 (07:03 → 75-3)
  - Hari 2: 65 (07:10 → 75-10)
  - **Total: 72 + 65 = 137**

---

### Test Case 3: Perubahan Ranking (Multiple Pegawai)
**Tujuan**: Memastikan ranking berubah otomatis ketika ada pegawai dengan skor lebih tinggi

**Setup Awal** (Hari 1):
- Pegawai A: 07:03 → Skor = 72
- Pegawai B: 07:05 → Skor = 70

**Ranking Hari 1**:
1. Pegawai A: 72
2. Pegawai B: 70

**Aksi** (Hari 2):
- Pegawai A: 07:10 → Skor = 65 → **Total Akumulasi = 72 + 65 = 137**
- Pegawai B: 07:06 → Skor = 69 → **Total Akumulasi = 70 + 69 = 139**

**Expected Result** (Ranking Hari 2):
1. **Pegawai B: 139** ← Ranking naik!
2. Pegawai A: 137 ← Ranking turun!

---

### Test Case 4: Tie-Breaking (Skor Sama)
**Tujuan**: Memastikan tie-breaking berdasarkan jam masuk tercepat

**Setup**:
- Pegawai A: Total Akumulasi = 150, Jam Masuk Terakhir = 07:05
- Pegawai B: Total Akumulasi = 150, Jam Masuk Terakhir = 07:03

**Expected Result**:
1. Pegawai B: 150 (jam 07:03 lebih cepat)
2. Pegawai A: 150

---

### Test Case 5: Reset Bulanan
**Tujuan**: Memastikan skor di-reset saat ganti bulan

**Aksi**:
1. Tanggal 31 Januari: Pegawai A memiliki total skor 500
2. Tanggal 1 Februari: Pegawai A absen pertama kali

**Expected Result**:
- Top 10 Global hanya menampilkan data bulan Februari
- Pegawai A mulai dari skor hari ini saja (tidak carry over dari Januari)

---

## 🔍 Cara Verifikasi Manual

### 1. Cek Database - Ranking Harian
```sql
-- Lihat ranking harian hari ini
SELECT
    p.nama,
    rh.jam_masuk,
    rh.skor_harian,
    rh.peringkat,
    rh.tanggal
FROM ranking_harian rh
JOIN pegawai p ON p.id = rh.pegawai_id
WHERE rh.tanggal = CURDATE()
ORDER BY rh.peringkat ASC;
```

**Expected**: Setiap kali ada absensi baru, ranking harian harus ter-update

---

### 2. Cek Database - Ranking Bulanan
```sql
-- Lihat ranking bulanan bulan ini
SELECT
    p.nama,
    rb.total_durasi as total_skor, -- Field ini menyimpan total skor
    rb.rata_rata_durasi as rata_rata_skor,
    rb.peringkat,
    rb.periode
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
WHERE rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY rb.peringkat ASC
LIMIT 10;
```

**Expected**:
- Total skor = akumulasi dari semua skor harian
- Ranking diurutkan berdasarkan total skor tertinggi

---

### 3. Cek Log Error
```bash
# Lihat error log Symfony
tail -f var/log/dev.log

# Atau cek Apache error log
tail -f C:/xampp/apache/logs/error.log
```

**Expected**: Tidak ada error terkait ranking service

---

## 🐛 Troubleshooting

### Problem 1: Top 10 tidak berubah setelah absensi
**Kemungkinan Penyebab**:
- Method `updateDailyRanking()` tidak dipanggil di `AbsensiController`
- Error di method `calculateMonthlyAccumulationBySkor()`

**Solusi**:
1. Cek apakah method `updateDailyRanking()` ada di line 605 `AbsensiController.php`
2. Cek log error untuk error SQL atau exception
3. Verifikasi field `skor_harian` ada di tabel `ranking_harian`

---

### Problem 2: Total Skor menampilkan 0
**Kemungkinan Penyebab**:
- Method `getTop10ByMonthlyScore()` tidak mengembalikan field `total_skor`
- Template masih menggunakan field `persentase`

**Solusi**:
1. Cek return value di `RankingService::getTop10ByMonthlyScore()` line 933-943
2. Pastikan template menggunakan `{{ pegawai_top.total_skor }}` bukan `{{ pegawai_top.persentase }}`

---

### Problem 3: Ranking tidak berubah meskipun skor sudah terakumulasi
**Kemungkinan Penyebab**:
- Method `recalculateRankingHarianBySkor()` tidak dipanggil
- Sorting di `calculateMonthlyAccumulationBySkor()` salah

**Solusi**:
1. Pastikan line 548 di `RankingService.php` memanggil `recalculateRankingHarianBySkor()`
2. Verifikasi sorting di line 1065: `return $b['total_skor'] <=> $a['total_skor'];` (DESC)

---

## ✅ Checklist Implementasi

- [x] Update `RankingService::updateDailyRanking()` untuk hitung skor
- [x] Update `RankingService::updateDailyRanking()` untuk panggil `calculateMonthlyAccumulationBySkor()`
- [x] Update template header tabel: "% Kehadiran" → "Total Skor"
- [x] Update template body tabel: tampilkan `total_skor`
- [x] Update JavaScript AJAX: handle field `total_skor`
- [x] Update footer: penjelasan sistem akumulasi skor
- [ ] **Testing manual**: Lakukan absensi dan verifikasi ranking berubah
- [ ] **Testing SQL**: Query database untuk verifikasi data

---

## 📊 Contoh Output yang Diharapkan

### Dashboard - Top 10 Global
```
🏆 Top 10 Pegawai (Global)

┌────┬─────────────────┬─────────────────────┬─────────────┬────────────┐
│ #  │ Nama            │ Unit Kerja          │ Total Skor  │ Badge      │
├────┼─────────────────┼─────────────────────┼─────────────┼────────────┤
│ 1  │ Ahmad Faisal    │ IT Development      │ 450         │ 🏆 Excellent│
│ 2  │ Siti Rahma      │ HRD                 │ 445         │ 🏆 Excellent│
│ 3  │ Budi Santoso    │ Finance             │ 440         │ 🏆 Excellent│
│ 4  │ Dewi Lestari    │ Marketing           │ 425         │ 🥇 Sangat Baik│
│ 5  │ Eko Prasetyo    │ IT Development      │ 420         │ 🥇 Sangat Baik│
│ 6  │ Rina Wijaya     │ Customer Service    │ 410         │ 🥇 Sangat Baik│
│ 7  │ Joko Widodo     │ Operations          │ 400         │ 🥈 Baik     │
│ 8  │ Maya Sari       │ HRD                 │ 395         │ 🥈 Baik     │
│ 9  │ Tono Suryadi    │ Finance             │ 385         │ 🥈 Baik     │
│ 10 │ Linda Kartini   │ Marketing           │ 380         │ 🥈 Baik     │
└────┴─────────────────┴─────────────────────┴─────────────┴────────────┘

* Ranking berdasarkan akumulasi total skor dari awal bulan hingga hari ini.
  Skor dihitung setiap hari berdasarkan kecepatan absen (07:00-08:15).
  Auto-update setiap ada absensi baru.
```

---

## 🚀 Next Steps

1. **Test Manual**: Lakukan absensi dengan 2-3 pegawai untuk verifikasi sistem
2. **Monitor Performance**: Cek query time untuk `calculateMonthlyAccumulationBySkor()`
3. **Add Caching**: Pertimbangkan cache ranking bulanan (update hanya saat ada absensi)
4. **Add Index**: Pastikan index di tabel `ranking_harian` dan `ranking_bulanan` optimal

---

## 📝 Notes

- Sistem ini menggunakan field `total_durasi` di tabel `ranking_bulanan` untuk menyimpan **total skor** (bukan durasi)
- Penamaan field `total_durasi` dipertahankan untuk backward compatibility
- Dokumentasi ini harus di-update jika ada perubahan pada sistem ranking
