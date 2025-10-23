# 🎮 Gamifikasi Ikhlas - Quick Reference

## Sistem Poin

### Cara Mendapat Poin
| Aksi | Poin | Keterangan |
|------|------|------------|
| 👁️ View Quote | +1 | Setiap kali melihat quote |
| ❤️ Like Quote | +2 | Setiap quote hanya sekali |
| 📌 Save Quote | +5 | Setiap quote hanya sekali |
| 🌅 Daily Login | +5 | Maksimal sekali per hari |

---

## Level & Badge

### Progression Path
```
Level 1: 🌱 Pemula Ikhlas      (0-50 poin)
    ↓ +51 poin
Level 2: 💡 Penyemangat        (51-150 poin)
    ↓ +100 poin
Level 3: 🔥 Inspirator         (151-300 poin)
    ↓ +150 poin
Level 4: 🌟 Teladan Ikhlas     (301-600 poin)
    ↓ +300 poin
Level 5: 👑 Duta Inspirasi     (601+ poin)
```

---

## Contoh Perhitungan Poin

### Scenario A: Pemula Aktif (1 Minggu)
```
Hari 1: Login(5) + View(1) + Like(2) = 8 poin
Hari 2: Login(5) + Like(2) + Save(5) = 12 poin
Hari 3: Login(5) + View(1) = 6 poin
Hari 4: Login(5) + Save(5) = 10 poin
Hari 5: Login(5) + Like(2) = 7 poin
Hari 6: Login(5) + View(1) + Save(5) = 11 poin
Hari 7: Login(5) + Like(2) = 7 poin

Total: 61 poin → Level 2 🌟
```

### Scenario B: Power User (1 Bulan)
```
Daily Login: 30 hari × 5 = 150 poin
Views: 60 quotes × 1 = 60 poin
Likes: 40 quotes × 2 = 80 poin
Saves: 30 quotes × 5 = 150 poin

Total: 440 poin → Level 4 🌟
```

---

## Fitur Gamifikasi

### ✅ Profile Page (`/profile/profil`)
- Current level & badge
- Total poin
- Progress bar ke level selanjutnya
- Koleksi badge yang sudah didapat
- Link ke leaderboard

### ✅ Leaderboard (`/ikhlas/leaderboard`)
- Top 10 users by total points
- Level display
- Breakdown: Likes + Saves + Total
- Ranking badges (👑🥈🥉🏅)

### ✅ Level Up Notification
- SweetAlert2 popup
- Animated badge icon
- Level info
- Total poin

### ✅ Toast Notifications
- Setiap aksi menunjukkan poin yang didapat
- Position: bottom-right
- Auto-dismiss after 3 seconds

---

## Testing Quick Start

### 1. Login Pertama Kali
```
URL: http://localhost/gembira/public/ikhlas
Expected: +5 poin (daily login)
Check: Console log "Adding 5 points to user..."
```

### 2. Like Quote
```
Action: Click ❤️ button
Expected: "+2 poin" in toast
Check: Button turns red, shows "Disukai"
```

### 3. Save Quote
```
Action: Click 📌 button
Expected: "+5 poin" in toast
Check: Button turns yellow, shows "Disimpan"
```

### 4. Check Profile
```
URL: http://localhost/gembira/public/profile/profil
Expected: Badge section shows
- Level 1 🌱
- Total poin: 12 (dari 3 aksi di atas)
- Progress bar: 24% (12/50)
```

### 5. Check Leaderboard
```
URL: http://localhost/gembira/public/ikhlas/leaderboard
Expected: User muncul di ranking
- Badge Lvl 1
- 12 total poin
```

---

## Troubleshooting

### Poin tidak bertambah?
1. Clear cache: `php bin/console cache:clear`
2. Check console log untuk error
3. Verify database tables exist
4. Check if GamificationService injected

### Level up tidak muncul?
1. Check SweetAlert2 CDN loaded
2. Verify flash message in controller
3. Check browser console for JS errors
4. Test dengan manually set points ke 49

### Badge tidak tampil di profile?
1. Verify userStats passed to template
2. Check GamificationService::getUserStats()
3. Verify user_badges table has data

### Leaderboard kosong?
1. Verify ada user dengan interactions
2. Check query JOIN dengan user_points
3. Clear leaderboard cache
4. Check SQL query syntax

---

## Database Quick Queries

### Check User Points
```sql
SELECT up.*, p.nama
FROM user_points up
JOIN pegawai p ON up.user_id = p.id
ORDER BY points_total DESC;
```

### Check User Badges
```sql
SELECT ub.*, p.nama
FROM user_badges ub
JOIN pegawai p ON ub.user_id = p.id
ORDER BY earned_date DESC;
```

### Manual Point Adjustment
```sql
-- Set user 2 to 300 points (level 3)
UPDATE user_points
SET points_total = 300, level = 3
WHERE user_id = 2;
```

### Reset All Points
```sql
TRUNCATE TABLE user_points;
TRUNCATE TABLE user_badges;
```

---

## Code Snippets

### Award Points Manually (Controller)
```php
$result = $this->gamificationService->addPoints(
    $user,
    10,
    'Custom action'
);

if ($result['level_up']) {
    $this->addFlash('success', 'Selamat naik level!');
}
```

### Get User Stats (Controller)
```php
$stats = $this->gamificationService->getUserStats($user);
// Returns: points_total, level, progress_percent, badges, etc.
```

### Check if Level Up (JavaScript)
```javascript
const response = await fetch('/ikhlas/api/interact', {
    method: 'POST',
    body: JSON.stringify({ quoteId: 1, action: 'like' })
});

const data = await response.json();

if (data.level_up && data.level_up.level_up) {
    // Show notification
    showLevelUpNotification(data.level_up);
}
```

---

## Performance Tips

### Cache Leaderboard
- TTL: 60 seconds
- Key: `ikhlas_leaderboard_top_10`
- Clear manually: `php bin/console cache:clear`

### Optimize Queries
- LEFT JOIN user_points for level info
- Index on user_id in both tables
- Limit results to top 10

### Reduce API Calls
- Cache user stats in session (optional)
- Batch point updates (if needed)

---

## Customization

### Change Point Values
File: `src/Service/GamificationService.php`
```php
public const POINTS_LIKE_QUOTE = 3;  // Changed from 2
public const POINTS_SAVE_QUOTE = 10; // Changed from 5
```

### Add More Levels
File: `src/Service/GamificationService.php`
```php
private array $levelThresholds = [
    1 => 0,
    2 => 51,
    3 => 151,
    4 => 301,
    5 => 601,
    6 => 1001,  // NEW!
];

private array $badges = [
    // ... existing ...
    6 => ['name' => 'Legenda', 'icon' => '⭐'],
];
```

### Custom Notification Colors
File: `templates/ikhlas/index.html.twig`
```javascript
Swal.fire({
    confirmButtonColor: '#10B981', // Green
    backdrop: `rgba(16, 185, 129, 0.2)`
});
```

---

## API Response Examples

### Successful Interaction (No Level Up)
```json
{
    "success": true,
    "action": "like",
    "status": true,
    "message": "❤️ Anda menyukai quote ini! +2 poin",
    "level_up": null
}
```

### Successful Interaction (With Level Up)
```json
{
    "success": true,
    "action": "save",
    "status": true,
    "message": "✅ Quote disimpan ke favorit! +5 poin",
    "level_up": {
        "success": true,
        "points_added": 5,
        "old_total": 48,
        "new_total": 53,
        "old_level": 1,
        "new_level": 2,
        "level_up": true,
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

## Monitoring & Analytics

### Check Daily Active Users
```sql
SELECT DATE(last_updated) as date, COUNT(*) as active_users
FROM user_points
WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(last_updated)
ORDER BY date DESC;
```

### Top Point Earners (This Month)
```sql
SELECT p.nama, up.points_total, up.level
FROM user_points up
JOIN pegawai p ON up.user_id = p.id
WHERE up.last_updated >= DATE_FORMAT(NOW(), '%Y-%m-01')
ORDER BY up.points_total DESC
LIMIT 10;
```

### Badge Distribution
```sql
SELECT badge_name, COUNT(*) as total_users
FROM user_badges
GROUP BY badge_name
ORDER BY COUNT(*) DESC;
```

---

**Quick Access URLs:**
- Profile: `/profile/profil`
- Leaderboard: `/ikhlas/leaderboard`
- Ikhlas Main: `/ikhlas`

**Support:**
- Documentation: `TAHAP_5_GAMIFIKASI_COMPLETED.md`
- Issue Tracker: Create ticket if bugs found
