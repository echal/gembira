# Tahap 8: Komentar Berbasis Profil Pengguna

**Status:** ✅ Completed
**Date:** 21 October 2025
**Version:** 1.0.0

## 🎯 Tujuan

Mengupgrade sistem komentar dengan fitur lengkap berbasis profil pengguna:
- Menampilkan foto profil, nama, jabatan, dan unit kerja
- Sistem balasan komentar (nested replies)
- Time ago format (berapa lama yang lalu)
- UI yang lebih menarik dan profesional
- Real-time interaction tanpa page reload

## 📊 Database Changes

### New Table: `quote_comments`

```sql
CREATE TABLE quote_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  user_id INT NOT NULL,
  parent_id INT NULL,  -- untuk balasan (nested comment)
  comment TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES pegawai(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES quote_comments(id) ON DELETE CASCADE,

  INDEX idx_quote_id (quote_id),
  INDEX idx_user_id (user_id),
  INDEX idx_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features:**
- `parent_id` memungkinkan nested replies (balasan komentar)
- CASCADE delete untuk data integrity
- Indexes untuk performance optimization
- UTF8MB4 untuk emoji support

### Updated Table: `pegawai`

```sql
ALTER TABLE pegawai
ADD COLUMN photo VARCHAR(255) NULL AFTER email;
```

**Purpose:** Menyimpan path foto profil user

## 🏗️ Backend Implementation

### 1. New Entity: QuoteComment

**File:** `src/Entity/QuoteComment.php`

#### Key Properties

```php
class QuoteComment
{
    private ?int $id = null;
    private ?Quote $quote = null;
    private ?Pegawai $user = null;
    private ?self $parent = null;  // Parent comment untuk replies
    private Collection $replies;    // Child comments
    private ?string $comment = null;
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $updatedAt = null;
}
```

#### Helper Methods

**isTopLevel()**: Check apakah comment adalah top-level (bukan reply)
```php
public function isTopLevel(): bool
{
    return $this->parent === null;
}
```

**getReplyCount()**: Hitung jumlah balasan
```php
public function getReplyCount(): int
{
    return $this->replies->count();
}
```

**getTimeAgo()**: Format waktu relatif
```php
public function getTimeAgo(): string
{
    $now = new \DateTime();
    $diff = $now->diff($this->createdAt);

    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
```

**toArray()**: Convert to array untuk JSON response
```php
public function toArray(bool $includeReplies = false): array
{
    $data = [
        'id' => $this->id,
        'comment' => $this->comment,
        'created_at' => $this->getFormattedCreatedAt(),
        'time_ago' => $this->getTimeAgo(),
        'user' => [
            'id' => $this->user->getId(),
            'name' => $this->user->getNama(),
            'photo' => $this->user->getPhoto() ?? '/images/default-user.png',
            'jabatan' => $this->user->getJabatan() ?? '-',
            'unit_kerja' => $this->user->getUnitKerja() ?? '-'
        ],
        'parent_id' => $this->parent ? $this->parent->getId() : null,
        'reply_count' => $this->getReplyCount()
    ];

    if ($includeReplies && $this->getReplyCount() > 0) {
        $data['replies'] = array_map(
            fn($reply) => $reply->toArray(false),
            $this->replies->toArray()
        );
    }

    return $data;
}
```

### 2. QuoteCommentRepository

**File:** `src/Repository/QuoteCommentRepository.php`

#### Key Methods

**findTopLevelByQuote()**: Get semua top-level comments (tanpa parent)
```php
public function findTopLevelByQuote(Quote $quote): array
{
    return $this->createQueryBuilder('c')
        ->andWhere('c.quote = :quote')
        ->andWhere('c.parent IS NULL')
        ->setParameter('quote', $quote)
        ->orderBy('c.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

**findByQuoteWithUser()**: Optimized query dengan user data
```php
public function findByQuoteWithUser(Quote $quote): array
{
    return $this->createQueryBuilder('c')
        ->select('c', 'u')
        ->join('c.user', 'u')
        ->andWhere('c.quote = :quote')
        ->andWhere('c.parent IS NULL')
        ->setParameter('quote', $quote)
        ->orderBy('c.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

**countTopLevelByQuote()**: Hitung jumlah top-level comments
```php
public function countTopLevelByQuote(Quote $quote): int
{
    return $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->andWhere('c.quote = :quote')
        ->andWhere('c.parent IS NULL')
        ->setParameter('quote', $quote)
        ->getQuery()
        ->getSingleScalarResult();
}
```

### 3. Updated Controller: IkhlasController

**File:** `src/Controller/IkhlasController.php`

#### Add Comment Endpoint

**Route:** `POST /ikhlas/api/quotes/{id}/comment`

```php
public function addComment(int $id, Request $request): JsonResponse
{
    $user = $this->getUser();
    $data = json_decode($request->getContent(), true);

    // Create new comment
    $comment = new QuoteComment();
    $comment->setQuote($quote);
    $comment->setUser($user);
    $comment->setComment(trim($data['comment']));

    // Handle parent comment (reply)
    if (isset($data['parent_id']) && $data['parent_id']) {
        $parentComment = $this->commentRepository->find($data['parent_id']);
        if ($parentComment) {
            $comment->setParent($parentComment);
        }
    }

    // Update quote's comment counter
    $quote->incrementComments();

    $this->em->persist($comment);
    $this->em->persist($quote);
    $this->em->flush();

    return new JsonResponse([
        'success' => true,
        'message' => '💬 Komentar berhasil ditambahkan!',
        'comment' => $comment->toArray(false),
        'totalComments' => $quote->getTotalComments()
    ]);
}
```

**Request Body:**
```json
{
  "comment": "Sangat inspiratif!",
  "parent_id": 123  // Optional, untuk reply
}
```

**Response:**
```json
{
  "success": true,
  "message": "💬 Komentar berhasil ditambahkan!",
  "comment": {
    "id": 456,
    "comment": "Sangat inspiratif!",
    "created_at": "21 Oct 2025 15:30",
    "time_ago": "Baru saja",
    "user": {
      "id": 1,
      "name": "John Doe",
      "photo": "/images/default-user.png",
      "jabatan": "Staff",
      "unit_kerja": "IT Department"
    },
    "parent_id": 123,
    "reply_count": 0
  },
  "totalComments": 10
}
```

#### Get Comments Endpoint

**Route:** `GET /ikhlas/api/quotes/{id}/comments`

```php
public function getComments(int $id): JsonResponse
{
    $quote = $this->quoteRepository->find($id);

    // Get top-level comments (with replies nested)
    $topLevelComments = $this->commentRepository->findByQuoteWithUser($quote);

    $comments = array_map(function($comment) {
        return $comment->toArray(true); // Include replies
    }, $topLevelComments);

    return new JsonResponse([
        'success' => true,
        'comments' => $comments,
        'total' => count($comments)
    ]);
}
```

**Response:**
```json
{
  "success": true,
  "comments": [
    {
      "id": 1,
      "comment": "Quote yang bagus!",
      "created_at": "21 Oct 2025 14:00",
      "time_ago": "1 jam lalu",
      "user": {
        "id": 1,
        "name": "John Doe",
        "photo": "/uploads/photos/john.jpg",
        "jabatan": "Manager",
        "unit_kerja": "Marketing"
      },
      "parent_id": null,
      "reply_count": 2,
      "replies": [
        {
          "id": 2,
          "comment": "Setuju!",
          "created_at": "21 Oct 2025 14:15",
          "time_ago": "45 menit lalu",
          "user": {
            "id": 2,
            "name": "Jane Smith",
            "photo": "/images/default-user.png",
            "jabatan": "Staff",
            "unit_kerja": "Marketing"
          },
          "parent_id": 1,
          "reply_count": 0
        }
      ]
    }
  ],
  "total": 1
}
```

### 4. Updated Entity: Pegawai

**File:** `src/Entity/Pegawai.php`

#### New Property

```php
#[ORM\Column(length: 255, nullable: true)]
private ?string $photo = null;

public function getPhoto(): ?string
{
    return $this->photo;
}

public function setPhoto(?string $photo): static
{
    $this->photo = $photo;
    return $this;
}
```

## 🎨 Frontend Implementation

### 1. Enhanced Comment UI

**File:** `templates/ikhlas/index.html.twig`

#### Comment Form with User Photo

```html
<div class="flex gap-3 items-start">
    <!-- User Photo -->
    <div class="flex-shrink-0">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center text-white font-bold">
            {% if app.user.photo %}
                <img src="{{ app.user.photo }}" alt="{{ app.user.nama }}" class="w-full h-full object-cover">
            {% else %}
                {{ app.user.nama|first|upper }}
            {% endif %}
        </div>
    </div>

    <!-- Comment Input -->
    <div class="flex-1">
        <textarea
            id="commentInput"
            rows="2"
            placeholder="Bagikan pemikiran Anda..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-none text-sm"
        ></textarea>

        <!-- Hidden field for reply -->
        <input type="hidden" id="commentParentId" value="">

        <!-- Reply indicator -->
        <div id="replyingTo" class="hidden mt-2 text-xs text-gray-600 bg-gray-50 px-3 py-2 rounded flex items-center justify-between">
            <span>Membalas: <span id="replyingToName" class="font-semibold"></span></span>
            <button onclick="cancelReply()" class="text-red-500 hover:text-red-700">✕</button>
        </div>

        <div class="flex justify-end mt-2">
            <button
                id="submitCommentBtn"
                class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg transition-colors font-medium text-sm"
            >
                Kirim
            </button>
        </div>
    </div>
</div>
```

### 2. JavaScript - Render Comments with Nested Replies

```javascript
function renderComment(comment, isReply = false) {
    const photo = comment.user.photo || '/images/default-user.png';
    const initial = comment.user.name.charAt(0).toUpperCase();
    const marginClass = isReply ? 'ml-12' : '';

    let repliesHtml = '';
    if (comment.replies && comment.replies.length > 0) {
        repliesHtml = comment.replies.map(reply => renderComment(reply, true)).join('');
    }

    return `
        <div class="${marginClass}">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:border-gray-300 transition-colors">
                <div class="flex items-start gap-3">
                    <!-- User Photo -->
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center text-white font-bold text-sm">
                            ${photo !== '/images/default-user.png'
                                ? \`<img src="${photo}" alt="${comment.user.name}" class="w-full h-full object-cover">\`
                                : initial}
                        </div>
                    </div>

                    <!-- Comment Content -->
                    <div class="flex-1">
                        <!-- User Info -->
                        <div class="mb-2">
                            <div class="font-semibold text-gray-800 text-sm">${escapeHtml(comment.user.name)}</div>
                            <div class="text-xs text-gray-500">${escapeHtml(comment.user.jabatan)} - ${escapeHtml(comment.user.unit_kerja)}</div>
                        </div>

                        <!-- Comment Text -->
                        <p class="text-gray-700 text-sm mb-2">${escapeHtml(comment.comment)}</p>

                        <!-- Comment Meta -->
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                ${comment.time_ago}
                            </span>
                            <button onclick="replyToComment(${comment.id}, '${escapeHtml(comment.user.name)}')"
                                    class="hover:text-purple-600 font-medium transition-colors">
                                ↩️ Balas
                            </button>
                            ${comment.reply_count > 0 ? \`
                                <span class="text-gray-400">• ${comment.reply_count} balasan</span>
                            \` : ''}
                        </div>
                    </div>
                </div>
            </div>
            ${repliesHtml}
        </div>
    `;
}
```

### 3. JavaScript - Reply Functionality

```javascript
// Reply to Comment
function replyToComment(commentId, userName) {
    const commentParentId = document.getElementById('commentParentId');
    const replyingTo = document.getElementById('replyingTo');
    const replyingToName = document.getElementById('replyingToName');
    const commentInput = document.getElementById('commentInput');

    commentParentId.value = commentId;
    replyingToName.textContent = userName;
    replyingTo.classList.remove('hidden');

    // Focus on input
    commentInput.focus();
    commentInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Cancel Reply
function cancelReply() {
    const commentParentId = document.getElementById('commentParentId');
    const replyingTo = document.getElementById('replyingTo');

    commentParentId.value = '';
    replyingTo.classList.add('hidden');
}
```

### 4. JavaScript - Submit with Reply Support

```javascript
async function submitComment() {
    const quoteId = currentQuoteId.value;
    const comment = commentInput.value.trim();
    const parentId = document.getElementById('commentParentId').value;

    if (!comment) {
        showToast('❌ Komentar tidak boleh kosong', 'error');
        return;
    }

    // Disable button
    submitCommentBtn.disabled = true;
    submitCommentBtn.textContent = 'Mengirim...';

    try {
        const requestBody = { comment: comment };
        if (parentId) {
            requestBody.parent_id = parseInt(parentId);
        }

        const response = await fetch(\`/ikhlas/api/quotes/\${quoteId}/comment\`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            commentInput.value = '';

            // Clear reply state
            cancelReply();

            // Update comment count
            if (quoteComments) {
                quoteComments.textContent = data.totalComments;
            }

            // Reload comments
            loadComments();
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error submitting comment:', error);
        showToast('❌ Terjadi kesalahan', 'error');
    } finally {
        // Re-enable button
        submitCommentBtn.disabled = false;
        submitCommentBtn.textContent = 'Kirim';
    }
}
```

## 🎨 UI/UX Features

### 1. User Avatar Display

**Default Avatar:**
- Gradient background (purple-400 to pink-400)
- First letter of user's name in uppercase
- Fallback jika tidak ada foto

**Custom Photo:**
- Rounded full circle
- Object-cover untuk proper scaling
- 10x10 (40px) size untuk optimal display

### 2. User Info Display

**Format:**
```
John Doe                    ← font-semibold, text-gray-800
Manager - Marketing         ← text-xs, text-gray-500
```

### 3. Time Ago Format

**Examples:**
- "Baru saja" (< 1 minute)
- "5 menit lalu"
- "2 jam lalu"
- "3 hari lalu"
- "1 bulan lalu"
- "2 tahun lalu"

### 4. Nested Reply Visual

**Indentation:**
- Top-level comments: No margin
- Replies: `ml-12` (48px left margin)
- Clear visual hierarchy

### 5. Reply Interaction

**Flow:**
1. User clicks "↩️ Balas"
2. Reply indicator shows: "Membalas: [Username]"
3. Textarea gets focus with smooth scroll
4. User types reply
5. Click "Kirim" or "✕" to cancel

### 6. Hover Effects

```css
/* Comment Card */
.hover:border-gray-300     /* Border color change on hover */

/* Reply Button */
.hover:text-purple-600     /* Text color change on hover */
```

## 📝 Migration from Old System

### Old Structure (Tahap 7)

**Table:** `user_quotes_interaction`
- comment TEXT (single field)
- No profile data
- No nested replies
- Simple text display

### New Structure (Tahap 8)

**Table:** `quote_comments`
- Dedicated comment table
- parent_id for replies
- Foreign keys with CASCADE
- Timestamps for tracking

### Migration Script

```sql
INSERT INTO quote_comments (quote_id, user_id, comment, created_at, updated_at)
SELECT
    i.quote_id,
    i.user_id,
    i.comment,
    i.updated_at,
    i.updated_at
FROM user_quotes_interaction i
WHERE i.comment IS NOT NULL
  AND i.comment != ''
  AND NOT EXISTS (
    SELECT 1 FROM quote_comments qc
    WHERE qc.quote_id = i.quote_id
      AND qc.user_id = i.user_id
      AND qc.comment = i.comment
  );
```

**Note:** Old table kept for backward compatibility

## 🔒 Security

### XSS Protection

```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
```

All user input escaped before rendering.

### SQL Injection Protection

- Doctrine ORM query builder
- Parameter binding
- Prepared statements

### Authorization

```php
#[IsGranted('ROLE_USER')]
```

All endpoints require authentication.

## ✨ Key Improvements

### Before (Tahap 7)
- Simple comment list
- No user info
- No replies
- Basic UI

### After (Tahap 8)
- Profile-based comments with photo, jabatan, unit kerja
- Nested reply system
- Time ago format
- Professional UI
- Better UX with reply indicators

## 📚 Files Modified

### Backend
1. **src/Entity/QuoteComment.php** - NEW
2. **src/Repository/QuoteCommentRepository.php** - NEW
3. **src/Entity/Pegawai.php** - Added photo property
4. **src/Controller/IkhlasController.php** - Updated comment endpoints

### Frontend
5. **templates/ikhlas/index.html.twig** - Enhanced comment UI

### Database
6. **quote_comments** table - NEW
7. **pegawai** table - Added photo column

## 🚀 Usage

### Post a Comment

```javascript
// Top-level comment
POST /ikhlas/api/quotes/123/comment
{
  "comment": "Sangat inspiratif!"
}

// Reply to comment
POST /ikhlas/api/quotes/123/comment
{
  "comment": "Setuju banget!",
  "parent_id": 456
}
```

### Get Comments

```javascript
GET /ikhlas/api/quotes/123/comments

Response:
{
  "success": true,
  "comments": [
    {
      "id": 1,
      "comment": "Quote bagus!",
      "user": {
        "name": "John Doe",
        "photo": "/uploads/john.jpg",
        "jabatan": "Manager",
        "unit_kerja": "IT"
      },
      "time_ago": "2 jam lalu",
      "reply_count": 1,
      "replies": [...]
    }
  ],
  "total": 1
}
```

---

**Developed for GEMBIRA - Tahap 8**
*Komentar Berbasis Profil Pengguna* 💬
