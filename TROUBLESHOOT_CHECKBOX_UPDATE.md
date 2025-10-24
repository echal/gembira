# Troubleshooting: Checkbox Update Tidak Berfungsi

## 🔍 Masalah yang Dilaporkan

User mencoba menonaktifkan checkbox "Perlu Scan QR Code" dan "Perlu Foto Selfie" di form Edit Jadwal Absensi, tapi setelah klik "Simpan Perubahan", tidak ada perubahan yang tersimpan.

## ✅ Yang Sudah Diperbaiki

### 1. **Controller - Name Attribute Mismatch** (FIXED)

**Masalah**: Controller mencari field tanpa prefix, tapi form mengirim dengan prefix `edit_`

**Sebelum**:
```php
$jamMulai = $request->request->get('jam_mulai');  // ❌ SALAH
$jamSelesai = $request->request->get('jam_selesai');
$keterangan = $request->request->get('keterangan');
```

**Sesudah**:
```php
$jamMulai = $request->request->get('edit_jam_mulai');  // ✅ BENAR
$jamSelesai = $request->request->get('edit_jam_selesai');
$keterangan = $request->request->get('edit_keterangan');
```

**File**: `src/Controller/AdminController.php:569-571`

### 2. **Debug Logging** (ADDED)

Ditambahkan error_log untuk tracking:
- Line 579-582: Log nilai checkbox yang diterima dari form
- Line 600-603: Log nilai setelah di-set ke Entity
- Line 608: Log konfirmasi save berhasil

## 🧪 Cara Test

### Test 1: Via UI (Recommended)

1. Buka Chrome DevTools (F12) → Tab "Network"
2. Login sebagai Super Admin
3. Buka `/admin/jadwal-absensi`
4. Klik edit pada "Apel Pagi"
5. **Nonaktifkan checkbox** "Perlu Scan QR Code" dan "Perlu Foto Selfie"
6. Klik "💾 Simpan Perubahan"
7. Lihat di Network tab:
   - Request URL: `/admin/jadwal-absensi/19/update`
   - Method: POST
   - Form Data: Harus berisi `perlu_qr_code: on` (jika dicentang) atau tidak ada (jika tidak dicentang)

### Test 2: Via Browser Console

Buka Console (F12) dan jalankan:

```javascript
// Cek nilai checkbox sebelum submit
console.log("QR Code:", document.getElementById('editPerluQrCode').checked);
console.log("Kamera:", document.getElementById('editPerluKamera').checked);
console.log("Validasi Admin:", document.getElementById('editPerluValidasiAdmin').checked);

// Cek FormData yang akan dikirim
const form = document.getElementById('formEditJadwal');
const formData = new FormData(form);
for (let pair of formData.entries()) {
    console.log(pair[0] + ': ' + pair[1]);
}
```

### Test 3: Via PHP Error Log

Setelah klik "Simpan Perubahan", cek file log:
- Windows XAMPP: `C:\xampp\php\logs\php_error_log`
- Linux: `/var/log/apache2/error.log` atau `/var/log/php-fpm/error.log`

Cari baris:
```
DEBUG updateJadwal - Checkbox values received:
  perlu_qr_code: on
  perlu_kamera: null
  perlu_validasi_admin: null
```

### Test 4: Via Database (Verifikasi Final)

```sql
SELECT id, nama_jadwal, perlu_qr_code, perlu_kamera, perlu_validasi_admin
FROM konfigurasi_jadwal_absensi
WHERE id = 19;
```

Expected result setelah uncheck semua:
```
| id | nama_jadwal | perlu_qr_code | perlu_kamera | perlu_validasi_admin |
|----|-------------|---------------|--------------|----------------------|
| 19 | Apel Pagi   |       0       |      0       |          0           |
```

## 🐛 Possible Issues

### Issue 1: Checkbox Tidak Terkirim (Unchecked = No Data)

**Penyebab**: HTML checkbox yang unchecked tidak mengirim data ke server.

**Solusi**: Controller sudah handle ini dengan default `false`:
```php
$jadwal->setPerluQrCode($perluQrCode === 'on' || $perluQrCode === '1' || $perluQrCode === true);
```

Jika `$perluQrCode` adalah `null` atau tidak ada, akan di-set ke `false`.

### Issue 2: Cache Tidak Clear

**Solusi**:
```bash
php bin/console cache:clear
```

Atau hard refresh browser: `Ctrl + Shift + R` (Windows) atau `Cmd + Shift + R` (Mac)

### Issue 3: JavaScript Error

Buka Console (F12) dan cek apakah ada error JavaScript. Jika ada, screenshot dan report.

### Issue 4: CSRF Token Invalid

Jika error "Invalid CSRF token", reload halaman dan coba lagi.

## 📊 Expected Behavior

### Scenario 1: Uncheck Semua (Absen Saja)
- **Action**: Nonaktifkan QR Code, Kamera, Validasi Admin
- **FormData sent**: (tidak ada field checkbox)
- **Controller receives**: `null` untuk semua
- **Saved to DB**: `0` untuk semua
- **Result**: Pegawai cukup klik tombol absen

### Scenario 2: Check QR Code + Kamera Only
- **Action**: Centang QR Code dan Kamera, nonaktifkan Validasi Admin
- **FormData sent**: `perlu_qr_code=on`, `perlu_kamera=on`
- **Controller receives**: `'on'`, `'on'`, `null`
- **Saved to DB**: `1`, `1`, `0`
- **Result**: Pegawai scan QR + upload foto

### Scenario 3: Check Semua
- **Action**: Centang semua checkbox
- **FormData sent**: `perlu_qr_code=on`, `perlu_kamera=on`, `perlu_validasi_admin=on`
- **Controller receives**: `'on'`, `'on'`, `'on'`
- **Saved to DB**: `1`, `1`, `1`
- **Result**: Pegawai scan QR + foto + menunggu validasi admin

## 🔧 Manual Fix (Jika UI Masih Tidak Berfungsi)

Jika setelah semua fix di atas, UI masih tidak berfungsi, gunakan SQL manual:

```sql
-- Set ke "Absen Saja"
UPDATE konfigurasi_jadwal_absensi
SET perlu_qr_code=0, perlu_kamera=0, perlu_validasi_admin=0
WHERE id=19;

-- Set ke QR + Kamera
UPDATE konfigurasi_jadwal_absensi
SET perlu_qr_code=1, perlu_kamera=1, perlu_validasi_admin=0
WHERE id=19;

-- Set ke Full Validation
UPDATE konfigurasi_jadwal_absensi
SET perlu_qr_code=1, perlu_kamera=1, perlu_validasi_admin=1
WHERE id=19;
```

## 📝 Next Steps

1. ✅ User coba edit jadwal lagi via UI
2. 📸 Jika masih gagal, screenshot:
   - Form modal dengan checkbox
   - Browser Console (F12)
   - Network tab saat submit
3. 📋 Report hasil test ke developer

## 🆘 Butuh Bantuan?

Jika masih ada masalah:
1. Screenshot form dan error
2. Copy paste dari Browser Console
3. Copy paste dari PHP error log
4. Report ke developer dengan info lengkap

---

**Updated**: 2025-10-24
**Status**: ⏳ Waiting for User Testing
