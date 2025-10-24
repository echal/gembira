# Testing Checklist: Migration IKHLAS → INSPIRASI (Strategi B)

## 📋 Perubahan yang Dilakukan

### ✅ File yang Diubah (4 files):

1. **`templates/components/bottom_nav.html.twig`**
   - Label menu: "Ikhlas" → "Inspirasi"
   - Emoji: 💭 → 💡

2. **`templates/ikhlas/index.html.twig`**
   - Page title: "Ikhlas - Inspirasi Kehidupan" → "Inspirasi - Motivasi Hari Ini"
   - Heading: "ikhlas" → "💡 inspirasi"
   - Tagline: "inspirasi kehidupan lahirkan semangat" → "Inspirasi Hari Ini, Semangat Selamanya"

3. **`templates/ikhlas/favorites.html.twig`**
   - Page title: "Favorit Saya - IKHLAS" → "Favorit Saya - Inspirasi"
   - Heading: "📚 Favorit Saya" → "📚 Favorit Inspirasi Saya"
   - Subtitle: "Quote yang Anda simpan" → "Koleksi inspirasi yang Anda simpan"

4. **`templates/ikhlas/leaderboard.html.twig`**
   - Page title: "Leaderboard - Ikhlas" → "Leaderboard - Inspirasi"
   - Title: "LEADERBOARD" → "🏆 LEADERBOARD INSPIRASI"
   - Subtitle: "Pengguna Paling Inspiratif" → "Inspirator Terbaik Bulan Ini"
   - Stats banner: "Data aktivitas menu Ikhlas" → "Total aktivitas berbagi inspirasi"

### ❌ Yang TIDAK Diubah (Backend tetap):

- ✅ URL tetap: `/ikhlas/*`
- ✅ Route names tetap: `app_ikhlas_*`
- ✅ Controller tetap: `IkhlasController`
- ✅ Service tetap: `IkhlasLeaderboardService`
- ✅ Template folder tetap: `templates/ikhlas/`
- ✅ Database tables tetap: `quotes`, `user_quotes_interaction`, dll

---

## 🧪 Testing Manual

### **Test 1: Menu Navigasi Bawah** ⏱️ 1 menit

1. Login sebagai user (pegawai)
2. Buka halaman dashboard (`/`)
3. Lihat menu navigasi bawah
4. **Expected**:
   - ✅ Menu ke-4 tampil: 💡 "Inspirasi" (bukan "Ikhlas")
   - ✅ Emoji lampu 💡 (bukan emoji pikiran 💭)
5. Klik menu "Inspirasi"
6. **Expected**:
   - ✅ URL tetap `/ikhlas` (tidak berubah)
   - ✅ Menu "Inspirasi" highlight biru (active state)

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 2: Halaman Utama Inspirasi** ⏱️ 2 menit

1. Buka halaman `/ikhlas`
2. **Expected**:
   - ✅ Page title di browser tab: "Inspirasi - Motivasi Hari Ini"
   - ✅ Header besar tampil: "💡 inspirasi" (dengan emoji lampu)
   - ✅ Tagline di bawah header: "Inspirasi Hari Ini, Semangat Selamanya"
   - ✅ Warna gradient tetap purple-pink
3. Test fungsi:
   - ✅ Swipe next/previous quote berfungsi
   - ✅ Like button berfungsi
   - ✅ Save button berfungsi
   - ✅ Comment berfungsi
   - ✅ Create new quote berfungsi

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 3: Halaman Favorit** ⏱️ 1 menit

1. Dari halaman utama, klik icon 📚 (Favorites)
2. **Expected**:
   - ✅ URL tetap `/ikhlas/my-favorites`
   - ✅ Page title di browser tab: "Favorit Saya - Inspirasi"
   - ✅ Heading: "📚 Favorit Inspirasi Saya"
   - ✅ Subtitle: "Koleksi inspirasi yang Anda simpan"
3. Test fungsi:
   - ✅ Back button berfungsi (kembali ke halaman utama)
   - ✅ List favorit tampil
   - ✅ Like/unlike berfungsi

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 4: Halaman Leaderboard** ⏱️ 1 menit

1. Dari halaman utama, klik icon 🏆 (Leaderboard)
2. **Expected**:
   - ✅ URL tetap `/ikhlas/leaderboard`
   - ✅ Page title di browser tab: "Leaderboard - Inspirasi"
   - ✅ Heading: "🏆 LEADERBOARD INSPIRASI"
   - ✅ Subtitle: "Inspirator Terbaik Bulan Ini"
   - ✅ Stats banner: "📊 Statistik Global Inspirasi"
   - ✅ Stats description: "Total aktivitas berbagi inspirasi"
3. Test fungsi:
   - ✅ Tab switching berfungsi (Bulanan/Minggu Ini)
   - ✅ Ranking list tampil
   - ✅ XP dan badge tampil

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 5: Bookmark & URL Lama** ⏱️ 1 menit

1. Buat bookmark halaman `/ikhlas`
2. Logout
3. Login lagi
4. Klik bookmark
5. **Expected**:
   - ✅ Halaman tetap berfungsi (tidak 404)
   - ✅ Tampil "💡 inspirasi" (nama baru)
   - ✅ Semua fungsi normal

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 6: Hard Refresh Browser** ⏱️ 30 detik

1. Di halaman `/ikhlas`, tekan `Ctrl + Shift + R` (Windows) atau `Cmd + Shift + R` (Mac)
2. **Expected**:
   - ✅ Halaman reload tanpa error
   - ✅ Tampilan "Inspirasi" muncul (tidak cache lama)
   - ✅ Emoji 💡 tampil (bukan 💭)

**Status**: [ ] PASS / [ ] FAIL

---

### **Test 7: Mobile Responsive** ⏱️ 1 menit

1. Buka halaman di mobile browser atau DevTools mobile mode
2. Test semua halaman:
   - `/ikhlas` (halaman utama)
   - `/ikhlas/my-favorites`
   - `/ikhlas/leaderboard`
3. **Expected**:
   - ✅ Menu navigasi bawah tampil "Inspirasi"
   - ✅ Heading responsive (ukuran text adjust)
   - ✅ Semua fungsi berfungsi di mobile

**Status**: [ ] PASS / [ ] FAIL

---

## 🎯 Summary Testing

| Test | Status | Notes |
|------|--------|-------|
| 1. Menu Navigasi Bawah | [ ] PASS / [ ] FAIL | |
| 2. Halaman Utama | [ ] PASS / [ ] FAIL | |
| 3. Halaman Favorit | [ ] PASS / [ ] FAIL | |
| 4. Halaman Leaderboard | [ ] PASS / [ ] FAIL | |
| 5. Bookmark & URL Lama | [ ] PASS / [ ] FAIL | |
| 6. Hard Refresh | [ ] PASS / [ ] FAIL | |
| 7. Mobile Responsive | [ ] PASS / [ ] FAIL | |

**Total Estimasi Waktu**: ~8 menit

---

## 📸 Screenshot Checklist

Ambil screenshot untuk dokumentasi:

- [ ] Menu navigasi bawah (tampil "Inspirasi")
- [ ] Halaman utama (header "💡 inspirasi")
- [ ] Halaman favorit (heading baru)
- [ ] Halaman leaderboard (heading & stats baru)

---

## 🐛 Bug Report Template

Jika menemukan bug, report dengan format:

```
Bug #: [nomor]
Page: [URL halaman]
Issue: [Deskripsi masalah]
Expected: [Yang diharapkan]
Actual: [Yang terjadi]
Screenshot: [Attach jika ada]
Browser: [Chrome/Firefox/Safari/dll]
```

---

## ✅ Sign-Off

- [ ] Semua test PASS
- [ ] Screenshot diambil
- [ ] Tidak ada bug critical
- [ ] Siap untuk commit & push

**Tested By**: _________________
**Date**: _________________
**Time**: _______ menit

---

**Status**: ⏳ Waiting for User Testing
