# Dokumentasi Perbaikan Perhitungan Jadwal Absensi PDF

## Gambaran Masalah

**Masalah Sebelumnya:**
- Kolom "Total Absen Apel Pagi" di laporan PDF menampilkan total hari kerja (18 hari)
- Kolom "Total Absen Ibadah Pagi" juga menampilkan total hari kerja (18 hari)
- Padahal seharusnya Apel Pagi hanya hari Senin, Ibadah Pagi hanya Selasa-Kamis

**Yang Diharapkan:**
- Apel Pagi → Total berdasarkan jumlah hari Senin dalam bulan (contoh September: 5 hari)
- Ibadah Pagi → Total berdasarkan jumlah hari Selasa-Kamis dalam bulan (contoh September: 13 hari)

## Perubahan Yang Dilakukan

### 1. Perbaikan Method `getStatistikByJadwalAbsensi()`

**File:** `src/Controller/AdminLaporanBulananController.php`
**Baris:** 1049-1162

**Perubahan Utama:**
```php
// SEBELUM (Salah):
$data['apel_pagi_total'] = $totalHariKerja;        // 18 hari
$data['ibadah_pagi_total'] = $totalHariKerja;      // 18 hari
$data['total_jadwal'] = $totalHariKerja * 2;       // 36 jadwal

// SETELAH (Benar):
$totalHariSenin = $this->hitungHariTertentuDalamBulan($tanggalAwal, $tanggalAkhir, [1]);           // 5 hari
$totalHariSelasaKamis = $this->hitungHariTertentuDalamBulan($tanggalAwal, $tanggalAkhir, [2,3,4]); // 13 hari

$data['apel_pagi_total'] = $totalHariSenin;         // 5 hari
$data['ibadah_pagi_total'] = $totalHariSelasaKamis; // 13 hari
$data['total_jadwal'] = $totalHariSenin + $totalHariSelasaKamis; // 18 jadwal
```

### 2. Fungsi Helper Baru: `hitungHariTertentuDalamBulan()`

**File:** `src/Controller/AdminLaporanBulananController.php`
**Baris:** 994-1014

**Fungsi Baru:**
```php
/**
 * FUNGSI HELPER BARU: Hitung jumlah hari tertentu dalam rentang bulan
 *
 * @param \DateTime $tanggalAwal Tanggal mulai periode (awal bulan)
 * @param \DateTime $tanggalAkhir Tanggal akhir periode (akhir bulan)
 * @param array $daftarHari Array nomor hari (1=Senin, 2=Selasa, dst)
 * @return int Jumlah hari yang cocok dalam periode
 */
private function hitungHariTertentuDalamBulan(\DateTime $tanggalAwal, \DateTime $tanggalAkhir, array $daftarHari): int
{
    $totalHari = 0;
    $tanggalIterator = clone $tanggalAwal;

    while ($tanggalIterator <= $tanggalAkhir) {
        $nomorHariSaatIni = (int)$tanggalIterator->format('N');

        if (in_array($nomorHariSaatIni, $daftarHari)) {
            $totalHari++;
        }

        $tanggalIterator->modify('+1 day');
    }

    return $totalHari;
}
```

**Cara Menggunakan:**
- `hitungHariTertentuDalamBulan($awal, $akhir, [1])` → Hitung semua hari Senin
- `hitungHariTertentuDalamBulan($awal, $akhir, [2,3,4])` → Hitung hari Selasa-Kamis

## Hasil Verifikasi

### Test September 2025:
- **Hari Senin:** 1, 8, 15, 22, 29 = **5 hari** ✅
- **Hari Selasa:** 2, 9, 16, 23, 30 = 5 hari
- **Hari Rabu:** 3, 10, 17, 24 = 4 hari
- **Hari Kamis:** 4, 11, 18, 25 = 4 hari
- **Total Selasa-Kamis:** 5 + 4 + 4 = **13 hari** ✅

### Hasil Laporan PDF Sekarang:
```
Kolom Apel Pagi → Total: 5, Hadir: [sesuai data real]
Kolom Ibadah Pagi → Total: 13, Hadir: [sesuai data real]
Total Alpha → Dihitung dari (5 + 13) - Total Hadir
Persentase → (Total Hadir / 18) × 100%
```

## Dampak Perubahan

### ✅ Yang Diperbaiki:
1. **Akurasi Perhitungan:** Total jadwal sekarang sesuai dengan hari kerja sebenarnya
2. **Konsistensi Data:** Laporan PDF menampilkan data yang benar
3. **Fleksibilitas:** Fungsi helper dapat digunakan untuk jadwal hari lain
4. **Dokumentasi:** Kode dilengkapi komentar bahasa Indonesia

### 🔄 Yang Tidak Berubah:
1. **UI Template:** Template PDF tetap sama, hanya data yang berubah
2. **Logika Kehadiran:** Cara menghitung hadir/terlambat tetap sama
3. **Fitur Export:** CSV dan PDF tetap berfungsi normal
4. **Permission:** Kontrol akses admin tetap sama

## Cara Testing

1. **Akses Menu:** Admin → Laporan Bulanan
2. **Pilih Periode:** September 2025 (atau bulan lain)
3. **Export PDF:** Klik tombol "Export PDF"
4. **Verifikasi:**
   - Kolom "Total Absen Apel Pagi" = 5 (bukan 18)
   - Kolom "Total Absen Ibadah Pagi" = 13 (bukan 18)
   - Total jadwal = 18 (5 + 13)

## Catatan Penting

### 🎯 Jadwal Yang Didukung:
- **Apel Pagi:** Hanya hari Senin
- **Ibadah Pagi:** Hanya hari Selasa, Rabu, Kamis

### ⚠️ Persyaratan:
- Jadwal "Apel Pagi" dan "Ibadah Pagi" harus ada di database
- Jadwal harus memiliki status `isAktif = true`
- Data absensi harus terhubung dengan `konfigurasiJadwal`

### 🔧 Pemeliharaan:
- Jika ada jadwal baru, perlu penyesuaian di method `getStatistikByJadwalAbsensi()`
- Fungsi helper dapat digunakan kembali untuk jadwal hari lain
- Debug log tersedia untuk troubleshooting

---

**Dibuat oleh:** System Administrator Gembira
**Tanggal:** 23 September 2025
**Status:** ✅ Implementasi Selesai dan Terverifikasi