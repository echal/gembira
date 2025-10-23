# Testing Checklist - Tahap 7 Interactive Quote System

## Pre-Testing Setup

### ✅ Database Verification
```bash
# Check columns exist
mysql -u root gembira_db -e "DESCRIBE quotes;" | grep -E "(total_likes|total_comments|total_views)"
mysql -u root gembira_db -e "DESCRIBE user_quotes_interaction;" | grep comment

# Expected output:
# total_likes      int(11)  YES    0
# total_comments   int(11)  YES    0
# total_views      int(11)  YES    0
# comment          text     YES    NULL
```

### ✅ Clear Symfony Cache
```bash
php bin/console cache:clear
```

### ✅ Browser DevTools Ready
- Open F12 Developer Tools
- Check Console tab for errors
- Check Network tab for AJAX requests

---

## Functional Testing

### 1. View Count Tracking

**Test Case 1.1: Initial View**
- [ ] Navigate to `/ikhlas`
- [ ] Page loads successfully
- [ ] Quote displays with statistics section
- [ ] Check database: `SELECT total_views FROM quotes WHERE id = [quote_id];`
- [ ] Expected: View count incremented by 1

**Test Case 1.2: Multiple Views**
- [ ] Refresh page 5 times
- [ ] Check database after each refresh
- [ ] Expected: View count increases by 5

**Test Case 1.3: View Counter Display**
- [ ] Check if `👁️ Views` counter shows correct number
- [ ] Expected: Matches database value

---

### 2. Like System

**Test Case 2.1: First Like**
- [ ] Click ❤️ Like button
- [ ] Button changes from 🤍 white to ❤️ red
- [ ] Toast notification appears: "❤️ Anda menyukai quote ini! +2 poin"
- [ ] Like counter updates instantly (e.g., 42 → 43)
- [ ] Check database: `SELECT total_likes FROM quotes WHERE id = [quote_id];`
- [ ] Expected: total_likes increased by 1

**Test Case 2.2: Unlike**
- [ ] Click ❤️ Like button again (toggle off)
- [ ] Button changes from ❤️ red to 🤍 white
- [ ] Toast notification: "Like dibatalkan"
- [ ] Like counter decreases (e.g., 43 → 42)
- [ ] Check database
- [ ] Expected: total_likes decreased by 1

**Test Case 2.3: Like Counter Accuracy**
- [ ] Multiple users like the same quote
- [ ] Each like increments counter
- [ ] Counter always matches database
- [ ] Expected: Real-time accuracy

**Test Case 2.4: Level Up on Like**
- [ ] User dengan points mendekati threshold
- [ ] Like a quote
- [ ] Expected: SweetAlert modal dengan badge baru muncul

---

### 3. Comment System

**Test Case 3.1: Open Comments Section**
- [ ] Click 💬 Comments icon
- [ ] Comments section slides down (removes `hidden` class)
- [ ] AJAX request to `/ikhlas/api/quotes/{id}/comments`
- [ ] Check Network tab: Status 200 OK
- [ ] Expected: Comments load or "Belum ada komentar" message

**Test Case 3.2: Submit Valid Comment**
- [ ] Type "Sangat inspiratif!" in textarea
- [ ] Click "Kirim" button
- [ ] Button disabled dengan text "Mengirim..."
- [ ] AJAX request to `/ikhlas/api/quotes/{id}/comment`
- [ ] Toast: "💬 Komentar berhasil ditambahkan!"
- [ ] Comment appears in list with username and timestamp
- [ ] Comment counter updates (e.g., 5 → 6)
- [ ] Textarea clears
- [ ] Button re-enables with text "Kirim"
- [ ] Check database: `SELECT comment FROM user_quotes_interaction WHERE quote_id = [id] AND user_id = [user_id];`
- [ ] Expected: Comment saved correctly

**Test Case 3.3: Submit Empty Comment**
- [ ] Leave textarea empty
- [ ] Click "Kirim"
- [ ] Expected: Toast error "❌ Komentar tidak boleh kosong"
- [ ] No AJAX request sent
- [ ] No database change

**Test Case 3.4: Submit Whitespace Only**
- [ ] Type "   " (spaces only) in textarea
- [ ] Click "Kirim"
- [ ] Expected: Toast error "❌ Komentar tidak boleh kosong"

**Test Case 3.5: XSS Attack Prevention**
- [ ] Type `<script>alert('XSS')</script>` in textarea
- [ ] Submit comment
- [ ] Expected: Comment saves but renders as plain text
- [ ] No alert dialog appears
- [ ] HTML is escaped in display

**Test Case 3.6: Long Comment**
- [ ] Type 500+ characters comment
- [ ] Submit
- [ ] Expected: Comment saves and displays properly
- [ ] No truncation (unless designed)

**Test Case 3.7: Multiple Comments**
- [ ] Submit 3 different comments
- [ ] Expected: All appear in reverse chronological order
- [ ] Each with correct username and timestamp
- [ ] Comment counter shows 3

**Test Case 3.8: Close Comments Section**
- [ ] Click 💬 icon again
- [ ] Expected: Section slides up (adds `hidden` class)

---

### 4. WhatsApp Share

**Test Case 4.1: Share Basic Quote**
- [ ] Click 🔗 Share icon
- [ ] WhatsApp Web opens in new tab
- [ ] Message is pre-filled with correct format:
  ```
  "Quote content here..."

  - Author Name

  ✨ Dibagikan dari GEMBIRA - Ikhlas
  (Inspirasi Kehidupan Lahirkan Semangat)
  ```
- [ ] Toast: "🔗 Membuka WhatsApp..."
- [ ] Expected: Text correctly formatted

**Test Case 4.2: Share Quote with Special Characters**
- [ ] Quote with emoji: "Life is 💯 amazing!"
- [ ] Click Share
- [ ] Expected: Emoji preserved in WhatsApp

**Test Case 4.3: Share Quote with Quotes**
- [ ] Quote: `She said "Hello"`
- [ ] Click Share
- [ ] Expected: Nested quotes handled correctly

**Test Case 4.4: Mobile WhatsApp App**
- [ ] Test on mobile device
- [ ] Click Share
- [ ] Expected: WhatsApp app opens (not web)

---

### 5. Statistics Display

**Test Case 5.1: All Counters Visible**
- [ ] Load quote page
- [ ] Check statistics section shows 4 items:
  - [ ] 👁️ Views with number
  - [ ] ❤️ Likes with number
  - [ ] 💬 Comments with number
  - [ ] 🔗 Share button
- [ ] Expected: All visible and properly aligned

**Test Case 5.2: Counter Updates**
- [ ] Like quote → Like counter updates
- [ ] Submit comment → Comment counter updates
- [ ] Refresh page → View counter updates
- [ ] Expected: All counters accurate

**Test Case 5.3: Zero Values**
- [ ] New quote with no interactions
- [ ] Expected: Shows "0" for each metric (not hidden)

---

## UI/UX Testing

### 6. Responsive Design

**Test Case 6.1: Mobile (375px)**
- [ ] Open DevTools → Device toolbar → iPhone SE
- [ ] Check statistics section:
  - [ ] Icons and numbers visible
  - [ ] No horizontal scroll
  - [ ] Text not cut off
- [ ] Check comments section:
  - [ ] Textarea full width
  - [ ] Submit button accessible
  - [ ] Comments readable

**Test Case 6.2: Tablet (768px)**
- [ ] Test on iPad size
- [ ] Layout adjusts properly
- [ ] Touch targets minimum 48px

**Test Case 6.3: Desktop (1920px)**
- [ ] Wide screen display
- [ ] Content centered
- [ ] No excessive stretching

---

### 7. Accessibility

**Test Case 7.1: Keyboard Navigation**
- [ ] Tab through all interactive elements
- [ ] Like button focusable
- [ ] Save button focusable
- [ ] Comment textarea focusable
- [ ] Submit button focusable
- [ ] Share button focusable
- [ ] Expected: Logical tab order

**Test Case 7.2: Focus States**
- [ ] Tab to each button
- [ ] Expected: Visible focus outline (2px blue)

**Test Case 7.3: Touch Targets**
- [ ] All buttons minimum 48x48px
- [ ] Easy to tap on mobile
- [ ] No accidental clicks

---

### 8. Performance

**Test Case 8.1: Page Load Time**
- [ ] Hard refresh (Ctrl+Shift+R)
- [ ] Check Network tab → DOMContentLoaded
- [ ] Expected: < 2 seconds

**Test Case 8.2: AJAX Response Time**
- [ ] Submit comment
- [ ] Check Network tab → Response time
- [ ] Expected: < 500ms

**Test Case 8.3: Comments Load Time**
- [ ] Open comments with 50+ comments
- [ ] Check load time
- [ ] Expected: < 1 second

**Test Case 8.4: No Memory Leaks**
- [ ] Navigate through 20 quotes
- [ ] Check DevTools → Performance → Memory
- [ ] Expected: No significant memory increase

---

### 9. Error Handling

**Test Case 9.1: Network Error - Comment Submit**
- [ ] Open DevTools → Network tab
- [ ] Set throttling to "Offline"
- [ ] Try to submit comment
- [ ] Expected: Toast error "❌ Terjadi kesalahan"
- [ ] Button re-enables

**Test Case 9.2: Network Error - Load Comments**
- [ ] Set offline mode
- [ ] Click comments icon
- [ ] Expected: Error message in comments section

**Test Case 9.3: Invalid Quote ID**
- [ ] Manually call `/ikhlas/api/quotes/99999/comments`
- [ ] Expected: 404 response with error message

**Test Case 9.4: Empty Textarea Blur**
- [ ] Focus textarea
- [ ] Type then delete all text
- [ ] Blur (click outside)
- [ ] Expected: No error, just empty

---

### 10. Security Testing

**Test Case 10.1: XSS via Comment**
- [ ] Submit: `<script>alert('xss')</script>`
- [ ] Expected: Displayed as plain text, no alert

**Test Case 10.2: XSS via HTML**
- [ ] Submit: `<img src=x onerror=alert('xss')>`
- [ ] Expected: Displayed as text, no image

**Test Case 10.3: SQL Injection**
- [ ] Submit: `'; DROP TABLE quotes; --`
- [ ] Expected: Saved as text, no DB damage

**Test Case 10.4: Authentication Required**
- [ ] Logout
- [ ] Try to access `/ikhlas/api/quotes/1/comment`
- [ ] Expected: 401 Unauthorized or redirect to login

---

### 11. Integration Testing

**Test Case 11.1: Like + Gamification**
- [ ] Like quote
- [ ] Expected: +2 points added to user
- [ ] Check `user_points` table

**Test Case 11.2: Like + Level Up**
- [ ] User at 50 points (near level 2 threshold: 51)
- [ ] Like quote (+2 points = 52 total)
- [ ] Expected: Level up modal appears

**Test Case 11.3: Comment + Statistics**
- [ ] Submit comment
- [ ] Expected: `quotes.total_comments` increments
- [ ] Frontend counter updates

**Test Case 11.4: View + Statistics**
- [ ] Load page
- [ ] Expected: `quotes.total_views` increments
- [ ] No user notification needed

---

### 12. Cross-Browser Testing

**Test Case 12.1: Chrome**
- [ ] All features work
- [ ] No console errors

**Test Case 12.2: Firefox**
- [ ] All features work
- [ ] WhatsApp share works

**Test Case 12.3: Safari (Mobile)**
- [ ] Touch events work
- [ ] WhatsApp app opens

**Test Case 12.4: Edge**
- [ ] Modern Edge (Chromium)
- [ ] All features functional

---

### 13. Database Integrity

**Test Case 13.1: Concurrent Likes**
- [ ] Two users like same quote simultaneously
- [ ] Expected: Both recorded, counter accurate

**Test Case 13.2: Concurrent Comments**
- [ ] Multiple users comment at once
- [ ] Expected: All saved, no data loss

**Test Case 13.3: Foreign Key Constraints**
- [ ] Delete user
- [ ] Expected: User's comments also deleted (CASCADE)

**Test Case 13.4: NULL Handling**
- [ ] User with no comment (comment = NULL)
- [ ] Expected: Not shown in comments list

---

## Regression Testing

### 14. Existing Features Still Work

**Test Case 14.1: Auto-Play**
- [ ] Quotes still auto-advance every 15 seconds
- [ ] Pause button works
- [ ] Progress bar animates

**Test Case 14.2: Manual Navigation**
- [ ] Previous button works
- [ ] Next button works
- [ ] Quote transitions smooth

**Test Case 14.3: Save Functionality**
- [ ] Save button still works
- [ ] Saved quotes appear in favorites
- [ ] +5 points awarded

**Test Case 14.4: Leaderboard**
- [ ] Gamification points counted correctly
- [ ] User ranking accurate
- [ ] Statistics updated

---

## Test Results Summary

### Pass/Fail Tracking

```
Total Test Cases: 60+

Functional Tests:     [ ] Pass  [ ] Fail
UI/UX Tests:          [ ] Pass  [ ] Fail
Performance Tests:    [ ] Pass  [ ] Fail
Security Tests:       [ ] Pass  [ ] Fail
Integration Tests:    [ ] Pass  [ ] Fail
Regression Tests:     [ ] Pass  [ ] Fail

Overall Status:       [ ] PASS  [ ] FAIL
```

### Critical Bugs Found
```
1. [Bug Description]
   Severity: High/Medium/Low
   Steps to reproduce:
   Expected:
   Actual:

2. ...
```

### Known Issues
```
1. [Issue Description]
   Impact: High/Medium/Low
   Workaround:

2. ...
```

---

## Post-Testing Actions

### If All Tests Pass ✅
1. [ ] Mark Tahap 7 as PRODUCTION READY
2. [ ] Deploy to production
3. [ ] Monitor error logs for 24h
4. [ ] Collect user feedback

### If Tests Fail ❌
1. [ ] Document all failures
2. [ ] Prioritize by severity
3. [ ] Fix critical bugs first
4. [ ] Re-run full test suite
5. [ ] Update documentation

---

## Automated Testing Commands

```bash
# Database tests
mysql -u root gembira_db < tests/tahap7_database_tests.sql

# PHP unit tests (if created)
php bin/phpunit tests/Controller/IkhlasControllerTest.php

# Check for PHP errors
php -l src/Controller/IkhlasController.php
php -l src/Entity/Quote.php
php -l src/Entity/UserQuoteInteraction.php

# Cache clear
php bin/console cache:clear --env=prod
```

---

## Sign-Off

**Tested By:** ___________________________

**Date:** ___________________________

**Result:** [ ] PASS  [ ] FAIL

**Notes:**
```
_________________________________________________
_________________________________________________
_________________________________________________
```

**Approved for Production:** [ ] YES  [ ] NO

**Signature:** ___________________________

---

**Testing completed for GEMBIRA - Tahap 7 Interactive Quote System** ✅
