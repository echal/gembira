# 💾 Database Backup - GEMBIRA

## 📋 Informasi Backup

**File**: `database271005.sql`
**Tanggal**: 27 Oktober 2025, 01:01:34 WITA
**Database**: `gembira_db`
**Server**: MariaDB 10.4.32
**Ukuran**: 867 KB

---

## 📊 Statistik Backup

- **Total Tabel**: 26 tabel
- **Total INSERT**: 23 statements (ada data di 23 tabel)
- **Backup Type**: Full backup (struktur + data)
- **Options**:
  - `--single-transaction` (konsistensi data)
  - `--routines` (stored procedures)
  - `--triggers` (trigger database)
  - `--events` (scheduled events)

---

## 📑 Daftar Tabel yang Di-backup

### 1. **Tabel Utama**
1. `absensi` - Data absensi pegawai
2. `pegawai` - Data pegawai
3. `admin` - Data administrator
4. `jadwal_absensi` - Konfigurasi jadwal absensi

### 2. **Tabel Ranking & Gamifikasi**
5. `ranking_harian` - Ranking harian berdasarkan skor
6. `ranking_bulanan` - Ranking bulanan (akumulasi skor)
7. `monthly_leaderboard` - Leaderboard bulanan XP
8. `user_points` - Poin gamifikasi user
9. `user_xp_log` - Log XP user
10. `user_badges` - Badge yang dimiliki user

### 3. **Tabel Event & Notifikasi**
11. `event` - Event/acara
12. `event_absensi` - Absensi event
13. `notifikasi` - Notifikasi sistem
14. `user_notifikasi` - Notifikasi per user

### 4. **Tabel Quote System**
15. `quotes` - Quote/motivasi
16. `quote_comments` - Komentar quote
17. `user_quotes_interaction` - Interaksi user dengan quote (like, share)

### 5. **Tabel Konfigurasi**
18. `konfigurasi_jadwal_absensi` - Konfigurasi jadwal
19. `system_configuration` - Konfigurasi sistem
20. `hari_libur` - Data hari libur
21. `sliders` - Banner slider

### 6. **Tabel Organisasi**
22. `unit_kerja` - Data unit kerja
23. `kepala_bidang` - Data kepala bidang
24. `kepala_kantor` - Data kepala kantor

### 7. **Tabel Sistem**
25. `doctrine_migration_versions` - Versi migrasi database
26. `messenger_messages` - Queue message Symfony

---

## 🔄 Cara Restore Database

### Method 1: Via Command Line (Recommended)
```bash
# Restore full database
cd c:/xampp/mysql/bin
./mysql.exe -u root gembira_db < c:/xampp/htdocs/gembira/database/database271005.sql

# Atau restore ke database baru
./mysql.exe -u root -e "CREATE DATABASE gembira_db_backup;"
./mysql.exe -u root gembira_db_backup < c:/xampp/htdocs/gembira/database/database271005.sql
```

### Method 2: Via phpMyAdmin
1. Buka phpMyAdmin: http://localhost/phpmyadmin
2. Pilih database `gembira_db` (atau buat database baru)
3. Klik tab "Import"
4. Choose file: `database271005.sql`
5. Klik "Go"

### Method 3: Via Symfony Console (Restore & Run Migrations)
```bash
# Drop & recreate database
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create

# Import backup
cd c:/xampp/mysql/bin
./mysql.exe -u root gembira_db < c:/xampp/htdocs/gembira/database/database271005.sql

# Run migrations (jika ada yang baru)
php bin/console doctrine:migrations:migrate
```

---

## ⚠️ PENTING - Sebelum Restore

1. **Backup Database Saat Ini** (jika ada data yang masih diperlukan):
```bash
cd c:/xampp/mysql/bin
./mysqldump.exe -u root gembira_db > c:/xampp/htdocs/gembira/database/backup_before_restore.sql
```

2. **Pastikan Aplikasi Tidak Sedang Berjalan**:
   - Stop server Symfony: `Ctrl+C` di terminal
   - Atau pastikan tidak ada user yang sedang menggunakan aplikasi

3. **Cek Kompatibilitas Versi**:
   - Backup ini dibuat dengan MariaDB 10.4.32
   - Pastikan server target kompatibel (MariaDB 10.4+ atau MySQL 5.7+)

---

## 📝 Catatan Backup Ini

### Perubahan Terakhir Sebelum Backup:
- ✅ Update sistem ranking ke akumulasi skor bulanan
- ✅ Method baru: `getRankingPribadiByMonthlyScore()`
- ✅ Method baru: `getRankingGroupByMonthlyScore()`
- ✅ Update template untuk menampilkan total skor
- ✅ Update API endpoint untuk return data skor bulanan

### Commit Terakhir:
- **Commit**: `e393920`
- **Message**: "Feat: Update Sistem Ranking ke Akumulasi Skor Bulanan Lengkap"
- **Tanggal**: 27 Oktober 2025

---

## 🔐 Keamanan Backup

### Lokasi Backup:
```
c:\xampp\htdocs\gembira\database\database271005.sql
```

### Rekomendasi:
1. ✅ Simpan di folder `database/` (sudah ada di `.gitignore`)
2. ⚠️ **JANGAN** commit file SQL ke GitHub (mengandung data sensitif)
3. 💾 Simpan copy di lokasi lain:
   - Cloud storage (Google Drive, Dropbox)
   - External hard drive
   - Network storage

### Enkripsi (Opsional):
```bash
# Compress + encrypt dengan password
cd c:/xampp/htdocs/gembira/database
7z a -p -mhe=on database271005.7z database271005.sql

# Decrypt + decompress
7z x database271005.7z
```

---

## 📆 Schedule Backup (Rekomendasi)

### Daily Backup (Otomatis)
Buat file `backup-daily.bat`:
```batch
@echo off
set backup_dir=c:\xampp\htdocs\gembira\database
set date=%date:~-4%%date:~3,2%%date:~0,2%
cd c:\xampp\mysql\bin
mysqldump.exe -u root --single-transaction gembira_db > %backup_dir%\backup_%date%.sql
echo Backup completed: backup_%date%.sql
```

Jalankan via Task Scheduler setiap hari jam 02:00 pagi.

### Weekly Backup (Manual)
Setiap Minggu, buat backup dengan nama format:
- `database_week_YYYYMMDD.sql`

### Before Major Updates (Manual)
Sebelum update besar, buat backup dengan nama format:
- `database_before_[nama_update].sql`

---

## 🆘 Recovery Plan

### Jika Terjadi Masalah:

#### 1. Data Corruption
```bash
# Restore dari backup terakhir
./mysql.exe -u root gembira_db < database271005.sql

# Verify integrity
php bin/console doctrine:schema:validate
```

#### 2. Accidental Data Loss
```bash
# Restore hanya tabel tertentu
./mysql.exe -u root gembira_db < database271005.sql --tables absensi ranking_harian
```

#### 3. Migration Failed
```bash
# Rollback ke backup
./mysql.exe -u root gembira_db < database271005.sql

# Clear cache
php bin/console cache:clear

# Retry migration
php bin/console doctrine:migrations:migrate
```

---

## ✅ Verifikasi Backup Berhasil

Jalankan query ini setelah restore:

```sql
-- Cek jumlah tabel
SELECT COUNT(*) as total_tables
FROM information_schema.tables
WHERE table_schema = 'gembira_db';
-- Expected: 26

-- Cek data pegawai
SELECT COUNT(*) as total_pegawai FROM pegawai;

-- Cek data absensi
SELECT COUNT(*) as total_absensi FROM absensi;

-- Cek ranking bulanan
SELECT COUNT(*) as total_ranking FROM ranking_bulanan
WHERE periode = DATE_FORMAT(CURDATE(), '%Y-%m');
```

---

## 📞 Support

Jika ada masalah dengan restore atau backup:

1. Cek error log MySQL: `c:/xampp/mysql/data/[hostname].err`
2. Cek Symfony log: `var/log/dev.log`
3. Dokumentasi lengkap: `docs/` folder

---

**Backup Created by**: Claude Code
**Date**: 2025-10-27 01:01:34 WITA
**Status**: ✅ **VERIFIED & COMPLETE**
