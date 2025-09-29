# 🔐 Dokumentasi Role & Permission Sistem Absensi Gembira

## 📋 Ringkasan Perubahan

Sistem role dan permission telah diperbaiki untuk memberikan akses yang tepat sesuai kebutuhan bisnis aplikasi absensi. Sistem ini sekarang mendukung dua role utama dengan pembatasan akses yang jelas berdasarkan unit kerja.

## 👥 Role yang Tersedia

### 1. **Super Admin** (Role: `super_admin`)
**Memiliki akses penuh ke semua fitur tanpa batasan unit kerja**

#### ✅ Hak Akses Super Admin:
- **Kelola Semua Pegawai** - Tambah, edit, hapus pegawai dari semua unit kerja
- **Kelola Semua Jadwal Absensi** - Atur jadwal untuk semua unit kerja
- **Validasi Semua Absensi** - Setujui/tolak absensi dari semua pegawai
- **Akses Semua Laporan** - Lihat dan download laporan kehadiran semua unit kerja
- **Kelola Semua Event** - Buat dan kelola event untuk semua unit kerja
- **Kelola User Admin** - Tambah, edit, hapus admin lain
- **Pengaturan Sistem** - Konfigurasi sistem secara global
- **Kelola Unit Kerja** - Tambah, edit, hapus unit kerja
- **Kelola Struktur Organisasi** - Atur kepala bidang dan kepala kantor
- **QR Code Manager** - Kelola dan perbaiki QR code
- **Manajemen Banner** - Kelola banner dan slider

### 2. **Admin Unit Kerja** (Role: `admin`)
**Akses terbatas hanya pada unit kerja tempat admin bekerja**

#### ✅ Hak Akses Admin Unit Kerja:
- **Kelola Pegawai Unit Sendiri** - Tambah, edit, hapus pegawai hanya dalam unit kerjanya
- **Kelola Jadwal Unit Sendiri** - Atur jadwal absensi untuk pegawai unit kerjanya
- **Validasi Absensi Unit Sendiri** - Setujui/tolak absensi pegawai unit kerjanya saja
- **Laporan Unit Sendiri** - Lihat dan download laporan kehadiran unit kerjanya
- **Event Unit Sendiri** - Kelola event khusus unit kerjanya
- **Profil Sendiri** - Lihat dan edit profil admin sendiri

#### ❌ Tidak Bisa Diakses Admin Unit Kerja:
- Menu User (kelola admin lain)
- Menu Pengaturan Role
- Menu Unit Kerja, Kepala Bidang, Kepala Kantor
- QR Code Manager
- Pengaturan Sistem
- Manajemen Banner
- Data pegawai dari unit kerja lain
- Absensi pegawai dari unit kerja lain

## 🔧 File yang Diubah

### 1. **Entity Admin** (`src/Entity/Admin.php`)
- ➕ Ditambah method `isSuperAdmin()` dan `isAdminUnit()`
- ➕ Ditambah method `canAccessUnitKerja()` dan `canManagePegawai()`
- ➕ Ditambah method `getAllowedPermissions()` untuk permission berdasarkan role
- 🔄 Diperbaiki default permission untuk admin baru

### 2. **AdminPermissionService** (`src/Service/AdminPermissionService.php`)
- ➕ Service baru untuk mengelola permission secara konsisten
- ➕ Method `canAccessMenu()` - cek akses menu berdasarkan role
- ➕ Method `canManagePegawai()` - cek permission kelola pegawai
- ➕ Method `canAccessUnitKerja()` - cek akses unit kerja
- ➕ Method `filterPegawaiByPermission()` - filter data pegawai berdasarkan role
- ➕ Method `getAccessDeniedMessage()` - pesan error dalam bahasa Indonesia

### 3. **Security Configuration** (`config/packages/security.yaml`)
- 🔄 Ditambah komentar untuk akses control yang lebih jelas
- ✅ Access control sudah sesuai dengan role system

### 4. **Controller Updates**

#### **AdminUserController** (`src/Controller/AdminUserController.php`)
- ➕ Ditambah dependency `AdminPermissionService` dan `ValidationBadgeService`
- ➕ Permission check: hanya Super Admin yang bisa kelola user admin
- ➕ Sidebar stats untuk badge validasi absen

#### **AdminValidasiAbsenController** (`src/Controller/AdminValidasiAbsenController.php`)
- ➕ Ditambah dependency `AdminPermissionService` dan `ValidationBadgeService`
- 🔄 Method `hitungStatistikValidasi()` - filter berdasarkan unit kerja admin
- 🔄 Method `ambilDaftarAbsensiDenganFilter()` - filter otomatis berdasarkan unit kerja
- ➕ Permission check pada method `approveAbsensi()` dan `rejectAbsensi()`
- ➕ Admin unit hanya bisa validasi absensi pegawai dari unit kerjanya

### 5. **Template Sidebar** (`templates/admin/_sidebar.html.twig`)
- 🔄 Menu **User** dan **Pengaturan Role** - hanya tampil untuk Super Admin
- 🔄 Menu **Unit Kerja**, **Kepala Bidang**, **Kepala Kantor** - hanya tampil untuk Super Admin
- 🔄 Menu **QR Code Manager** dan **Manajemen Banner** - hanya tampil untuk Super Admin
- 🔄 Menu **Pengaturan** - hanya tampil untuk Super Admin
- ➕ Label **(Unit Saya)** pada menu untuk Admin Unit Kerja

## 🎯 Cara Kerja Permission System

### 1. **Validasi di Controller**
```php
// Cek apakah admin bisa akses fitur tertentu
if (!$this->permissionService->canAccessFeature($admin, 'kelola_user_admin')) {
    $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'mengelola user admin'));
    return $this->redirectToRoute('app_admin_dashboard');
}

// Cek apakah admin bisa kelola pegawai tertentu
if (!$this->permissionService->canManagePegawai($admin, $pegawai)) {
    return new JsonResponse([
        'success' => false,
        'message' => $this->permissionService->getAccessDeniedMessage($admin, 'mengelola pegawai dari unit kerja lain')
    ], 403);
}
```

### 2. **Filter Data Berdasarkan Unit Kerja**
```php
// Filter otomatis berdasarkan unit kerja admin
if (!$admin->isSuperAdmin() && $admin->getUnitKerjaEntity()) {
    $queryBuilder->andWhere('p.unitKerjaEntity = :adminUnitKerja')
               ->setParameter('adminUnitKerja', $admin->getUnitKerjaEntity());
}
```

### 3. **Kondisi di Template**
```twig
{# Menu hanya tampil untuk Super Admin #}
{% if app.user.isSuperAdmin() %}
    <a href="{{ path('app_admin_user') }}">User</a>
{% endif %}

{# Label berbeda berdasarkan role #}
<span>Pegawai {{ app.user.isSuperAdmin() ? '' : '(Unit Saya)' }}</span>
```

## 📊 Contoh Skenario Penggunaan

### **Skenario 1: Super Admin Login**
- ✅ Bisa lihat semua menu di sidebar
- ✅ Bisa kelola pegawai dari semua unit kerja
- ✅ Bisa validasi absensi dari semua pegawai
- ✅ Bisa akses laporan kehadiran semua unit kerja
- ✅ Bisa tambah/edit/hapus admin lain

### **Skenario 2: Admin Unit "IT" Login**
- ❌ Menu User, Pengaturan, Unit Kerja tidak terlihat
- ✅ Menu "Pegawai (Unit Saya)" hanya tampilkan pegawai unit IT
- ✅ Menu "Validasi Absen (Unit Saya)" hanya tampilkan absensi pegawai IT
- ✅ Menu "Laporan Kehadiran (Unit Saya)" hanya tampilkan laporan unit IT
- ❌ Tidak bisa validasi absensi pegawai dari unit HR atau Finance

### **Skenario 3: Admin Unit "HR" Login**
- ✅ Hanya bisa lihat dan kelola pegawai unit HR
- ❌ Tidak bisa lihat data pegawai unit IT
- ✅ Bisa validasi absensi pegawai HR
- ❌ Tidak bisa akses menu pengaturan sistem

## ⚠️ Catatan Penting

1. **Keamanan Data**: Admin unit kerja tidak bisa mengakses data unit kerja lain
2. **Role Assignment**: Admin harus di-assign ke unit kerja agar bisa bekerja optimal
3. **Permission Konsisten**: Semua controller menggunakan `AdminPermissionService` yang sama
4. **Error Message**: Pesan error menggunakan bahasa Indonesia yang mudah dipahami
5. **Badge Validasi**: Badge "Validasi Absen" di sidebar sudah filter berdasarkan unit kerja admin

## 🔄 Testing yang Perlu Dilakukan

1. **Test Super Admin**: Login sebagai Super Admin, pastikan bisa akses semua menu dan data
2. **Test Admin Unit**: Login sebagai Admin Unit, pastikan hanya bisa akses data unit sendiri
3. **Test Cross-Unit Access**: Admin unit A tidak bisa akses data unit B
4. **Test Permission Error**: Pastikan error message muncul saat akses ditolak
5. **Test Badge**: Badge validasi absen hanya tampil untuk data yang relevan dengan admin

---

**✅ Status: Role & Permission System sudah diperbaiki dan siap digunakan**

*Dibuat pada: September 2025*
*Developer: Indonesian Developer*