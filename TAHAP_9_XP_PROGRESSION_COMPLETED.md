# 🎮 Tahap 9: Sistem XP & Level Progression - COMPLETED ✅

## 📋 Ringkasan Implementasi

Tahap 9 (Bagian 1 & 2) telah selesai diimplementasikan dengan sukses! Sistem XP Progression yang komprehensif sekarang tersedia di aplikasi GEMBIRA.

---

## ✅ Komponen yang Telah Dibuat

### 1. **Entities (Database Models)**

#### ✅ UserXpLog Entity
**File**: `src/Entity/UserXpLog.php`

**Fungsi**: Mencatat semua aktivitas XP yang diperoleh user

**Fields**:
- `id` - Primary key
- `user` - Relasi ke Pegawai (ManyToOne)
- `xp_earned` - Jumlah XP yang diperoleh
- `activity_type` - Jenis aktivitas (create_quote, like_quote, dll)
- `description` - Deskripsi aktivitas
- `related_id` - ID entitas terkait (misalnya quote_id)
- `created_at` - Timestamp

**Indexes**:
- `idx_user_xp_log_user` pada `user_id`
- `idx_user_xp_log_created` pada `created_at`

---

#### ✅ MonthlyLeaderboard Entity
**File**: `src/Entity/MonthlyLeaderboard.php`

**Fungsi**: Menyimpan ranking bulanan user berdasarkan XP

**Fields**:
- `id` - Primary key
- `user` - Relasi ke Pegawai (ManyToOne)
- `month` - Bulan (1-12)
- `year` - Tahun
- `xp_monthly` - Total XP bulan ini
- `rank_monthly` - Ranking bulan ini
- `created_at` - Timestamp pembuatan
- `updated_at` - Timestamp update terakhir

**Indexes**:
- `idx_month_year` pada `(month, year)`
- `idx_xp_monthly` pada `xp_monthly`
- `idx_rank_monthly` pada `rank_monthly`

**Unique Constraint**: `(user_id, month, year)` - Satu user hanya punya 1 record per bulan

---

#### ✅ Pegawai Entity - XP Fields
**File**: `src/Entity/Pegawai.php` (updated)

**Fields Baru**:
- `total_xp` (INT, default: 0) - Total XP sepanjang waktu
- `current_level` (INT, default: 1) - Level saat ini (1-5)
- `current_badge` (VARCHAR(10), default: '🌱') - Badge emoji

**Helper Methods Baru**:
- `getTotalXp()` / `setTotalXp()`
- `getCurrentLevel()` / `setCurrentLevel()`
- `getCurrentBadge()` / `setCurrentBadge()`
- `getXpProgress()` - Menghitung progress XP ke level berikutnya
- `getLevelTitle()` - Mendapatkan title level (Pemula, Bersemangat, dll)

---

### 2. **Repositories (Data Access Layer)**

#### ✅ UserXpLogRepository
**File**: `src/Repository/UserXpLogRepository.php`

**Methods**:
- `getTotalXpByUser(Pegawai $user): int` - Total XP user sepanjang waktu
- `getMonthlyXpByUser(Pegawai $user, int $month, int $year): int` - XP bulan tertentu
- `getUserXpLogs(Pegawai $user, int $limit, int $offset): array` - History XP dengan pagination
- `getRecentActivities(int $limit): array` - Aktivitas XP terbaru (semua user)
- `countActivitiesByType(Pegawai $user, string $activityType): int` - Jumlah aktivitas per tipe
- `getXpBreakdownByType(Pegawai $user): array` - Breakdown XP per tipe aktivitas
- `getDailyXpChart(Pegawai $user, int $days): array` - Data chart XP harian (30 hari terakhir)

---

#### ✅ MonthlyLeaderboardRepository
**File**: `src/Repository/MonthlyLeaderboardRepository.php`

**Methods**:
- `findTop10ByMonthYear(int $month, int $year): array` - Top 10 user bulan tertentu
- `findAllByMonthYear(int $month, int $year, int $limit): array` - Full leaderboard (max 50)
- `findOrCreateForUser(Pegawai $user, int $month, int $year): MonthlyLeaderboard` - Find atau create entry
- `updateUserXpMonthly(Pegawai $user, int $xp, int $month, int $year): void` - Update XP bulanan
- `recalculateRankings(int $month, int $year): void` - Recalculate semua ranking
- `getUserRanking(Pegawai $user, int $month, int $year): ?array` - Ranking user bulan tertentu
- `getMonthlyComparison(Pegawai $user): array` - Perbandingan bulan ini vs bulan lalu
- `getMonthlyHistory(Pegawai $user, int $months): array` - History 6 bulan terakhir

---

### 3. **Service (Business Logic)**

#### ✅ UserXpService
**File**: `src/Service/UserXpService.php`

**XP Constants**:
```php
XP_CREATE_QUOTE = 20   // Membuat quote baru
XP_LIKE_QUOTE = 3      // Menyukai quote
XP_COMMENT_QUOTE = 5   // Mengomentari quote
XP_SHARE_QUOTE = 8     // Membagikan quote
XP_VIEW_QUOTE = 1      // Melihat quote (max 3x/hari)
```

**Level System**:
| Level | XP Range | Badge | Title |
|-------|----------|-------|-------|
| 1 | 0 - 200 | 🌱 | Pemula |
| 2 | 201 - 400 | 🌿 | Bersemangat |
| 3 | 401 - 700 | 🌺 | Berdedikasi |
| 4 | 701 - 1100 | 🌞 | Ahli |
| 5 | 1101+ | 🏆 | Master |

**Core Methods**:
- `addXp(Pegawai $user, int $xp, string $activityType, ...): array` - Add XP & update level/leaderboard
- `calculateLevel(int $totalXp): int` - Hitung level berdasarkan total XP
- `getBadgeForLevel(int $level): string` - Get badge emoji untuk level
- `getTitleForLevel(int $level): string` - Get title untuk level
- `getUserXpProgress(Pegawai $user): array` - Progress detail user ke level berikutnya
- `getUserXpHistory(Pegawai $user, ...): array` - History aktivitas XP user
- `getXpBreakdown(Pegawai $user): array` - Breakdown XP per tipe aktivitas
- `getMonthlyLeaderboard(?int $month, ?int $year): array` - Top 10 leaderboard
- `getFullMonthlyLeaderboard(...): array` - Full leaderboard (50 users)
- `getUserRanking(Pegawai $user, ...): ?array` - Ranking user di leaderboard
- `getMonthlyComparison(Pegawai $user): array` - Perbandingan bulan ini vs lalu
- `recalculateUserLevel(Pegawai $user): void` - Recalculate level dari XP logs
- `syncAllUsersXp(): array` - Sync semua user (untuk migration/repair)
- `awardXpForActivity(Pegawai $user, string $activity, ...): array` - Convenience method untuk award XP

---

### 4. **Database Migration**

#### ✅ Migration File
**File**: `migrations/Version20250124000000_AddXpProgressionSystem.php`

**Changes**:
1. ✅ Created table `user_xp_log` with indexes and foreign keys
2. ✅ Created table `monthly_leaderboard` with indexes, unique constraint, and foreign keys
3. ✅ Added 3 columns to `pegawai`: `total_xp`, `current_level`, `current_badge`

**Status**: ✅ **MIGRATED** - Semua tabel dan kolom sudah dibuat

---

## 🧪 Testing & Verification

### ✅ Database Structure Verified

Tabel dan kolom sudah diverifikasi ada di database:
- ✅ `user_xp_log` table exists
- ✅ `monthly_leaderboard` table exists
- ✅ `pegawai.total_xp` column exists (default: 0)
- ✅ `pegawai.current_level` column exists (default: 1)
- ✅ `pegawai.current_badge` column exists (default: 🌱)

---

## 📊 Data Flow Diagram

```
User Activity (Like, Comment, Create Quote, dll)
        ↓
IkhlasController calls UserXpService.awardXpForActivity()
        ↓
UserXpService.addXp()
        ↓
1. Create UserXpLog entry
2. Update Pegawai.total_xp
3. Calculate new level → Update Pegawai.current_level & current_badge
4. Update MonthlyLeaderboard.xp_monthly
5. Recalculate rankings
        ↓
Return result with level_up info (if applicable)
```

---

## 🎯 Cara Menggunakan XP Service

### Example 1: Award XP untuk Like Quote

```php
// Di IkhlasController
$result = $this->userXpService->awardXpForActivity(
    $user,
    'like_quote',
    $quoteId
);

if ($result['level_up']) {
    // User naik level!
    $message = "🎉 Selamat! Anda naik ke Level {$result['new_level']} - {$result['level_title']}!";
}
```

### Example 2: Manual Add XP dengan Custom Amount

```php
$result = $this->userXpService->addXp(
    $user,
    50,                    // XP amount
    'special_bonus',       // Activity type
    'Bonus login harian',  // Description
    null                   // Related ID
);
```

### Example 3: Get User's XP Progress

```php
$progress = $this->userXpService->getUserXpProgress($user);
// Returns:
[
    'current_xp' => 150,
    'current_level' => 1,
    'level_title' => 'Pemula',
    'current_xp_in_level' => 150,
    'xp_needed_for_next_level' => 200,
    'xp_to_next_level' => 50,
    'percentage_progress' => 75.0,
    'next_level' => 2,
    'is_max_level' => false
]
```

### Example 4: Get Monthly Leaderboard

```php
// Top 10 bulan ini
$leaderboard = $this->userXpService->getMonthlyLeaderboard();

// Top 10 bulan tertentu
$leaderboard = $this->userXpService->getMonthlyLeaderboard(10, 2025); // Oktober 2025

// Full leaderboard (50 users)
$fullLeaderboard = $this->userXpService->getFullMonthlyLeaderboard();
```

### Example 5: Get User's Ranking

```php
$ranking = $this->userXpService->getUserRanking($user);
// Returns:
[
    'rank' => 5,
    'xp' => 320,
    'total_users' => 48
]
// User ranked #5 out of 48 users
```

---

## 🚀 Next Steps (Tahap 9 Bagian 3)

### Pending Tasks:

1. **Create ResetMonthlyLeaderboardCommand**
   - Command Symfony untuk reset leaderboard bulanan
   - Scheduled via cron job (tanggal 1 setiap bulan)
   - Preserves total_xp, hanya reset monthly ranking

2. **Update IkhlasController**
   - Integrate UserXpService ke semua endpoint interaksi
   - Award XP untuk: create, like, comment, share, view
   - Return level_up notification ke frontend

3. **Update UI Templates**
   - **leaderboard.html.twig**: Tampilkan monthly leaderboard dengan XP, levels, badges
   - **profile.html.twig**: XP progress bar, current level, badge, XP history
   - **ikhlas/index.html.twig**: Level-up notification popup

4. **Testing**
   - Test semua XP awards functionality
   - Test level progression (1→2, 2→3, dll)
   - Test monthly leaderboard ranking
   - Test monthly reset command

---

## 📝 Notes

### Design Decisions:
1. **Monthly Leaderboard Reset**: XP bulanan di-reset setiap bulan, tapi `total_xp` preserved untuk level progression
2. **Level Cap**: Max level adalah 5 (Master)
3. **XP Logging**: Semua aktivitas XP dicatat di `user_xp_log` untuk audit trail
4. **Ranking Calculation**: Auto-recalculate setiap ada update XP bulanan
5. **Tie-breaker**: Jika XP sama, user yang lebih dulu mendapat XP dapat ranking lebih tinggi

### Integration Points:
- Service harus di-inject ke IkhlasController
- GamificationService (existing) bisa tetap dipakai untuk poin/badges lain
- UserXpService fokus khusus untuk XP & Level progression

---

## ✅ Summary Status

| Component | Status | File |
|-----------|--------|------|
| UserXpLog Entity | ✅ Created | src/Entity/UserXpLog.php |
| MonthlyLeaderboard Entity | ✅ Created | src/Entity/MonthlyLeaderboard.php |
| Pegawai XP Fields | ✅ Added | src/Entity/Pegawai.php |
| UserXpLogRepository | ✅ Created | src/Repository/UserXpLogRepository.php |
| MonthlyLeaderboardRepository | ✅ Created | src/Repository/MonthlyLeaderboardRepository.php |
| UserXpService | ✅ Created | src/Service/UserXpService.php |
| Database Migration | ✅ Executed | migrations/Version20250124000000_AddXpProgressionSystem.php |
| Testing | ✅ Verified | Database structure confirmed |

---

**Status Keseluruhan**: ✅ **TAHAP 9 BAGIAN 1 & 2 COMPLETED**

**Siap untuk**: Bagian 3 - Command, Controller Integration, & UI Updates

**Tanggal Selesai**: 22 Oktober 2025
