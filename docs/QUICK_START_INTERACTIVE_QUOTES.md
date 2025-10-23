# Quick Start Guide - Interactive Quote System

## Fitur Utama

### 1️⃣ Statistik Real-Time
Setiap quote menampilkan:
- 👁️ **Views**: Jumlah tampilan
- ❤️ **Likes**: Jumlah like
- 💬 **Comments**: Jumlah komentar
- 🔗 **Share**: Tombol share ke WhatsApp

### 2️⃣ Sistem Komentar
- Klik icon 💬 untuk membuka/tutup section komentar
- Tulis komentar di textarea
- Klik "Kirim" untuk posting
- Komentar muncul real-time tanpa reload

### 3️⃣ Share ke WhatsApp
- Klik icon 🔗
- Quote + author otomatis ter-format
- WhatsApp terbuka dengan pesan siap kirim

## Cara Menggunakan

### Like Quote
```javascript
// Otomatis update counter
❤️ Likes: 42 → 43
Toast: "❤️ Anda menyukai quote ini! +2 poin"
```

### Komentar
```javascript
1. Klik icon 💬
2. Section komentar muncul
3. Tulis komentar
4. Klik "Kirim"
5. Komentar muncul di list
6. Counter update: 💬 5 → 6
```

### Share WhatsApp
```
Format:
"Quote content here..."

- Author Name

✨ Dibagikan dari GEMBIRA - Ikhlas
(Inspirasi Kehidupan Lahirkan Semangat)
```

## API Endpoints

### Comment
```bash
POST /ikhlas/api/quotes/{id}/comment
Body: {"comment": "Text komentar"}
```

### Get Comments
```bash
GET /ikhlas/api/quotes/{id}/comments
```

## Database

### Quotes Table
```sql
total_likes INT DEFAULT 0
total_comments INT DEFAULT 0
total_views INT DEFAULT 0
```

### User Quotes Interaction
```sql
comment TEXT NULL
```

## File Locations

### Backend
- Controller: `src/Controller/IkhlasController.php`
- Entity Quote: `src/Entity/Quote.php`
- Entity Interaction: `src/Entity/UserQuoteInteraction.php`

### Frontend
- Template: `templates/ikhlas/index.html.twig`
- JavaScript: Inline dalam template (baris 629-822)

## Testing

```bash
# 1. Buka halaman Ikhlas
http://localhost/gembira/public/ikhlas

# 2. Test Like
Klik tombol Like → Counter bertambah

# 3. Test Comment
Klik 💬 → Tulis komentar → Kirim

# 4. Test Share
Klik 🔗 → WhatsApp terbuka

# 5. Check Database
mysql -u root gembira_db -e "SELECT id, total_likes, total_comments, total_views FROM quotes LIMIT 5;"
```

## Troubleshooting

### Komentar tidak muncul?
```javascript
// Check console
F12 → Console → Cari error
// Check database
mysql> SELECT * FROM user_quotes_interaction WHERE comment IS NOT NULL;
```

### Counter tidak update?
```javascript
// Pastikan totalLikes ada di response
console.log(data.totalLikes);
// Check element exists
console.log(document.getElementById('quoteLikes'));
```

### WhatsApp tidak terbuka?
- Pastikan popup blocker tidak aktif
- Check format URL di console
- Test dengan browser berbeda

## Tips

✅ **DO:**
- Test di mobile untuk UX yang optimal
- Monitor statistik untuk engagement
- Encourage users untuk berkomentar

❌ **DON'T:**
- Jangan hapus escapeHtml() function (XSS protection)
- Jangan auto-refresh comments terlalu sering (performance)
- Jangan allow HTML dalam komentar (security)

---

**Happy Coding! 🚀**
