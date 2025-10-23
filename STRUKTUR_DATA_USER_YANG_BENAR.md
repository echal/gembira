# Struktur Data User yang BENAR

## 📋 Konsep User di Sistem Gembira

Sistem Gembira memiliki **2 tipe user** yang berbeda:

### 1. Admin/Super Admin
- **Disimpan di:** Table `admin`
- **Role yang valid:** `'admin'` atau `'super_admin'`
- **Login dengan:** Username + Password
- **Redirect setelah login:** `/admin/dashboard` (Panel Kontrol Admin)
- **Menu di Admin Panel:** `/admin/user/` (User Management)
- **Fungsi:** Mengelola sistem, data pegawai, jadwal, laporan, validasi absensi

### 2. Pegawai
- **Disimpan di:** Table `pegawai`
- **Entity:** `App\Entity\Pegawai`
- **Login dengan:** NIP + Password
- **Redirect setelah login:** `/absensi` (Halaman Absensi)
- **Menu di Admin Panel:** `/admin/struktur-organisasi/pegawai` (Data Management - Pegawai)
- **Fungsi:** Melakukan absensi, lihat laporan kehadiran pribadi

## ❌ DATA YANG SALAH

### Admin dengan role='pegawai' (DATA ERROR!)

**TIDAK SEHARUSNYA** ada user di table `admin` dengan field `role='pegawai'`. Ini adalah **data yang salah** yang mungkin terjadi karena:
- Import Excel yang salah
- Sinkronisasi data yang tidak seharusnya dilakukan
- Command `app:sync-pegawai-to-admin` yang seharusnya tidak dijalankan

**Contoh data salah:**
```
Table: admin
ID: 3
Username: 198201262008011013
Nama: Faisal Kasim
Role: pegawai  ⚠️ SALAH!
```

**Seharusnya:**
- User ini **HANYA** ada di table `pegawai`
- **TIDAK** ada di table `admin`

## ✅ Struktur Data yang BENAR

### Table: `admin`
```
ID | Username      | Nama Lengkap   | Role        | Status
---+---------------+----------------+-------------+--------
1  | admin_super   | Super Admin    | super_admin | aktif
8  | admin_kepeg   | Admin Kepeg    | admin       | aktif
9  | admin_keu     | Admin Keuangan | admin       | aktif
```

**Catatan:** Field `role` hanya boleh: `'super_admin'` atau `'admin'`

### Table: `pegawai`
```
ID | NIP                | Nama                    | Status
---+--------------------+-------------------------+--------
1  | 123456789          | Ahmad Budiman           | aktif
2  | 198201262008011013 | Faisal Kasim            | aktif
3  | 198308012009101001 | SYAMSUL, SE             | aktif
4  | 197709072009101002 | ABD. KADIR AMIN, S. HI  | aktif
```

**Catatan:** Semua pegawai HANYA ada di table ini, TIDAK di table admin

## 🔧 Cara Membersihkan Data yang Salah

### Langkah 1: Cek Data yang Salah

```bash
cd c:\xampp\htdocs\gembira
php check_admin_with_pegawai_role.php
```

Output akan menunjukkan user mana saja di table `admin` yang punya role='pegawai'

### Langkah 2: Cleanup Data

```bash
php cleanup_admin_pegawai_role.php
```

Script ini akan:
1. Cek apakah user sudah ada di table `pegawai`
2. Jika sudah ada, hapus dari table `admin`
3. Jika belum ada, skip untuk safety

**Konfirmasi:** Script akan meminta konfirmasi `yes` sebelum menghapus data

### Langkah 3: Verifikasi

Setelah cleanup, cek lagi:

```bash
php check_admin_with_pegawai_role.php
```

Seharusnya output: "✅ BAGUS! Tidak ada admin dengan role='pegawai'"

## 🎯 Login Behavior Setelah Cleanup

### Sebelum Cleanup:
```
Faisal Kasim login → Ditemukan di table 'admin' dengan role='pegawai'
                   → Entity: Admin
                   → Redirect: /admin/dashboard (❌ SALAH!)
```

### Setelah Cleanup:
```
Faisal Kasim login → Ditemukan di table 'pegawai'
                   → Entity: Pegawai
                   → Redirect: /absensi (✅ BENAR!)
```

## 📝 Penjelasan Provider Chain

Symfony menggunakan `chain_provider` di `security.yaml`:

```yaml
chain_provider:
    chain:
        providers: ['admin_provider', 'pegawai_provider']
```

**Urutan pengecekan:**
1. Cek di `admin_provider` (table `admin`)
2. Jika tidak ketemu, cek di `pegawai_provider` (table `pegawai`)

**Problem sebelum cleanup:**
- Username `198201262008011013` ditemukan di **table admin** dengan role='pegawai'
- Symfony berhenti di provider pertama (admin_provider)
- Entity yang digunakan: `Admin` (bukan `Pegawai`)
- Result: Redirect salah!

**Setelah cleanup:**
- Username `198201262008011013` TIDAK ada di table admin
- Symfony lanjut ke provider kedua (pegawai_provider)
- Entity yang digunakan: `Pegawai`
- Result: Redirect benar ke `/absensi`!

## ⚠️ Catatan Penting

### Jangan Jalankan Command Ini:
```bash
# ❌ JANGAN JALANKAN INI!
php bin/console app:sync-pegawai-to-admin
```

Command ini akan membuat data duplikat (pegawai masuk ke table admin dengan role='pegawai').

### Import Excel
Ketika import user via Excel:
- **Untuk Admin/Super Admin:** Import ke `/admin/user/`
- **Untuk Pegawai:** Import ke `/admin/struktur-organisasi/pegawai`
- **Field role:** Hanya boleh `'admin'` atau `'super_admin'`, BUKAN `'pegawai'`

## 🔐 Security & Access Control

Meskipun redirect sudah benar, sistem tetap punya proteksi di `security.yaml`:

```yaml
access_control:
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/absensi, roles: ROLE_USER }
```

Jadi meskipun ada redirect error, user tetap tidak bisa akses halaman yang tidak sesuai role mereka.

## ✅ Kesimpulan

**Struktur Data yang Benar:**
- ✅ Table `admin` → HANYA untuk Admin & Super Admin
- ✅ Table `pegawai` → HANYA untuk Pegawai
- ❌ TIDAK boleh ada Admin dengan role='pegawai'

**Login Redirect yang Benar:**
- ✅ Admin/Super Admin → `/admin/dashboard`
- ✅ Pegawai → `/absensi`

**Menu di Admin Panel:**
- ✅ Admin/Super Admin → `/admin/user/`
- ✅ Pegawai → `/admin/struktur-organisasi/pegawai`

---

**Updated:** 2025-01-23
**Status:** ✅ Documented & Ready for Cleanup
