# Fix Redirect Login - Pegawai ke Halaman Absensi

## 📋 Deskripsi Masalah

Sebelumnya, ketika **pegawai** login atau mengganti password, mereka **diarahkan ke halaman admin** atau halaman dashboard yang salah. Padahal seharusnya:
- **Admin/Operator** → `/admin/dashboard` (panel kontrol admin)
- **Pegawai** → `/absensi` (halaman absensi pegawai)

## 🔍 Penyebab Masalah

Symfony menyimpan URL terakhir yang diakses user dalam session sebagai `_security.main.target_path`. Ketika user login kembali, Symfony secara default akan mencoba redirect ke URL tersebut.

**Skenario yang menyebabkan bug:**
1. Pegawai membuka halaman admin sebelumnya (misal untuk keperluan testing)
2. Session menyimpan `/admin/...` sebagai target_path
3. Pegawai ganti password → logout → login ulang
4. Sistem redirect ke target_path yang tersimpan = `/admin/...` ❌

## ✅ Solusi

Update file `src/Security/LoginSuccessHandler.php` untuk:

1. **Hapus `target_path` dari session** setiap kali login sukses
2. **Prioritaskan entity type** (Pegawai vs Admin) daripada role
3. **Pastikan Pegawai SELALU** diarahkan ke user dashboard, meskipun mereka punya `ROLE_ADMIN`

### Perubahan Kode

**File:** `src/Security/LoginSuccessHandler.php`

**Penambahan (lines 45-51):**
```php
// PENTING: Hapus target_path dari session untuk memastikan redirect yang benar
// Ini mencegah user diarahkan ke halaman yang salah (contoh: pegawai ke admin panel)
// terutama setelah ganti password atau logout
$session = $request->getSession();
if ($session->has('_security.main.target_path')) {
    $session->remove('_security.main.target_path');
}
```

**Update Komentar (lines 53-55):**
```php
// Redirect berdasarkan tipe ENTITY (bukan role)
// Prioritas: entity type > role
// Ini memastikan Pegawai SELALU ke user dashboard, meskipun punya ROLE_ADMIN
```

## 🎯 Hasil Setelah Fix

### Flow Sebelum Fix:
```
Pegawai Login → ❌ Redirect ke Admin Panel atau Dashboard salah
Admin Login → ❌ Mungkin ke halaman yang salah
```

### Flow Setelah Fix:
```
Pegawai Login → ✅ Redirect ke /absensi (Halaman Absensi Pegawai)
Admin Login → ✅ Redirect ke /admin/dashboard (Panel Kontrol Admin)
```

### Setelah Ganti Password:
```
Pegawai Ganti Password → Logout → Login Ulang → ✅ Redirect ke /absensi
Admin Ganti Password → Logout → Login Ulang → ✅ Redirect ke /admin/dashboard
```

## 📝 Penjelasan Teknis

### 1. Session Target Path
Symfony `form_login` secara default menyimpan URL yang gagal diakses (karena belum login) ke dalam session key `_security.main.target_path`. Setelah login sukses, user akan diarahkan ke URL tersebut.

### 2. Prioritas Redirect
Dengan menghapus `target_path` dari session, kita memaksa sistem untuk:
- ✅ **Menggunakan LoginSuccessHandler logic** (berdasarkan entity type)
- ❌ Bukan menggunakan target_path yang tersimpan di session

### 3. Entity Type vs Role
```php
if ($user instanceof Admin) {
    $targetUrl = $this->urlGenerator->generate('app_admin_dashboard');
} elseif ($user instanceof Pegawai) {
    $targetUrl = $this->urlGenerator->generate('app_absensi_dashboard');
}
```

Ini memastikan bahwa:
- Entity `Admin` → selalu ke `/admin/dashboard` (Panel Kontrol Admin)
- Entity `Pegawai` → selalu ke `/absensi` (Halaman Absensi Pegawai)

## 🧪 Testing

### Test Case 1: Login Pegawai Normal
1. Buka halaman login
2. Login dengan akun pegawai (NIP + Password)
3. **Expected:** Redirect ke `/absensi` ✅
4. **Sebelumnya:** Redirect ke `/admin` atau dashboard lain ❌

### Test Case 2: Login Admin/Operator
1. Buka halaman login
2. Login dengan akun admin (Username + Password)
3. **Expected:** Redirect ke `/admin/dashboard` ✅

### Test Case 3: Pegawai Ganti Password
1. Login sebagai pegawai
2. Buka `/profile/ganti-password`
3. Ganti password
4. Logout otomatis
5. Login dengan password baru
6. **Expected:** Redirect ke `/absensi` ✅

### Test Case 4: Admin Ganti Password
1. Login sebagai admin (entity Admin)
2. Ganti password via form admin
3. Logout → Login ulang
4. **Expected:** Redirect ke `/admin/dashboard` ✅

### Test Case 5: Pegawai dengan ROLE_ADMIN
1. Login sebagai pegawai yang punya `ROLE_ADMIN` di field `roles`
2. **Expected:** Tetap ke `/absensi` ✅
3. **Reasoning:** Entity type adalah `Pegawai`, bukan `Admin`

## 🔒 Security Considerations

### Keamanan Session
- Menghapus `target_path` dari session **tidak mengurangi keamanan**
- Sistem tetap mengecek role dan permission di setiap route
- User tidak bisa akses halaman yang tidak sesuai role mereka

### Access Control
Meskipun redirect sudah benar, sistem tetap punya proteksi di `security.yaml`:
```yaml
access_control:
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/dashboard, roles: ROLE_USER }
```

Jadi meskipun ada bug redirect, user tidak bisa akses halaman yang tidak sesuai role.

## 📚 Referensi

### File yang Dimodifikasi
- ✅ `src/Security/LoginSuccessHandler.php`

### File Terkait (Tidak Dimodifikasi)
- `config/packages/security.yaml` - Konfigurasi security Symfony
- `templates/profile/ganti_password.html.twig` - Form ganti password pegawai
- `src/Controller/ProfileController.php` - Controller ganti password
- `src/Controller/SecurityController.php` - Controller login/logout

### Symfony Documentation
- [Authentication Success Handler](https://symfony.com/doc/current/security/form_login.html#redirecting-after-success)
- [Target Path Redirect](https://symfony.com/doc/current/security.html#redirect-after-login)

## 🔑 Perbedaan Konsep User

### Admin/Operator (Entity: `Admin`)
- **Akun untuk:** Operator admin di setiap bidang/unit kerja
- **Login dengan:** Username + Password
- **Redirect ke:** `/admin/dashboard` (Panel kontrol admin)
- **Role:** `ROLE_ADMIN`
- **Fungsi:** Mengelola sistem, data pegawai, laporan, dll

### Pegawai (Entity: `Pegawai`)
- **Akun untuk:** Setiap pegawai/karyawan
- **Login dengan:** NIP + Password
- **Redirect ke:** `/absensi` (Halaman absensi)
- **Role:** `ROLE_USER`
- **Fungsi:** Melakukan absensi, lihat laporan kehadiran pribadi

## ✨ Kesimpulan

Dengan fix ini, sistem sekarang:
- ✅ **Pegawai** SELALU diarahkan ke `/absensi` setelah login
- ✅ **Admin/Operator** SELALU diarahkan ke `/admin/dashboard` setelah login
- ✅ Tidak terpengaruh oleh `target_path` yang tersimpan di session
- ✅ Flow ganti password bekerja dengan benar untuk semua tipe user
- ✅ Redirect berdasarkan **entity type**, bukan role

---

**Updated:** 2025-01-23
**Author:** Claude Code Assistant
**Status:** ✅ Implemented & Tested
