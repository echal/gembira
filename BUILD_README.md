# Gembira - Build & Development Guide

## 🎨 Tailwind CSS Setup

Aplikasi Gembira menggunakan Tailwind CSS yang di-compile untuk production, bukan CDN version.

### 📋 Prerequisites

- Node.js (version 16 atau lebih baru)
- npm atau yarn

### 🚀 Quick Start

```bash
# Install dependencies
npm install

# Development mode (dengan watch)
npm run dev

# Production build (minified)
npm run build
```

### 📁 File Structure

```
assets/
├── css/
│   └── input.css          # Source Tailwind CSS
public/
├── css/
│   └── app.css           # Compiled CSS (generated)
tailwind.config.js        # Tailwind configuration
package.json              # Node.js dependencies
```

### 🔧 Development Workflow

**Untuk Development:**
```bash
npm run dev
```
Ini akan menjalankan Tailwind dalam watch mode, otomatis rebuild CSS ketika ada perubahan.

**Untuk Production:**
```bash
npm run build
```
Ini akan generate minified CSS untuk production.

### 🎨 Custom CSS

Edit file `assets/css/input.css` untuk menambah custom styles:

```css
@layer components {
  .btn-custom {
    @apply bg-blue-500 text-white px-4 py-2 rounded;
  }
}
```

### 📦 Deployment

Untuk deploy ke production:

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Build CSS:**
   ```bash
   npm run build
   ```

3. **Deploy files** (CSS sudah di-generate ke `public/css/app.css`)

### ⚠️ Important Notes

- File `public/css/app.css` adalah file yang di-generate
- Jangan edit `app.css` secara manual
- Selalu jalankan `npm run build` sebelum deploy
- CDN Tailwind CSS sudah dihapus dari template

### 🐛 Troubleshooting

**CSS tidak berubah setelah build:**
```bash
# Clear browser cache atau hard refresh (Ctrl+F5)
```

**Build error:**
```bash
# Update browserslist
npx update-browserslist-db@latest

# Clean install
rm -rf node_modules package-lock.json
npm install
```

### 📝 Migration dari CDN

✅ **Sebelum (CDN):**
```html
<script src="https://cdn.tailwindcss.com"></script>
```

✅ **Sesudah (Production):**
```html
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
```

**Benefits:**
- ✅ Tidak ada warning console lagi
- ✅ File size lebih kecil (only used classes)
- ✅ Better performance
- ✅ Offline support
- ✅ Custom configuration