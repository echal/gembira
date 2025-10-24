# Cara Import Database Production ke Localhost

## Masalah Umum Saat Import Database

Ketika mengimport database dari production (cPanel) ke localhost (XAMPP), sering terjadi error seperti:

### Error #1: DEFINER Error
```
Error: Access denied; you need SUPER privilege for this operation
```
**Penyebab**: SQL dump mengandung `DEFINER=`user`@`host`` yang tidak ada di localhost

### Error #2: Database Name Mismatch
```
Error: Unknown database 'gaspulco_gembira'
```
**Penyebab**: Database production bernama `gaspulco_gembira`, localhost menggunakan `gembira_db`

### Error #3: Charset Issues
```
Error: Unknown collation: 'utf8mb4_0900_ai_ci'
```
**Penyebab**: Versi MySQL/MariaDB berbeda antara production dan localhost

### Error #4: Foreign Key Constraints
```
Error: Cannot add or update a child row: a foreign key constraint fails
```
**Penyebab**: Data diimport dalam urutan yang salah

## Solusi: Gunakan Script Import Otomatis

Saya telah membuat script yang mengatasi semua error di atas secara otomatis.

### Method 1: MySQL Command Line (Recommended)

Script ini menggunakan mysql command untuk import yang lebih cepat dan reliable.

```bash
# Download backup dari production terlebih dahulu
# Letakkan di folder gembira, misal: backup_production.sql

# Jalankan script
php import_production_db.php backup_production.sql
```

**Kelebihan:**
- ✅ Paling cepat untuk file besar
- ✅ Lebih reliable untuk database kompleks
- ✅ Error handling otomatis

### Method 2: PHP PDO (Backup Method)

Jika Method 1 tidak bisa (mysql command tidak tersedia):

```bash
php import_production_db_pdo.php backup_production.sql
```

**Kelebihan:**
- ✅ Tidak perlu mysql command
- ✅ Otomatis clean up DEFINER
- ✅ Otomatis ganti database name
- ✅ Otomatis handle charset
- ✅ Progress indicator

**Fitur Otomatis:**
- Hapus DEFINER dari SQL
- Ganti `gaspulco_gembira` → `gembira_db`
- Disable foreign key checks saat import
- Convert charset ke utf8mb4
- Verifikasi hasil import

## Langkah-langkah Lengkap

### Step 1: Download Backup dari Production

**Via cPanel:**
1. Login ke cPanel
2. Buka phpMyAdmin
3. Pilih database `gaspulco_gembira`
4. Klik tab "Export"
5. Method: Quick
6. Format: SQL
7. Klik "Go"
8. Download file `.sql`

**Via SSH (jika tersedia):**
```bash
mysqldump -u username -p gaspulco_gembira > backup_production.sql
```

### Step 2: Siapkan Localhost

```bash
# Pastikan XAMPP sudah running
# - Apache
# - MySQL

# Cek .env.local sudah benar
# DATABASE_URL="mysql://root:@127.0.0.1:3306/gembira_db?..."
```

### Step 3: Import Database

**Jika file backup sudah di folder gembira:**
```bash
cd c:\xampp\htdocs\gembira

# Method 1 (recommended)
php import_production_db.php backup_production.sql

# Atau Method 2
php import_production_db_pdo.php backup_production.sql
```

**Jika file backup di folder lain:**
```bash
cd c:\xampp\htdocs\gembira

# Gunakan full path
php import_production_db.php "C:\Users\YourName\Downloads\gembira_backup.sql"
```

### Step 4: Verifikasi & Cleanup

```bash
# Clear Symfony cache
php bin/console cache:clear

# Jalankan cleanup scripts
php cleanup_admin_pegawai_role.php
php delete_invalid_quotes_simple.php

# Test di browser
# http://localhost/gembira/public/
```

## Manual Import (Jika Script Tidak Bisa)

### Via phpMyAdmin Localhost

1. **Buka phpMyAdmin**: http://localhost/phpmyadmin
2. **Create Database**: `gembira_db` dengan collation `utf8mb4_unicode_ci`
3. **Import**:
   - Klik database `gembira_db`
   - Tab "Import"
   - Choose file: pilih backup SQL
   - **PENTING**: Centang "Enable foreign key checks" → **UNCHECK** (matikan)
   - Klik "Go"

### Jika Ada Error DEFINER

Edit file SQL secara manual:

```bash
# Buka file SQL dengan text editor (Notepad++, VSCode)
# Find & Replace:
# Cari: DEFINER=`gaspulco_gembira`@`localhost`
# Ganti dengan: (kosongkan, hapus)

# Atau gunakan sed (jika tersedia):
sed -i 's/DEFINER=`[^`]*`@`[^`]*`//g' backup_production.sql
```

### Jika Ada Error Database Name

Find & Replace di file SQL:
```
Cari: gaspulco_gembira
Ganti: gembira_db
```

## Troubleshooting

### Error: "mysql command not found"

**Solusi**: Gunakan Method 2 (PDO)
```bash
php import_production_db_pdo.php backup_production.sql
```

### Error: "MySQL server has gone away"

**Penyebab**: File SQL terlalu besar, timeout

**Solusi**: Edit `C:\xampp\mysql\bin\my.ini`
```ini
[mysqld]
max_allowed_packet=256M
wait_timeout=600
```

Restart MySQL, lalu import lagi.

### Error: "Cannot allocate memory"

**Penyebab**: PHP memory limit terlalu kecil

**Solusi**: Edit `php.ini`
```ini
memory_limit=512M
```

### Import Stuck/Hang

**Solusi**: Split SQL file menjadi bagian kecil
```bash
# Gunakan tool seperti MySQL Workbench atau phpMyAdmin
# untuk import bagian per bagian
```

## Verifikasi Import Berhasil

Setelah import, cek hal-hal berikut:

### 1. Cek Jumlah Data

```sql
-- Via phpMyAdmin atau mysql command
USE gembira_db;

SELECT COUNT(*) FROM pegawai;      -- Harus > 0
SELECT COUNT(*) FROM admin;        -- Harus > 0
SELECT COUNT(*) FROM absensi;      -- Harus ada data
SELECT COUNT(*) FROM quote;        -- 8 (sebelum cleanup) atau 3 (setelah cleanup)
```

### 2. Cek Struktur Tabel

```sql
-- Cek kolom latitude & longitude ada
DESCRIBE absensi;

-- Harus ada kolom:
-- - latitude (decimal 10,8)
-- - longitude (decimal 11,8)
```

### 3. Test Login

```
http://localhost/gembira/public/login

# Test dengan user dari production
# Jika bisa login → import berhasil
```

## After Import Checklist

- [ ] Clear cache: `php bin/console cache:clear`
- [ ] Test login admin
- [ ] Test login pegawai
- [ ] Cek halaman absensi
- [ ] Cek halaman IKHLAS
- [ ] Run cleanup scripts jika diperlukan:
  - [ ] `php cleanup_admin_pegawai_role.php`
  - [ ] `php delete_invalid_quotes_simple.php`

## Script Files

- `import_production_db.php` - Import menggunakan mysql command (Method 1)
- `import_production_db_pdo.php` - Import menggunakan PHP PDO (Method 2)

## Tips

1. **Selalu backup database localhost** sebelum import (jika ada data penting)
2. **Download fresh backup** dari production setiap kali import
3. **Gunakan Method 1** jika memungkinkan (lebih cepat)
4. **Disable antivirus** sementara jika import lambat
5. **Close aplikasi lain** untuk menghemat memory

## Error Spesifik Anda

Berdasarkan struktur tabel yang Anda tunjukkan, kolom `latitude` dan `longitude` sudah ada dengan definisi yang benar:

```sql
`latitude` decimal(10,8) DEFAULT NULL COMMENT 'Koordinat latitude GPS...',
`longitude` decimal(11,8) DEFAULT NULL COMMENT 'Koordinat longitude GPS...'
```

Ini artinya struktur tabel sudah benar. Jika Anda masih mendapat error, mohon tunjukkan:
1. **Pesan error lengkap** yang muncul
2. **Di bagian mana** error terjadi (tabel apa, baris berapa)
3. **Method import** yang Anda gunakan (phpMyAdmin, mysql command, dll)

Dengan informasi ini saya bisa membantu lebih spesifik.
