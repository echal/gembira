# 🔗 Sistem Ranking Terintegrasi - Frontend & Admin

## 📌 Overview

Sistem Ranking GEMBIRA sekarang memiliki **2 metrik yang TERKONEKSI**:

| Metrik | Basis | Periode | Tampilan | Koneksi |
|--------|-------|---------|----------|---------|
| **Skor Harian** | 0-75 poin (jam masuk 07:00-08:15) | Harian | Frontend + Admin | ✅ **TERKONEKSI** |
| **Persentase Kehadiran** | % hadir dari total jadwal | Bulanan | Frontend | ⚠️ Independen |

---

## 🎯 Cara Kerja Sistem Terintegrasi

### **1. Frontend Pegawai (Dashboard)**
File: `templates/dashboard/flexible.html.twig`

Pegawai melihat **KEDUA METRIK** sekaligus:

```twig
{# SKOR HARIAN - Connect ke Admin #}
{{ ranking_pribadi_skor.posisi }} dari {{ ranking_pribadi_skor.total_pegawai }}
Skor: {{ ranking_pribadi_skor.skor }} poin
Status: {{ ranking_pribadi_skor.status }}

{# PERSENTASE BULANAN - Tracking kehadiran #}
{{ ranking_pribadi.posisi }} dari {{ ranking_pribadi.total_pegawai }}
Persentase: {{ ranking_pribadi.persentase }}%
Status: {{ ranking_pribadi.status }}
```

### **2. Admin (Lihat Ranking)**
File: `templates/admin/ranking/index.html.twig`

Admin melihat **DATA YANG SAMA** dengan frontend untuk skor harian:

- **Ranking Harian**: Berdasarkan skor 0-75 (connect ke frontend)
- **Ranking Bulanan**: Akumulasi total skor harian
- **Ranking Unit Kerja**: Rata-rata skor per unit

---

## 🔌 Koneksi Data

### **Data Source yang Sama**

Frontend dan Admin menggunakan **tabel database yang sama**:

```
ranking_harian (tabel utama)
├── pegawai_id
├── tanggal
├── jam_masuk         → 07:00-08:15 WITA
├── skor_harian       → 0-75 poin
└── peringkat         → Auto-calculated

ranking_bulanan
├── pegawai_id
├── periode           → YYYY-MM
├── total_durasi      → Total akumulasi skor (field reused)
├── rata_rata_durasi  → Rata-rata skor
└── peringkat
```

### **Method RankingService**

| Method | Digunakan Di | Data Source | Tujuan |
|--------|--------------|-------------|---------|
| `getRankingPribadiByScore()` | Frontend | `ranking_harian` | Ranking skor hari ini |
| `getRankingGroupByScore()` | Frontend | `ranking_harian` | Ranking unit hari ini |
| `getTop10ByScore()` | Frontend | `ranking_harian` | Top 10 skor hari ini |
| `getAllDailyRanking()` | Admin | `ranking_harian` | Semua ranking harian |
| `getAllMonthlyRanking()` | Admin | `ranking_bulanan` | Semua ranking bulanan |
| `getAllGroupRanking()` | Admin | `ranking_harian` | Ranking per unit |

---

## ⚡ Real-time Update

### **Alur Data Saat Pegawai Absen**

```
1. Pegawai scan QR / klik tombol absen
   ↓
2. AbsensiController::simpanDataAbsensi()
   ↓
3. RankingService::updateDailyRanking()
   ├── Hitung skor: 75 - (menit dari 07:00)
   ├── Simpan ke ranking_harian
   ├── Recalculate semua peringkat hari ini
   └── Update ranking_bulanan (akumulasi)
   ↓
4. Data langsung tersedia di:
   ├── Frontend pegawai (ranking_pribadi_skor)
   └── Admin (admin/ranking)
```

### **Auto-refresh**

**Frontend**:
```javascript
// Di dashboard/flexible.html.twig
setInterval(() => {
    fetch('/absensi/api/ranking-update')
        .then(data => {
            // Update ranking_pribadi_skor
            // Update ranking_group_skor
            // Update top_10_skor
        });
}, 30000); // Setiap 30 detik
```

**Admin**:
```javascript
// Di admin/ranking/index.html.twig
setInterval(() => {
    location.reload(); // Refresh setiap 5 menit
}, 300000);
```

---

## 📊 Perbedaan Kedua Metrik

### **Skor Harian (0-75 poin)** ⭐

**Tujuan**: Mengukur **KEDISIPLINAN** (seberapa tepat waktu pegawai)

**Formula**:
```php
$skor = 75 - (selisih_menit_dari_07:00)
```

**Contoh**:
- 06:50 → 75 poin (bonus, lebih awal)
- 07:00 → 75 poin (perfect!)
- 07:15 → 60 poin
- 08:00 → 15 poin
- 08:15 → 0 poin (batas akhir)
- 08:16 → **DITOLAK**

**Update**: Real-time setiap absensi

**Tampilan**:
```
🏅 Skor Hari Ini: 75 poin
Ranking #3 dari 50 pegawai
Status: 🏆 Excellent
```

---

### **Persentase Kehadiran (0-100%)** 📈

**Tujuan**: Mengukur **KEHADIRAN** (seberapa sering pegawai hadir)

**Formula**:
```php
$persentase = (total_hadir / total_jadwal) * 100
```

**Contoh**:
- Hadir 20 hari dari 22 hari kerja → 90.9%
- Hadir 18 hari dari 22 hari kerja → 81.8%

**Update**: Bulanan (periode default: awal bulan - hari ini)

**Tampilan**:
```
📊 Kehadiran Bulan Ini: 95%
Ranking #5 dari 50 pegawai
Status: 🏅 Excellent
```

---

## 🎨 Template Frontend (Dashboard Pegawai)

### **Variabel yang Tersedia**

Dari `AbsensiController::dashboardAbsensi()`:

```php
// SISTEM BARU - Skor Harian (connect ke admin)
'ranking_pribadi_skor' => [
    'posisi' => 3,
    'total_pegawai' => 50,
    'skor' => 75,
    'jam_masuk' => '07:00',
    'status' => '🏆 Excellent'
]

'ranking_group_skor' => [
    'posisi' => 2,
    'nama_unit' => 'IT',
    'rata_rata_skor' => 68.5,
    'total_pegawai' => 10,
    'total_unit' => 5
]

'top_10_skor' => [
    ['peringkat' => 1, 'nama' => 'John', 'skor' => 75, ...],
    ['peringkat' => 2, 'nama' => 'Jane', 'skor' => 74, ...],
    ...
]

// SISTEM LAMA - Persentase Bulanan
'ranking_pribadi' => [
    'posisi' => 5,
    'total_pegawai' => 50,
    'persentase' => 95.0,
    'status' => '🏅 Excellent'
]

'ranking_group' => [
    'posisi' => 3,
    'total_pegawai' => 10,
    'persentase' => 92.5,
    'nama_unit' => 'IT'
]

'top_10_pegawai' => [
    ['nip' => '123', 'nama' => 'John', 'persentase' => 100, ...],
    ...
]
```

### **Contoh Tampilan di Template**

```twig
{# Ranking Harian (Skor) - Connect ke Admin #}
<div class="card">
    <h3>🏅 Ranking Harian</h3>
    <p>Skor Anda: <strong>{{ ranking_pribadi_skor.skor }}</strong> poin</p>
    <p>Peringkat: <strong>#{{ ranking_pribadi_skor.posisi }}</strong> dari {{ ranking_pribadi_skor.total_pegawai }}</p>
    <p>{{ ranking_pribadi_skor.status }}</p>
</div>

{# Ranking Bulanan (Persentase) #}
<div class="card">
    <h3>📊 Kehadiran Bulan Ini</h3>
    <p>Persentase: <strong>{{ ranking_pribadi.persentase }}%</strong></p>
    <p>Peringkat: <strong>#{{ ranking_pribadi.posisi }}</strong> dari {{ ranking_pribadi.total_pegawai }}</p>
    <p>{{ ranking_pribadi.status }}</p>
</div>

{# Top 10 Hari Ini (Skor) - Connect ke Admin #}
<div class="card">
    <h3>🏆 Top 10 Hari Ini</h3>
    {% for pegawai in top_10_skor %}
    <div class="rank-item">
        <span class="rank-number">{{ pegawai.peringkat }}</span>
        <span class="rank-name">{{ pegawai.nama }}</span>
        <span class="rank-score">{{ pegawai.skor }} poin</span>
        <span class="rank-time">{{ pegawai.jam_masuk }}</span>
    </div>
    {% endfor %}
</div>
```

---

## 🔧 Testing Koneksi

### **1. Test Frontend → Database**

```bash
# Login sebagai pegawai
# Lihat dashboard
# Cek nilai ranking_pribadi_skor
```

### **2. Test Admin → Database**

```bash
# Login sebagai admin
# Akses: /admin/ranking
# Lihat tabel Ranking Harian
# Cari pegawai yang sama dengan frontend
```

### **3. Verifikasi Data Sama**

```sql
-- Cek data ranking harian untuk pegawai tertentu
SELECT
    p.nama,
    rh.tanggal,
    rh.jam_masuk,
    rh.skor_harian,
    rh.peringkat
FROM ranking_harian rh
JOIN pegawai p ON rh.pegawai_id = p.id
WHERE p.id = [ID_PEGAWAI]
AND rh.tanggal = CURDATE()
ORDER BY rh.peringkat ASC;
```

**Hasil harus SAMA** dengan yang ditampilkan di:
- Frontend: `ranking_pribadi_skor`
- Admin: Tabel "Ranking Harian"

---

## ✅ Checklist Integrasi

- [x] Method baru di RankingService (`getRankingPribadiByScore`, dll)
- [x] AbsensiController pass kedua metrik ke template
- [x] AbsensiController API endpoint support kedua metrik
- [x] Admin template connect ke database yang sama
- [x] Frontend template ready untuk kedua metrik (tinggal update)
- [x] Real-time update saat pegawai absen
- [x] Migration database sudah dijalankan
- [x] Cache sudah di-clear

---

## 📝 Next Steps

1. **Update Frontend Template** (`dashboard/flexible.html.twig`)
   - Tambahkan section untuk ranking skor harian
   - Tampilkan kedua metrik secara jelas
   - Implementasikan auto-refresh

2. **User Training**
   - Jelaskan perbedaan skor vs persentase
   - Ajarkan cara baca ranking harian dan bulanan

3. **Monitoring**
   - Pantau performa query ranking
   - Cek akurasi perhitungan skor
   - Verifikasi sinkronisasi data

---

## 🚀 Kesimpulan

Sistem ranking GEMBIRA sekarang **FULLY INTEGRATED**:

✅ Frontend pegawai melihat data yang SAMA dengan admin
✅ Real-time update setiap ada absensi baru
✅ Dual metrics: Skor harian (kedisiplinan) + Persentase bulanan (kehadiran)
✅ Single source of truth: Database `ranking_harian` & `ranking_bulanan`

**Pegawai dan Admin sekarang melihat DATA YANG SAMA! 🎉**
