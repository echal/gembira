# 📅 Dokumentasi Event Management System - Gembira

## 🎯 Overview

Sistem Event Management telah disempurnakan dengan fitur notifikasi otomatis, kategori yang diperbaharui, dan UI/UX yang konsisten dengan tema biru langit aplikasi Gembira.

## ✨ Fitur Utama

### 🔧 Admin Panel
- **Manajemen Event Lengkap**: Create, Read, Update, Delete
- **Target Audience**: Bisa memilih semua unit kerja atau unit kerja tertentu
- **Kategori Baru**: 🔵 Kegiatan Kantor, 🟢 Kegiatan Pusat, 🟣 Kegiatan Internal, 🟠 Kegiatan External
- **Lokasi Preset**: Kanwil Kemenag Sulbar, Aula Kanwil, Asrama Haji, Online/Virtual
- **Notifikasi Otomatis**: Sistem otomatis mengirim notifikasi ke pegawai sesuai target audience

### 👤 User Interface
- **Kalender Interaktif**: Menampilkan event dengan filter unit kerja
- **Badge Kategori Berwarna**: Setiap kategori memiliki warna dan emoji yang konsisten
- **Grid Layout**: Tampilan yang rapi dengan shadow dan rounded corners
- **Notifikasi Real-time**: Counter badge yang update otomatis
- **Absensi Event**: Pegawai bisa absen langsung dari kalender

## 📊 Struktur Database

### Tabel `event`
```sql
-- Kategori yang diperbarui
kategori_event VARCHAR(50) -- kegiatan_kantor, kegiatan_pusat, kegiatan_internal, kegiatan_external

-- Target audience dan unit kerja
target_audience VARCHAR(10) -- 'all' atau 'custom' 
target_units JSON -- Array ID unit kerja (nullable)
```

### Tabel `notifikasi` (BARU)
```sql
id INT PRIMARY KEY AUTO_INCREMENT
pegawai_id INT NOT NULL -- hubungan: FK ke pegawai
event_id INT NULL -- hubungan: FK ke event (opsional)
judul VARCHAR(200) NOT NULL
pesan LONGTEXT NOT NULL
tipe VARCHAR(50) NOT NULL -- event_baru, absensi, pengumuman, reminder
sudah_dibaca BOOLEAN DEFAULT FALSE
waktu_dibuat DATETIME NOT NULL
waktu_dibaca DATETIME NULL
```

## 🎨 Badge Kategori

| Kategori | Emoji | Warna Badge |
|----------|-------|-------------|
| Kegiatan Kantor | 🔵 | `bg-blue-100 text-blue-800` |
| Kegiatan Pusat | 🟢 | `bg-green-100 text-green-800` |
| Kegiatan Internal | 🟣 | `bg-purple-100 text-purple-800` |
| Kegiatan External | 🟠 | `bg-orange-100 text-orange-800` |

## 📍 Lokasi Preset

- 📍 Kanwil Kemenag Sulbar
- 🏛️ Aula Kanwil  
- 🕌 Asrama Haji
- 💻 Online/Virtual
- 📍 Lokasi Lain (custom input)

## 🔔 Sistem Notifikasi

### Tipe Notifikasi
1. **Event Baru** (`event_baru`): 📅 
   - Dikirim ketika admin membuat event baru
   - Target sesuai dengan target audience event

2. **Reminder** (`reminder`): ⏰
   - Pengingat sebelum event dimulai
   - Dapat diatur jadwal otomatis

3. **Pengumuman** (`pengumuman`): 📢
   - Untuk pengumuman umum dari admin
   - Bisa ke semua pegawai atau unit kerja tertentu

4. **Absensi** (`absensi`): ✅
   - Konfirmasi absensi event berhasil dicatat

### Fitur Notifikasi
- ✅ Real-time counter badge di navigation
- ✅ Auto-refresh setiap 30 detik  
- ✅ Mark as read individual/batch
- ✅ Tampilan dengan tema biru langit konsisten
- ✅ Auto-cleanup notifikasi lama (command)

## 🚀 File-file yang Dibuat/Diperbarui

### Entities
- `src/Entity/Event.php` - Method baru untuk badge kategori
- `src/Entity/Notifikasi.php` - Entity baru untuk notifikasi

### Controllers
- `src/Controller/AdminEventController.php` - Integrasi notifikasi otomatis
- `src/Controller/NotifikasiController.php` - Controller notifikasi

### Services
- `src/Service/NotifikasiService.php` - Logic bisnis notifikasi

### Repositories
- `src/Repository/NotifikasiRepository.php` - Query notifikasi
- `src/Repository/EventRepository.php` - Query event dengan filter unit

### Forms
- `src/Form/EventType.php` - Kategori dan lokasi baru

### Templates
- `templates/user/kalender/index.html.twig` - Grid layout baru, badge kategori
- `templates/notifikasi/index.html.twig` - Halaman notifikasi
- `templates/admin/event/new.html.twig` - Form dengan kategori baru
- `templates/admin/event/index.html.twig` - Badge kategori di admin

### Commands
- `src/Command/CleanNotifikasiCommand.php` - Cleanup notifikasi lama

### Migrations
- `migrations/Version20250829115653.php` - Tabel notifikasi

## 🔗 Hubungan/Relasi Data

```php
// hubungan: Event -> Pegawai (melalui target audience)
if ($event->getTargetAudience() === 'all') {
    // Semua pegawai aktif
} else {
    // Pegawai di unit kerja dalam targetUnits
}

// hubungan: Notifikasi -> Pegawai (direct FK)
$notifikasi->setPegawai($pegawai);

// hubungan: Notifikasi -> Event (optional FK)
$notifikasi->setEvent($event);

// hubungan: Event -> Unit Kerja (via JSON array)
$event->getTargetUnits() // [1, 3, 5] = ID unit kerja
```

## 📱 API Endpoints

### Notifikasi
- `GET /notifikasi/api/count` - Jumlah notifikasi belum dibaca
- `GET /notifikasi/api/latest` - Notifikasi terbaru
- `POST /notifikasi/api/mark-read/{id}` - Tandai dibaca
- `POST /notifikasi/api/mark-all-read` - Tandai semua dibaca

### Event
- `GET /kalender/api/events/{date}` - Event pada tanggal tertentu
- `POST /kalender/api/absen-event/{id}` - Absensi event

## 🎛️ Command Line

```bash
# Bersihkan notifikasi lama (default 30 hari)
php bin/console app:clean-notifikasi

# Bersihkan notifikasi lebih dari 7 hari
php bin/console app:clean-notifikasi 7

# Setup crontab untuk cleanup otomatis
0 2 * * * /usr/bin/php /path/to/project/bin/console app:clean-notifikasi
```

## 🎯 Flow Kerja Sistem

### 1. Admin Membuat Event
1. Admin mengisi form event dengan kategori dan target audience
2. Event disimpan ke database
3. **Sistem otomatis mengirim notifikasi** ke pegawai sesuai target audience
4. Notifikasi muncul di menu Notifikasi pegawai

### 2. Pegawai Melihat Event  
1. Pegawai login dan buka menu Kalender
2. **Sistem filter event** berdasarkan unit kerja pegawai
3. Hanya event yang ditargetkan untuk unit kerja pegawai yang tampil
4. Event ditampilkan dengan **badge kategori berwarna**

### 3. Notifikasi Real-time
1. **Counter badge** di navigation bar update otomatis
2. Pegawai klik menu Notifikasi untuk detail
3. Pegawai bisa mark as read individual atau semua
4. **Auto-cleanup** notifikasi lama via cron job

## 🎨 Konsistensi UI/UX

### Tema Biru Langit
- **Primary**: `bg-sky-400`, `bg-sky-500`, `bg-sky-600`  
- **Accent**: `bg-sky-50`, `bg-sky-100`, `border-sky-200`
- **Text**: `text-sky-600`, `text-sky-700`, `text-sky-800`

### Komponen Konsisten
- ✅ Rounded corners (`rounded-lg`, `rounded-xl`)
- ✅ Shadow consistency (`shadow-sm`, `shadow-md`)  
- ✅ Transition animations (`transition-colors`, `hover:bg-sky-100`)
- ✅ Badge patterns (`px-2 py-1 rounded-full text-xs font-medium`)

## 💡 Tips Implementasi

1. **Bahasa Indonesia**: Semua label, pesan, dan teks menggunakan Bahasa Indonesia
2. **Responsive Design**: Grid otomatis adjust di mobile/desktop
3. **Error Handling**: Notifikasi gagal tidak mengganggu proses utama
4. **Performance**: Auto-refresh dibatasi 30 detik, cleanup otomatis
5. **Security**: CSRF protection, user validation di setiap endpoint

---

**Status**: ✅ **IMPLEMENTASI LENGKAP**  
**Version**: 2.0 - Enhanced Event Management with Notifications  
**Date**: 29 Agustus 2025  
**Developer**: Claude Assistant