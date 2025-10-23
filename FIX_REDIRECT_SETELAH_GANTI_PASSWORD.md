# Fix Redirect Setelah Ganti Password

## 📋 Deskripsi Masalah

Sebelumnya, ketika **pegawai** mengganti password melalui halaman user (`/profile/ganti-password`), setelah logout dan login ulang, mereka **diarahkan ke halaman admin** padahal seharusnya tetap ke halaman user/pegawai.

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
Pegawai Ganti Password → Logout → Login Ulang → ❌ Redirect ke Admin Panel
```

### Flow Setelah Fix:
```
Pegawai Ganti Password → Logout → Login Ulang → ✅ Redirect ke User Dashboard
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
    // Ke admin dashboard
} elseif ($user instanceof Pegawai) {
    // Ke user dashboard - MESKIPUN PUNYA ROLE_ADMIN
}
```

Ini memastikan bahwa:
- Entity `Admin` → selalu ke `/admin/dashboard`
- Entity `Pegawai` → selalu ke `/dashboard` (user dashboard)

## 🧪 Testing

### Test Case 1: Ganti Password Normal
1. Login sebagai pegawai
2. Buka `/profile/ganti-password`
3. Ganti password
4. Logout otomatis
5. Login dengan password baru
6. **Expected:** Redirect ke `/dashboard` (user) ✅
7. **Sebelumnya:** Mungkin redirect ke `/admin/dashboard` ❌

### Test Case 2: Pegawai dengan ROLE_ADMIN
1. Login sebagai pegawai yang punya `ROLE_ADMIN` di field `roles`
2. Ganti password
3. Logout → Login ulang
4. **Expected:** Tetap ke `/dashboard` (user) ✅
5. **Reasoning:** Entity type adalah `Pegawai`, bukan `Admin`

### Test Case 3: Admin Ganti Password
1. Login sebagai admin (entity Admin)
2. Ganti password via form admin
3. Logout → Login ulang
4. **Expected:** Redirect ke `/admin/dashboard` ✅

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

## ✨ Kesimpulan

Dengan fix ini, sistem sekarang:
- ✅ Pegawai **SELALU** diarahkan ke user dashboard setelah login
- ✅ Admin **SELALU** diarahkan ke admin dashboard setelah login
- ✅ Tidak terpengaruh oleh `target_path` yang tersimpan di session
- ✅ Flow ganti password bekerja dengan benar untuk semua tipe user

---

**Updated:** 2025-01-XX
**Author:** Claude Code Assistant
**Status:** ✅ Implemented & Tested
