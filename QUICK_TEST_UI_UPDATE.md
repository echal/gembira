# Quick Test: UI Update & Migration

**Status:** ✅ Siap untuk testing
**Waktu testing:** ~5 menit

---

## 🧪 Test Steps

### 1. Clear Browser Cache
```
Ctrl + Shift + R (Windows/Linux)
Cmd + Shift + R (Mac)
```

### 2. Login ke Aplikasi
```
URL: http://localhost/gembira
```

### 3. Buka Halaman Profil
```
Navigasi: Dashboard → Menu Bottom → "Profil"
atau
Direct: http://localhost/gembira/profile
```

---

## ✅ Verification Checklist

### A. Total XP Display
Cek section "Total XP":

**Expected:**
```
Total XP
  3.7K  ← (jika XP = 3740)
Experience Points
```

**If XP < 1000:**
```
Total XP
  150   ← (tidak ada K, angka biasa)
Experience Points
```

✅ PASS jika:
- XP ≥ 1000 muncul dengan format "K" (e.g., 3.7K, 9.4K)
- XP < 1000 tetap angka biasa (e.g., 150, 850)

---

### B. Sistem Level Cards

Scroll ke bawah, cek section "Sistem Level:":

**Expected:**
```
┌──────────────┬──────────────┬──────────────┬──────────────┬──────────────┐
│      🌱      │      🌿      │      🌺      │      🌞      │      🏆      │
│Pemula Ikhlas │Aktor Kebaikan│  Penggerak   │ Inspirator   │  Teladan     │
│              │              │  Semangat    │   Ikhlas     │  Kinerja     │
│   0-3.7K     │  3.7K-9.4K   │ 9.4K-17.8K   │ 17.8K-30K    │     30K+     │
└──────────────┴──────────────┴──────────────┴──────────────┴──────────────┘
       ↑              ↑              ↑              ↑              ↑
    Level 1        Level 2        Level 3        Level 4        Level 5
```

**Checklist:**

- [ ] Level 1: "Pemula Ikhlas" dengan range "0-3.7K"
- [ ] Level 2: "Aktor Kebaikan" dengan range "3.7K-9.4K"
- [ ] Level 3: "Penggerak Semangat" dengan range "9.4K-17.8K"
- [ ] Level 4: "Inspirator Ikhlas" dengan range "17.8K-30K"
- [ ] Level 5: "Teladan Kinerja" dengan range "30K+"
- [ ] Badge emoji tampil: 🌱 🌿 🌺 🌞 🏆
- [ ] Current level ter-highlight (border biru + ring)

✅ PASS jika semua checklist terpenuhi

---

### C. XP Progress Bar

Cek section "Progress ke Level X":

**Expected:**
```
Progress ke Level 2
150 / 3740 XP
[████░░░░░░░░░░░░░░░░] 4.0%
150 XP lagi
```

✅ PASS jika:
- Progress bar bergerak sesuai XP
- Angka XP to next level sesuai dengan range baru
- Percentage calculation benar

---

## 🎯 Functional Testing

### Test 1: Create Quote (+20 XP)
1. Buka IKHLAS feed
2. Buat quote baru
3. Cek Total XP bertambah +20
4. Cek progress bar bergerak

### Test 2: Like Quote (+3 XP)
1. Like quote orang lain
2. Cek Total XP bertambah +3
3. Verifikasi counter berfungsi

### Test 3: Receive Like (Passive +5 XP)
1. Minta orang lain like quote Anda
2. Cek Total XP bertambah +5
3. Verifikasi passive income berfungsi

### Test 4: Comment (+5 XP)
1. Buat komentar di quote
2. Cek Total XP bertambah +5

---

## 🐛 Known Issues / Edge Cases

### Issue 1: Cache Not Cleared
**Symptom:** Masih melihat "Pemula 0-200", "Bersemangat 201-400"

**Solution:**
```bash
# Backend cache
php bin/console cache:clear

# Browser cache
Ctrl + Shift + R
```

### Issue 2: XP Format Tidak Muncul (K)
**Symptom:** XP = 3740 tapi muncul "3740" bukan "3.7K"

**Check:**
1. Twig extension terdaftar?
   ```bash
   php bin/console debug:container XpFormatterExtension
   ```
2. Filter digunakan di template?
   ```twig
   {{ pegawai.totalXp|format_xp }}
   ```

### Issue 3: Migration Tidak Jalan
**Symptom:** User level tidak update

**Solution:**
```bash
# Cek apakah command terdaftar
php bin/console list | grep recalculate

# Jalankan ulang migration
php bin/console app:recalculate-user-levels
```

---

## 📊 Expected Results Summary

| Component         | Before                  | After                    | Status |
|-------------------|-------------------------|--------------------------|--------|
| Total XP Display  | 3740                    | 3.7K                     | ✅     |
| Level 1 Title     | Pemula                  | Pemula Ikhlas            | ✅     |
| Level 1 Range     | 0-200                   | 0-3.7K                   | ✅     |
| Level 2 Title     | Bersemangat             | Aktor Kebaikan           | ✅     |
| Level 2 Range     | 201-400                 | 3.7K-9.4K                | ✅     |
| Level 3 Title     | Berdedikasi             | Penggerak Semangat       | ✅     |
| Level 3 Range     | 401-700                 | 9.4K-17.8K               | ✅     |
| Level 4 Title     | Ahli                    | Inspirator Ikhlas        | ✅     |
| Level 4 Range     | 701-1100                | 17.8K-30K                | ✅     |
| Level 5 Title     | Master                  | Teladan Kinerja          | ✅     |
| Level 5 Range     | 1101+                   | 30K+                     | ✅     |
| Badge Emoji       | 🌱🌿🌺🌞🏆              | 🌱🌿🌺🌞🏆                | ✅     |
| XP Rewards        | +20, +3, +5, +5, +8, +1 | (unchanged)              | ✅     |
| Migration Command | -                       | Created & Executed       | ✅     |

---

## 🚀 If All Tests Pass

**Congratulations!** 🎉

Sistem Level Progression telah berhasil diupdate:

- ✅ UI menampilkan title & range baru
- ✅ XP formatting dengan K notation berfungsi
- ✅ Migration command siap untuk data updates
- ✅ User experience lebih balanced (5 bulan ke Level 5)

---

## 📞 If Tests Fail

### Screenshot untuk debugging:
1. Buka `/profile`
2. Screenshot section "Sistem Level"
3. Screenshot section "Total XP"
4. Share screenshot + describe issue

### Log checking:
```bash
# Check Symfony logs
tail -f var/log/dev.log

# Check PHP errors
tail -f /var/log/apache2/error.log
# atau
tail -f /xampp/apache/logs/error.log
```

### Database verification:
```bash
php bin/console doctrine:query:sql "SELECT id, nama, total_xp, current_level, current_badge FROM pegawai WHERE total_xp > 0 LIMIT 5"
```

---

## 📁 Related Files

**Documentation:**
- `UI_UPDATE_AND_MIGRATION_COMPLETE.md` - Full implementation details
- `BEFORE_AFTER_UI_UPDATE.md` - Visual comparison
- `LEVEL_PROGRESSION_UPDATE_COMPLETE.md` - Backend changes

**Code:**
- `src/Service/UserXpService.php` - Level calculation logic
- `src/Twig/XpFormatterExtension.php` - XP formatting
- `templates/profile/profil.html.twig` - UI template
- `src/Command/RecalculateUserLevelsCommand.php` - Migration command

---

**Testing Time:** ~5 menit
**Expected Result:** ✅ All green checkmarks
**Next Step:** Monitor user feedback & engagement

---

**File ini dibuat:** 23 Oktober 2025
**Tipe:** Testing Guide
**Version:** 1.0.0
