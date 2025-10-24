# 📦 Panduan Deploy Aplikasi Gembira ke Production (cPanel)

## 🎯 Tujuan

Deploy aplikasi Symfony Gembira ke production server cPanel dengan **AMAN** tanpa menghapus database yang sudah ada.

---

## ⚠️ PENTING - Baca Sebelum Deploy!

### ❌ JANGAN:
- ❌ Langsung replace semua file
- ❌ Drop database production yang sudah ada
- ❌ Import database backup tanpa cek structure dulu
- ❌ Deploy tanpa backup terlebih dahulu

### ✅ LAKUKAN:
- ✅ Backup database production terlebih dahulu
- ✅ Test di localhost sebelum deploy
- ✅ Deploy file-file code saja (tanpa database)
- ✅ Jalankan migration/update database yang diperlukan
- ✅ Clear cache setelah deploy

---

## 📋 Pre-Deployment Checklist

### 1. Backup Production Database

**DI CPANEL (Before Deploy):**

```bash
# Login ke cPanel → phpMyAdmin
# Pilih database gembira
# Export → SQL → GO
# Simpan file: gembira_backup_YYYY-MM-DD.sql
```

**ATAU via Terminal cPanel:**

```bash
cd ~/public_html/gembira
php bin/console app:backup-database
# File akan tersimpan di: var/backup/gembira_backup_*.sql
```

### 2. Test Localhost

**Pastikan semua berjalan normal di localhost:**

```bash
# Clear cache
php bin/console cache:clear

# Check database schema
php bin/console doctrine:schema:validate

# Run all checks
php verify_ikhlas_data.php
php check_missing_columns.php

# Test aplikasi di browser
# http://localhost/gembira/
```

### 3. List Perubahan yang Akan Di-Deploy

**Commit History Terbaru:**

```bash
git log --oneline -10
```

**Perubahan di Session Ini:**
1. ✅ Fix redirect login (Pegawai ke /absensi)
2. ✅ Cleanup Admin dengan role='pegawai'
3. ✅ Tambah tagline IKHLAS
4. ✅ Fix background leaderboard
5. ✅ Verifikasi data IKHLAS

---

## 🚀 Langkah Deployment

### STEP 1: Backup Production (CRITICAL!)

**Login ke cPanel → File Manager**

```
1. Buat folder backup di luar public_html
   Lokasi: ~/backups/gembira_YYYY-MM-DD/

2. Compress folder gembira yang lama
   public_html/gembira → Compress → gembira_backup_YYYY-MM-DD.zip

3. Download ke komputer lokal
   (Double backup untuk safety!)
```

**Backup Database:**

```
cPanel → phpMyAdmin
→ Database: gembira
→ Export → Quick → Format: SQL → GO
→ Save: gembira_db_backup_YYYY-MM-DD.sql
```

### STEP 2: Prepare Files untuk Upload

**Di Localhost (Development):**

```bash
cd c:\xampp\htdocs\gembira

# Pull latest dari GitHub
git pull origin master

# Install/Update dependencies (jika ada perubahan)
composer install --no-dev --optimize-autoloader

# JANGAN sertakan file-file ini:
# - .env (sudah ada di production dengan config berbeda)
# - var/cache/* (akan di-generate ulang)
# - var/log/* (log production berbeda)
# - vendor/* (jika sudah ada di production)
```

**Buat Archive untuk Upload:**

**OPSI A: Upload via Git (Recommended)**
```bash
# Pastikan semua sudah di-commit
git status

# Push ke GitHub
git push origin master
```

**OPSI B: Upload Manual via File Manager**
```bash
# Compress hanya folder yang diperlukan:
# - src/
# - templates/
# - config/
# - public/ (exclude uploads/)
# - bin/
# - migrations/ (jika ada)

# JANGAN include:
# - .env (biarkan .env production yang lama)
# - var/cache/
# - var/log/
# - vendor/ (akan composer install di server)
```

### STEP 3: Upload ke Production

**OPSI A: Via Git (Recommended & Aman)**

```bash
# SSH ke cPanel (jika ada akses SSH)
cd ~/public_html/gembira

# Backup .env production dulu
cp .env .env.production.backup

# Pull dari GitHub
git pull origin master

# Restore .env production
cp .env.production.backup .env
```

**OPSI B: Via cPanel File Manager**

```bash
# 1. Login cPanel → File Manager
# 2. Navigate ke: public_html/gembira
# 3. Upload file-file yang berubah (SELECTIVE!)

# File yang PERLU di-upload:
- src/Controller/*.php (yang berubah)
- src/Security/LoginSuccessHandler.php
- src/Service/*.php (yang berubah)
- templates/ikhlas/*.twig (yang berubah)
- public/index.php (jika ada perubahan)

# File yang JANGAN di-upload:
- .env (biarkan .env production)
- var/ (akan di-clear nanti)
- vendor/ (jika tidak perlu update)
```

### STEP 4: Update Dependencies (Jika Perlu)

**Via Terminal SSH:**

```bash
cd ~/public_html/gembira

# Update Composer dependencies
composer install --no-dev --optimize-autoloader

# Jika tidak ada SSH, skip step ini
```

### STEP 5: Database Migration (AMAN!)

**JANGAN import database backup!** Database production sudah ada data lengkap.

**Yang PERLU dilakukan:**

```bash
# Via Terminal SSH atau via cPanel Terminal
cd ~/public_html/gembira

# 1. Check apakah ada kolom yang kurang
php check_missing_columns.php

# 2. Jika ada kolom yang kurang, jalankan fix
php run_database_fix.php

# 3. ATAU manual via phpMyAdmin:
# Jalankan SQL dari file: fix_database_after_import.sql
```

**Update Kolom yang Kurang (Manual via phpMyAdmin):**

```sql
-- Hanya jalankan jika kolom belum ada
-- Check dulu dengan: SHOW COLUMNS FROM pegawai;

-- Contoh jika kolom 'photo' belum ada:
ALTER TABLE `pegawai`
ADD COLUMN IF NOT EXISTS `photo` VARCHAR(255) NULL;

-- Dst sesuai dengan fix_database_after_import.sql
```

### STEP 6: Clear Cache Production

**Via Terminal SSH:**

```bash
cd ~/public_html/gembira

# Clear cache
php bin/console cache:clear --env=prod --no-debug

# Warm up cache
php bin/console cache:warmup --env=prod
```

**Via cPanel File Manager (Jika tidak ada SSH):**

```bash
# Delete folder:
public_html/gembira/var/cache/prod/

# Aplikasi akan auto-generate cache baru saat diakses
```

### STEP 7: Set Permissions (PENTING!)

```bash
# Via SSH
cd ~/public_html/gembira

chmod -R 755 .
chmod -R 775 var/cache var/log
chmod -R 775 public/uploads

# Via cPanel File Manager:
# Klik kanan folder var → Change Permissions → 775
# Klik kanan folder public/uploads → Change Permissions → 775
```

### STEP 8: Cleanup Admin dengan role='pegawai' (OPSIONAL)

**Jika ingin bersihkan data admin dengan role pegawai:**

```bash
# Via Terminal
cd ~/public_html/gembira

# 1. Check data yang akan dihapus
php check_admin_with_pegawai_role.php

# 2. Cleanup (dengan konfirmasi)
php cleanup_admin_pegawai_role.php
# Ketik: yes (untuk konfirmasi)

# 3. Clear cache lagi
php bin/console cache:clear --env=prod
```

### STEP 9: Test Production

**Test Akses:**

```
1. Buka: https://yourdomain.com/gembira/
   ✅ Homepage load dengan benar

2. Test Login Admin:
   https://yourdomain.com/gembira/login
   ✅ Login berhasil
   ✅ Redirect ke /admin/dashboard

3. Test Login Pegawai:
   ✅ Login dengan NIP
   ✅ Redirect ke /absensi (BUKAN /admin/dashboard)

4. Test IKHLAS:
   https://yourdomain.com/gembira/ikhlas
   ✅ Tagline muncul: "inspirasi kehidupan lahirkan semangat"
   ✅ Data quotes muncul

5. Test Leaderboard:
   https://yourdomain.com/gembira/ikhlas/leaderboard
   ✅ Background biru langit (bukan purple)
   ✅ Data statistik global benar
   ✅ Data quotes terpopuler benar
```

---

## 🔧 Troubleshooting

### Problem 1: Error 500 Setelah Deploy

**Solution:**

```bash
# Clear cache
rm -rf var/cache/prod/*

# Check .env
# Pastikan APP_ENV=prod dan DATABASE_URL benar

# Check permissions
chmod -R 775 var/
```

### Problem 2: Data Tidak Muncul / Cache Lama

**Solution:**

```bash
# Clear cache Symfony
php bin/console cache:clear --env=prod

# Clear cache browser
# Ctrl + Shift + R (hard refresh)
```

### Problem 3: Database Error "Column not found"

**Solution:**

```bash
# Run database fix
php run_database_fix.php

# ATAU manual via phpMyAdmin
# Execute SQL dari: fix_database_after_import.sql
```

### Problem 4: Login Redirect Salah

**Solution:**

```bash
# 1. Pastikan LoginSuccessHandler.php sudah terupdate
# 2. Clear cache
php bin/console cache:clear --env=prod

# 3. Cleanup admin dengan role pegawai
php cleanup_admin_pegawai_role.php

# 4. Test login ulang dengan incognito mode
```

---

## 📊 Post-Deployment Verification

### Checklist Verifikasi:

```bash
✅ Homepage accessible
✅ Login admin works → redirect to /admin/dashboard
✅ Login pegawai works → redirect to /absensi
✅ IKHLAS tagline visible
✅ IKHLAS leaderboard background correct (blue sky)
✅ Statistik Global shows real data
✅ Quotes Terpopuler shows real data
✅ No PHP errors in log
✅ Database intact (no data loss)
```

### Verify Data:

```bash
# Via SSH
cd ~/public_html/gembira

# Check IKHLAS data
php verify_ikhlas_data.php

# Check missing columns
php check_missing_columns.php

# Check user Faisal Kasim
php check_user_faisal.php
```

---

## 🔐 Security Checklist

### Production .env Configuration:

```env
# Pastikan setting ini di production:
APP_ENV=prod
APP_DEBUG=0

# Database production (jangan ganti!)
DATABASE_URL="mysql://user:password@localhost:3306/gembira_db"

# Secret harus berbeda dari dev
APP_SECRET=your_production_secret_here
```

### File Permissions:

```bash
# Root folder
chmod 755 ~/public_html/gembira

# Writable folders
chmod 775 ~/public_html/gembira/var/cache
chmod 775 ~/public_html/gembira/var/log
chmod 775 ~/public_html/gembira/public/uploads

# Protect sensitive files
chmod 600 ~/public_html/gembira/.env
```

---

## 📝 Summary Deployment Steps

```
1. ✅ BACKUP production database & files
2. ✅ PULL latest code dari GitHub
3. ✅ UPLOAD hanya file code yang berubah (jangan .env)
4. ✅ RUN database fix jika ada kolom baru
5. ✅ CLEAR cache production
6. ✅ SET permissions yang benar
7. ✅ CLEANUP admin role='pegawai' (opsional)
8. ✅ TEST semua fitur
9. ✅ VERIFY data intact
```

---

## ⚠️ Rollback Plan (Jika Gagal)

### Jika Deploy Gagal:

```bash
# 1. Restore backup files
cd ~/public_html
rm -rf gembira
unzip backups/gembira_backup_YYYY-MM-DD.zip

# 2. Restore database (jika ter-modify)
# Via phpMyAdmin:
# Import → gembira_db_backup_YYYY-MM-DD.sql

# 3. Clear cache
cd ~/public_html/gembira
php bin/console cache:clear --env=prod
```

---

## 📞 Need Help?

### Log Files:

```bash
# Check error logs
tail -f var/log/prod.log

# Check PHP errors
tail -f ~/public_html/gembira/var/log/prod.log
```

### Debug Mode (Temporary):

```env
# .env (temporary untuk debug)
APP_ENV=dev
APP_DEBUG=1

# INGAT: Kembalikan ke prod setelah selesai debug!
APP_ENV=prod
APP_DEBUG=0
```

---

## ✅ Deployment Complete!

Setelah semua steps di atas, aplikasi Gembira sudah ter-deploy dengan **AMAN** ke production tanpa menghapus database yang sudah ada.

**Changes Deployed:**
- ✅ Fix redirect login (Admin vs Pegawai)
- ✅ Data admin role='pegawai' cleaned up
- ✅ IKHLAS tagline added
- ✅ Leaderboard background fixed
- ✅ Data verification scripts added

**Database Status:**
- ✅ Data production intact
- ✅ No data loss
- ✅ Schema updated (if needed)
- ✅ Fully functional

---

**Last Updated:** 2025-10-24
**Version:** Symfony 6.x
**Environment:** Production (cPanel)
