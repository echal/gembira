# 📌 User Header - Quick Reference Card

> **Component:** `templates/components/user_header.html.twig`
> **Version:** 1.0.0
> **Status:** ✅ Production Ready

---

## ⚡ Quick Usage

### Basic (Dashboard-style with welcome info)

```twig
{% include 'components/user_header.html.twig' with {
    'show_welcome': true
} %}
```

### Standard (Internal pages with back button)

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'title': 'Page Title',
    'subtitle': 'Page subtitle'
} %}
```

---

## 📝 Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `show_back_button` | bool | `false` | Show back arrow |
| `back_url` | string | `path('app_dashboard')` | Back button URL |
| `title` | string | `'GEMBIRA'` | Main title |
| `subtitle` | string | `'Gerakan...'` | Subtitle text |
| `show_welcome` | bool | `false` | Show welcome + clock |

---

## 🎨 Common Patterns

### Home/Dashboard

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': false,
    'title': 'GEMBIRA',
    'subtitle': 'Gerakan Munajat Bersama Untuk Kinerja',
    'show_welcome': true
} %}
```

### List Pages (Laporan, Kalender, etc)

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'back_url': path('app_dashboard'),
    'title': 'Laporan Absensi',
    'subtitle': 'Riwayat kehadiran Anda'
} %}
```

### Detail Pages (Profil, Settings, etc)

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'title': 'Profil Saya',
    'subtitle': 'Kelola informasi pribadi'
} %}
```

### Custom Back URL

```twig
{% include 'components/user_header.html.twig' with {
    'show_back_button': true,
    'back_url': path('app_user_laporan'),
    'title': 'Detail Laporan',
    'subtitle': 'Tanggal {{ tanggal|date("d/m/Y") }}'
} %}
```

---

## 📱 Responsive Breakpoints

| Screen | Logo | Font Nama | Font NIP | Max Width |
|--------|------|-----------|----------|-----------|
| `< 640px` | 32px | 12px | 10px | 100px |
| `640-1024px` | 40px | 14px | 12px | 140px |
| `> 1024px` | 48px | 16px | 14px | 180px |

---

## 🔧 Customization

### Change Logo

Edit `user_header.html.twig`:

```twig
<img src="{{ asset('images/your-logo.png') }}"
     alt="Logo"
     class="w-8 h-8 md:w-10 md:h-10 lg:w-12 lg:h-12">
```

### Add Menu Item

Edit dropdown section:

```twig
<a href="{{ path('your_route') }}"
   class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600">
    <span class="mr-3">🎯</span>
    <span>Menu Name</span>
</a>
```

### Change Color Scheme

Replace `from-sky-400 to-sky-500` with:

```twig
{# Blue #}
from-blue-400 to-blue-500

{# Purple #}
from-purple-400 to-purple-500

{# Green #}
from-green-400 to-green-500

{# Custom gradient #}
from-sky-500 via-blue-500 to-indigo-500
```

---

## 🎯 JavaScript Functions

### Toggle Dropdown

```javascript
toggleUserDropdown()
```

### Close Dropdown

```javascript
closeUserDropdown()
```

### Update Clock (if show_welcome: true)

```javascript
updateHeaderJam()
```

---

## 🐛 Troubleshooting

### Header not showing

```bash
# Check file exists
ls templates/components/user_header.html.twig
```

### Dropdown not working

```javascript
// Check console for errors
console.log(document.getElementById('userDropdown'));
```

### Logo not showing

```twig
{# Use fallback #}
{% if asset('images/logo-gembira.png') is not empty %}
    <img src="..." />
{% else %}
    <div class="w-10 h-10 bg-white rounded-full">G</div>
{% endif %}
```

### Text overflow

```twig
{# Adjust max-width #}
max-w-[120px] md:max-w-[160px] lg:max-w-[200px]
```

---

## ✅ Pre-flight Checklist

Before deploying:

- [ ] Component file exists in `templates/components/`
- [ ] Logo image exists in `public/images/`
- [ ] All routes referenced in dropdown exist
- [ ] Tested on mobile (< 640px)
- [ ] Tested on tablet (768px)
- [ ] Tested on desktop (1440px)
- [ ] Dropdown opens/closes smoothly
- [ ] Back button redirects correctly
- [ ] No console errors

---

## 📊 Pages Using This Component

✅ Updated:
- `templates/dashboard/index.html.twig`
- `templates/user/laporan/riwayat.html.twig`
- `templates/user/jadwal.html.twig`
- `templates/profile/profil.html.twig`
- `templates/user/kalender/index.html.twig`

🔜 Todo (if any):
- `templates/...` (add here)

---

## 🚀 Performance Tips

### 1. Cache Component

```yaml
# config/packages/twig.yaml
twig:
    cache: '%kernel.cache_dir%/twig'
```

### 2. Optimize Assets

```bash
# Compile Tailwind CSS
npm run build
```

### 3. Enable HTTP/2

```apache
# .htaccess
# Enable HTTP/2 for better component loading
```

---

## 🎁 Related Files

- **Component:** `templates/components/user_header.html.twig`
- **Docs:** `docs/USER_HEADER_COMPONENT.md`
- **Comparison:** `docs/HEADER_BEFORE_AFTER_COMPARISON.md`
- **Enhancement:** `docs/USER_HEADER_AVATAR_ENHANCEMENT.md`
- **Summary:** `PERBAIKAN_HEADER_USER_SUMMARY.md`

---

## 📞 Quick Help

| Issue | Solution |
|-------|----------|
| Logo too small | Increase: `w-10 h-10` → `w-12 h-12` |
| Text too long | Add/adjust `max-w-[...]` |
| Dropdown cut off | Check `z-50` and `z-[60]` |
| Notif badge wrong | Implement `app.user.unreadNotifications` |
| Back button broken | Verify route name in `path()` |

---

## 🔗 Common Routes

```twig
{# Dashboard #}
{{ path('app_dashboard') }}

{# Profil #}
{{ path('app_profile_view') }}

{# Password #}
{{ path('app_profile_change_password') }}

{# Tanda Tangan #}
{{ path('app_profile_tanda_tangan') }}

{# Laporan #}
{{ path('app_user_laporan') }}

{# Kalender #}
{{ path('app_user_kalender') }}

{# Notifikasi #}
{{ path('app_notifikasi') }}
```

---

## 💡 Pro Tips

1. **Consistent Spacing:** Always use `px-3 md:px-4` for consistency
2. **Truncate Long Text:** Use `truncate` + `max-w-[...]`
3. **Test Mobile First:** Design for 320px, then scale up
4. **Use Semantic HTML:** Proper `<header>`, `<nav>`, `<button>` tags
5. **Accessibility:** Add `aria-label` for icon buttons

---

## 📚 Learn More

- [Full Documentation](docs/USER_HEADER_COMPONENT.md)
- [Before/After Comparison](docs/HEADER_BEFORE_AFTER_COMPARISON.md)
- [Avatar Enhancement Guide](docs/USER_HEADER_AVATAR_ENHANCEMENT.md)
- [Tailwind CSS Docs](https://tailwindcss.com)

---

**Last Updated:** 2025-10-20
**Maintainer:** Development Team
**Support:** Check documentation or review component code

---

## 🎨 Visual Preview

```
┌────────────────────────────────────────────────────────────┐
│ [←] 🎯 GEMBIRA              🔔  Your Name Here  👤▼        │
│        Subtitle text here          NIP 123456789           │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼ (on click)
                    ┌──────────────────┐
                    │ Your Name        │
                    │ Jabatan          │
                    │ Unit Kerja       │
                    ├──────────────────┤
                    │ 👤 Profil        │
                    │ 🏠 Dashboard     │
                    │ 🔐 Password      │
                    │ 📝 Tanda Tangan  │
                    ├──────────────────┤
                    │ 🚪 Logout        │
                    └──────────────────┘
```

---

**Happy Coding! 🚀**

Print this page for quick reference during development.
