# 🏆 Sistem Ranking Dinamis - Quick Start Guide

## 📌 Ringkasan Perubahan

Sistem ranking di aplikasi GEMBIRA telah diupgrade dari **statis** menjadi **dinamis real-time**:

- ✅ **Sebelum**: Ranking dihitung manual berdasarkan persentase kehadiran bulanan
- ✅ **Sekarang**: Ranking diupdate otomatis setiap kali ada absensi baru

---

## 🚀 Quick Setup (5 Menit)

### 1. Jalankan Migration Database

```bash
# Opsi A: Via Symfony (Recommended)
php bin/console doctrine:migrations:migrate

# Opsi B: Via SQL Manual
# Import file: migrations/ranking_tables.sql ke database gembira
```

### 2. Hitung Ranking Awal

```bash
php bin/console app:reset-ranking
```

### 3. Setup Auto-Reset (Cron Job)

**Windows** - Task Scheduler:
- Program: `C:\xampp\php\php.exe`
- Arguments: `C:\xampp\htdocs\gembira\bin\console app:reset-ranking`
- Trigger: Monthly, Day 1, 00:00

**Linux/Mac** - Crontab:
```bash
0 0 1 * * cd /path/to/gembira && php bin/console app:reset-ranking
```

✅ **Selesai!** Sistem ranking dinamis sudah aktif.

---

## 📊 Tabel Database Baru

| Tabel | Fungsi |
|-------|--------|
| `absensi_durasi` | Menyimpan durasi keterlambatan/kedatangan harian |
| `ranking_harian` | Ranking pegawai per hari |
| `ranking_bulanan` | Akumulasi ranking selama sebulan |

---

## 🔍 Cara Kerja

```
┌──────────────┐
│ Pegawai Absen│ (Contoh: 07:15 WIB)
└──────┬───────┘
       │
       ▼
┌────────────────────────────────────┐
│ Sistem Hitung Durasi               │
│ - Jam Ideal: 07:00                 │
│ - Jam Masuk: 07:15                 │
│ - Durasi: +15 menit (Terlambat)    │
└──────┬─────────────────────────────┘
       │
       ▼
┌────────────────────────────────────┐
│ Update Ranking Harian Hari Ini     │
│ - Pegawai A: +5 menit → Rank 1     │
│ - Pegawai B: +10 menit → Rank 2    │
│ - Pegawai C: +15 menit → Rank 3 ✅ │
└──────┬─────────────────────────────┘
       │
       ▼
┌────────────────────────────────────┐
│ Update Ranking Bulanan             │
│ - Total Durasi Bulan Ini           │
│ - Rata-rata per Hari               │
│ - Peringkat Bulanan                │
└────────────────────────────────────┘
```

---

## 📁 File-File Penting

### Kode Utama
- `src/Entity/AbsensiDurasi.php` - Entity absensi durasi
- `src/Entity/RankingHarian.php` - Entity ranking harian
- `src/Entity/RankingBulanan.php` - Entity ranking bulanan
- `src/Repository/AbsensiDurasiRepository.php` - Repository absensi durasi
- `src/Repository/RankingHarianRepository.php` - Repository ranking harian
- `src/Repository/RankingBulananRepository.php` - Repository ranking bulanan
- `src/Service/RankingService.php` - **Logic utama ranking** ⭐
- `src/Controller/AbsensiController.php` - Controller absensi (sudah diupdate)
- `src/Command/ResetRankingCommand.php` - Command reset ranking bulanan

### Database
- `migrations/Version20250120000000_AddRankingTables.php` - Doctrine migration
- `migrations/ranking_tables.sql` - SQL manual migration

### Dokumentasi
- `docs/RANKING_SYSTEM.md` - **Dokumentasi lengkap** 📖
- `README_RANKING.md` - Quick start guide (file ini)

---

## 🎯 Method Penting di RankingService

```php
// 1. Update ranking setelah absensi (OTOMATIS dipanggil di AbsensiController)
$rankingService->updateDailyRanking($pegawai, $waktuAbsensi);

// 2. Dapatkan ranking pribadi pegawai
$ranking = $rankingService->getRankingPribadi($pegawai);
// Output: ['posisi' => 5, 'total_pegawai' => 50, 'persentase' => 95.5]

// 3. Dapatkan ranking group/unit kerja
$ranking = $rankingService->getRankingGroup($pegawai);
// Output: ['posisi' => 2, 'total_pegawai' => 10, 'nama_unit' => 'Sekretariat']

// 4. Dapatkan top 10 pegawai
$top10 = $rankingService->getTop10();
// Output: Array of top 10 pegawai dengan data lengkap

// 5. Hitung ulang ranking bulanan
$rankingService->calculateMonthlyAccumulation($tahun, $bulan);

// 6. Reset ranking bulanan (untuk awal bulan baru)
$rankingService->resetMonthlyRanking($tahun, $bulan);
```

---

## 🌐 API Endpoint

**GET** `/absensi/api/ranking-update`

Response:
```json
{
  "success": true,
  "ranking_pribadi": { "posisi": 5, "total_pegawai": 50, ... },
  "ranking_group": { "posisi": 2, "total_pegawai": 10, ... },
  "top_10_pegawai": [...],
  "timestamp": "2025-01-20 14:30:00"
}
```

Digunakan untuk auto-refresh ranking di dashboard (setiap 5 menit).

---

## 🧪 Testing

### Test Manual di Browser

1. Login sebagai pegawai
2. Lakukan absensi
3. Cek dashboard - ranking harus update otomatis
4. Refresh halaman - ranking tetap sama

### Test via Command Line

```bash
# Hitung ranking bulan ini
php bin/console app:reset-ranking

# Hitung ranking bulan lalu
php bin/console app:reset-ranking --tahun=2024 --bulan=12

# Lihat help
php bin/console app:reset-ranking --help
```

### Cek Data Database

```sql
-- Lihat absensi durasi hari ini
SELECT p.nama, ad.jam_masuk, ad.durasi_menit
FROM absensi_durasi ad
JOIN pegawai p ON ad.pegawai_id = p.id
WHERE ad.tanggal = CURDATE()
ORDER BY ad.durasi_menit ASC;

-- Lihat ranking harian hari ini
SELECT p.nama, rh.peringkat, rh.total_durasi
FROM ranking_harian rh
JOIN pegawai p ON rh.pegawai_id = p.id
WHERE rh.tanggal = CURDATE()
ORDER BY rh.peringkat ASC;

-- Lihat ranking bulanan bulan ini
SELECT p.nama, rb.peringkat, rb.total_durasi, rb.rata_rata_durasi
FROM ranking_bulanan rb
JOIN pegawai p ON rb.pegawai_id = p.id
WHERE rb.periode = DATE_FORMAT(NOW(), '%Y-%m')
ORDER BY rb.peringkat ASC;
```

---

## ❓ Troubleshooting

### Ranking tidak update setelah absensi?

**Solusi 1**: Cek error log
```bash
tail -f var/log/prod.log
```

**Solusi 2**: Recalculate manual
```bash
php bin/console app:reset-ranking
```

### Foreign key constraint error?

**Solusi**: Pastikan tabel `pegawai` sudah ada sebelum membuat tabel ranking

```sql
SHOW TABLES LIKE 'pegawai';
```

### Data tidak akurat?

**Solusi**: Hapus data hari ini dan absen ulang

```sql
DELETE FROM ranking_bulanan WHERE periode = '2025-01';
DELETE FROM ranking_harian WHERE tanggal = CURDATE();
DELETE FROM absensi_durasi WHERE tanggal = CURDATE();
```

Lalu jalankan:
```bash
php bin/console app:reset-ranking
```

---

## 📚 Dokumentasi Lengkap

Lihat **[docs/RANKING_SYSTEM.md](docs/RANKING_SYSTEM.md)** untuk:
- Arsitektur sistem detail
- Database schema lengkap
- API documentation
- Maintenance guide
- Best practices

---

## ✅ Checklist Implementasi

- [x] Buat 3 Entity baru (AbsensiDurasi, RankingHarian, RankingBulanan)
- [x] Buat 3 Repository baru
- [x] Update RankingService dengan logic dinamis
- [x] Update AbsensiController untuk auto-update ranking
- [x] Buat Command reset ranking bulanan
- [x] Buat migration database
- [x] Buat dokumentasi lengkap

---

## 🎉 Selamat!

Sistem ranking dinamis sudah siap digunakan. Setiap kali pegawai melakukan absensi, ranking akan otomatis diupdate secara real-time!

**Happy Coding! 🚀**

---

**© 2025 Aplikasi GEMBIRA**
