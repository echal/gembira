# Dokumentasi: Komponen User Header

## 📋 Deskripsi

Komponen `user_header.html.twig` adalah header reusable yang telah distabilkan untuk semua halaman user dalam aplikasi GEMBIRA. Komponen ini menampilkan logo, nama user, NIP, dan ikon notifikasi dengan tampilan yang rapi, proporsional, dan responsif.

## 🎯 Tujuan Perbaikan

Perbaikan ini dilakukan untuk:

1. **Menghilangkan duplikasi kode** - Header yang sama digunakan di banyak halaman
2. **Konsistensi UI** - Semua halaman user memiliki tampilan header yang seragam
3. **Responsivitas** - Header menyesuaikan dengan berbagai ukuran layar (mobile, tablet, desktop)
4. **Maintainability** - Perubahan header cukup dilakukan di satu file
5. **Persiapan untuk fitur "Ikhlas"** - Foundation yang stabil untuk navigasi baru

## 📁 Lokasi File

```
templates/components/user_header.html.twig
```

## 🔧 Cara Penggunaan

### Penggunaan Dasar

```twig
{% include 'components/user_header.html.twig' %}
```

### Dengan Konfigurasi

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'back_url': path('app_dashboard'),
    'title': 'Judul Halaman',
    'subtitle': 'Subjudul atau keterangan',
    'show_welcome': false
} %}
```

## 📝 Parameter yang Tersedia

| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| `show_back_button` | boolean | `false` | Menampilkan tombol kembali di sebelah kiri logo |
| `back_url` | string | `path('app_dashboard')` | URL tujuan tombol kembali |
| `title` | string | `'GEMBIRA'` | Judul utama di header |
| `subtitle` | string | `'Gerakan Munajat Bersama Untuk Kinerja'` | Subjudul di bawah judul |
| `show_welcome` | boolean | `false` | Menampilkan info selamat datang dengan jam WITA |

## 🎨 Fitur Komponen

### 1. **Layout Responsif**

Header menggunakan Tailwind CSS dengan class responsif:

- **Mobile (< 640px)**: Logo 8x8, text-sm
- **Tablet (640px - 1024px)**: Logo 10x10, text-base
- **Desktop (> 1024px)**: Logo 12x12, text-lg

### 2. **Elemen Header**

#### A. Logo GEMBIRA (Kiri)
```html
<img src="{{ asset('images/logo-gembira.png') }}"
     alt="GEMBIRA"
     class="w-8 h-8 md:w-10 md:h-10 lg:w-12 lg:h-12 object-contain">
```

- Ukuran proporsional berdasarkan screen size
- Fallback ke placeholder jika gambar tidak ada

#### B. Nama User & NIP (Kanan)
```html
<div class="text-right flex flex-col items-end min-w-0 max-w-[100px] sm:max-w-[120px] md:max-w-[140px] lg:max-w-[180px]">
    <span class="font-semibold text-xs md:text-sm text-white truncate w-full">
        {{ app.user.nama|default('User') }}
    </span>
    <span class="text-xs text-sky-100 truncate w-full">
        {{ app.user.nip|default('---') }}
    </span>
</div>
```

**Perbaikan utama:**
- `flex-col` untuk susunan vertikal
- `truncate` untuk mencegah overflow teks panjang
- `max-w-[...]` responsif agar tidak melebar berlebihan

#### C. Ikon Notifikasi
```html
<a href="{{ path('app_notifikasi') }}"
   class="hover:scale-110 transition-transform duration-150">
    <svg class="w-5 h-5 md:w-6 md:h-6">...</svg>
    <!-- Badge notifikasi dinamis -->
    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 ...">
        {{ app.user.unreadNotifications > 9 ? '9+' : app.user.unreadNotifications }}
    </span>
</a>
```

**Fitur:**
- Hover effect: `scale-110` untuk feedback visual
- Badge merah untuk notifikasi unread
- Positioning absolute untuk badge

#### D. Dropdown Menu
```html
<button onclick="toggleUserDropdown()">...</button>
<div id="userDropdown" class="hidden absolute right-0 top-full ...">
    <!-- Menu items -->
</div>
```

**Menu items:**
- 👤 Profil
- 🏠 Dashboard
- 🔐 Ganti Password
- 📝 Tanda Tangan
- 🚪 Logout

## 🖥️ Contoh Implementasi

### Dashboard (dengan welcome info)

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': false,
    'title': 'GEMBIRA',
    'subtitle': 'Gerakan Munajat Bersama Untuk Kinerja',
    'show_welcome': true
} %}
```

### Halaman Laporan

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'back_url': path('app_dashboard'),
    'title': 'Laporan Absensi ' ~ periode_filter,
    'subtitle': '🔒 Bulan berjalan - Data pribadi Anda',
    'show_welcome': false
} %}
```

### Halaman Profil

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'back_url': path('app_dashboard'),
    'title': 'Profil Saya',
    'subtitle': 'Kelola informasi pribadi Anda',
    'show_welcome': false
} %}
```

## 📱 Responsivitas Breakdown

### Mobile (<640px)

- Logo: 32px (8 rem units)
- Font nama: 12px (text-xs)
- Font NIP: 10px (text-xs)
- Max-width nama: 100px
- Padding container: 12px (px-3)

### Tablet (640px - 1024px)

- Logo: 40px (10 rem units)
- Font nama: 14px (text-sm)
- Font NIP: 12px (text-xs)
- Max-width nama: 140px
- Padding container: 16px (px-4)

### Desktop (>1024px)

- Logo: 48px (12 rem units)
- Font nama: 16px (text-base)
- Font NIP: 14px (text-sm)
- Max-width nama: 180px
- Padding container: 16px (px-4)

## 🎭 JavaScript Functions

Komponen ini menyediakan fungsi JavaScript untuk interaksi dropdown:

### `toggleUserDropdown()`
Toggle dropdown menu user dengan animasi

### `closeUserDropdown()`
Menutup dropdown menu user

### `updateHeaderJam()` (opsional)
Update jam realtime di welcome info (hanya jika `show_welcome: true`)

## 🔄 Migration dari Header Lama

Untuk mengupdate halaman yang masih menggunakan header lama:

**Sebelum:**
```twig
<div class="bg-gradient-to-r from-sky-400 to-sky-500 ...">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <!-- ... kode header manual ... -->
        </div>
    </div>
</div>
```

**Sesudah:**
```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'title': 'Judul Halaman',
    'subtitle': 'Subjudul'
} %}
```

## 📊 Halaman yang Telah Diupdate

✅ Status update komponen header:

1. ✅ `templates/dashboard/index.html.twig` - Dashboard utama
2. ✅ `templates/user/laporan/riwayat.html.twig` - Laporan absensi
3. ✅ `templates/user/jadwal.html.twig` - Jadwal absensi
4. ✅ `templates/profile/profil.html.twig` - Profil user
5. ✅ `templates/user/kalender/index.html.twig` - Kalender kegiatan

## 🚀 Persiapan untuk Fitur "Ikhlas"

Komponen header ini telah disiapkan untuk mendukung navigasi fitur "Ikhlas" yang akan ditambahkan:

1. **Struktur modular** - Mudah menambahkan menu item baru
2. **Dropdown extensible** - Tinggal tambah link di dropdown menu
3. **Konsistensi visual** - Fitur baru akan match dengan design system
4. **Performance optimized** - JavaScript ringan dan efisien

## 🔮 Future Enhancements

Rencana pengembangan komponen header:

- [ ] Tambahkan foto profil user (avatar)
- [ ] Integrasi real-time notification count
- [ ] Support untuk multiple themes (dark mode)
- [ ] Accessibility improvements (ARIA labels)
- [ ] Animasi transisi yang lebih smooth
- [ ] Search bar global di header (opsional)

## 🐛 Troubleshooting

### Header tidak muncul

**Solusi:** Pastikan file komponen ada di `templates/components/user_header.html.twig`

### Dropdown tidak berfungsi

**Solusi:** Pastikan JavaScript terload dan tidak ada konflik function name

### Logo tidak muncul

**Solusi:** Periksa path asset: `public/images/logo-gembira.png`

### Teks nama terlalu panjang

**Solusi:** Komponen sudah menggunakan `truncate`, tapi bisa adjust `max-w-[...]` jika perlu

### Notifikasi badge tidak update

**Solusi:** Implementasikan `app.user.unreadNotifications` di User entity/service

## 📚 Referensi

- [Tailwind CSS Responsive Design](https://tailwindcss.com/docs/responsive-design)
- [Twig Template Include](https://twig.symfony.com/doc/3.x/tags/include.html)
- [Symfony Asset Component](https://symfony.com/doc/current/components/asset.html)

---

**Dibuat:** 2025-10-20
**Status:** ✅ Production Ready
**Versi:** 1.0.0
**Author:** Claude Code Assistant
