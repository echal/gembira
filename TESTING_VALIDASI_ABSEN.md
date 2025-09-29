# 🧪 Testing Guide - Halaman Validasi Absensi

## 📋 Checklist Testing

### ✅ 1. Testing Akses Halaman
- [ ] Login sebagai admin
- [ ] Akses URL: `/admin/validasi-absen`
- [ ] Pastikan halaman loading tanpa error
- [ ] Cek statistik cards muncul
- [ ] Cek tabel absensi muncul

### ✅ 2. Testing Filter & Search
- [ ] Filter berdasarkan status (pending/disetujui/ditolak)
- [ ] Filter berdasarkan tanggal mulai dan akhir
- [ ] Tombol reset filter
- [ ] DataTables search box berfungsi

### ✅ 3. Testing Individual Actions
- [ ] Klik tombol approve (hijau) pada absensi pending
- [ ] Modal keterangan muncul
- [ ] Submit dengan keterangan → success message
- [ ] Status absensi berubah menjadi "disetujui"
- [ ] Cek tombol approve hilang setelah disetujui

- [ ] Klik tombol reject (merah) pada absensi pending
- [ ] Modal keterangan muncul
- [ ] Submit tanpa keterangan → error message
- [ ] Submit dengan keterangan → success message
- [ ] Status absensi berubah menjadi "ditolak"

### ✅ 4. Testing Bulk Actions
- [ ] Pilih beberapa absensi dengan checkbox
- [ ] Klik "Setujui Terpilih"
- [ ] Konfirmasi muncul dengan jumlah yang benar
- [ ] Proses berhasil → semua terpilih jadi disetujui

- [ ] Pilih beberapa absensi dengan checkbox
- [ ] Klik "Tolak Terpilih"
- [ ] Konfirmasi muncul
- [ ] Proses berhasil → semua terpilih jadi ditolak

### ✅ 5. Testing Detail & Preview
- [ ] Klik foto absensi → modal preview muncul
- [ ] Foto tampil dengan benar dan responsive
- [ ] Klik tombol detail (mata) → modal detail muncul
- [ ] Info pegawai lengkap tampil
- [ ] Info jadwal tampil
- [ ] Info absensi (waktu, lokasi GPS) tampil
- [ ] Foto dalam modal detail tampil (jika ada)

### ✅ 6. Testing Response & Error Handling
- [ ] Approve absensi yang sudah disetujui → error message
- [ ] Reject absensi yang sudah ditolak → error message
- [ ] Akses dengan user non-admin → 403 Forbidden
- [ ] AJAX timeout → error message yang friendly
- [ ] Server error 500 → error message yang friendly

## 🛠️ Troubleshooting Common Issues

### Problem: Halaman tidak muncul
**Check**:
```bash
# Cek routing terdaftar
php bin/console debug:router | grep validasi

# Cek cache
php bin/console cache:clear
```

### Problem: Data tidak muncul
**Check**:
```sql
-- Cek ada data absensi pending
SELECT COUNT(*) FROM absensi WHERE statusValidasi = 'pending';

-- Cek struktur table sesuai entity
DESCRIBE absensi;
```

### Problem: AJAX error 500
**Check**:
```bash
# Cek error log
tail -f var/log/dev.log

# Cek permission folder upload
ls -la public/uploads/absensi/
```

### Problem: Foto tidak muncul
**Check**:
```bash
# Pastikan folder exists dan writable
mkdir -p public/uploads/absensi
chmod 755 public/uploads/absensi

# Cek sample foto ada
ls -la public/uploads/absensi/
```

## 🎯 Test Data Sample

### Create Test Data
```sql
-- Insert sample absensi yang perlu validasi
INSERT INTO absensi (
    pegawai_id,
    tanggal,
    waktuAbsensi,
    status,
    statusValidasi,
    fotoPath,
    latitude,
    longitude,
    keterangan,
    createdAt
) VALUES
(1, '2024-01-15', '2024-01-15 08:15:00', 'hadir', 'pending', 'sample1.jpg', '-6.200000', '106.816666', 'Terlambat 15 menit', NOW()),
(2, '2024-01-15', '2024-01-15 08:30:00', 'hadir', 'pending', 'sample2.jpg', '-6.201000', '106.817000', 'Absen normal', NOW()),
(3, '2024-01-15', '2024-01-15 12:00:00', 'izin', 'pending', NULL, NULL, NULL, 'Izin sakit', NOW());
```

## 🔍 Manual Testing Scenarios

### Scenario 1: Happy Path - Approve
1. Login as admin
2. Go to `/admin/validasi-absen`
3. See 3 pending absensi in table
4. Click approve on first absensi
5. Enter keterangan: "Disetujui, lokasi sesuai"
6. Submit → Success message appears
7. Refresh → absensi status changed to "disetujui"
8. Check statistics card updated

### Scenario 2: Happy Path - Reject
1. Continue from scenario 1
2. Click reject on second absensi
3. Enter keterangan: "Ditolak, lokasi tidak sesuai"
4. Submit → Success message appears
5. Status changed to "ditolak"
6. Check statistics updated

### Scenario 3: Bulk Operations
1. Continue from scenario 2
2. Check remaining pending absensi
3. Click "Setujui Terpilih"
4. Confirm → Success message
5. All selected items approved
6. Statistics updated correctly

### Scenario 4: Error Cases
1. Try to approve already approved absensi → Error message
2. Try to reject without keterangan → Error message
3. Access as non-admin user → 403 error
4. Submit with very long keterangan (>1000 chars) → Validation error

## 📊 Performance Testing

### Load Testing
```bash
# Test with many records
# Insert 1000 sample absensi
for i in {1..1000}; do
    php bin/console app:create-sample-absensi
done

# Check page load time with filters
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost/admin/validasi-absen?status=pending"
```

### Memory Testing
```bash
# Monitor memory usage
php -d memory_limit=128M bin/console debug:container | grep memory
```

## ✅ Test Completion Checklist

Setelah semua test berhasil:
- [ ] Dokumentasi update sesuai hasil testing
- [ ] Bug yang ditemukan sudah diperbaiki
- [ ] Performance acceptable (< 2 detik load time)
- [ ] Error handling sudah proper
- [ ] User experience smooth dan intuitive
- [ ] Security checks passed (authorization, input validation)
- [ ] Mobile responsive (optional)

---
**📅 Testing Date**: ___________
**👨‍💻 Tester**: ___________
**✅ Status**: ___________
**📝 Notes**: ___________