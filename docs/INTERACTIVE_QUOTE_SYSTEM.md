# Interactive Quote System (Tahap 7)

**Status:** ✅ Completed
**Date:** 2025-10-21
**Version:** 1.0.0

## Overview

Sistem interaksi quote yang mirip dengan media sosial, memungkinkan user untuk:
- Like dan save quotes dengan tracking real-time
- Menulis dan membaca komentar
- Melihat statistik (views, likes, comments)
- Share quotes ke WhatsApp

## Features Implemented

### 1. Statistics Tracking
- **View Count**: Otomatis bertambah setiap kali quote ditampilkan
- **Like Count**: Real-time update saat user like/unlike
- **Comment Count**: Update otomatis saat ada komentar baru

### 2. Comment System
- User dapat menulis komentar pada setiap quote
- Komentar ditampilkan dengan nama user dan timestamp
- Real-time loading tanpa page reload
- XSS protection dengan HTML escaping

### 3. WhatsApp Share
- Share quote lengkap dengan author ke WhatsApp
- Format teks yang rapi dan professional
- Branding "GEMBIRA - Ikhlas"

## Database Changes

### Table: `quotes`
```sql
ALTER TABLE quotes
ADD COLUMN total_likes INT DEFAULT 0,
ADD COLUMN total_comments INT DEFAULT 0,
ADD COLUMN total_views INT DEFAULT 0;
```

### Table: `user_quotes_interaction`
```sql
ALTER TABLE user_quotes_interaction
ADD COLUMN comment TEXT NULL;
```

## Backend Implementation

### 1. Controller Endpoints

#### Track View Count
**File:** `src/Controller/IkhlasController.php`
**Method:** `index()`
```php
// Track view count
$quote->incrementViews();
$this->em->persist($quote);
$this->em->flush();
```

#### Update Like Counter
**Method:** `interactWithQuote()`
```php
if ($action === 'like') {
    // Update quote's like counter
    if ($newStatus && !$oldStatus) {
        $quote->incrementLikes();
    } elseif (!$newStatus && $oldStatus) {
        $quote->decrementLikes();
    }

    $this->em->persist($quote);
    $this->em->flush();

    return new JsonResponse([
        'totalLikes' => $quote->getTotalLikes()
    ]);
}
```

#### Add Comment
**Route:** `POST /ikhlas/api/quotes/{id}/comment`
**Method:** `addComment(int $id, Request $request)`

**Request Body:**
```json
{
  "comment": "Komentar user"
}
```

**Response:**
```json
{
  "success": true,
  "message": "💬 Komentar berhasil ditambahkan!",
  "comment": {
    "id": 123,
    "user": "John Doe",
    "comment": "Komentar user",
    "createdAt": "21 Oct 2025 14:30"
  },
  "totalComments": 5
}
```

#### Get Comments
**Route:** `GET /ikhlas/api/quotes/{id}/comments`
**Method:** `getComments(int $id)`

**Response:**
```json
{
  "success": true,
  "comments": [
    {
      "id": 123,
      "user": "John Doe",
      "comment": "Sangat inspiratif!",
      "createdAt": "21 Oct 2025 14:30"
    }
  ],
  "total": 1
}
```

### 2. Entity Updates

#### Quote Entity
**File:** `src/Entity/Quote.php`

**New Properties:**
```php
#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
private int $totalLikes = 0;

#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
private int $totalComments = 0;

#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
private int $totalViews = 0;
```

**New Methods:**
- `incrementLikes()`: Menambah counter likes
- `decrementLikes()`: Mengurangi counter likes (min 0)
- `incrementComments()`: Menambah counter comments
- `incrementViews()`: Menambah counter views
- `getTotalLikes()`, `getTotalComments()`, `getTotalViews()`

#### UserQuoteInteraction Entity
**File:** `src/Entity/UserQuoteInteraction.php`

**New Property:**
```php
#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $comment = null;
```

**New Methods:**
- `getComment()`: Get komentar user
- `setComment(?string $comment)`: Set komentar (update timestamp otomatis)
- `hasComment()`: Check apakah ada komentar

## Frontend Implementation

### 1. Statistics Display

**Location:** `templates/ikhlas/index.html.twig`

```html
<!-- Quote Statistics -->
<div class="bg-gray-50 rounded-xl p-4 mb-6">
    <div class="flex justify-around items-center text-center">
        <!-- Views -->
        <div class="flex flex-col items-center">
            <span class="text-2xl mb-1">👁️</span>
            <span id="quoteViews" class="text-lg font-bold text-gray-800">{{ quote.totalViews }}</span>
            <span class="text-xs text-gray-600">Views</span>
        </div>
        <!-- Likes -->
        <div class="flex flex-col items-center">
            <span class="text-2xl mb-1">❤️</span>
            <span id="quoteLikes" class="text-lg font-bold text-gray-800">{{ quote.totalLikes }}</span>
            <span class="text-xs text-gray-600">Likes</span>
        </div>
        <!-- Comments -->
        <div class="flex flex-col items-center cursor-pointer" id="commentsCountBtn">
            <span class="text-2xl mb-1">💬</span>
            <span id="quoteComments" class="text-lg font-bold text-gray-800">{{ quote.totalComments }}</span>
            <span class="text-xs text-gray-600">Comments</span>
        </div>
        <!-- Share -->
        <div class="flex flex-col items-center cursor-pointer" id="shareBtn">
            <span class="text-2xl mb-1">🔗</span>
            <span class="text-xs text-gray-600 mt-3">Share</span>
        </div>
    </div>
</div>
```

### 2. Comments Section

```html
<!-- Comments Section -->
<div id="commentsSection" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 hidden">
    <!-- Comment Form -->
    <div class="mb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">💬 Tulis Komentar</h3>
        <div class="flex gap-2">
            <textarea id="commentInput" rows="2"
                      placeholder="Bagikan pemikiran Anda..."
                      class="flex-1 px-4 py-2 border rounded-lg"></textarea>
            <button id="submitCommentBtn"
                    class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                Kirim
            </button>
        </div>
    </div>

    <!-- Comments List -->
    <div id="commentsList" class="space-y-3">
        <!-- Comments loaded dynamically -->
    </div>
</div>
```

### 3. JavaScript Functions

#### Toggle Comments
```javascript
function toggleComments() {
    if (commentsSection.classList.contains('hidden')) {
        commentsSection.classList.remove('hidden');
        loadComments();
    } else {
        commentsSection.classList.add('hidden');
    }
}
```

#### Submit Comment
```javascript
async function submitComment() {
    const quoteId = currentQuoteId.value;
    const comment = commentInput.value.trim();

    const response = await fetch(`/ikhlas/api/quotes/${quoteId}/comment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment: comment })
    });

    const data = await response.json();

    if (data.success) {
        commentInput.value = '';
        quoteComments.textContent = data.totalComments;
        loadComments();
    }
}
```

#### Share to WhatsApp
```javascript
function shareToWhatsApp() {
    const content = quoteContent.textContent.replace(/^"|"$/g, '').trim();
    const author = quoteAuthor.textContent.replace(/^-\s*|-\s*$/g, '').trim();

    const shareText = `${content}\n\n- ${author}\n\n✨ Dibagikan dari GEMBIRA - Ikhlas\n(Inspirasi Kehidupan Lahirkan Semangat)`;

    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
    window.open(whatsappUrl, '_blank');
}
```

#### Update Like Count
```javascript
// In handleInteraction function
if (action === 'like') {
    updateLikeButton(data.status);
    const quoteLikes = document.getElementById('quoteLikes');
    if (quoteLikes && data.totalLikes !== undefined) {
        quoteLikes.textContent = data.totalLikes;
    }
}
```

## User Flow

### Viewing Statistics
1. User membuka halaman Ikhlas
2. Quote ditampilkan dengan statistik (views, likes, comments)
3. View count otomatis bertambah

### Like Quote
1. User klik tombol Like
2. AJAX request ke `/ikhlas/api/interact`
3. Backend update `user_quotes_interaction` dan `quotes.total_likes`
4. Response mengembalikan `totalLikes` baru
5. Frontend update counter secara real-time
6. Toast notification muncul

### Comment on Quote
1. User klik icon Comments untuk toggle section
2. Comments dimuat via AJAX dari `/ikhlas/api/quotes/{id}/comments`
3. User menulis komentar di textarea
4. User klik "Kirim"
5. AJAX request ke `/ikhlas/api/quotes/{id}/comment`
6. Backend save comment dan update `quotes.total_comments`
7. Frontend reload comments dan update counter

### Share to WhatsApp
1. User klik icon Share
2. JavaScript ekstrak content dan author quote
3. Format teks dengan branding GEMBIRA
4. Buka WhatsApp Web dengan pre-filled message
5. User tinggal pilih contact dan send

## Security Considerations

### XSS Protection
```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
```

Semua komentar di-escape sebelum ditampilkan untuk mencegah XSS attacks.

### Input Validation
- Backend: Validasi comment tidak boleh kosong
- Frontend: Trim whitespace sebelum submit
- Database: Comment column adalah TEXT nullable

### SQL Injection Protection
- Menggunakan Doctrine ORM query builder
- Parameter binding otomatis
- Prepared statements

## Testing Checklist

- [x] View count bertambah setiap kali quote ditampilkan
- [x] Like count update saat like/unlike
- [x] Comment dapat ditulis dan disimpan
- [x] Comments list loading dengan benar
- [x] Comment count update setelah submit
- [x] WhatsApp share membuka dengan text yang benar
- [x] XSS protection berfungsi
- [x] Real-time updates tanpa page reload
- [x] Toast notifications muncul dengan benar
- [x] Responsive design di mobile

## Performance Optimization

1. **Lazy Loading Comments**: Comments hanya dimuat saat section dibuka
2. **Debouncing**: Tidak ada auto-refresh untuk comments (manual only)
3. **Minimal DB Queries**: View increment dalam satu query
4. **Frontend Caching**: Statistics tidak auto-refresh (kecuali manual reload)

## Future Enhancements

### Possible Improvements:
1. **Edit/Delete Comment**: Allow users to edit/delete their own comments
2. **Reply to Comments**: Nested comment system
3. **Like Comments**: Users can like individual comments
4. **Share Options**: Add Facebook, Twitter, Telegram
5. **Notification**: Notify quote author when someone comments
6. **Pagination**: Paginate comments if too many
7. **Real-time Updates**: WebSocket for live comment updates
8. **Comment Moderation**: Admin approval system
9. **Rich Text**: Allow formatting in comments (bold, italic)
10. **Emoji Reactions**: Quick reactions beyond just like

## API Reference

### POST /ikhlas/api/interact
Update like/save status

**Request:**
```json
{
  "quoteId": 123,
  "action": "like"
}
```

**Response:**
```json
{
  "success": true,
  "action": "like",
  "status": true,
  "totalLikes": 42,
  "message": "❤️ Anda menyukai quote ini! +2 poin",
  "level_up": null
}
```

### POST /ikhlas/api/quotes/{id}/comment
Add comment to quote

**Request:**
```json
{
  "comment": "Sangat inspiratif!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "💬 Komentar berhasil ditambahkan!",
  "comment": {
    "id": 456,
    "user": "John Doe",
    "comment": "Sangat inspiratif!",
    "createdAt": "21 Oct 2025 14:30"
  },
  "totalComments": 5
}
```

### GET /ikhlas/api/quotes/{id}/comments
Get all comments for a quote

**Response:**
```json
{
  "success": true,
  "comments": [
    {
      "id": 456,
      "user": "John Doe",
      "comment": "Sangat inspiratif!",
      "createdAt": "21 Oct 2025 14:30"
    }
  ],
  "total": 1
}
```

## Files Modified

### Backend
- `src/Controller/IkhlasController.php` - Added comment endpoints and view tracking
- `src/Entity/Quote.php` - Added statistics fields and methods
- `src/Entity/UserQuoteInteraction.php` - Added comment field

### Frontend
- `templates/ikhlas/index.html.twig` - Added statistics display, comments section, and JavaScript

### Database
- `quotes` table - Added total_likes, total_comments, total_views columns
- `user_quotes_interaction` table - Added comment column

## Conclusion

Tahap 7 Interactive Quote System telah berhasil diimplementasikan dengan lengkap. Sistem ini menyediakan pengalaman interaktif seperti media sosial untuk quotes, dengan tracking statistik real-time, sistem komentar, dan fitur share ke WhatsApp.

**Next Steps:**
- Monitor user engagement dengan statistics
- Collect feedback untuk improvements
- Consider implementing future enhancements
- Optimize performance jika ada bottleneck

---

**Developed with ❤️ for GEMBIRA - Ikhlas**
*Inspirasi Kehidupan Lahirkan Semangat*
