# 🔍 Manual Debug Guide - Dashboard Absensi Issue

## Problem: "Jadwal Absensi tidak tersedia untuk hari ini" - Kamera tidak muncul

### Step 1: 🌐 Akses Dashboard dengan Debug Mode

1. **Jalankan Symfony development server:**
   ```bash
   cd C:\xampp\htdocs\gembira
   symfony server:start
   # Atau gunakan php -S localhost:8000 -t public
   ```

2. **Buka browser dan akses:** `http://localhost:8000/`

3. **Login sebagai pegawai** (gunakan data test atau user valid)

4. **Lihat output debug** di halaman web atau di console browser (F12)

### Step 2: 🔍 Analisis Debug Checkpoints

DashboardController sudah dilengkapi 6 debug checkpoint. Perhatikan output:

#### ✅ CHECKPOINT 1: User Authentication
```
🔍 DEBUG 1 - User Info: {
  "user_type": "App\\Entity\\Pegawai",  // Harus Pegawai
  "user_id": 123,                       // ID valid
  "user_name": "Nama Pegawai"           // Nama user
}
```

#### ✅ CHECKPOINT 2: DateTime Analysis  
```
🕒 DEBUG 2 - DateTime Info: {
  "timezone": "Asia/Makassar",
  "current_datetime": "2025-09-01 14:30:00 WITA",
  "day_number_iso": 1,                   // 1=Senin, 2=Selasa, dst
  "day_name_id": "Senin"
}
```

#### ✅ CHECKPOINT 3A: Database Query - All Jadwal
```
📋 DEBUG 3A - All Jadwal in Database: [
  {
    "id": 1,
    "jenis": "apel_pagi", 
    "nama": "Apel Pagi",
    "hari_diizinkan": [1,2,3,4,5],      // Array hari (1-7)
    "jam_mulai": "07:00",
    "jam_selesai": "07:30",
    "is_aktif": true
  }
  // ... jadwal lainnya
]
```

#### ✅ CHECKPOINT 3B: Jadwal untuk Hari Ini
```
📅 DEBUG 3B - Jadwal untuk Hari {1}: [
  {
    "id": 1,
    "nama": "Apel Pagi", 
    "hari_match": true,                  // Apakah hari cocok?
    "is_aktif": true                     // Status aktif?
  }
]
```

#### ✅ CHECKPOINT 4: Card Processing
```
🔄 DEBUG 4A - Processing 3 jadwal...

🎯 DEBUG 4C - Kartu #0: {
  "id": 1,
  "nama": "Apel Pagi",
  "jam_mulai": "07:00:00",
  "jam_selesai": "07:30:00", 
  "hari_diizinkan": [1,2,3,4,5],
  "is_aktif": true,
  "jam_terbuka": false,                 // Waktu absensi terbuka?
  "is_hari_aktif": true,                // Hari + status aktif?
  "route_url": "/absensi/kamera/1", 
  "route_status": "✅ Route OK",
  "final_status": "🟡 WAITING/INACTIVE"  // Status akhir
}
```

#### ✅ CHECKPOINT 5: Final Summary
```
📊 DEBUG 5 - Final Summary: {
  "total_jadwal_found": 3,              // Jadwal ditemukan dari DB
  "total_kartu_created": 3,             // Kartu berhasil dibuat
  "available_count": 0,                 // Berapa yang bisa diklik (hijau)
  "waiting_count": 2,                   // Berapa yang menunggu (kuning)
  "inactive_count": 1                   // Berapa yang nonaktif (abu-abu)
}
```

### Step 3: 🚨 Identifikasi Masalah Berdasarkan Output

#### ❌ Problem A: Tidak ada jadwal di database
```
📋 DEBUG 3A - All Jadwal in Database: []
📅 DEBUG 3B - Jadwal untuk Hari {1}: []
🚨 PROBLEM IDENTIFIED: Tidak ada kartu absensi dibuat!
```

**SOLUSI:** Tambah data jadwal_absensi di database

#### ❌ Problem B: Jadwal ada tapi hari tidak match
```
📅 DEBUG 3B - Jadwal untuk Hari {1}: []  // Empty padahal ada jadwal
```

**SOLUSI:** Periksa kolom `hari_diizinkan` di database, harus berisi array JSON [1,2,3,4,5]

#### ❌ Problem C: Jadwal jam NULL
```
🚫 DEBUG 4B - Jadwal Skip: {
  "status": "❌ SKIPPED - Jam NULL",
  "jam_mulai": "NULL",
  "jam_selesai": "NULL"
}
```

**SOLUSI:** Update jam_mulai dan jam_selesai yang NULL di database

#### ❌ Problem D: Semua kartu menunggu (kuning)
```
📊 DEBUG 5 - Final Summary: {
  "available_count": 0,     // Seharusnya > 0
  "waiting_count": 3        // Semua menunggu
}
```

**SOLUSI:** Periksa jam absensi - kemungkinan belum masuk waktu atau sudah lewat

### Step 4: 🛠️ Quick Fix Commands

#### Fix A: Cek Database Jadwal
```sql
SELECT * FROM jadwal_absensi WHERE is_aktif = 1;
SELECT id, jenis_absensi, hari_diizinkan, jam_mulai, jam_selesai, is_aktif 
FROM jadwal_absensi;
```

#### Fix B: Update Hari yang Salah  
```sql
UPDATE jadwal_absensi 
SET hari_diizinkan = '[1,2,3,4,5]'  -- Senin-Jumat
WHERE hari_diizinkan IS NULL OR hari_diizinkan = '';
```

#### Fix C: Update Jam yang NULL
```sql
UPDATE jadwal_absensi 
SET jam_mulai = '07:00:00', jam_selesai = '07:30:00'
WHERE jam_mulai IS NULL OR jam_selesai IS NULL;
```

### Step 5: 🎯 Troubleshooting Berdasarkan Template

Jika masih muncul "Tidak Ada Absensi Hari Ini" di template:

1. **Cek di browser console:** Lihat debug output dari dump()
2. **Cek server logs:** `tail -f var/log/dev.log` 
3. **Cek network tab:** Apakah ada error AJAX/fetch?

### Step 6: 🧹 Cleanup Debug (Setelah Selesai)

Setelah masalah teridentifikasi dan diperbaiki, hapus semua debug code:

1. Hapus semua `dump()` statements di `DashboardController::index()`
2. Hapus `error_log()` statements  
3. Hapus file `DEBUG_MANUAL.md` ini
4. Hapus `tests/Debug/DashboardDebugTest.php`

### 📞 Support

Jika masih bermasalah:
1. Screenshoot debug output dari browser
2. Copy-paste output dari Step 2 checkpoints
3. Sertakan query database dari Step 4

---
**Generated by Claude Code debugging system**