# Dokumentasi Sistem Ranking Dinamis - Aplikasi GEMBIRA

## 📋 Daftar Isi

1. [Pendahuluan](#pendahuluan)
2. [Arsitektur Sistem](#arsitektur-sistem)
3. [Database Schema](#database-schema)
4. [Alur Kerja Sistem](#alur-kerja-sistem)
5. [Instalasi & Setup](#instalasi--setup)
6. [Cara Penggunaan](#cara-penggunaan)
7. [API Documentation](#api-documentation)
8. [Maintenance & Troubleshooting](#maintenance--troubleshooting)

---

## 🎯 Pendahuluan

Sistem Ranking Dinamis adalah fitur baru yang memungkinkan perhitungan dan pembaruan ranking pegawai **secara real-time** setiap kali ada absensi baru.

### Fitur Utama

- ✅ **Update Otomatis**: Ranking diperbarui setiap kali pegawai melakukan absensi
- ✅ **Ranking Harian**: Menampilkan peringkat pegawai per hari berdasarkan waktu kedatangan
- ✅ **Ranking Bulanan**: Akumulasi ranking harian selama satu bulan penuh
- ✅ **Ranking Unit Kerja**: Peringkat dalam group/unit kerja masing-masing
- ✅ **Top 10 Global**: Menampilkan 10 pegawai terbaik secara keseluruhan
- ✅ **Reset Otomatis**: Command untuk reset ranking setiap awal bulan
- ✅ **History Data**: Data ranking lama tetap tersimpan untuk analisis

### Perubahan dari Sistem Lama

| Aspek | Sistem Lama | Sistem Baru |
|-------|-------------|-------------|
| **Update** | Manual/Static | Real-time/Dinamis |
| **Perhitungan** | Persentase kehadiran | Durasi keterlambatan |
| **Frekuensi** | Sekali per bulan | Setiap absensi baru |
| **Data** | Agregat saja | Harian + Bulanan |
| **History** | Tidak ada | Tersimpan per periode |

---

## 🏗️ Arsitektur Sistem

### Komponen Utama

```
┌─────────────────────────────────────────────────────────┐
│                   ABSENSI CONTROLLER                     │
│  (Menangani proses absensi pegawai)                      │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│                   RANKING SERVICE                        │
│  • updateDailyRanking()                                  │
│  • calculateMonthlyAccumulation()                        │
│  • resetMonthlyRanking()                                 │
│  • getRankingPribadi()                                   │
│  • getRankingGroup()                                     │
│  • getTop10()                                            │
└─────┬───────────────────────┬──────────────┬────────────┘
      │                       │              │
      ▼                       ▼              ▼
┌──────────────┐    ┌──────────────┐  ┌──────────────┐
│ AbsensiDurasi│    │RankingHarian │  │RankingBulanan│
│  Repository  │    │  Repository  │  │  Repository  │
└──────┬───────┘    └──────┬───────┘  └──────┬───────┘
       │                   │                  │
       ▼                   ▼                  ▼
┌──────────────────────────────────────────────────────────┐
│                    DATABASE TABLES                        │
│  • absensi_durasi                                         │
│  • ranking_harian                                         │
│  • ranking_bulanan                                        │
└───────────────────────────────────────────────────────────┘
```

### Layer Architecture

1. **Controller Layer** ([AbsensiController.php](../src/Controller/AbsensiController.php))
   - Menangani request HTTP
   - Memanggil RankingService setelah absensi tersimpan

2. **Service Layer** ([RankingService.php](../src/Service/RankingService.php))
   - Business logic untuk perhitungan ranking
   - Orchestration antara berbagai repository

3. **Repository Layer**
   - [AbsensiDurasiRepository.php](../src/Repository/AbsensiDurasiRepository.php)
   - [RankingHarianRepository.php](../src/Repository/RankingHarianRepository.php)
   - [RankingBulananRepository.php](../src/Repository/RankingBulananRepository.php)

4. **Entity Layer**
   - [AbsensiDurasi.php](../src/Entity/AbsensiDurasi.php)
   - [RankingHarian.php](../src/Entity/RankingHarian.php)
   - [RankingBulanan.php](../src/Entity/RankingBulanan.php)

---

## 📊 Database Schema

### Tabel 1: `absensi_durasi`

Menyimpan durasi absensi harian setiap pegawai.

```sql
CREATE TABLE absensi_durasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NOT NULL,
    durasi_menit INT NOT NULL,  -- Positif = terlambat, Negatif = lebih awal
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
    INDEX idx_tanggal (tanggal),
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE
);
```

**Keterangan:**
- `durasi_menit`: Selisih antara jam masuk actual dengan jam ideal (07:00)
  - Nilai positif = Terlambat (contoh: 30 = terlambat 30 menit)
  - Nilai negatif = Lebih awal (contoh: -15 = 15 menit lebih awal)

### Tabel 2: `ranking_harian`

Menyimpan ranking pegawai setiap hari.

```sql
CREATE TABLE ranking_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    tanggal DATE NOT NULL,
    total_durasi INT NOT NULL,
    peringkat INT NOT NULL,  -- 1 = terbaik
    updated_at DATETIME NOT NULL,

    INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
    INDEX idx_tanggal_peringkat (tanggal, peringkat),
    UNIQUE KEY unique_pegawai_tanggal (pegawai_id, tanggal),
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE
);
```

**Keterangan:**
- `peringkat`: 1 = pegawai dengan durasi terkecil (paling awal datang)
- Diupdate setiap kali ada absensi baru pada hari tersebut

### Tabel 3: `ranking_bulanan`

Menyimpan ranking pegawai setiap bulan (akumulasi dari ranking harian).

```sql
CREATE TABLE ranking_bulanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    periode VARCHAR(7) NOT NULL,  -- Format: YYYY-MM
    total_durasi INT NOT NULL,
    rata_rata_durasi FLOAT DEFAULT NULL,
    peringkat INT NOT NULL,  -- 1 = terbaik
    updated_at DATETIME NOT NULL,

    INDEX idx_pegawai_periode (pegawai_id, periode),
    INDEX idx_periode_peringkat (periode, peringkat),
    UNIQUE KEY unique_pegawai_periode (pegawai_id, periode),
    FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE
);
```

**Keterangan:**
- `periode`: Format YYYY-MM (contoh: "2025-01" untuk Januari 2025)
- `total_durasi`: Total akumulasi durasi selama sebulan
- `rata_rata_durasi`: Rata-rata durasi per hari

---

## ⚙️ Alur Kerja Sistem

### 1. Proses Absensi & Update Ranking

```
[Pegawai Absen]
    ↓
[AbsensiController::simpanDataAbsensi()]
    ↓
[Simpan ke tabel `absensi`]
    ↓
[RankingService::updateDailyRanking()] ← **TRIGGER OTOMATIS**
    ↓
┌───────────────────────────────────────────┐
│ 1. Hitung durasi absensi                  │
│    - Bandingkan dengan jam ideal (07:00)  │
│    - Durasi positif = terlambat           │
│    - Durasi negatif = lebih awal          │
└─────────────┬─────────────────────────────┘
              ↓
┌───────────────────────────────────────────┐
│ 2. Simpan ke `absensi_durasi`             │
│    - pegawai_id, tanggal, jam_masuk       │
│    - durasi_menit                          │
└─────────────┬─────────────────────────────┘
              ↓
┌───────────────────────────────────────────┐
│ 3. Recalculate ranking harian hari ini    │
│    - Ambil semua absensi hari ini          │
│    - Urutkan berdasarkan durasi_menit ASC │
│    - Update peringkat di `ranking_harian` │
└─────────────┬─────────────────────────────┘
              ↓
┌───────────────────────────────────────────┐
│ 4. Update ranking bulanan                 │
│    - Agregasi dari `ranking_harian`       │
│    - Update `ranking_bulanan`             │
└───────────────────────────────────────────┘
```

### 2. Reset Ranking Bulanan (Awal Bulan)

```
[Tanggal 1 - Pukul 00:00]
    ↓
[Cron Job/Task Scheduler]
    ↓
[php bin/console app:reset-ranking]
    ↓
[ResetRankingCommand::execute()]
    ↓
[RankingService::resetMonthlyRanking()]
    ↓
┌───────────────────────────────────────────┐
│ 1. Hitung ulang ranking bulanan           │
│    - Agregasi semua ranking_harian        │
│    - Buat/update ranking_bulanan          │
│    - Data bulan lalu tetap tersimpan      │
└───────────────────────────────────────────┘
```

---

## 🚀 Instalasi & Setup

### Step 1: Jalankan Migration Database

**Opsi A: Via Symfony Console** (Recommended)

```bash
# Jalankan migration
php bin/console doctrine:migrations:migrate

# atau jika menggunakan migration file khusus
php bin/console doctrine:migrations:execute --up Version20250120000000_AddRankingTables
```

**Opsi B: Via SQL Manual**

```bash
# Buka phpMyAdmin atau MySQL client
# Pilih database gembira
# Jalankan file: migrations/ranking_tables.sql

# Atau via command line:
mysql -u root -p gembira < migrations/ranking_tables.sql
```

### Step 2: Verifikasi Tabel Berhasil Dibuat

```bash
php bin/console doctrine:schema:validate
```

Atau via MySQL:

```sql
SHOW TABLES LIKE '%ranking%';
SHOW TABLES LIKE '%durasi%';
```

### Step 3: Hitung Ranking Awal (Opsional)

Jika sudah ada data absensi sebelumnya, hitung ranking untuk bulan ini:

```bash
php bin/console app:reset-ranking
```

### Step 4: Setup Cron Job untuk Auto-Reset

**Linux/macOS:**

```bash
# Edit crontab
crontab -e

# Tambahkan baris ini (jalankan setiap tanggal 1 pukul 00:00)
0 0 1 * * cd /path/to/gembira && php bin/console app:reset-ranking >> /var/log/ranking-reset.log 2>&1
```

**Windows (Task Scheduler):**

1. Buka **Task Scheduler**
2. Create Basic Task
3. Name: "Reset Ranking Bulanan Gembira"
4. Trigger: **Monthly**, Day **1**, Time **00:00**
5. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\gembira\bin\console app:reset-ranking`
   - Start in: `C:\xampp\htdocs\gembira`

---

## 📖 Cara Penggunaan

### 1. Mengambil Ranking Pribadi Pegawai

```php
// Di Controller atau Service
$rankingService = $this->container->get(RankingService::class);
$pegawai = $this->getUser(); // Pegawai yang sedang login

// Dapatkan ranking pribadi
$rankingPribadi = $rankingService->getRankingPribadi($pegawai);

// Output:
// [
//     'posisi' => 5,
//     'total_pegawai' => 50,
//     'persentase' => 95.5,
//     'status' => '✅ Sangat Baik'
// ]
```

### 2. Mengambil Ranking Group/Unit Kerja

```php
$rankingGroup = $rankingService->getRankingGroup($pegawai);

// Output:
// [
//     'posisi' => 2,
//     'total_pegawai' => 10,
//     'persentase' => 96.0,
//     'nama_unit' => 'Sekretariat'
// ]
```

### 3. Mengambil Top 10 Pegawai

```php
$top10 = $rankingService->getTop10();

// Output:
// [
//     [
//         'nip' => '198501012010011001',
//         'nama' => 'Ahmad Fauzi',
//         'unit_kerja' => 'Sekretariat',
//         'persentase' => 98.5,
//         'status' => '🥇 Terbaik'
//     ],
//     // ... 9 pegawai lainnya
// ]
```

### 4. Update Manual Ranking Setelah Absensi

```php
// Biasanya otomatis dipanggil di AbsensiController
// Tapi bisa dipanggil manual jika perlu

$pegawai = $this->getUser();
$waktuAbsensi = new \DateTime(); // atau waktu tertentu

$result = $rankingService->updateDailyRanking($pegawai, $waktuAbsensi);

// Output:
// [
//     'success' => true,
//     'message' => 'Ranking berhasil diupdate',
//     'durasi_menit' => 15,  // Terlambat 15 menit
//     'peringkat_harian' => 3,
//     'formatted_durasi' => 'Terlambat 15 menit'
// ]
```

### 5. Reset Ranking Bulanan Manual

```bash
# Reset untuk bulan ini
php bin/console app:reset-ranking

# Reset untuk bulan tertentu
php bin/console app:reset-ranking --tahun=2025 --bulan=1
```

---

## 🔌 API Documentation

### API Endpoint: Get Ranking Update

**URL:** `/absensi/api/ranking-update`
**Method:** `GET`
**Auth:** Required (ROLE_USER)

**Response:**

```json
{
  "success": true,
  "ranking_pribadi": {
    "posisi": 5,
    "total_pegawai": 50,
    "persentase": 95.5,
    "status": "✅ Sangat Baik"
  },
  "ranking_group": {
    "posisi": 2,
    "total_pegawai": 10,
    "persentase": 96.0,
    "nama_unit": "Sekretariat"
  },
  "top_10_pegawai": [
    {
      "nip": "198501012010011001",
      "nama": "Ahmad Fauzi",
      "unit_kerja": "Sekretariat",
      "persentase": 98.5,
      "status": "🥇 Terbaik"
    }
    // ... 9 pegawai lainnya
  ],
  "timestamp": "2025-01-20 14:30:00"
}
```

**Usage dalam JavaScript:**

```javascript
// Auto-refresh ranking setiap 5 menit
setInterval(async () => {
    const response = await fetch('/absensi/api/ranking-update');
    const data = await response.json();

    if (data.success) {
        // Update DOM dengan data baru
        updateRankingDisplay(data);
    }
}, 5 * 60 * 1000);
```

---

## 🛠️ Maintenance & Troubleshooting

### Melihat Data Ranking

```sql
-- Lihat ranking harian hari ini
SELECT p.nama, rh.total_durasi, rh.peringkat
FROM ranking_harian rh
JOIN pegawai p ON rh.pegawai_id = p.id
WHERE rh.tanggal = CURDATE()
ORDER BY rh.peringkat ASC;

-- Lihat ranking bulanan bulan ini
SELECT p.nama, rb.total_durasi, rb.rata_rata_durasi, rb.peringkat
FROM ranking_bulanan rb
JOIN pegawai p ON rb.pegawai_id = p.id
WHERE rb.periode = DATE_FORMAT(NOW(), '%Y-%m')
ORDER BY rb.peringkat ASC;
```

### Recalculate Ranking Paksa

Jika terjadi inkonsistensi data:

```bash
# Hitung ulang ranking bulan ini
php bin/console app:reset-ranking

# Hitung ulang bulan tertentu
php bin/console app:reset-ranking --tahun=2024 --bulan=12
```

### Clear Cache Symfony

```bash
php bin/console cache:clear
```

### Troubleshooting Common Issues

#### 1. Ranking tidak update setelah absensi

**Penyebab:** Error dalam RankingService atau database connection

**Solusi:**
```bash
# Cek error log
tail -f var/log/prod.log
# atau lihat error_log PHP

# Recalculate manual
php bin/console app:reset-ranking
```

#### 2. Peringkat tidak sesuai

**Penyebab:** Data durasi salah atau jam ideal berubah

**Solusi:**
```sql
-- Cek data absensi_durasi
SELECT * FROM absensi_durasi WHERE tanggal = CURDATE();

-- Hapus dan recalculate
DELETE FROM ranking_harian WHERE tanggal = CURDATE();
DELETE FROM absensi_durasi WHERE tanggal = CURDATE();

-- Lalu absen ulang atau jalankan recalculate
```

#### 3. Foreign key constraint error

**Penyebab:** Tabel pegawai tidak ditemukan atau structure berbeda

**Solusi:**
```sql
-- Lihat struktur foreign key
SHOW CREATE TABLE absensi_durasi;
SHOW CREATE TABLE ranking_harian;

-- Jika perlu, drop dan recreate tabel
DROP TABLE IF EXISTS ranking_bulanan;
DROP TABLE IF EXISTS ranking_harian;
DROP TABLE IF EXISTS absensi_durasi;

-- Lalu jalankan migration lagi
```

---

## 📞 Support & Contact

Untuk pertanyaan atau masalah terkait sistem ranking, hubungi:

- **Developer**: Tim IT GEMBIRA
- **Email**: it.gembira@example.com
- **Documentation**: [/docs/RANKING_SYSTEM.md](./RANKING_SYSTEM.md)

---

## 📝 Changelog

### Version 2.0.0 (2025-01-20)

- ✨ Sistem ranking dinamis real-time
- ✨ Tabel baru: absensi_durasi, ranking_harian, ranking_bulanan
- ✨ Command reset ranking bulanan
- ✨ API endpoint untuk update ranking
- ✨ Auto-refresh ranking di dashboard
- 📚 Dokumentasi lengkap

### Version 1.0.0 (Sebelumnya)

- ✅ Sistem ranking statis berdasarkan persentase kehadiran

---

**© 2025 Aplikasi GEMBIRA - Gerakan Munajat Bersama Untuk Kinerja**
