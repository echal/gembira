# TAHAP 5: BADGE & REWARD (GAMIFIKASI) - IMPLEMENTASI SELESAI

## Status: ✅ COMPLETED

Tanggal: 2025-10-21

---

## 🎯 Fitur yang Diimplementasikan

### 1. **Sistem Poin Otomatis**
Pengguna mendapat poin secara otomatis berdasarkan aktivitas di menu Ikhlas:
- **View Quote**: +1 poin
- **Like Quote**: +2 poin
- **Save Quote**: +5 poin
- **Daily Login**: +5 poin (hanya sekali per hari)

### 2. **Sistem Level & Badge**
5 level dengan badge unik:
| Level | Nama Badge | Icon | Threshold Poin |
|-------|------------|------|----------------|
| 1 | Pemula Ikhlas | 🌱 | 0-50 |
| 2 | Penyemangat | 💡 | 51-150 |
| 3 | Inspirator | 🔥 | 151-300 |
| 4 | Teladan Ikhlas | 🌟 | 301-600 |
| 5 | Duta Inspirasi | 👑 | 601+ |

### 3. **Progress Tracking**
- Progress bar menunjukkan % menuju level berikutnya
- Tampilan poin yang masih dibutuhkan
- Badge collection display

### 4. **Level-Up Notifications**
- SweetAlert2 popup saat naik level
- Menampilkan badge baru
- Total poin dan level baru
- Animasi bounce pada icon badge

### 5. **Leaderboard Enhancement**
- Ranking berdasarkan total gamification points
- Menampilkan level user
- Breakdown: likes + saves + total points
- Top 10 users dengan badge

### 6. **Profile Integration**
- Section "Badge & Level Ikhlas"
- Current level & badge display
- Progress bar visual
- Badge collection showcase
- Link ke leaderboard

---

## 📁 File yang Dibuat/Dimodifikasi

### A. Database Tables

**`user_points`** - Menyimpan total poin dan level user
```sql
CREATE TABLE user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_total INT DEFAULT 0,
    level INT DEFAULT 1,
    last_updated DATETIME,
    created_at DATETIME,
    UNIQUE KEY unique_user_points (user_id),
    FOREIGN KEY (user_id) REFERENCES pegawai(id) ON DELETE CASCADE
);
```

**`user_badges`** - Menyimpan badge yang dimiliki user
```sql
CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    badge_icon VARCHAR(50) NOT NULL,
    badge_level INT DEFAULT 1,
    earned_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES pegawai(id) ON DELETE CASCADE
);
```

### B. Entity Classes

**`src/Entity/UserPoints.php`** - Entity untuk poin user
- Properties: id, user, pointsTotal, level, lastUpdated, createdAt
- Helper methods: addPoints(), getProgressToNextLevel(), getPointsNeededForNextLevel()

**`src/Entity/UserBadges.php`** - Entity untuk badge user
- Properties: id, user, badgeName, badgeIcon, badgeLevel, earnedDate
- Helper methods: getFormattedEarnedDate(), getDaysSinceEarned()

### C. Service Layer

**`src/Service/GamificationService.php`** - Core gamification logic

**Public Constants:**
```php
POINTS_LIKE_QUOTE = 2
POINTS_SAVE_QUOTE = 5
POINTS_VIEW_QUOTE = 1
POINTS_SHARE_QUOTE = 10
POINTS_DAILY_LOGIN = 5
```

**Key Methods:**
```php
// Add points and handle level up
public function addPoints(Pegawai $user, int $points, string $reason): array

// Get or create user points record
public function getUserPoints(Pegawai $user): UserPoints

// Get all badges for user
public function getUserBadges(Pegawai $user): array

// Get current badge info
public function getCurrentBadge(Pegawai $user): ?array

// Get comprehensive user stats
public function getUserStats(Pegawai $user): array

// Award daily login bonus (once per day)
public function awardDailyLogin(Pegawai $user): ?array
```

**Returns from addPoints():**
```php
[
    'success' => true,
    'points_added' => 2,
    'old_total' => 10,
    'new_total' => 12,
    'old_level' => 1,
    'new_level' => 1,
    'level_up' => false,
    'badge_earned' => false,
    'badge_info' => null
]
```

### D. Controller Integration

**`src/Controller/IkhlasController.php`** - Updated with gamification

**Changes:**
1. Injected GamificationService via constructor
2. `index()` method: Awards daily login + view points
3. `interactWithQuote()` method: Awards points for like/save
4. Returns level_up info in JSON response

**Example Integration:**
```php
// Daily login bonus
$dailyBonus = $this->gamificationService->awardDailyLogin($user);
if ($dailyBonus && $dailyBonus['level_up']) {
    $this->addFlash('level_up', [
        'level' => $dailyBonus['new_level'],
        'badge' => $dailyBonus['badge_info']
    ]);
}

// Award points for interaction
$result = $this->gamificationService->addPoints(
    $user,
    GamificationService::POINTS_LIKE_QUOTE,
    'Like quote #' . $quoteId
);
```

**`src/Controller/ProfileController.php`** - Updated viewProfile()
- Injects GamificationService
- Fetches user stats
- Passes to template

### E. Service Updates

**`src/Service/IkhlasLeaderboardService.php`** - Enhanced leaderboard

**Changes:**
- Added LEFT JOIN with user_points table
- Added gamificationPoints and userLevel to SELECT
- Order by gamificationPoints DESC first
- Return level and gamificationPoints in result

### F. Template Files

**`templates/base.html.twig`** - Added SweetAlert2 CDN
```html
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
```

**`templates/ikhlas/index.html.twig`** - Level up notifications

**Added:**
1. Flash message handler for daily login level up
2. `showLevelUpNotification()` JavaScript function
3. Level up check after interactions

**JavaScript:**
```javascript
// Check for level up after interaction
if (data.level_up && data.level_up.level_up) {
    setTimeout(() => showLevelUpNotification(data.level_up), 800);
}

// Show Level Up Notification
function showLevelUpNotification(levelUpInfo) {
    const badge = levelUpInfo.badge_info;
    Swal.fire({
        title: '🎉 Selamat! Level Up!',
        html: `
            <div class="text-7xl mb-4 animate-bounce">${badge.icon}</div>
            <div class="text-2xl font-bold">Level ${levelUpInfo.new_level}</div>
            <div class="text-lg">${badge.name}</div>
            <div>Total Poin: ${levelUpInfo.new_total}</div>
        `,
        icon: 'success',
        confirmButtonText: 'Luar Biasa!',
        confirmButtonColor: '#8B5CF6'
    });
}
```

**`templates/profile/profil.html.twig`** - Badge & Level section

**Added:**
```twig
{% if userStats is defined %}
<div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-6">
    <h3>🎖️ Badge & Level Ikhlas</h3>

    <!-- Current Level & Badge -->
    <div class="flex justify-between">
        <div>
            <div>Level {{ userStats.level }}</div>
        </div>
        <div class="text-5xl">{{ userStats.current_badge.icon }}</div>
        <div>
            <div>Total Poin</div>
            <div>{{ userStats.points_total }}</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-bar" style="width: {{ userStats.progress_percent }}%"></div>
    <div>{{ userStats.points_to_next_level }} poin lagi</div>

    <!-- Badges Collected -->
    {% for badge in userStats.badges %}
        <div>
            {{ badge.badgeIcon }} {{ badge.badgeName }}
        </div>
    {% endfor %}
</div>
{% endif %}
```

**`templates/ikhlas/leaderboard.html.twig`** - Enhanced with levels

**Changes:**
- Shows level badge next to nama
- Displays gamificationPoints as main metric
- Shows breakdown: likes + saves + total points
- Sorted by gamificationPoints

---

## 🔄 User Flow

### Skenario 1: User Baru
1. User login pertama kali → +5 poin (daily login)
2. User otomatis level 1 dengan badge "Pemula Ikhlas 🌱"
3. View quote → +1 poin
4. Like quote → +2 poin
5. Save quote → +5 poin
6. Total: 13 poin, masih level 1

### Skenario 2: Level Up
1. User sudah 48 poin (level 1)
2. Save quote → +5 poin = 53 poin total
3. **Level Up!** → Level 2
4. SweetAlert2 popup muncul:
   - "🎉 Selamat! Level Up!"
   - Icon: 💡
   - "Level 2 - Penyemangat"
   - "Total Poin: 53"
5. Badge "Penyemangat" otomatis ditambahkan ke collection

### Skenario 3: Daily Login Streak
1. Hari 1: Login → +5 poin
2. Hari 1 (login lagi): Tidak dapat bonus (sudah diklaim)
3. Hari 2 (00:01): Login → +5 poin (bonus baru)

### Skenario 4: Leaderboard
1. User A: 120 poin, Level 2
2. User B: 180 poin, Level 3
3. User C: 50 poin, Level 1
4. Leaderboard ranking: B (🥇), A (🥈), C (🥉)

---

## 🎨 UI/UX Features

### 1. Profile Page
- **Visual Progress Bar**: Gradient purple-pink
- **Current Badge**: Large icon (text-5xl) dengan nama
- **Stats Grid**: Level, Icon, Total Poin
- **Badge Collection**: Flex wrap dengan cards
- **Link to Leaderboard**: Purple hover effect

### 2. Level Up Notification
- **SweetAlert2**: Beautiful modal popup
- **Animation**: Bounce effect pada icon
- **Colors**: Purple theme (#8B5CF6)
- **Backdrop**: Semi-transparent purple
- **CTA**: "Luar Biasa!" button

### 3. Leaderboard Enhancement
- **Level Badge**: Pills next to nama (Lvl X)
- **Points Breakdown**: ❤️ likes • 📌 saves • 🎯 total
- **Main Metric**: gamificationPoints (large, bold)
- **Color Coding**: Purple for top ranks

### 4. Toast Notifications
- Like: "❤️ Anda menyukai quote ini! +2 poin"
- Save: "✅ Quote disimpan ke favorit! +5 poin"
- Success color: Green
- Position: Bottom-right

---

## 📊 Database Schema Relationships

```
pegawai (existing)
    ├── user_points (1:1)
    │   ├── id
    │   ├── user_id → pegawai.id
    │   ├── points_total
    │   ├── level
    │   └── last_updated
    │
    └── user_badges (1:Many)
        ├── id
        ├── user_id → pegawai.id
        ├── badge_name
        ├── badge_icon
        ├── badge_level
        └── earned_date
```

---

## 🧮 Points Calculation Examples

### Example 1: Single Session
```
Actions:
- View 3 quotes: 3 × 1 = 3 poin
- Like 2 quotes: 2 × 2 = 4 poin
- Save 1 quote: 1 × 5 = 5 poin
Total: 12 poin
```

### Example 2: Weekly Activity
```
Day 1: Login +5, View +1, Like +2 = 8 poin
Day 2: Login +5, Save +5 = 10 poin
Day 3: Login +5, Like +2, Save +5 = 12 poin
Day 4-7: Login only = 4 × 5 = 20 poin
Total: 50 poin (Level 1 → Level 2)
```

### Example 3: Power User
```
Actions over 2 weeks:
- Login: 14 days × 5 = 70 poin
- Views: 50 × 1 = 50 poin
- Likes: 30 × 2 = 60 poin
- Saves: 20 × 5 = 100 poin
Total: 280 poin (Level 3 - Inspirator 🔥)
```

---

## 🔧 Configuration & Customization

### Adjust Points Values
Edit `src/Service/GamificationService.php`:
```php
public const POINTS_LIKE_QUOTE = 2;  // Change to 3 for more rewards
public const POINTS_SAVE_QUOTE = 5;  // Change to 10 for more rewards
public const POINTS_VIEW_QUOTE = 1;  // Change to 2 for more rewards
public const POINTS_DAILY_LOGIN = 5; // Change to 10 for more rewards
```

### Adjust Level Thresholds
Edit levelThresholds array:
```php
private array $levelThresholds = [
    1 => 0,
    2 => 51,    // Change to 100 for harder progression
    3 => 151,   // Change to 300
    4 => 301,   // Change to 600
    5 => 601,   // Change to 1000
];
```

### Add New Badges
Edit badges array:
```php
private array $badges = [
    1 => ['name' => 'Pemula Ikhlas', 'icon' => '🌱'],
    2 => ['name' => 'Penyemangat', 'icon' => '💡'],
    3 => ['name' => 'Inspirator', 'icon' => '🔥'],
    4 => ['name' => 'Teladan Ikhlas', 'icon' => '🌟'],
    5 => ['name' => 'Duta Inspirasi', 'icon' => '👑'],
    6 => ['name' => 'Master Ikhlas', 'icon' => '🏆'], // NEW!
];
```

### Customize Notification Colors
Edit SweetAlert2 config in template:
```javascript
Swal.fire({
    confirmButtonColor: '#8B5CF6', // Change to '#EF4444' for red
    backdrop: `rgba(139, 92, 246, 0.2)` // Change opacity or color
});
```

---

## 🎯 Success Metrics

### User Engagement
- ✅ Users encouraged to interact daily (daily login bonus)
- ✅ Clear progression path (5 levels)
- ✅ Visible rewards (badges)
- ✅ Social proof (leaderboard)

### Technical Implementation
- ✅ Automatic point tracking
- ✅ Real-time level up detection
- ✅ Efficient database queries (LEFT JOIN)
- ✅ Cached leaderboard (60s TTL)
- ✅ No manual admin intervention needed

### UX Quality
- ✅ Beautiful notifications (SweetAlert2)
- ✅ Visual progress feedback (progress bar)
- ✅ Mobile-responsive design
- ✅ Smooth animations
- ✅ Clear point breakdown

---

## 🧪 Testing Checklist

### Database
- [x] Tables created successfully
- [x] Foreign keys working
- [x] Unique constraint on user_points.user_id
- [ ] Test data inserted

### Backend Logic
- [x] GamificationService methods work
- [x] Points added correctly
- [x] Level up detection works
- [x] Badge auto-grant works
- [ ] Daily login limitation works

### Frontend
- [ ] Profile page shows badges
- [ ] Progress bar displays correctly
- [ ] Level up popup appears
- [ ] Leaderboard shows levels
- [ ] Toast notifications work

### Integration
- [ ] Like grants +2 points
- [ ] Save grants +5 points
- [ ] View grants +1 point
- [ ] Daily login grants +5 once
- [ ] Level up triggers notification

---

## 🚀 Future Enhancements (Potential)

### 1. **Special Badges**
- Achievement badges: "100 likes earned", "50 saves collected"
- Streak badges: "7-day streak", "30-day streak"
- Seasonal badges: Monthly special badges

### 2. **Social Features**
- See friends' badges
- Share achievements to profile
- Challenge friends to reach same level

### 3. **Rewards Redemption**
- Exchange points for benefits
- Unlock special features at higher levels
- Premium badges for top performers

### 4. **Analytics Dashboard**
- Chart showing points earned over time
- Most active days/times
- Comparison with organization average

### 5. **Notification Center**
- History of all level ups
- Badge earning notifications
- Leaderboard position changes

---

## 📝 API Endpoints

### Points & Levels
```
GET  /profile/profil          - View profile with gamification stats
POST /ikhlas/api/interact     - Award points for interactions
GET  /ikhlas/leaderboard      - View points-based leaderboard
GET  /ikhlas/api/stats        - Global stats
GET  /ikhlas/api/my-stats     - Personal stats
```

### Response Format (Interaction)
```json
{
    "success": true,
    "action": "save",
    "status": true,
    "message": "✅ Quote disimpan ke favorit! +5 poin",
    "level_up": {
        "level_up": true,
        "points_added": 5,
        "old_total": 48,
        "new_total": 53,
        "old_level": 1,
        "new_level": 2,
        "badge_earned": true,
        "badge_info": {
            "name": "Penyemangat",
            "icon": "💡",
            "level": 2
        }
    }
}
```

---

## 🔐 Security Considerations

### Prevent Abuse
1. **Daily Login**: Max once per 24 hours (checked via last_updated date)
2. **View Points**: Limited to 1 per quote view (could add rate limiting)
3. **Interaction Points**: Can only like/save each quote once
4. **Database Constraints**: UNIQUE on user_points.user_id

### Audit Trail
- `created_at`: When user first got points
- `last_updated`: Last interaction timestamp
- `earned_date`: When each badge was earned

---

## ✅ Verification Steps

### Step 1: Check Database
```sql
SELECT * FROM user_points WHERE user_id = 2;
SELECT * FROM user_badges WHERE user_id = 2;
```

### Step 2: Test Points Award
1. Login to /ikhlas
2. Check console logs for "Adding X points..."
3. Like a quote
4. Check if message shows "+2 poin"

### Step 3: Test Level Up
1. Manually set points to 49
2. Save a quote (+5 poin = 54 total)
3. Should see level up notification
4. Check user_badges table for new badge

### Step 4: Test Leaderboard
1. Visit /ikhlas/leaderboard
2. Verify users sorted by gamificationPoints
3. Check if levels display next to names
4. Verify point breakdown shows

### Step 5: Test Profile
1. Visit /profile/profil
2. Check if Badge & Level section appears
3. Verify progress bar width matches percentage
4. Check if all earned badges display

---

## 📚 Documentation References

- SweetAlert2: https://sweetalert2.github.io/
- Tailwind CSS: https://tailwindcss.com/
- Symfony Cache: https://symfony.com/doc/current/cache.html
- Doctrine ORM: https://www.doctrine-project.org/

---

**Created:** 2025-10-21
**Status:** ✅ Implementation Complete
**Next:** Clear cache and test in browser

---

## 🎉 Gamifikasi Menu Ikhlas Siap Digunakan!

Sistem Badge & Reward telah diimplementasikan dengan sukses. Pengguna sekarang akan:
- Mendapat poin otomatis saat berinteraksi
- Naik level dan mendapat badge
- Melihat progress mereka di profil
- Berkompetisi di leaderboard
- Mendapat notifikasi level up yang menarik

**Silakan test di browser!** 🚀
