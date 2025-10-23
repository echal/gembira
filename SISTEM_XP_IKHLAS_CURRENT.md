# 📊 Sistem XP IKHLAS - Status Terkini

## 🎯 Ringkasan Sistem XP

Berikut adalah **sistem pemberian XP lengkap** yang saat ini aktif di aplikasi IKHLAS:

---

## 💰 Tabel Pemberian XP

| No | Aktivitas | XP | Limit | Keterangan |
|----|-----------|-----|-------|------------|
| 1️⃣ | **Membuat Quote** | **+20** | ∞ | Saat user membuat quote baru |
| 2️⃣ | **Memberi Like** | **+3** | ∞ | Saat user like quote orang lain |
| 3️⃣ | **Menerima Like** | **+5** | ∞ | Saat quote user di-like orang lain (passive income) |
| 4️⃣ | **Memberi Komentar** | **+5** | ∞ | Saat user comment di quote |
| 5️⃣ | **Share Quote** | **+8** | ∞ | Saat user share quote ke WhatsApp |
| 6️⃣ | **View Quote** | **+1** | **3x/hari** | Saat user view feed quotes (max 3x) |
| 7️⃣ | **Daily Login** | Bonus | 1x/hari | Login pertama hari ini (gamification) |

---

## 🔥 Sistem XP Detail

### 1. ✅ Membuat Quote (+20 XP)

**Trigger**: Saat user submit quote baru via form

**Endpoint**: `POST /ikhlas/api/create-quote`

**Kode** (IkhlasController.php, Line 173-177):
```php
// Award XP for creating a quote
$xpResult = $this->userXpService->awardXpForActivity(
    $user,
    'create_quote',
    $quote->getId()
);
```

**Response Message**:
```
🎉 Kata semangatmu telah dibagikan! +20 XP
```

**Contoh**:
- User buat 1 quote → **+20 XP**
- User buat 5 quote → **+100 XP**
- User buat 10 quote/hari → **+200 XP/hari**

---

### 2. ✅ Memberi Like (+3 XP)

**Trigger**: Saat user click button like pada quote

**Endpoint**: `POST /ikhlas/api/interact` (action: 'like')

**Kode** (IkhlasController.php, Line 268-272):
```php
// Just liked - award XP to the user who liked
$xpResult = $this->userXpService->awardXpForActivity(
    $user,
    'like_quote',
    $quoteId
);
```

**Response Message**:
```
❤️ Anda menyukai quote ini! +3 XP
```

**Contoh**:
- User like 10 quote → **+30 XP**
- User like 50 quote/hari → **+150 XP/hari**

---

### 3. ✨ Menerima Like (+5 XP) - **PASSIVE INCOME!**

**Trigger**: Saat quote user di-like oleh orang lain

**Endpoint**: `POST /ikhlas/api/interact` (action: 'like')

**Kode** (IkhlasController.php, Line 291-297):
```php
// Award XP to quote author for receiving a like (+5 XP)
$quoteAuthorName = $quote->getAuthor();
if ($quoteAuthorName) {
    $quoteAuthor = $this->em->getRepository(Pegawai::class)
        ->findOneBy(['nama' => $quoteAuthorName]);

    if ($quoteAuthor && $quoteAuthor->getId() !== $user->getId()) {
        // Don't reward if user likes their own quote
        $this->userXpService->addXp(
            $quoteAuthor,
            5, // +5 XP for receiving like
            'receive_like',
            'Quote Anda mendapat like',
            $quoteId
        );
    }
}
```

**Prevention**: Tidak dapat XP jika user like quote sendiri

**Contoh Passive Income**:
- User buat 1 quote viral → di-like 20 orang → **+100 XP** (tanpa effort tambahan!)
- User buat 10 quote → masing-masing di-like 5 orang → **+250 XP** passive

**Ini adalah fitur TERBARU yang membuat quote berkualitas sangat menguntungkan!**

---

### 4. ✅ Memberi Komentar (+5 XP)

**Trigger**: Saat user submit komentar di quote

**Endpoint**: `POST /ikhlas/api/quotes/{id}/comment`

**Kode** (IkhlasController.php, Line 511-516):
```php
// Award XP for commenting
$xpResult = $this->userXpService->awardXpForActivity(
    $user,
    'comment_quote',
    $quote->getId()
);
```

**Response Message**:
```
💬 Komentar berhasil ditambahkan! +5 XP
```

**Contoh**:
- User comment 10 quote → **+50 XP**
- User comment 20 quote/hari → **+100 XP/hari**

---

### 5. ✅ Share Quote (+8 XP)

**Trigger**: Saat user click button share (WhatsApp)

**Endpoint**: `POST /ikhlas/api/quotes/{id}/share`

**Kode** (IkhlasController.php, Line 593-598):
```php
// Award XP for sharing
$xpResult = $this->userXpService->awardXpForActivity(
    $user,
    'share_quote',
    $quote->getId()
);
```

**Response Message**:
```
📤 Quote dibagikan! +8 XP
```

**Contoh**:
- User share 5 quote → **+40 XP**
- User share 10 quote/hari → **+80 XP/hari**

---

### 6. ✅ View Quote (+1 XP, Max 3x/hari)

**Trigger**: Saat user view feed quotes (otomatis)

**Endpoint**: `GET /ikhlas` (index page)

**Limit**: **Maksimal 3x per hari**

**Kode** (UserXpService.php, Line 329-337):
```php
// Check daily limit for view_quote
if ($activity === 'view_quote' && !$this->canAwardViewXp($user)) {
    return [
        'success' => false,
        'error' => 'Daily view limit reached (max 3x/day)',
        'xp_earned' => 0,
        'level_up' => false
    ];
}
```

**Contoh**:
- View ke-1 hari ini → **+1 XP** ✅
- View ke-2 hari ini → **+1 XP** ✅
- View ke-3 hari ini → **+1 XP** ✅
- View ke-4 hari ini → **+0 XP** ❌ (limit reached!)
- Besok reset → Bisa dapat 3x lagi

**Purpose**: Mencegah XP farming dengan refresh terus-menerus

---

### 7. ✅ Daily Login Bonus

**Trigger**: Login pertama hari ini

**Endpoint**: `GET /ikhlas` (index page)

**Kode** (IkhlasController.php, Line 42-49):
```php
// Award daily login bonus (only once per day)
$dailyBonus = $this->gamificationService->awardDailyLogin($user);
if ($dailyBonus && $dailyBonus['level_up']) {
    $this->addFlash('level_up', [
        'level' => $dailyBonus['new_level'],
        'badge' => $dailyBonus['badge_info']
    ]);
}
```

**XP**: Variable (tergantung level)

**Contoh**:
- Login hari ini → **Bonus XP** (sistem gamification lama)
- Streak 7 hari berturut → **Bonus lebih besar**

---

## 📊 Rangkuman Activity Types

| Activity Type | XP Value | File Location |
|---------------|----------|---------------|
| `create_quote` | 20 | UserXpService.php:15 |
| `like_quote` | 3 | UserXpService.php:16 |
| `receive_like` | 5 | IkhlasController.php:292 |
| `comment_quote` | 5 | UserXpService.php:17 |
| `share_quote` | 8 | UserXpService.php:18 |
| `view_quote` | 1 | UserXpService.php:19 |

---

## 🎯 Level System

| Level | XP Range | Badge | Title | Julukan |
|-------|----------|-------|-------|---------|
| **1** | 0 - 200 | 🌱 | Pemula Ikhlas | Penanam Niat Baik |
| **2** | 201 - 400 | 🌿 | Aktor Kebaikan | Penyemai Semangat |
| **3** | 401 - 700 | 🌺 | Penggerak Semangat | Inspirator Harian |
| **4** | 701 - 1100 | 🌞 | Inspirator Ikhlas | Teladan Komunitas |
| **5** | 1101+ | 🏆 | Teladan Kinerja | Legenda Ikhlas |

---

## 💡 Strategi Mendapatkan XP

### Strategi 1: Content Creator (Fokus Buat Quote)
**Target**: Passive income dari quote viral

**Aktivitas Harian**:
- Buat 3 quote berkualitas → **+60 XP**
- Quote di-like 30 kali total → **+150 XP** (passive!)
- Like 10 quote orang → **+30 XP**
- **Total**: **240 XP/hari**

**Per Bulan**: 240 × 30 = **7,200 XP** (Level 5 guaranteed!)

---

### Strategi 2: Active Engager (Fokus Interaksi)
**Target**: Cepat naik level dengan interaksi

**Aktivitas Harian**:
- View 3 quote → **+3 XP** (max)
- Like 30 quote → **+90 XP**
- Comment 10 quote → **+50 XP**
- Share 5 quote → **+40 XP**
- Buat 2 quote → **+40 XP**
- **Total**: **223 XP/hari**

**Per Bulan**: 223 × 30 = **6,690 XP** (Level 5!)

---

### Strategi 3: Balanced (Mix Content + Interaksi)
**Target**: Pertumbuhan seimbang

**Aktivitas Harian**:
- Buat 2 quote berkualitas → **+40 XP**
- Quote di-like 10 kali → **+50 XP** (passive)
- Like 15 quote → **+45 XP**
- Comment 5 quote → **+25 XP**
- Share 3 quote → **+24 XP**
- View 3 quote → **+3 XP**
- **Total**: **187 XP/hari**

**Per Bulan**: 187 × 30 = **5,610 XP** (Level 5!)

---

## 📈 Contoh Progression

### Week 1 (Starting - 0 XP)
**User Baru mulai aktif**:
- Membuat 5 quote → 100 XP
- Quote di-like 20 kali → 100 XP
- Like 30 quote → 90 XP
- Comment 10 quote → 50 XP
- **Total Week 1**: 340 XP → **Level 2** 🌿 (Aktor Kebaikan)

---

### Week 2 (340 XP → Level 2)
**Mulai konsisten**:
- Buat 7 quote lagi → 140 XP
- Quote di-like 30 kali → 150 XP
- Interaksi aktif → 100 XP
- **Total Week 2**: +390 XP → **730 XP** → **Level 4** 🌞 (Inspirator Ikhlas)

---

### Month 1 (730 XP → Level 4)
**Konsisten aktif setiap hari**:
- Buat 60 quote total → 1,200 XP
- Quote di-like 200 kali → 1,000 XP
- Interaksi aktif daily → 600 XP
- **Total Month 1**: **2,800 XP** → **Level 5** 🏆 (Teladan Kinerja - Legenda Ikhlas!)

---

## 🎮 Gamification Features

### 1. Level Up Animation
**Trigger**: Saat total XP mencapai threshold level baru

**Visual**:
```
🎉 Selamat! Level Up!

        🌿

    Level 2
Aktor Kebaikan
Penyemai Semangat

Terus tingkatkan XP Anda untuk naik level!
```

**Sound**: (Optional - bisa ditambahkan)

---

### 2. XP Notification Toast
**Trigger**: Setelah aktivitas yang memberikan XP

**Visual**:
```
┌────────────────────────────────────┐
│ ❤️ Anda menyukai quote ini! +3 XP │
└────────────────────────────────────┘
```

**Duration**: 3 detik, auto-dismiss

---

### 3. Monthly Leaderboard
**Location**: `/ikhlas/leaderboard`

**Features**:
- Top 50 users XP bulan ini
- Current user ranking
- XP earned this month
- Level & badge display

---

## 📊 Database Tracking

### Table: `user_xp_log`
**Setiap aktivitas XP tercatat di table ini**:

| Column | Type | Description |
|--------|------|-------------|
| `id` | int | Primary key |
| `user_id` | int | User yang dapat XP |
| `xp_earned` | int | Jumlah XP |
| `activity_type` | varchar | Tipe aktivitas |
| `description` | varchar | Deskripsi |
| `related_id` | int | ID quote terkait |
| `created_at` | datetime | Timestamp |

**Contoh Data**:
```sql
INSERT INTO user_xp_log VALUES
(1, 123, 20, 'create_quote', 'Membuat quote baru', 456, '2025-10-22 14:30:00'),
(2, 123, 3, 'like_quote', 'Menyukai quote', 789, '2025-10-22 14:35:00'),
(3, 456, 5, 'receive_like', 'Quote Anda mendapat like', 789, '2025-10-22 14:35:00');
```

---

## 🔒 Anti-Abuse Measures

### 1. ✅ Daily View Limit (3x/hari)
**Prevents**: XP farming dengan refresh terus-menerus

**Implementation**:
```php
public function canAwardViewXp(Pegawai $user): bool
{
    $today = new \DateTime('today');
    $tomorrow = new \DateTime('tomorrow');

    $viewCount = $this->xpLogRepository->countActivityByTypeAndDate(
        $user,
        'view_quote',
        $today,
        $tomorrow
    );

    return $viewCount < 3; // Max 3 views per day
}
```

---

### 2. ✅ No Self-Like Bonus
**Prevents**: User like quote sendiri untuk dapat +5 XP

**Implementation**:
```php
if ($quoteAuthor && $quoteAuthor->getId() !== $user->getId()) {
    // Don't reward if user likes their own quote
    $this->userXpService->addXp($quoteAuthor, 5, 'receive_like', ...);
}
```

---

### 3. ✅ Activity Logging
**Enables**: Tracking semua aktivitas untuk audit

**Benefit**: Admin bisa cek jika ada aktivitas mencurigakan

---

### 4. ✅ Author-Only Delete
**Prevents**: User hapus quote orang lain

**Implementation**:
```php
if ($quote->getAuthor() !== $user->getNama()) {
    return new JsonResponse([
        'success' => false,
        'message' => 'Anda tidak memiliki izin untuk menghapus quote ini'
    ], 403);
}
```

---

## 📱 User Experience

### Visual Feedback
**Setiap aktivitas memberikan feedback jelas**:

1. **Create Quote**: Toast hijau "🎉 +20 XP"
2. **Like**: Icon berubah ❤️ + Toast "+3 XP"
3. **Receive Like**: (Silent, tapi tercatat di log)
4. **Comment**: Toast "💬 +5 XP"
5. **Share**: Toast "📤 +8 XP"
6. **Level Up**: Modal besar dengan animasi 🎉

---

## 🎯 Kesimpulan Sistem XP IKHLAS

### ✅ Yang Sudah Berjalan:
1. ✅ Membuat Quote (+20 XP)
2. ✅ Memberi Like (+3 XP)
3. ✅ **Menerima Like (+5 XP)** - PASSIVE INCOME!
4. ✅ Memberi Komentar (+5 XP)
5. ✅ Share Quote (+8 XP)
6. ✅ View Quote (+1 XP, max 3x/hari)
7. ✅ Daily Login Bonus
8. ✅ Level System dengan julukan
9. ✅ Monthly Leaderboard
10. ✅ XP Logging & Tracking

### 🎯 Fitur Unggulan:
- 🏆 **Passive Income**: Quote viral = XP terus mengalir
- 🛡️ **Anti-Abuse**: Daily limits & validation
- 📊 **Transparent**: Semua XP tercatat di database
- 🎮 **Engaging**: Level up animation & notifications
- 🔒 **Secure**: Permission system untuk edit/delete

### 📈 Impact:
- User termotivasi buat quote berkualitas (passive income)
- Engagement meningkat (like, comment, share)
- Fair play (daily limits mencegah farming)
- Community building (leaderboard competition)

---

**Status**: ✅ **FULLY OPERATIONAL**

Sistem XP IKHLAS saat ini **sudah sangat lengkap dan siap production**!

---

*Dokumentasi Sistem XP IKHLAS*
*Last Updated: 22 Oktober 2025*
*All Features: ACTIVE & WORKING*
