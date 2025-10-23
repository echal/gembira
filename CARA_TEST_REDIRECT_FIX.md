# Cara Test Redirect Fix untuk User Faisal Kasim

## 📋 Status Saat Ini

Berdasarkan pengecekan database:

✅ **User Faisal Kasim** ditemukan:
- **Table admin**: ID 3, Username: 198201262008011013, **Role: pegawai** ⚠️
- **Table pegawai**: ID 2, NIP: 198201262008011013

✅ **LoginSuccessHandler.php** sudah benar:
- Mengecek field `role` di entity Admin
- Jika `role='pegawai'` → redirect ke `/absensi`

✅ **Cache Symfony** sudah di-clear

## 🔍 Mengapa Masih ke /admin/dashboard?

Kemungkinan penyebab:

1. **Session browser masih menyimpan state lama**
   - Session PHP yang masih aktif dengan kode lama
   - Browser cache yang menyimpan redirect lama

2. **Kode belum di-reload oleh web server**
   - PHP-FPM atau Apache perlu restart (jika production)

## ✅ Solusi: Langkah-Langkah Test

### Opsi 1: Logout dan Login Ulang (Recommended)

1. **Logout dari admin panel**
   - Klik menu Logout di admin panel
   - Pastikan session benar-benar di-clear

2. **Clear browser cache (atau gunakan Incognito/Private mode)**
   - Chrome: Ctrl + Shift + N (Incognito)
   - Firefox: Ctrl + Shift + P (Private Window)
   - Edge: Ctrl + Shift + N (InPrivate)

3. **Login ulang dengan user Faisal Kasim**
   - Username: `198201262008011013`
   - Password: [password yang sesuai]

4. **Hasil yang diharapkan:**
   - ✅ Redirect ke `/absensi` (Halaman Absensi Pegawai)
   - ❌ BUKAN ke `/admin/dashboard`

### Opsi 2: Clear Session Manual (Jika Opsi 1 Gagal)

Jika masih gagal, clear session secara manual:

1. **Clear session Symfony**
   ```bash
   cd c:\xampp\htdocs\gembira
   php bin/console cache:pool:clear cache.app
   php bin/console cache:pool:clear cache.system
   ```

2. **Hapus folder session (jika perlu)**
   ```bash
   # Lokasi session biasanya di:
   # c:\xampp\tmp\
   # atau check di php.ini: session.save_path
   ```

3. **Restart XAMPP Apache** (jika perlu)
   - Stop Apache
   - Start Apache

4. **Login ulang dengan Incognito mode**

### Opsi 3: Force Logout Semua User

Jika user masih login dengan session lama:

```bash
cd c:\xampp\htdocs\gembira
php bin/console cache:clear --no-warmup
```

## 🧪 Verifikasi Fix Bekerja

Setelah login ulang, cek:

1. **URL yang dituju:**
   - ✅ Harus: `http://localhost/gembira/absensi` atau `http://localhost/gembira/public/absensi`
   - ❌ Bukan: `http://localhost/gembira/admin/dashboard`

2. **Halaman yang tampil:**
   - ✅ Halaman Absensi dengan grid icon jadwal absensi
   - ❌ Bukan Admin Panel Gembira

3. **Navigasi bawah:**
   - ✅ Ada navigation bar: Home, Laporan, Kalender, Notifikasi, Akun Saya
   - ❌ Bukan sidebar admin

## 🔧 Debug Jika Masih Gagal

Jika setelah semua langkah di atas masih redirect ke `/admin/dashboard`, jalankan:

```bash
cd c:\xampp\htdocs\gembira
php check_user_faisal.php
```

Output akan menunjukkan:
- Data user Faisal Kasim
- Role yang tersimpan
- Expected redirect
- Diagnosa masalah

## 📝 Penjelasan Teknis

### Mengapa User Faisal Kasim Ada di 2 Tabel?

User ini kemungkinan di-sync dari table `pegawai` ke table `admin` menggunakan:
- Command: `php bin/console app:sync-pegawai-to-admin`
- Atau: Import Excel yang membuat admin dengan role='pegawai'

### Provider Mana yang Digunakan?

Symfony menggunakan **chain_provider** dengan urutan:
1. `admin_provider` (cek table admin)
2. `pegawai_provider` (cek table pegawai)

Karena username `198201262008011013` ditemukan di table `admin`, maka:
- ✅ Entity yang digunakan: `App\Entity\Admin`
- ✅ Field role: `'pegawai'`
- ✅ LoginSuccessHandler akan cek: `$user->getRole() === 'pegawai'` → TRUE
- ✅ Redirect ke: `/absensi`

### Kode LoginSuccessHandler

```php
if ($user instanceof Admin) {
    // Cek field role di entity Admin
    if ($user->getRole() === 'pegawai') {
        // Admin dengan role pegawai → diarahkan ke halaman absensi
        $targetUrl = $this->urlGenerator->generate('app_absensi_dashboard');
    } else {
        // Admin dengan role 'admin' atau 'super_admin' → panel admin
        $targetUrl = $this->urlGenerator->generate('app_admin_dashboard');
    }
}
```

## ✅ Kesimpulan

Kode sudah benar! Masalahnya adalah **session lama** yang masih tersimpan di browser/server.

**Solusi:**
1. ✅ Logout
2. ✅ Clear cache (atau gunakan Incognito)
3. ✅ Login ulang
4. ✅ Seharusnya sekarang ke `/absensi`

---

**Note:** Jika masih ada masalah setelah semua langkah di atas, kemungkinan ada konfigurasi lain yang perlu dicek (misalnya ada custom redirect di controller lain).
