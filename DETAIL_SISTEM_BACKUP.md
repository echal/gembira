# 📦 Detail Sistem Backup Database - GEMBIRA

**File:** `src/Service/BackupService.php`
**Dokumentasi:** Yang di-backup dan cara kerjanya

---

## 🎯 Apa Saja yang Di-Backup?

### ✅ SEMUA DATA BERIKUT INI:

#### 1. **Semua Tabel Database** 📊
Backup mencakup **SELURUH tabel** di database `gembira_db`, termasuk:

**Tabel Master:**
- ✅ `pegawai` - Data pegawai (NIP, nama, email, password, jabatan, unit kerja, photo, tanda tangan, XP, level, dll)
- ✅ `admin` - Data admin sistem
- ✅ `unit_kerja` - Data unit kerja/departemen
- ✅ `jadwal_absensi` - Jadwal absensi (legacy)
- ✅ `konfigurasi_jadwal_absensi` - Konfigurasi jadwal fleksibel

**Tabel Transaksi:**
- ✅ `absensi` - Record absensi harian (tanggal, waktu, status, foto, GPS latitude/longitude, QR code)
- ✅ `event` - Data event/kegiatan
- ✅ `event_absensi` - Absensi event
- ✅ `notifikasi` - Notifikasi sistem
- ✅ `user_notifikasi` - Notifikasi per user

**Tabel IKHLAS (Quote System):**
- ✅ `quote` - Quotes motivasi yang diposting pegawai
- ✅ `quote_comment` - Komentar di quotes
- ✅ `user_quote_interaction` - Like & favorite quotes

**Tabel Gamifikasi (XP System):**
- ✅ `user_xp_log` - Log aktivitas XP (siapa dapat XP, berapa, kapan, dari aktivitas apa)
- ✅ `monthly_leaderboard` - Ranking bulanan pegawai
- ✅ `user_points` - Points tracking
- ✅ `user_badges` - Badge system

**Tabel System:**
- ✅ `doctrine_migration_versions` - Migration history
- ✅ Dan semua tabel lainnya yang ada di database

#### 2. **Stored Procedures & Functions** 🔧
```sql
--routines
```
Backup include semua stored procedures dan functions yang dibuat di database (jika ada).

#### 3. **Database Triggers** ⚡
```sql
--triggers
```
Backup include semua triggers (auto-execute code) yang attached ke tabel (jika ada).

#### 4. **Structure & Data** 🏗️
Backup include:
- ✅ **CREATE TABLE** statements (struktur tabel)
- ✅ **INSERT INTO** statements (semua data di tabel)
- ✅ **Indexes** (primary key, unique, index)
- ✅ **Foreign Keys** (relasi antar tabel)
- ✅ **Default Values** (nilai default kolom)
- ✅ **Auto Increment** counters
- ✅ **Character Set & Collation** settings

---

## 🔍 Detail Command Backup

**Command yang digunakan:**
```bash
mysqldump
  --host=localhost
  --port=3306
  --user=root
  --password=
  --single-transaction    # ← PENTING: Backup konsisten tanpa lock tabel
  --routines              # ← Include stored procedures & functions
  --triggers              # ← Include triggers
  gembira_db > gembira_backup_2025-10-23_14-30-00.sql
```

### Penjelasan Parameter:

#### `--single-transaction`
**Sangat Penting!** 🌟
- Backup dilakukan dalam satu transaction
- Database **TIDAK di-lock** selama backup
- User tetap bisa input absensi, posting quote, dll saat backup berjalan
- Data tetap konsisten (snapshot di waktu tertentu)
- Cocok untuk database production yang sibuk

**Tanpa ini:** Database akan di-lock → user tidak bisa akses → error!

#### `--routines`
- Include stored procedures
- Include functions
- Example: jika ada `CREATE PROCEDURE hitungAbsensi()`, akan ter-backup

#### `--triggers`
- Include triggers
- Example: jika ada `BEFORE INSERT` trigger, akan ter-backup

---

## 📂 Lokasi File Backup

**Directory:** `var/backup/`

**Nama File Format:**
```
gembira_backup_YYYY-MM-DD_HH-MM-SS.sql
```

**Contoh:**
```
var/backup/gembira_backup_2025-10-23_14-30-00.sql
var/backup/gembira_backup_2025-10-23_02-00-00.sql (auto backup)
var/backup/gembira_backup_2025-10-22_16-45-12.sql
```

**Permission:** `0755` (readable oleh semua, writable oleh owner)

---

## 🔒 Keamanan Backup

### ✅ Yang Aman:
1. **File disimpan di server** (`var/backup/`)
2. **Tidak accessible via web** (di luar public directory)
3. **Hanya admin yang bisa download**
4. **Log setiap download** (siapa, kapan, file apa)
5. **Auto cleanup** (simpan 10 backup terbaru, hapus yang lama)

### ⚠️ Perhatian:
1. **Password di-backup!** (hash bcrypt, bukan plain text)
2. **Data sensitif included** (GPS lokasi, foto absensi, dll)
3. **File SQL bisa dibaca** jika ada akses ke server
4. **Harus download & simpan di tempat aman** (external drive, cloud encrypted)

---

## 📊 Ukuran File Backup

**Estimasi berdasarkan data:**

| Jumlah Pegawai | Absensi/Bulan | Quote | Ukuran Backup |
|----------------|---------------|-------|---------------|
| 50 pegawai     | ~1,000 record | 100   | ~500 KB       |
| 100 pegawai    | ~2,000 record | 500   | ~1 MB         |
| 200 pegawai    | ~4,000 record | 1,000 | ~2-3 MB       |
| 500 pegawai    | ~10,000 record| 5,000 | ~10-15 MB     |

**Note:** Ukuran bertambah seiring waktu karena data historis terakumulasi.

---

## 🔄 Auto Backup System

### Cara Kerja:

1. **Enable Auto Backup** (toggle ON di UI)
2. **Set waktu backup** (default: 02:00 AM)
3. **Set retention** (default: 30 hari)
4. **System akan:**
   - Jalankan backup otomatis setiap hari di jam yang ditentukan
   - Simpan di `var/backup/`
   - Hapus backup yang lebih lama dari retention days
   - Log ke system log

### Implementasi:

**Via Symfony Console Command:**
```bash
php bin/console app:backup:create
```

**Via Cron Job (Linux):**
```bash
# Edit crontab
crontab -e

# Tambahkan (jalankan setiap hari jam 02:00)
0 2 * * * cd /path/to/gembira && php bin/console app:backup:create
```

**Via Windows Task Scheduler:**
- Trigger: Daily, 02:00 AM
- Action: Run `php.exe bin/console app:backup:create`

---

## 💾 Cara Restore Backup

### Via phpMyAdmin:
1. Buka phpMyAdmin
2. Pilih database `gembira_db`
3. Tab **Import**
4. Choose file → pilih `gembira_backup_*.sql`
5. Klik **Go**
6. Tunggu hingga selesai
7. **WAJIB:** Jalankan fix script:
   ```bash
   php run_database_fix.php
   php bin/console cache:clear
   ```

### Via MySQL Command Line:
```bash
# Drop existing database (HATI-HATI!)
mysql -u root -p -e "DROP DATABASE IF EXISTS gembira_db"

# Create new database
mysql -u root -p -e "CREATE DATABASE gembira_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Import backup
mysql -u root -p gembira_db < gembira_backup_2025-10-23_14-30-00.sql

# Fix missing columns
php run_database_fix.php

# Clear cache
php bin/console cache:clear
```

### ⚠️ **PENTING Setelah Restore:**
```bash
# WAJIB jalankan ini untuk fix struktur database!
php run_database_fix.php
php bin/console cache:clear
```

**Kenapa?** Karena backup dari production mungkin tidak punya kolom-kolom baru yang ada di development (IKHLAS system, XP fields, dll).

---

## 🧪 Test Backup

### Manual Test:
```bash
# 1. Create backup
Klik "Backup Sekarang" di admin panel

# 2. Verify file created
ls -lh var/backup/

# 3. Check file content (optional)
head -50 var/backup/gembira_backup_*.sql

# 4. Download backup
Klik "Download Backup Terbaru"

# 5. Verify download OK
# File harus bisa dibuka dengan text editor
```

### Auto Test Command:
```bash
# Check mysqldump available
mysqldump --version

# Test backup creation
php bin/console app:backup:create

# Verify
ls -lh var/backup/
```

---

## 📋 Checklist Data yang Di-Backup

### ✅ Data Master:
- [x] Pegawai (NIP, nama, jabatan, unit kerja)
- [x] Admin
- [x] Unit Kerja
- [x] Password (hash bcrypt)
- [x] Photo profil pegawai
- [x] Tanda tangan digital

### ✅ Data Absensi:
- [x] Tanggal & waktu absensi
- [x] Status (hadir/izin/sakit)
- [x] Foto selfie absensi
- [x] GPS latitude & longitude
- [x] QR Code yang di-scan
- [x] Keterangan/alasan

### ✅ Data IKHLAS:
- [x] Quotes yang diposting
- [x] Komentar di quotes
- [x] Like & favorite
- [x] Timestamp posting

### ✅ Data Gamifikasi:
- [x] Total XP pegawai
- [x] Level & badge
- [x] Log aktivitas XP
- [x] Leaderboard bulanan
- [x] Ranking history

### ✅ Data System:
- [x] Notifikasi
- [x] Event & kegiatan
- [x] Migration versions
- [x] System configuration

### ❌ Yang TIDAK Di-Backup:
- [ ] File uploads (foto, signature) di folder `public/uploads/`
- [ ] Cache (`var/cache/`)
- [ ] Logs (`var/log/`)
- [ ] Session data
- [ ] Temporary files

**⚠️ Note:** Untuk **full backup**, perlu backup folder `public/uploads/` secara terpisah!

---

## 🔧 Troubleshooting

### Error: "mysqldump: command not found"

**Solusi:**
```bash
# Cek path mysqldump
which mysqldump

# Tambahkan ke PATH (XAMPP)
export PATH=$PATH:/Applications/XAMPP/xamppfiles/bin
# atau
export PATH=$PATH:/opt/lampp/bin
```

### Error: "Access denied for user"

**Solusi:**
- Cek username & password di `.env.local`
- Pastikan user punya privilege `SELECT, LOCK TABLES`

### Backup File Kosong (0 bytes)

**Solusi:**
- Cek error di `var/log/dev.log`
- Jalankan manual: `mysqldump -u root -p gembira_db > test.sql`
- Cek apakah database ada data

### Backup Lambat (>1 menit)

**Possible causes:**
- Database sangat besar (>100 MB)
- Server lambat
- Banyak indexes

**Solusi:**
- Normal untuk database besar
- Consider compress backup (gzip)

---

## 💡 Best Practices

### 1. **Backup Schedule**
- **Daily:** Auto backup jam 02:00 (minimal traffic)
- **Weekly:** Download & simpan di external storage
- **Monthly:** Archive backup untuk compliance

### 2. **Retention Policy**
- **Keep:** 10 backup terbaru di server
- **Download:** Backup sebelum update besar
- **Archive:** Backup akhir bulan untuk 1 tahun

### 3. **Storage**
- **Server:** `var/backup/` (auto-managed)
- **External:** Google Drive, Dropbox (encrypted)
- **Offline:** External HDD (monthly)

### 4. **Testing**
- **Monthly:** Test restore di environment staging
- **Before Major Update:** Backup + verify bisa restore
- **After Incident:** Immediate backup

---

## 📞 FAQ

### Q: Berapa lama proses backup?
**A:** Tergantung ukuran database:
- 500 KB: ~1-2 detik
- 1 MB: ~3-5 detik
- 10 MB: ~10-15 detik
- 100 MB: ~1-2 menit

### Q: Apakah backup bisa dilakukan saat jam kerja?
**A:** Ya! Karena pakai `--single-transaction`, database tidak di-lock. User tetap bisa absen, posting quote, dll.

### Q: Berapa lama backup disimpan?
**A:** Default: 10 backup terbaru. Backup lama otomatis dihapus.

### Q: Apakah password pegawai aman di backup?
**A:** Ya, password di-hash dengan bcrypt (tidak bisa di-decrypt).

### Q: Bagaimana backup foto absensi?
**A:** Foto di folder `public/uploads/` **TIDAK included** di SQL backup. Perlu backup folder terpisah via FTP/rsync.

### Q: Bisa restore backup lama?
**A:** Ya, asal structure compatible. Jika ada error, jalankan `run_database_fix.php`.

---

## 🚀 Upgrade Ideas (Future)

### Versi 2.0:
- [ ] Compressed backup (gzip) untuk save space
- [ ] Differential backup (hanya perubahan)
- [ ] Cloud backup integration (Google Drive API)
- [ ] Email notification setelah backup
- [ ] Backup verification (test restore)
- [ ] Multi-version retention (daily/weekly/monthly)
- [ ] Backup encryption (AES-256)
- [ ] Backup file uploads folder
- [ ] Point-in-time recovery

---

**Dokumentasi ini dibuat:** 23 Oktober 2025
**Version:** 1.0
**Author:** Claude (Automated)

**File terkait:**
- `src/Service/BackupService.php` - Backup logic
- `src/Controller/AdminController.php` - Backup endpoints
- `run_database_fix.php` - Post-restore fix script
