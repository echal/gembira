# 📋 Dokumentasi Halaman Admin - Validasi Absensi

## 🎯 Tujuan Fitur
Halaman **Validasi Absensi** adalah fitur khusus untuk admin yang memungkinkan verifikasi manual terhadap absensi pegawai yang memerlukan persetujuan ekstra. Fitur ini berguna untuk:

- ✅ Memvalidasi absensi pada jadwal-jadwal penting
- 🔍 Verifikasi foto selfie dan lokasi GPS
- 📊 Kontrol kualitas data absensi
- 🎯 Meningkatkan akurasi laporan kehadiran

## 🗂️ Struktur File

### 1. Controller Utama
**File**: `src/Controller/AdminValidasiAbsenController.php`

```
📁 AdminValidasiAbsenController.php
├── index()              → Halaman utama dengan statistik dan daftar
├── approveAbsensi()     → API untuk menyetujui absensi
├── rejectAbsensi()      → API untuk menolak absensi
├── bulkAction()         → API untuk aksi massal (approve/reject)
├── detailAbsensi()      → API untuk detail lengkap absensi
└── Helper Methods:
    ├── hitungStatistikValidasi()    → Hitung data dashboard
    ├── ambilDaftarAbsensiDenganFilter() → Query absensi dengan filter
    └── logAktivitasAdmin()          → Log aktivitas validasi
```

### 2. Template Utama
**File**: `templates/admin/validasi_absen.html.twig`

```
📁 validasi_absen.html.twig
├── 📊 Dashboard Statistik (4 kartu):
│   ├── Pending (menunggu validasi)
│   ├── Approved Today (disetujui hari ini)
│   ├── Rejected Today (ditolak hari ini)
│   └── Approval Rate (tingkat persetujuan %)
├── 🔍 Form Filter:
│   ├── Status Validasi
│   ├── Tanggal Mulai
│   └── Tanggal Akhir
├── 📋 Tabel Data Absensi
├── 🖼️ Modal Preview Foto
└── 📝 Modal Input Keterangan
```

### 3. Entity Terkait
**File**: `src/Entity/Absensi.php`

**Field penting untuk validasi**:
```php
// Status validasi (pending/disetujui/ditolak)
private ?string $statusValidasi = 'pending';

// Admin yang memvalidasi
private ?Admin $validatedBy = null;

// Tanggal validasi
private ?\DateTimeInterface $tanggalValidasi = null;

// Keterangan validasi (alasan approve/reject)
private ?string $catatanAdmin = null;

// Data GPS untuk verifikasi lokasi
private ?string $latitude = null;
private ?string $longitude = null;

// Foto untuk verifikasi
private ?string $fotoPath = null;
```

## 🚀 Cara Kerja Sistem

### 1. Flow Validasi Normal
```
1. Pegawai absen pada jadwal yang perlu validasi
2. Absensi tersimpan dengan statusValidasi = 'pending'
3. Admin masuk ke halaman validasi
4. Admin review foto, lokasi, dan waktu
5. Admin klik approve/reject + isi keterangan
6. Status berubah menjadi 'disetujui'/'ditolak'
7. Data tersimpan dengan info validator dan waktu
```

### 2. Flow Bulk Action
```
1. Admin pilih multiple absensi (checkbox)
2. Admin klik "Setujui Terpilih" atau "Tolak Terpilih"
3. Konfirmasi muncul dengan jumlah yang dipilih
4. System proses semua absensi sekaligus
5. Response menampilkan berapa yang berhasil/gagal
```

## 🎨 Fitur-Fitur Dashboard

### 📊 Statistik Kartu
```javascript
// Kartu 1: Menunggu Validasi (Warna Ungu)
stats.pending → Jumlah absensi dengan status 'pending'

// Kartu 2: Disetujui Hari Ini (Warna Hijau)
stats.approved_today → Approved hari ini (berdasarkan validatedAt)

// Kartu 3: Ditolak Hari Ini (Warna Merah)
stats.rejected_today → Rejected hari ini (berdasarkan validatedAt)

// Kartu 4: Tingkat Persetujuan (Warna Biru)
stats.approval_rate → Persentase approve vs total validated
```

### 🔍 Filter dan Pencarian
- **Status**: Pending, Disetujui, Ditolak, Semua
- **Tanggal**: Range dari tanggal mulai sampai akhir
- **Reset**: Kembali ke view default (pending hari ini)

### 📋 Tabel Data
Kolom yang ditampilkan:
- ☑️ Checkbox untuk bulk selection
- 👤 Pegawai (nama + NIP)
- 📅 Tanggal absensi
- ⏰ Waktu absensi
- 📊 Status kehadiran (hadir/izin/sakit)
- 🖼️ Preview foto (klik untuk modal)
- 🗺️ Lokasi GPS (koordinat)
- 🔖 Status validasi (badge berwarna)
- ⚡ Tombol aksi

## 🛠️ API Endpoints

### 1. Approve Individual
```http
POST /admin/validasi-absen/approve/{id}
Content-Type: application/json

{
    "alasan": "Absensi sesuai dengan jadwal kerja"
}

Response:
{
    "success": true,
    "message": "✅ Absensi [Nama Pegawai] berhasil disetujui!"
}
```

### 2. Reject Individual
```http
POST /admin/validasi-absen/reject/{id}
Content-Type: application/json

{
    "alasan": "Lokasi tidak sesuai dengan area kerja"
}

Response:
{
    "success": true,
    "message": "❌ Absensi [Nama Pegawai] ditolak. Alasan: [alasan]"
}
```

### 3. Bulk Action
```http
POST /admin/validasi-absen/bulk-action
Content-Type: application/json

{
    "action": "approve", // atau "reject"
    "absensi_ids": [1, 2, 3, 4],
    "alasan": "Batch processing by admin" // wajib untuk reject
}

Response:
{
    "success": true,
    "message": "📊 Bulk approval selesai: 3 berhasil, 1 gagal",
    "berhasil": 3,
    "gagal": 1
}
```

### 4. Detail Absensi
```http
GET /admin/validasi-absen/detail/{id}

Response:
{
    "success": true,
    "data": {
        "pegawai": {
            "nama": "John Doe",
            "nip": "123456",
            "unit_kerja": "IT",
            "jabatan": "Staff"
        },
        "jadwal": {
            "nama": "Shift Pagi",
            "jam_mulai": "08:00",
            "jam_selesai": "17:00",
            "emoji": "🌅"
        },
        "absensi": {
            "waktu": "2024-01-15 08:15:00",
            "status_validasi": "pending",
            "foto_path": "20240115_081500_john.jpg",
            "latitude": "-6.200000",
            "longitude": "106.816666",
            "keterangan": "Terlambat karena macet"
        }
    }
}
```

## 🎭 JavaScript Functions

### Fungsi Utama
```javascript
// Approve individual dengan modal keterangan
function approveAbsensi(id)

// Reject individual dengan modal keterangan
function rejectAbsensi(id)

// Submit keterangan ke server
$('#submitKeterangan').click()

// Bulk approve dengan konfirmasi
$('#btnBulkApprove').click()

// Bulk reject dengan konfirmasi
$('#btnBulkReject').click()

// Lihat detail lengkap dengan modal SweetAlert
function viewDetail(id)

// Preview foto dalam modal Bootstrap
$('.foto-preview').click()

// Select all checkbox
$('#selectAll').change()

// Helper functions
function getStatusColor(status)    // Warna badge
function getStatusText(status)     // Text status
function showKeterangan(text)      // Modal keterangan existing
```

## 🔧 Troubleshooting

### ❌ Error: "Absensi tidak ditemukan"
**Penyebab**: ID absensi tidak valid atau sudah dihapus
**Solusi**: Refresh halaman dan coba lagi

### ❌ Error: "Hanya absensi pending yang dapat diproses"
**Penyebab**: Absensi sudah di-approve/reject sebelumnya
**Solusi**: Filter ulang untuk melihat status terbaru

### ❌ Error: "Alasan penolakan harus diisi"
**Penyebab**: Reject tanpa mengisi keterangan
**Solusi**: Isi keterangan sebelum reject

### ❌ Error AJAX "500 Internal Server Error"
**Penyebab**:
- Database connection issue
- Validation error di entity
- Missing field di database

**Solusi**:
1. Cek log error di `var/log/`
2. Pastikan database fields sesuai entity
3. Cek permission file uploads

### ❌ Foto tidak muncul
**Penyebab**: Path foto salah atau file tidak ada
**Solusi**:
1. Cek folder `public/uploads/absensi/`
2. Pastikan web server bisa akses folder tersebut
3. Cek setting permission folder (755)

## 🔐 Security & Permissions

### Role-Based Access
```php
#[IsGranted('ROLE_ADMIN')]
```
Hanya user dengan role ADMIN yang bisa akses halaman ini.

### CSRF Protection
Tidak menggunakan CSRF token untuk API karena sudah menggunakan:
- JSON payload
- AJAX dengan proper headers
- Role-based authorization

### Input Validation
- ID absensi: numeric, exists in database
- Status validasi: enum (pending/disetujui/ditolak)
- Alasan: max 1000 karakter, XSS protection

## 🚀 Performance Optimization

### Database Query
- ✅ Menggunakan Query Builder dengan proper joins
- ✅ Index pada field: statusValidasi, validatedAt, waktuAbsensi
- ✅ Limit 50 records per load untuk performance
- ✅ Pagination bisa ditambahkan jika diperlukan

### Frontend
- ✅ DataTables untuk sorting/searching client-side
- ✅ AJAX untuk real-time updates tanpa refresh
- ✅ Lazy loading untuk foto preview
- ✅ Bulk operations untuk efficiency

## 📝 Maintenance Notes

### Update Database Schema
Jika menambah field baru di Entity Absensi, jalankan:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Update Permissions
Untuk menambah role baru yang bisa akses validasi:
```php
#[IsGranted('ROLE_ADMIN || ROLE_SUPERVISOR')]
```

### Monitoring & Logs
Semua aktivitas validasi dicatat di:
- PHP error_log untuk debugging
- Bisa ditambahkan database logging di method `logAktivitasAdmin()`

---
**📅 Last Updated**: 2024-01-15
**👨‍💻 Developer**: Indonesian Developer Team
**🔖 Version**: 1.0
**📧 Contact**: admin@gembira.local