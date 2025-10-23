# 🚀 Quick Test Guide - Tahap 4 Leaderboard

## ⚡ Fast Testing Steps

### 1️⃣ Clear Cache (Already Done ✅)
```bash
php bin/console cache:clear
```
**Status:** ✅ Cache cleared successfully

---

### 2️⃣ Open Main Ikhlas Page
**URL:** `http://localhost/gembira/public/ikhlas`

**What to Check:**
- [ ] Page loads without errors
- [ ] Random quote displays
- [ ] Like/Save buttons work
- [ ] Stats panel appears at bottom
- [ ] Stats show: 5 quotes, 17 interaksi, 17 likes, 8 saves
- [ ] "Lihat Detail Leaderboard" link visible

---

### 3️⃣ Test Stats Panel Auto-Load
**Action:** Wait for page to fully load

**Expected:**
```javascript
// Stats should load via AJAX automatically
{
  "totalQuotes": 5,
  "totalInteractions": 17,
  "totalLikes": 17,
  "totalSaves": 8,
  "totalActiveUsers": 6
}
```

**Check DevTools Console:**
- [ ] No JavaScript errors
- [ ] AJAX request to `/ikhlas/api/stats` succeeds
- [ ] Stats display in grid (2x2 on mobile, 4 columns on desktop)

---

### 4️⃣ Open Leaderboard Page
**URL:** `http://localhost/gembira/public/ikhlas/leaderboard`

**What to Check:**

#### A. Global Stats Banner
- [ ] Purple/pink gradient background
- [ ] Shows 4 metrics in grid:
  - 📚 Total Quotes: **5**
  - 💬 Total Interaksi: **17**
  - ❤️ Total Likes: **17**
  - 📌 Total Saves: **8**

#### B. Top 10 Leaderboard
- [ ] Rank 1: **FAISAL KASIM** with 👑 gold gradient (11 poin)
- [ ] Rank 2: **SYAMSUL** with 🥈 silver gradient (8 poin)
- [ ] Rank 3: **ABD. KADIR AMIN** with 🥉 bronze gradient (7 poin)
- [ ] Rank 4: **TAUHID** with 🏅 blue gradient (4 poin)
- [ ] Rank 5: **BHAKTY MAULANA** with 🏅 blue gradient (2 poin)
- [ ] Rank 6: **I GEDE KARNAWA** with 🏅 blue gradient (1 poin)
- [ ] Point breakdown shows (❤️ X poin • 📌 Y poin)

#### C. Top Quotes Section
- [ ] Shows 3 popular quotes
- [ ] Quote #1: "Ikhlas adalah kunci..." (5 likes, 3 saves)
- [ ] Quote #2: "Bekerja dengan ikhlas..." (4 likes, 1 save)
- [ ] Quote #3: "Setiap hari adalah..." (3 likes, 2 saves)
- [ ] Each quote shows author name

#### D. User Rank Card
**Note:** This only appears if logged-in user has interactions
- [ ] Check if card appears for test users (FAISAL, SYAMSUL, etc.)
- [ ] Shows user's rank number
- [ ] Shows user's total points
- [ ] Shows badge emoji

#### E. Navigation
- [ ] "← Kembali ke Menu Ikhlas" button works
- [ ] Bottom navigation shows active "Ikhlas" menu

---

### 5️⃣ Test Responsive Design

#### Mobile View (<640px)
- [ ] Stats grid: 2 columns
- [ ] Font sizes reduced
- [ ] Names truncate with ellipsis if too long
- [ ] Cards stack vertically
- [ ] Touch targets large enough

#### Desktop View (>768px)
- [ ] Stats grid: 4 columns
- [ ] Full names display
- [ ] More padding/spacing
- [ ] Larger fonts

---

### 6️⃣ Test API Endpoints

#### A. Global Stats API
```bash
# Using curl (if logged in)
curl http://localhost/gembira/public/ikhlas/api/stats
```

**Expected Response:**
```json
{
  "totalQuotes": 5,
  "totalInteractions": 17,
  "totalLikes": 17,
  "totalSaves": 8,
  "totalActiveUsers": 6
}
```

#### B. Personal Stats API
```bash
# Requires authentication
curl http://localhost/gembira/public/ikhlas/api/my-stats
```

**Expected Response (for user with interactions):**
```json
{
  "totalInteractions": 5,
  "totalLikes": 5,
  "totalSaves": 3,
  "rank": 1,
  "totalPoints": 11,
  "badge": "👑"
}
```

---

### 7️⃣ Test Caching

#### Test Cache Behavior
1. Open leaderboard page
2. Note the load time
3. Refresh page within 60 seconds
4. Should load faster (from cache)
5. Wait 60+ seconds
6. Refresh again
7. Should query database again

#### Clear Cache Manually
```bash
php bin/console cache:clear
```

---

### 8️⃣ Test Interactions

#### Create New Interaction
1. Go to main Ikhlas page
2. Click Like ❤️ on a quote
3. Click Save 📌 on same quote
4. Wait 60 seconds (cache expires)
5. Go to leaderboard
6. Check if your points increased:
   - Like = +1 poin
   - Save = +2 poin
   - Total = +3 poin

---

### 9️⃣ Browser Console Checks

#### No Errors Expected
```javascript
// Console should be clean, no red errors
✅ No 404 errors
✅ No JavaScript syntax errors
✅ No CORS errors
✅ AJAX requests succeed (200 status)
```

#### Network Tab
- [ ] `/ikhlas/api/stats` returns 200
- [ ] Response type: `application/json`
- [ ] Response time: < 500ms (with cache)

---

### 🔟 Database Verification

#### Current Data
```bash
mysql -u root -e "USE gembira_db;
SELECT p.nama,
       SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as likes,
       SUM(CASE WHEN i.saved = 1 THEN 2 ELSE 0 END) as saves,
       (SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN i.saved = 1 THEN 2 ELSE 0 END)) as total
FROM pegawai p
LEFT JOIN user_quotes_interaction i ON i.user_id = p.id
GROUP BY p.id, p.nama
HAVING total > 0
ORDER BY total DESC
LIMIT 6;"
```

**Expected Output:**
```
nama                     | likes | saves | total
-------------------------|-------|-------|------
FAISAL KASIM, S.Kom     |   5   |   6   |  11
SYAMSUL, SE             |   4   |   4   |   8
ABD. KADIR AMIN, S. HI  |   3   |   4   |   7
TAUHID, S.Ag            |   2   |   2   |   4
BHAKTY MAULANA, S.H     |   2   |   0   |   2
I GEDE KARNAWA, S.Ag    |   1   |   0   |   1
```

---

## ✅ Success Criteria

### Must Pass
- [x] Cache cleared successfully
- [x] Routes registered (8 total)
- [x] Service autowired
- [x] Database tables exist
- [x] Sample data inserted (5 quotes, 17 interactions)
- [ ] Main page loads without errors
- [ ] Stats panel displays correctly
- [ ] Leaderboard page shows top 6 users
- [ ] Badges display correctly (👑🥈🥉🏅)
- [ ] Gradient colors applied to top 3
- [ ] Top quotes section shows 3 quotes
- [ ] API endpoints return valid JSON
- [ ] Responsive design works on mobile

### Nice to Have
- [ ] Rank 1 has pulse animation
- [ ] Auto-refresh works (60s interval)
- [ ] Smooth transitions/animations
- [ ] Loading states for AJAX
- [ ] Toast notifications work

---

## 🐛 Common Issues & Solutions

### Issue: Page shows 500 error
**Solution:** Check `var/log/dev.log` for errors
```bash
tail -f var/log/dev.log
```

### Issue: Stats panel doesn't load
**Solution:** Check browser console for JS errors
- Verify AJAX endpoint URL is correct
- Check if user is authenticated

### Issue: Leaderboard is empty
**Solution:** Verify sample data exists
```bash
mysql -u root -e "USE gembira_db; SELECT COUNT(*) FROM user_quotes_interaction;"
```
Should return: 17

### Issue: Cache not clearing
**Solution:** Try manual cache clear
```bash
rm -rf var/cache/dev
php bin/console cache:clear
```

### Issue: Service not found
**Solution:** Check autowiring
```bash
php bin/console debug:container IkhlasLeaderboardService
```

---

## 📸 Expected Screenshots

### Main Page
```
┌──────────────────────────────────────────┐
│ ← IKHLAS                      🔔 [User] │
├──────────────────────────────────────────┤
│                                          │
│  "Quote text here..."                   │
│  - Author                                │
│                                          │
│  [← Sebelumnya]      [Selanjutnya →]   │
│  [❤️ Like]  [📌 Save]  [🔀 Random]     │
│                                          │
│  ═══════════════ QUICK LINKS ══════════ │
│  [Favorit Saya] [Leaderboard]          │
│                                          │
│  ════════════ STATISTIK ════════════    │
│  📚 5    💬 17    ❤️ 17    📌 8        │
│  → Lihat Detail Leaderboard            │
└──────────────────────────────────────────┘
```

### Leaderboard Page
```
┌──────────────────────────────────────────┐
│ ← LEADERBOARD            🔔 [User]      │
├──────────────────────────────────────────┤
│  📊 STATISTIK GLOBAL                    │
│  ┌──────┬──────┬──────┬──────┐        │
│  │  5   │  17  │  17  │  8   │        │
│  └──────┴──────┴──────┴──────┘        │
│                                          │
│  🏆 TOP 10 PENGGUNA                     │
│  ┌────────────────────────────┐        │
│  │ 👑 FAISAL KASIM    11 poin │ (Gold) │
│  ├────────────────────────────┤        │
│  │ 🥈 SYAMSUL         8 poin  │ (Silver)│
│  ├────────────────────────────┤        │
│  │ 🥉 ABD. KADIR      7 poin  │ (Bronze)│
│  ├────────────────────────────┤        │
│  │ 🏅 TAUHID          4 poin  │ (Blue) │
│  └────────────────────────────┘        │
│                                          │
│  ✨ QUOTES TERPOPULER                   │
│  [Quote 1]  ❤️ 5  📌 3                 │
│  [Quote 2]  ❤️ 4  📌 1                 │
│  [Quote 3]  ❤️ 3  📌 2                 │
│                                          │
│  [← Kembali ke Menu Ikhlas]            │
└──────────────────────────────────────────┘
```

---

## 🎯 Testing Priority

### High Priority (Must Test First)
1. ✅ Cache clear - **DONE**
2. Main page loads
3. Stats panel displays
4. Leaderboard page loads
5. Top 10 displays correctly

### Medium Priority
6. API endpoints return JSON
7. Responsive design
8. User rank card (if applicable)
9. Top quotes section

### Low Priority
10. Animations/transitions
11. Auto-refresh
12. Cache behavior

---

**Last Updated:** 2025-10-21
**Status:** Ready for Manual Testing
**Estimated Test Time:** 15-20 minutes

---

## 🚦 Quick Status Check

Run this command to verify everything is ready:
```bash
# Check all routes
php bin/console debug:router | grep ikhlas

# Check service
php bin/console debug:container IkhlasLeaderboardService

# Check database
mysql -u root -e "USE gembira_db; SELECT COUNT(*) as quotes FROM quotes; SELECT COUNT(*) as interactions FROM user_quotes_interaction;"
```

**All Green?** → Start browser testing! 🎉
