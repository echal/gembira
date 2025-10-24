# Cara Menghapus Quotes dari User Tidak Valid

## Latar Belakang

Berdasarkan hasil analisis data IKHLAS di production server, ditemukan quotes dari user yang **tidak ada dalam daftar pegawai**:

- **Pak Dedi** (1 quote dengan 2 likes, 1 save)
- **Pak Budi** (1 quote dengan 4 likes, 1 save)
- **Bu Ani** (1 quote dengan 3 likes, 2 saves)
- **Anonim** (2 quotes: ID 1 dengan 5 likes/3 saves, ID 2 dengan 4 likes/1 save)

**Total**: 5 quotes dari user tidak valid yang perlu dihapus.

## Mengapa Harus Dihapus?

1. **Konsistensi Data**: Semua quotes di IKHLAS harus berasal dari pegawai yang terdaftar di sistem
2. **Integritas Leaderboard**: Leaderboard hanya boleh menampilkan pegawai yang valid
3. **Data Cleanup**: Menghilangkan data sample/test yang tidak relevan

## Dampak Penghapusan

- **Quotes dihapus**: 5 quotes (dari total 8 quotes)
- **Interactions dihapus**: ~12-15 interactions (likes & saves dari pegawai ke quotes tersebut)
- **Quotes tersisa**: 3 quotes dari pegawai yang valid
- **Tidak ada data pegawai yang terhapus**: Hanya menghapus quotes, tidak menghapus data pegawai

## Pilihan Metode Penghapusan

### Metode 1: Menggunakan Script PHP (Recommended)

Script ini aman, interaktif, dan menggunakan transaksi database.

```bash
# Di production server
cd /path/to/gembira
php delete_invalid_quotes_simple.php
```

**Fitur script:**
- ✅ Menampilkan preview data yang akan dihapus
- ✅ Meminta konfirmasi sebelum menghapus
- ✅ Menggunakan database transaction (rollback jika error)
- ✅ Verifikasi hasil setelah penghapusan
- ✅ Tidak perlu Symfony kernel (lebih cepat)

**Output yang diharapkan:**
```
📋 STEP 1: Cek quotes yang akan dihapus
=========================================
Total quotes ditemukan: 5

  • ID: 1 | Author: Anonim
    Content: Ikhlas adalah kunci ketenangan hati...
  • ID: 2 | Author: Anonim
    Content: Bekerja dengan ikhlas adalah ibadah...
  • ID: 3 | Author: Bu Ani
    Content: Setiap hari adalah kesempatan baru...
  [... dll ...]

🔗 STEP 2: Cek interactions yang akan dihapus
=============================================
Total interactions yang akan terhapus: 18

⚠️  RINGKASAN PENGHAPUSAN
=========================
Total quotes yang akan dihapus: 5
Total interactions yang akan dihapus: 18

Apakah Anda yakin ingin menghapus semua data di atas? (yes/no):
```

**Ketik `yes` untuk melanjutkan, `no` untuk membatalkan.**

### Metode 2: Menggunakan SQL File Manual

Jika Anda lebih nyaman dengan SQL langsung:

```bash
# 1. Buka file delete_invalid_quotes.sql
# 2. Jalankan query SELECT untuk preview data
# 3. Uncomment query DELETE jika sudah yakin
# 4. Jalankan melalui phpMyAdmin/MySQL client
```

File: `delete_invalid_quotes.sql`

## Langkah-langkah Lengkap

### Persiapan (WAJIB!)

```bash
# 1. Backup database production
php backup_before_deploy.php

# Atau manual:
mysqldump -u username -p database_name > backup_sebelum_hapus_quotes_$(date +%Y%m%d_%H%M%S).sql
```

### Eksekusi Penghapusan

```bash
# 2. Jalankan script penghapusan
php delete_invalid_quotes_simple.php

# 3. Ketik 'yes' untuk konfirmasi setelah melihat preview data
```

### Verifikasi & Clear Cache

```bash
# 4. Clear Symfony cache
php bin/console cache:clear

# 5. Cek hasilnya melalui browser
# Buka: https://your-domain.com/ikhlas/leaderboard
```

### Verifikasi Manual (Opsional)

```sql
-- Cek tidak ada lagi quotes dari user tidak valid
SELECT COUNT(*) FROM quote
WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim');
-- Hasil yang diharapkan: 0

-- Cek total quotes tersisa
SELECT COUNT(*) FROM quote;
-- Hasil yang diharapkan: 3

-- Cek semua quotes yang tersisa
SELECT id, author, SUBSTRING(content, 1, 80) as preview
FROM quote
ORDER BY created_at DESC;
-- Hasil yang diharapkan: Semua author adalah nama pegawai yang valid
```

## Hasil yang Diharapkan

Setelah penghapusan berhasil:

### Database:
- ✅ 5 quotes dari user tidak valid terhapus
- ✅ ~12-15 interactions terkait terhapus
- ✅ 3 quotes dari pegawai valid tersisa
- ✅ Tidak ada quotes dengan author 'Anonim', 'Pak Dedi', 'Pak Budi', atau 'Bu Ani'

### Halaman /ikhlas/leaderboard:
- **Statistik Global** akan menampilkan:
  - Total Quotes: 3 (berkurang dari 8)
  - Total Interactions: ~6-12 (berkurang dari 24)
  - Total Likes: berkurang
  - Total Saves: berkurang

- **Quotes Terpopuler** akan menampilkan:
  - Hanya quotes dari pegawai yang terdaftar
  - Tidak ada lagi quotes dari Anonim, Pak Dedi, Pak Budi, Bu Ani

- **Leaderboard XP** tetap normal (tidak terpengaruh karena XP dari aktivitas pegawai lain)

## Troubleshooting

### Error: "could not find driver"
**Penyebab**: PHP PDO extension belum aktif

**Solusi**:
```bash
# Cek extension
php -m | grep pdo

# Jika tidak ada, aktifkan di php.ini:
# extension=pdo_mysql
```

### Error: "Cannot delete or update a parent row"
**Penyebab**: Foreign key constraint

**Solusi**: Script sudah menghapus interactions terlebih dahulu, tapi jika masih error:
```sql
-- Manual: Hapus interactions dulu
DELETE FROM user_quote_interaction
WHERE quote_id IN (1, 2, 3, ...);

-- Baru hapus quotes
DELETE FROM quote WHERE id IN (1, 2, 3, ...);
```

### Tidak mau menghapus, ingin rollback
**Solusi**:
1. Restore dari backup yang dibuat sebelumnya
2. Script menggunakan transaction, jadi jika error akan auto-rollback

## Files Terkait

- `delete_invalid_quotes_simple.php` - Script PHP untuk penghapusan interaktif (RECOMMENDED)
- `delete_invalid_quotes.sql` - SQL file untuk penghapusan manual
- `verify_ikhlas_data.php` - Script untuk verifikasi data IKHLAS

## Catatan Penting

⚠️ **SELALU backup database sebelum menghapus data!**

⚠️ **Periksa preview data dengan teliti sebelum konfirmasi penghapusan!**

✅ **Setelah penghapusan, pastikan clear cache Symfony**

✅ **Verifikasi hasil melalui browser dan query SQL**
