# 🔧 Panduan Admin - Update Sistem IKHLAS & Level Progression

**Update Tanggal:** 23 Oktober 2025
**Untuk:** Admin & IT Support

---

## 📋 Ringkasan Update

Update ini menambahkan **Sistem Gamifikasi lengkap** ke aplikasi GEMBIRA:

1. ✅ Fitur IKHLAS (Social Media Internal) - Quote posting system
2. ✅ Sistem XP (Experience Points) - Tracking aktivitas pegawai
3. ✅ Level Progression System - 5 level dengan progression seimbang
4. ✅ Monthly Leaderboard - Kompetisi bulanan
5. ✅ Admin Dashboard - Monitor & manage sistem

**Total perubahan:**
- 90 files changed
- 29,791 insertions
- 638 deletions

---

## 🚀 Deployment Checklist

### Pre-Deployment (PENTING!)
- [x] Backup database
- [x] Commit code ke Git
- [x] Push ke GitHub: https://github.com/echal/gembira.git

### Deployment Steps

#### 1. Pull Code dari GitHub
```bash
cd /path/to/gembira
git pull origin master
```

#### 2. Install Dependencies (jika ada yang baru)
```bash
composer install
npm install  # jika ada frontend dependencies
```

#### 3. Run Database Migration
```bash
# Create database tables untuk sistem baru
php bin/console doctrine:migrations:migrate

# Atau jika belum ada migration, create schema
php bin/console doctrine:schema:update --force
```

#### 4. Clear Cache (WAJIB!)
```bash
# Development
php bin/console cache:clear

# Production
php bin/console cache:clear --env=prod --no-debug
```

#### 5. Set Permissions (Linux/Mac)
```bash
chmod -R 777 var/cache
chmod -R 777 var/log
```

#### 6. Verify Migration Berhasil
```bash
# Cek apakah command baru terdaftar
php bin/console list | grep ikhlas
php bin/console list | grep recalculate

# Expected output:
# - app:reset-monthly-leaderboard
# - app:recalculate-user-levels
```

---

## 🗄️ Database Changes

### Tabel Baru yang Dibuat:

1. **`quote`** - Menyimpan quotes yang diposting pegawai
   - id, author, content, created_at, updated_at

2. **`quote_comment`** - Komentar di quotes
   - id, quote_id, author_id, content, created_at

3. **`user_quote_interaction`** - Tracking likes & favorites
   - id, user_id, quote_id, type (like/favorite), created_at

4. **`user_xp_log`** - Log semua perolehan XP
   - id, user_id, xp_amount, activity_type, created_at

5. **`monthly_leaderboard`** - Leaderboard bulanan
   - id, user_id, year, month, xp, rank

6. **`user_points`** - Tracking poin user (legacy, future use)

7. **`user_badges`** - Badge system (future use)

### Kolom Baru di Tabel `pegawai`:

- `total_xp` (INT) - Total XP yang dikumpulkan
- `current_level` (INT) - Level saat ini (1-5)
- `current_badge` (VARCHAR) - Badge emoji (🌱🌿🌺🌞🏆)
- `level_title` (VARCHAR) - Title level (e.g., "Pemula Ikhlas")

---

## 🎛️ Admin Dashboard

### Akses Dashboard XP:
```
URL: /admin/xp-dashboard
Menu: Admin Panel → Dashboard XP
```

### Fitur Dashboard:
1. **Summary Cards**
   - Total users
   - Total XP di sistem
   - Active users bulan ini
   - Rata-rata XP per user

2. **Top Performers**
   - 10 pegawai dengan XP tertinggi bulan ini
   - Real-time ranking

3. **Recent Activities**
   - Log aktivitas terbaru (posting, like, komentar)
   - Timestamp real-time

4. **Charts** (future enhancement)
   - XP distribution
   - Activity trends
   - Engagement metrics

---

## 🔨 Console Commands untuk Admin

### 1. Reset Monthly Leaderboard
```bash
# Manual reset (awal bulan)
php bin/console app:reset-monthly-leaderboard

# Output:
# ✅ Monthly leaderboard reset successfully
# - Old records archived: 286 users
# - New leaderboard created for [Month Year]
```

**Catatan:**
- Jalankan setiap awal bulan (bisa dijadwalkan via cron)
- Data lama tidak hilang, hanya diarsipkan

**Cron Job Setup (Linux):**
```bash
# Edit crontab
crontab -e

# Tambahkan baris ini (jalankan setiap tanggal 1 jam 00:01)
1 0 1 * * cd /path/to/gembira && php bin/console app:reset-monthly-leaderboard
```

### 2. Recalculate User Levels
```bash
# Preview changes (dry-run)
php bin/console app:recalculate-user-levels --dry-run

# Execute recalculation
php bin/console app:recalculate-user-levels

# With verbose output
php bin/console app:recalculate-user-levels -v
```

**Kapan digunakan:**
- Setelah mengubah LEVEL_RANGES di kode
- Jika ada data corruption
- Untuk audit/verification

**Output:**
```
Summary:
- Total Users Processed: 286
- Users Updated: 15
- Users Unchanged: 271
- Errors: 0

Level Distribution:
Level 1 (🌱 Pemula Ikhlas): 245
Level 2 (🌿 Aktor Kebaikan): 30
Level 3 (🌺 Penggerak Semangat): 8
Level 4 (🌞 Inspirator Ikhlas): 2
Level 5 (🏆 Teladan Kinerja): 1
```

---

## 📊 Monitoring & Analytics

### Database Queries untuk Monitoring:

#### 1. Top 10 Users by Total XP
```sql
SELECT
    nama,
    total_xp,
    current_level,
    current_badge,
    level_title
FROM pegawai
WHERE total_xp IS NOT NULL
ORDER BY total_xp DESC
LIMIT 10;
```

#### 2. Monthly Activity Summary
```sql
SELECT
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as total_quotes,
    COUNT(DISTINCT author) as unique_authors
FROM quote
GROUP BY month
ORDER BY month DESC;
```

#### 3. Most Liked Quotes
```sql
SELECT
    q.id,
    q.content,
    q.author,
    COUNT(i.id) as total_likes
FROM quote q
LEFT JOIN user_quote_interaction i ON q.id = i.quote_id AND i.type = 'like'
GROUP BY q.id
ORDER BY total_likes DESC
LIMIT 10;
```

#### 4. User Engagement Rate
```sql
SELECT
    p.nama,
    p.total_xp,
    COUNT(DISTINCT q.id) as quotes_posted,
    COUNT(DISTINCT qc.id) as comments_made,
    COUNT(DISTINCT i.id) as interactions
FROM pegawai p
LEFT JOIN quote q ON q.author = p.nama
LEFT JOIN quote_comment qc ON qc.author_id = p.id
LEFT JOIN user_quote_interaction i ON i.user_id = p.id
GROUP BY p.id
ORDER BY p.total_xp DESC
LIMIT 20;
```

### Log Files untuk Debugging:

```bash
# Application logs
tail -f var/log/dev.log
tail -f var/log/prod.log

# Web server logs
tail -f /var/log/apache2/error.log   # Apache
tail -f /var/log/nginx/error.log     # Nginx

# PHP errors
tail -f /xampp/apache/logs/error.log  # XAMPP
```

---

## ⚙️ Configuration Files

### 1. XP Rewards Configuration
**File:** `src/Service/UserXpService.php`

**Current values:**
```php
private const XP_REWARDS = [
    'create_quote' => 20,
    'like_quote' => 3,
    'receive_like' => 5,  // Passive income!
    'comment_quote' => 5,
    'share_quote' => 8,
    'view_quote' => 1,    // Max 3x/day
    'save_favorite' => 1,
];
```

**Untuk mengubah:**
1. Edit file `src/Service/UserXpService.php`
2. Ubah nilai di array `XP_REWARDS`
3. Clear cache: `php bin/console cache:clear`
4. Commit & push changes

### 2. Level Ranges Configuration
**File:** `src/Service/UserXpService.php`

**Current ranges:**
```php
private const LEVEL_RANGES = [
    1 => ['min' => 0,     'max' => 3740,   'badge' => '🌱', 'title' => 'Pemula Ikhlas'],
    2 => ['min' => 3741,  'max' => 9350,   'badge' => '🌿', 'title' => 'Aktor Kebaikan'],
    3 => ['min' => 9351,  'max' => 17765,  'badge' => '🌺', 'title' => 'Penggerak Semangat'],
    4 => ['min' => 17766, 'max' => 29985,  'badge' => '🌞', 'title' => 'Inspirator Ikhlas'],
    5 => ['min' => 29986, 'max' => 999999, 'badge' => '🏆', 'title' => 'Teladan Kinerja'],
];
```

**Setelah mengubah:**
1. Run recalculate command: `php bin/console app:recalculate-user-levels`
2. Update UI di `templates/profile/profil.html.twig` (Level System cards)

---

## 🛡️ Security & Permissions

### User Permissions:
- ✅ Users bisa posting/edit/delete **quotes sendiri**
- ✅ Users bisa like/comment semua quotes
- ✅ Users **tidak bisa** edit/delete quotes orang lain
- ✅ Users **tidak bisa** manipulasi XP secara manual

### Admin Permissions:
- ✅ Admin bisa akses Dashboard XP
- ✅ Admin bisa reset monthly leaderboard
- ✅ Admin bisa recalculate user levels
- ✅ Admin bisa **moderate** content (hapus quote tidak pantas)

### To Moderate a Quote (Manual):
```sql
-- Hapus quote tertentu (by ID)
DELETE FROM quote WHERE id = [quote_id];

-- Atau via Symfony
php bin/console doctrine:query:sql "DELETE FROM quote WHERE id = [quote_id]"
```

---

## 🐛 Troubleshooting

### Issue 1: XP Tidak Bertambah

**Check:**
```bash
# Cek log errors
tail -f var/log/dev.log

# Cek apakah UserXpService berjalan
php bin/console debug:container UserXpService
```

**Solution:**
- Clear cache: `php bin/console cache:clear`
- Verify database connection
- Check XP_REWARDS configuration

### Issue 2: Level Tidak Update

**Check:**
```bash
# Verify database column ada
php bin/console doctrine:schema:validate

# Cek user level secara manual
php bin/console doctrine:query:sql "SELECT id, nama, total_xp, current_level FROM pegawai WHERE id = [user_id]"
```

**Solution:**
- Run recalculate: `php bin/console app:recalculate-user-levels`
- Check migration: `php bin/console doctrine:migrations:status`

### Issue 3: Leaderboard Kosong

**Check:**
```sql
-- Cek data leaderboard
SELECT * FROM monthly_leaderboard WHERE year = 2025 AND month = 10;
```

**Solution:**
- Reset leaderboard: `php bin/console app:reset-monthly-leaderboard`
- Verify cron job running (jika pakai cron)

### Issue 4: UI Tidak Update (Cache)

**Solution:**
```bash
# Clear all caches
php bin/console cache:clear
php bin/console cache:warmup

# Browser cache
# Minta user: Ctrl + Shift + R (hard refresh)
```

### Issue 5: Migration Error

**Error:** "Table already exists"

**Solution:**
```bash
# Mark migration as executed
php bin/console doctrine:migrations:version Version20250124000000_AddXpProgressionSystem --add
```

---

## 📈 Performance Optimization

### Database Indexes
Pastikan indexes ada di kolom-kolom ini:
```sql
-- pegawai table
CREATE INDEX idx_total_xp ON pegawai(total_xp);
CREATE INDEX idx_current_level ON pegawai(current_level);

-- quote table
CREATE INDEX idx_created_at ON quote(created_at);
CREATE INDEX idx_author ON quote(author);

-- user_quote_interaction table
CREATE INDEX idx_user_quote ON user_quote_interaction(user_id, quote_id);
CREATE INDEX idx_type ON user_quote_interaction(type);

-- monthly_leaderboard table
CREATE INDEX idx_year_month ON monthly_leaderboard(year, month);
CREATE INDEX idx_rank ON monthly_leaderboard(rank);
```

### Caching Strategy
- Twig templates: Auto-cached by Symfony
- XP calculations: Computed on-demand, cached in entity
- Leaderboard: Query monthly data only (not full history)

---

## 📊 Metrics to Monitor

### Daily Metrics:
1. **Daily Active Users (DAU)**
   - Target: >50% pegawai aktif per hari

2. **Average XP per Active User**
   - Baseline: ~100-200 XP/hari

3. **Quotes Posted per Day**
   - Healthy: 10-30 quotes/hari

4. **Engagement Rate**
   - Likes per quote: Avg 5-10
   - Comments per quote: Avg 2-5

### Weekly Metrics:
1. **Weekly Active Users (WAU)**
   - Target: >80% pegawai aktif per minggu

2. **Top Performers**
   - Track consistency (not just total XP)

3. **Content Quality**
   - Monitor negative/spam content

### Monthly Metrics:
1. **Monthly Active Users (MAU)**
   - Target: 100% pegawai pernah login

2. **Level Distribution**
   - Should follow pyramid: Banyak di Level 1-2, sedikit di Level 5

3. **Leaderboard Competition**
   - Check if same people always win (not healthy)

---

## 🔄 Future Enhancements (Roadmap)

### Phase 1 (Next 3 months):
- [ ] Push notifications untuk level up
- [ ] Email digest mingguan (top quotes)
- [ ] Achievement badges system
- [ ] Quote of the week feature

### Phase 2 (6 months):
- [ ] Mobile app (iOS/Android)
- [ ] Advanced analytics dashboard
- [ ] Quote categories/tags
- [ ] Search & filter functionality

### Phase 3 (1 year):
- [ ] AI-powered content moderation
- [ ] Gamification v2 (team challenges)
- [ ] Integration dengan HR system
- [ ] Reward redemption system

---

## 📞 Support & Contact

### Technical Issues:
- Check logs first: `var/log/dev.log`
- Check database: MySQL/MariaDB console
- Clear cache: `php bin/console cache:clear`

### Code Repository:
- GitHub: https://github.com/echal/gembira.git
- Branch: `master`

### Documentation:
- Developer docs: `docs/` folder
- User guide: `PENJELASAN_UPDATE_UNTUK_PEGAWAI.md`
- This admin guide: `PENJELASAN_UPDATE_UNTUK_ADMIN.md`

---

## ✅ Post-Deployment Verification

### Checklist:
- [ ] Login berhasil
- [ ] Menu IKHLAS muncul
- [ ] Bisa posting quote
- [ ] XP bertambah setelah aktivitas
- [ ] Like/comment berfungsi
- [ ] Leaderboard tampil
- [ ] Admin dashboard accessible
- [ ] Profile menampilkan level yang benar
- [ ] No PHP errors di log
- [ ] Performance normal (load time < 2s)

### Test Accounts:
Buat 2-3 test accounts untuk verify:
1. User biasa (test pegawai flow)
2. Admin user (test admin dashboard)

---

## 📝 Change Log

### Version 1.0.0 (23 Oktober 2025)
- ✅ Initial release sistem IKHLAS
- ✅ XP & Level Progression system
- ✅ Monthly leaderboard
- ✅ Admin dashboard
- ✅ Complete documentation

---

**Deployment berhasil! Sistem siap digunakan! 🚀**

**Questions?** Buka issue di GitHub atau contact developer.

---

*Dokumentasi ini dibuat: 23 Oktober 2025*
*Last updated: Version 1.0.0*
*Prepared by: Claude (Automated)*
