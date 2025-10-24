# Fitur Baru: Checkbox Pengaturan Jenis Absensi

## 📋 Deskripsi

Menambahkan fitur checkbox untuk mengatur jenis absensi di form **Edit Jadwal Absensi**, sehingga admin dapat dengan mudah mengatur apakah sebuah jadwal absensi memerlukan:

1. ✅ **Scan QR Code** - Pegawai harus scan QR code
2. 📸 **Foto Selfie** - Pegawai harus upload foto selfie
3. 👨‍💼 **Validasi Admin** - Absensi harus divalidasi oleh admin
4. 🆓 **Absen Saja** - Jika semua checkbox OFF, cukup klik tombol absen

## 🎯 Lokasi Fitur

**Halaman**: `/admin/jadwal-absensi`

**Cara Akses**:
1. Login sebagai Super Admin
2. Buka menu "Kelola Jadwal Absensi"
3. Klik tombol **Edit** (icon pensil) pada jadwal yang ingin diubah
4. Modal "Edit Jadwal Absensi" akan muncul dengan checkbox baru

## 🖼️ Tampilan UI

Modal Edit sekarang menampilkan section baru:

```
📋 Pengaturan Jenis Absensi

□ Perlu Scan QR Code
  Pegawai harus scan QR code saat absen

□ Perlu Foto Selfie
  Pegawai harus upload foto selfie saat absen

□ Perlu Validasi Admin
  Absensi harus divalidasi oleh admin

ℹ️ Info:
Absen Saja: Jika semua checkbox dinonaktifkan, pegawai cukup
klik tombol absen tanpa scan QR, foto, atau validasi admin.
```

## 🔧 Perubahan Teknis

### 1. Form Type
**File**: `src/Form/KonfigurasiJadwalAbsensiType.php`

Ditambahkan field checkbox:
```php
->add('perluValidasiAdmin', CheckboxType::class, [
    'label' => 'Perlu Validasi Admin',
    'help' => 'Centang jika absensi memerlukan validasi/approval dari admin',
    'required' => false,
    'attr' => ['class' => 'form-check-input']
])
```

### 2. Template Twig
**File**: `templates/admin/jadwal_absensi.html.twig`

Ditambahkan 3 checkbox di modal edit:
- `editPerluQrCode`
- `editPerluKamera`
- `editPerluValidasiAdmin`

### 3. JavaScript
**File**: `templates/admin/jadwal_absensi.html.twig` (line 398-401)

Update fungsi `editJadwal()` untuk populate checkbox:
```javascript
// Populate checkbox fitur absensi
document.getElementById('editPerluQrCode').checked = jadwal.perlu_qr_code === 1 || jadwal.perlu_qr_code === true;
document.getElementById('editPerluKamera').checked = jadwal.perlu_kamera === 1 || jadwal.perlu_kamera === true;
document.getElementById('editPerluValidasiAdmin').checked = jadwal.perlu_validasi_admin === 1 || jadwal.perlu_validasi_admin === true;
```

### 4. Controller
**File**: `src/Controller/AdminController.php`

#### Method `editJadwal()` (line 527)
Ditambahkan field di response JSON:
```php
'perlu_validasi_admin' => $jadwal->isPerluValidasiAdmin()
```

#### Method `updateJadwal()` (line 587-590)
Ditambahkan logic update checkbox:
```php
$jadwal->setPerluQrCode($perluQrCode === 'on' || $perluQrCode === '1' || $perluQrCode === true);
$jadwal->setPerluKamera($perluKamera === 'on' || $perluKamera === '1' || $perluKamera === true);
$jadwal->setPerluValidasiAdmin($perluValidasiAdmin === 'on' || $perluValidasiAdmin === '1' || $perluValidasiAdmin === true);
```

## 💾 Database

Kolom sudah ada di tabel `konfigurasi_jadwal_absensi`:
- `perlu_qr_code` (TINYINT)
- `perlu_kamera` (TINYINT)
- `perlu_validasi_admin` (TINYINT)

## 🧪 Testing

### Test Case 1: Absen Saja (Semua OFF)
1. Edit jadwal "Apel Pagi"
2. Nonaktifkan semua checkbox
3. Simpan
4. Pegawai seharusnya bisa absen hanya dengan klik tombol

### Test Case 2: QR Code + Foto
1. Edit jadwal
2. Centang "Perlu Scan QR Code" dan "Perlu Foto Selfie"
3. Simpan
4. Pegawai harus scan QR dan upload foto

### Test Case 3: Validasi Admin
1. Edit jadwal
2. Centang "Perlu Validasi Admin"
3. Simpan
4. Absensi pegawai akan masuk ke menu "Validasi Absen"

### Test Case 4: Semua Aktif
1. Edit jadwal
2. Centang semua checkbox
3. Simpan
4. Pegawai harus scan QR, foto, DAN menunggu validasi admin

## 📊 Contoh Konfigurasi

### Apel Pagi (Standar)
- ✅ Perlu Scan QR Code
- ✅ Perlu Foto Selfie
- ❌ Perlu Validasi Admin

### Ibadah Pagi (Fleksibel)
- ❌ Perlu Scan QR Code
- ❌ Perlu Foto Selfie
- ❌ Perlu Validasi Admin
→ **Absen Saja**

### Rapat Penting (Strict)
- ✅ Perlu Scan QR Code
- ✅ Perlu Foto Selfie
- ✅ Perlu Validasi Admin
→ **Full Validation**

## ✅ Checklist Implementasi

- [x] Tambah field `perluValidasiAdmin` di Form Type
- [x] Update template modal edit dengan 3 checkbox
- [x] Update JavaScript populate checkbox saat edit
- [x] Update Controller `editJadwal()` untuk kirim data checkbox
- [x] Update Controller `updateJadwal()` untuk simpan checkbox
- [x] Clear Symfony cache
- [x] Dokumentasi

## 🚀 Deployment

Untuk production, jalankan:
```bash
git add src/Form/KonfigurasiJadwalAbsensiType.php
git add src/Controller/AdminController.php
git add templates/admin/jadwal_absensi.html.twig
git commit -m "Feat: Tambah Checkbox Pengaturan Jenis Absensi di Edit Jadwal"
git push origin master
```

Di server production:
```bash
git pull origin master
php bin/console cache:clear --env=prod
```

## 📝 Notes

- Checkbox yang **unchecked** tidak mengirim data ke server, jadi default value adalah `false`
- Logic validation: `$value === 'on' || $value === '1' || $value === true`
- UI menggunakan Tailwind CSS untuk styling
- Kompatibel dengan existing data (backward compatible)

---

**Dibuat**: 2025-10-24
**Author**: Claude Code Assistant
**Status**: ✅ Ready for Production
