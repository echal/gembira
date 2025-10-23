# 🔧 Cara Fix Database Setelah Import Backup

**Error yang muncul:**
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'a0_.latitude' in 'field list'
```

**Penyebab:**
Database backup dari production tidak memiliki tabel/kolom baru yang dibuat untuk sistem IKHLAS.

---

## ✅ Solusi 1: Import SQL Fix (PALING MUDAH)

### Langkah 1: Import SQL Fix via phpMyAdmin

1. Buka **phpMyAdmin** di browser: `http://localhost/phpmyadmin`
2. Pilih database **`gembira`** di sidebar kiri
3. Klik tab **"SQL"** di bagian atas
4. Buka file `fix_database_after_import.sql` dengan text editor (Notepad++)
5. **Copy semua isi** file tersebut
6. **Paste** ke textarea SQL di phpMyAdmin
7. Klik tombol **"Go"** atau **"Kirim"**
8. Tunggu hingga selesai (akan muncul pesan success)

### Langkah 2: Verify Database

Jalankan query ini di tab SQL untuk verify:

```sql
-- Cek kolom latitude di tabel absensi
SHOW COLUMNS FROM absensi LIKE '%latitude%';

-- Cek kolom XP di tabel pegawai
SHOW COLUMNS FROM pegawai LIKE '%xp%';

-- Cek tabel quote ada atau tidak
SHOW TABLES LIKE 'quote%';
```

**Expected result:**
- `latitude` dan `longitude` ada di tabel `absensi`
- `total_xp`, `current_level`, `current_badge`, `level_title` ada di tabel `pegawai`
- Tabel `quote`, `quote_comment`, `user_quote_interaction` exist

---

## ✅ Solusi 2: Import via MySQL Command Line

Jika Anda prefer command line:

```bash
# Masuk ke folder xampp/mysql/bin
cd C:\xampp\mysql\bin

# Import SQL fix
mysql -u root -p gembira < C:\xampp\htdocs\gembira\fix_database_after_import.sql

# Atau jika tidak pakai password
mysql -u root gembira < C:\xampp\htdocs\gembira\fix_database_after_import.sql
```

---

## ✅ Solusi 3: Via Symfony Console (ALTERNATIF)

Jika SQL import berhasil tapi masih ada error, jalankan:

### Step 1: Clear Cache
```bash
php bin/console cache:clear
```

### Step 2: Validate Schema
```bash
php bin/console doctrine:schema:validate
```

### Step 3: Update Schema (Hati-hati!)
```bash
# Preview SQL yang akan dijalankan
php bin/console doctrine:schema:update --dump-sql

# Execute (HANYA jika SQL terlihat aman)
php bin/console doctrine:schema:update --force
```

**⚠️ WARNING:** `doctrine:schema:update --force` bisa **merusak data** jika tidak hati-hati!
- Backup database dulu sebelum run command ini
- Cek `--dump-sql` output terlebih dahulu

---

## 🧪 Testing Setelah Fix

### Test 1: Cek Homepage
```
URL: http://localhost/gembira
```
✅ PASS jika: Tidak ada error, homepage load normal

### Test 2: Cek Menu IKHLAS
```
URL: http://localhost/gembira/ikhlas
```
✅ PASS jika: Halaman IKHLAS muncul (meskipun kosong)

### Test 3: Cek Profile
```
URL: http://localhost/gembira/profile
```
✅ PASS jika: Profile muncul dengan Level & XP (default: Level 1, 0 XP)

### Test 4: Test Posting Quote
1. Buka menu IKHLAS
2. Klik "Buat Quote"
3. Tulis quote test: "Test quote pertama"
4. Submit

✅ PASS jika: Quote berhasil di-post & XP +20

---

## 🐛 Troubleshooting

### Error 1: "Unknown column 'latitude'"

**Solusi:**
- Import ulang `fix_database_after_import.sql`
- Atau add manual via phpMyAdmin:
  ```sql
  ALTER TABLE absensi
  ADD COLUMN latitude DECIMAL(10,8) NULL,
  ADD COLUMN longitude DECIMAL(11,8) NULL;
  ```

### Error 2: "Table 'quote' doesn't exist"

**Solusi:**
- Import ulang `fix_database_after_import.sql`
- Pastikan semua CREATE TABLE statements ter-execute

### Error 3: "Unknown database type enum"

**Solusi:**
- Ini warning, bukan critical error
- Bisa diabaikan atau fix dengan:
  ```bash
  php bin/console doctrine:mapping:convert --force xml ./doctrine-mapping
  ```

### Error 4: "Foreign key constraint fails"

**Solusi:**
- Matikan foreign key check temporary:
  ```sql
  SET FOREIGN_KEY_CHECKS=0;
  -- Run your ALTER/CREATE statements
  SET FOREIGN_KEY_CHECKS=1;
  ```

### Error 5: Cache Issues

**Solusi:**
```bash
# Clear all caches
php bin/console cache:clear
php bin/console cache:warmup

# Jika masih error, delete manual
rm -rf var/cache/*
# atau di Windows:
rmdir /s /q var\cache
```

---

## 📊 Verify Database Structure

Jalankan query ini untuk memastikan semua OK:

```sql
-- 1. Cek struktur tabel absensi
DESCRIBE absensi;

-- 2. Cek struktur tabel pegawai
DESCRIBE pegawai;

-- 3. Cek tabel-tabel IKHLAS
SHOW TABLES LIKE '%quote%';
SHOW TABLES LIKE '%monthly_leaderboard%';

-- 4. Count records
SELECT 'absensi' as tabel, COUNT(*) as jumlah FROM absensi
UNION ALL
SELECT 'pegawai', COUNT(*) FROM pegawai
UNION ALL
SELECT 'quote', COUNT(*) FROM quote
UNION ALL
SELECT 'user_quote_interaction', COUNT(*) FROM user_quote_interaction;
```

**Expected output:**
```
tabel                      | jumlah
---------------------------+--------
absensi                    | (sesuai data backup)
pegawai                    | (sesuai data backup)
quote                      | 0 (karena baru)
user_quote_interaction     | 0 (karena baru)
```

---

## 🔄 Reset Database (NUCLEAR OPTION)

Jika semua solusi di atas gagal dan Anda ingin **start fresh**:

### Step 1: Backup Data Penting
```sql
-- Backup pegawai
CREATE TABLE pegawai_backup AS SELECT * FROM pegawai;

-- Backup absensi
CREATE TABLE absensi_backup AS SELECT * FROM absensi;
```

### Step 2: Drop & Recreate Database
```sql
DROP DATABASE gembira;
CREATE DATABASE gembira CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gembira;
```

### Step 3: Import Backup SQL Lagi
- Import file backup SQL dari production
- Import `fix_database_after_import.sql`

### Step 4: Run Migrations
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

---

## 📞 Jika Masih Error

### Collect Information:
1. Screenshot error lengkap
2. Output dari:
   ```bash
   php bin/console doctrine:schema:validate
   ```
3. Output dari:
   ```sql
   SHOW TABLES;
   DESCRIBE absensi;
   DESCRIBE pegawai;
   ```

### Check Logs:
```bash
# Symfony logs
tail -f var/log/dev.log

# Apache/PHP logs
tail -f C:\xampp\apache\logs\error.log
```

---

## ✅ Success Indicators

Anda berhasil jika:
- ✅ Homepage load tanpa error
- ✅ Menu IKHLAS accessible
- ✅ Bisa posting quote
- ✅ XP bertambah setelah aktivitas
- ✅ Profile menampilkan Level & XP
- ✅ Tidak ada error di console browser
- ✅ Tidak ada error di log Symfony

---

## 📝 Notes

### Kenapa Error Ini Terjadi?

**Skenario:**
1. Anda develop di localhost dengan database kosong
2. Saya create migration & entity baru (IKHLAS system)
3. Anda import backup dari production (database lama tanpa IKHLAS)
4. Code expect kolom/tabel baru, tapi database tidak punya
5. ERROR! 💥

**Solusi:**
- Setiap kali import backup production, run `fix_database_after_import.sql`
- Atau sync production database dengan migration terbaru sebelum backup

### Best Practice Going Forward

1. **Di Production:**
   ```bash
   # Jalankan migration sebelum backup
   php bin/console doctrine:migrations:migrate

   # Baru backup database
   mysqldump -u user -p gembira > backup.sql
   ```

2. **Di Localhost:**
   ```bash
   # Setelah import backup
   mysql -u root gembira < fix_database_after_import.sql

   # Clear cache
   php bin/console cache:clear
   ```

3. **Development Flow:**
   - Pull latest code dari GitHub
   - Import backup SQL (jika ada)
   - Run `fix_database_after_import.sql`
   - Run `php bin/console doctrine:migrations:migrate`
   - Clear cache
   - Test!

---

**Good luck! Jika masih ada masalah, kasih tahu detail error-nya! 🚀**

---

*Dibuat: 23 Oktober 2025*
*File: CARA_FIX_DATABASE_SETELAH_IMPORT.md*
