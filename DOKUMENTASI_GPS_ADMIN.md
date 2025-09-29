# Dokumentasi Fitur GPS dan Detail Absensi Admin

## 📋 Ringkasan Implementasi

Fitur ini menambahkan kemampuan admin untuk:
1. **Melihat lokasi GPS** dari setiap absensi pegawai
2. **Mengakses detail lengkap** absensi dengan informasi teknis untuk maintenance
3. **Memahami sistem** yang digunakan dalam bahasa Indonesia yang mudah dipahami
4. **Tampilan tanggal Indonesia** yang konsisten di seluruh sistem

---

## 🗺️ Fitur GPS Location

### A. Pengambilan GPS dari Perangkat User

**File:** `templates/absensi/form_mobile.html.twig`

```javascript
// Auto-collect GPS location saat user membuka form absensi
getGpsLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                
                // Simpan GPS ke hidden form fields
                document.getElementById('latitude').value = latitude;
                document.getElementById('longitude').value = longitude;
            }
        );
    }
}
```

**Penjelasan untuk Admin:**
- GPS diambil otomatis saat user membuka form absensi
- Koordinat disimpan dalam format: `latitude,longitude` (contoh: `-6.1753924,106.8271528`)
- User harus memberikan izin akses lokasi di browser

### B. Penyimpanan GPS di Database

**File:** `src/Controller/AbsensiController.php`

```php
// Validasi GPS coordinate untuk sistem baru
if ($jadwalId && (empty($latitude) || empty($longitude))) {
    return new JsonResponse([
        'success' => false,
        'message' => '❌ Lokasi GPS diperlukan untuk absensi. Pastikan GPS aktif dan berikan izin akses.'
    ]);
}

// Set GPS location jika tersedia
if ($latitude && $longitude) {
    $absensi->setLokasiAbsensi("{$latitude},{$longitude}");
}
```

**Penjelasan untuk Admin:**
- GPS mandatory untuk sistem jadwal baru (KonfigurasiJadwalAbsensi)
- GPS optional untuk sistem lama (JadwalAbsensi)
- Format penyimpanan: `latitude,longitude` di kolom `lokasi_absensi`

### C. Tampilan GPS di Admin Panel

**File:** `templates/admin/laporan_kehadiran/index.html.twig`

```html
<!-- Kolom GPS di tabel laporan -->
<td class="px-6 py-4 whitespace-nowrap">
    {% if absensi.lokasiAbsensi %}
        {% set coordinates = absensi.lokasiAbsensi|split(',') %}
        {% if coordinates|length == 2 %}
            <button type="button" 
                    class="text-green-600 hover:text-green-800 text-sm font-medium"
                    onclick="showMapModal('{{ coordinates[0]|trim }}', '{{ coordinates[1]|trim }}', '{{ absensi.pegawai.nama }}')"
                    title="Lihat lokasi GPS pada peta">
                🗺️ GPS
            </button>
        {% else %}
            <span class="bg-yellow-100 text-yellow-800 px-2.5 py-0.5 rounded-full text-xs"
                  title="Koordinat GPS format tidak valid">
                ⚠️ Invalid
            </span>
        {% endif %}
    {% else %}
        <span class="bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full text-xs"
              title="Absensi tanpa GPS">
            📍 No GPS
        </span>
    {% endif %}
</td>
```

**Penjelasan untuk Admin:**
- **🗺️ GPS**: Klik untuk lihat lokasi di peta Google Maps
- **⚠️ Invalid**: Format koordinat tidak valid (untuk maintenance)
- **📍 No GPS**: Absensi tanpa data GPS

---

## 👁️ Fitur Detail Absensi

### A. Controller API Detail

**File:** `src/Controller/AdminLaporanKehadiranController.php`

**Route:** `GET /admin/laporan-kehadiran/api/detail/{id}`

```php
/**
 * API untuk mengambil detail absensi
 * Digunakan untuk modal detail pada tabel laporan kehadiran
 * 
 * Menampilkan informasi lengkap absensi dalam bahasa Indonesia yang mudah dipahami
 * oleh admin untuk keperluan maintenance dan validasi data.
 */
#[Route('/api/detail/{id}', name: 'app_admin_laporan_kehadiran_detail', methods: ['GET'])]
public function getAbsensiDetail(int $id): Response
```

**Data yang Dikembalikan:**

1. **Data Pegawai**
   - Nama, NIP, Jabatan, Unit Kerja

2. **Data Absensi**
   - Tanggal (format Indonesia: "Senin, 15 September 2025")
   - Waktu absensi dengan keterangan
   - Status kehadiran dengan warna dan ikon
   - Informasi jadwal (sistem lama/baru)
   - QR Code info
   - Keterangan tambahan

3. **Data GPS dan Lokasi**
   - Status GPS (tersedia/tidak)
   - Koordinat latitude/longitude
   - Link ke Google Maps

4. **Data Teknis (untuk maintenance)**
   - IP Address
   - User Agent
   - System type (sistem lama/baru/manual)
   - Created timestamp

5. **Data Validasi**
   - Status validasi admin
   - Catatan admin
   - Validator dan tanggal validasi

### B. Template Modal Detail

**File:** `templates/admin/laporan_kehadiran/detail_modal.html.twig`

**Fitur Tampilan:**
- **Layout responsif** 2 kolom untuk desktop, 1 kolom untuk mobile
- **Color coding** untuk status dan kategori
- **Bahasa Indonesia** yang mudah dipahami pegawai
- **Tooltips** untuk penjelasan teknis
- **Link Google Maps** langsung dari koordinat GPS
- **Collapse section** untuk informasi teknis

### C. JavaScript Loading

**File:** `templates/admin/laporan_kehadiran/index.html.twig`

```javascript
function showDetailModal(absensiId) {
    const modal = document.getElementById('detailModal');
    const modalContent = modal.querySelector('.bg-white');
    
    // Tampilkan loading state
    showModal('detailModal');
    modalContent.innerHTML = `<loading-spinner>`;
    
    // Load data via AJAX
    fetch(`/admin/laporan-kehadiran/api/detail/${absensiId}`)
        .then(response => response.text())
        .then(html => {
            modalContent.innerHTML = html;
        })
        .catch(error => {
            // Error handling dengan retry button
        });
}
```

---

## 📊 Informasi Status dan Kode

### Status Kehadiran
| Kode | Tampilan | Warna | Keterangan |
|------|----------|-------|------------|
| `hadir` | ✅ Hadir | Hijau | Pegawai hadir tepat waktu |
| `terlambat` | ⏰ Terlambat | Kuning | Pegawai hadir tetapi terlambat |
| `tidak_hadir` | ❌ Tidak Hadir | Merah | Pegawai tidak melakukan absensi |
| `izin` | 📄 Izin | Biru | Pegawai mendapat izin tidak masuk |

### Status GPS
| Tampilan | Keterangan | Action |
|----------|------------|--------|
| 🗺️ GPS | GPS tersedia dan valid | Klik untuk lihat peta |
| ⚠️ Invalid | Format koordinat tidak valid | Untuk maintenance admin |
| 📍 No GPS | Absensi tanpa GPS | - |

### Status QR Code
| Status | Keterangan |
|--------|------------|
| 📱 Terverifikasi | Menggunakan QR Code valid |
| 📝 Manual | Tanpa QR Code |

### Status Validasi Admin
| Status | Icon | Keterangan |
|--------|------|------------|
| Pending | ⏳ | Menunggu validasi admin |
| Disetujui | ✅ | Sudah divalidasi dan disetujui |
| Ditolak | ❌ | Ditolak oleh admin |

### Sistem Absensi
| Sistem | Keterangan |
|--------|------------|
| Sistem Jadwal Fleksibel (Baru) | KonfigurasiJadwalAbsensi - GPS mandatory |
| Sistem Jadwal Tetap (Lama) | JadwalAbsensi - GPS optional |
| Manual/Legacy | Tanpa jadwal sistem |

---

## 🔧 Troubleshooting untuk Admin

### Problem: GPS tidak muncul di laporan

**Kemungkinan Penyebab:**
1. User tidak memberikan izin GPS
2. Browser tidak support geolocation
3. HTTPS tidak digunakan (GPS blocked di HTTP)

**Solusi:**
- Pastikan aplikasi menggunakan HTTPS
- Instruksikan user untuk allow location access
- Cek browser compatibility

### Problem: Format koordinat Invalid

**Kemungkinan Penyebab:**
1. Data corrupt di database
2. Format penyimpanan tidak konsisten

**Solusi:**
```sql
-- Cek format koordinat yang tidak valid
SELECT id, lokasi_absensi FROM absensi 
WHERE lokasi_absensi IS NOT NULL 
AND lokasi_absensi NOT REGEXP '^-?[0-9]+\.?[0-9]*,-?[0-9]+\.?[0-9]*$';
```

### Problem: Detail modal tidak load

**Kemungkinan Penyebab:**
1. Route tidak ditemukan
2. Database error
3. Permission issue

**Solusi:**
- Cek browser console untuk error
- Verify route: `/admin/laporan-kehadiran/api/detail/{id}`
- Cek log Symfony

---

## 📝 Maintenance Notes

**Untuk Update Future:**

1. **GPS Validation**: Tambahkan validasi jarak dari kantor
2. **Batch GPS Update**: Tool untuk update GPS data secara batch
3. **GPS Analytics**: Analisa lokasi absensi pegawai
4. **Export Enhancement**: Tambahkan GPS ke CSV export
5. **Mobile Optimization**: Improve GPS accuracy di mobile

**Database Index:**
```sql
-- Index untuk performance query GPS
CREATE INDEX idx_absensi_lokasi ON absensi(lokasi_absensi);
CREATE INDEX idx_absensi_tanggal_lokasi ON absensi(tanggal, lokasi_absensi);
```

**Backup Considerations:**
- GPS data sensitif, pastikan backup encrypted
- Regular cleanup GPS data lama jika perlu

---

## 🇮🇩 Fitur Bahasa Indonesia

### A. Format Tanggal di Tabel

**File:** `templates/admin/laporan_kehadiran/index.html.twig`

```html
<!-- Hari dalam bahasa Indonesia -->
<div class="font-medium text-gray-900">
    {{ absensi.tanggal|date('l')|replace({
        'Monday': 'Senin',
        'Tuesday': 'Selasa', 
        'Wednesday': 'Rabu',
        'Thursday': 'Kamis',
        'Friday': 'Jumat',
        'Saturday': 'Sabtu',
        'Sunday': 'Minggu'
    }) }}
</div>
```

### B. Format Tanggal di Controller

**File:** `src/Controller/AdminLaporanKehadiranController.php`

```php
/**
 * Helper: Format tanggal ke bahasa Indonesia
 * Contoh output: "Senin, 15 September 2025"
 */
private function formatTanggalIndonesia(\DateTimeInterface $tanggal): string
{
    $formatInggris = $tanggal->format('l, d F Y');
    
    // Replace hari dan bulan ke bahasa Indonesia
    $formatIndonesia = str_replace([
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 
        'Friday', 'Saturday', 'Sunday'
    ], [
        'Senin', 'Selasa', 'Rabu', 'Kamis', 
        'Jumat', 'Sabtu', 'Minggu'
    ], $formatInggris);
    
    return str_replace([
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ], [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ], $formatIndonesia);
}
```

**Hasil:**
- Tabel laporan: **"Senin"** (bukan "Monday")
- Modal detail: **"Senin, 15 September 2025"** (format lengkap Indonesia)

---

## ✅ Testing Checklist

- [x] GPS collection dari form absensi
- [x] GPS storage di database format benar
- [x] GPS display di admin tabel
- [x] Modal detail load dengan benar
- [x] Google Maps link berfungsi
- [x] Responsive di mobile
- [x] Error handling saat GPS gagal
- [x] Permission handling browser GPS
- [x] HTTPS requirement untuk GPS
- [x] **Format tanggal bahasa Indonesia** di tabel dan modal

---

*Dokumentasi ini dibuat untuk memudahkan admin dalam maintenance dan troubleshooting fitur GPS dan detail absensi.*