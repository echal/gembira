# Enhancement: Foto Profil di User Header

## 📸 Deskripsi

Dokumen ini berisi panduan untuk menambahkan foto profil kecil (avatar) di samping nama user pada komponen header.

## 🎨 Design Proposal

### Option 1: Avatar di Sebelah Kiri Nama (Recommended)

```twig
<!-- User Info dengan Avatar -->
<div class="relative z-50">
    <button id="userMenuButton"
            onclick="toggleUserDropdown()"
            class="flex items-center gap-2 bg-white bg-opacity-20 hover:bg-opacity-30 px-2 md:px-3 py-2 rounded-lg text-xs md:text-sm backdrop-blur-sm transition-all duration-150 ease-in-out">

        <!-- Avatar Photo -->
        <div class="flex-shrink-0">
            {% if app.user.fotoPath %}
            <img src="{{ asset('uploads/avatars/' ~ app.user.fotoPath) }}"
                 alt="{{ app.user.nama }}"
                 class="w-8 h-8 md:w-9 md:h-9 rounded-full object-cover border-2 border-white shadow-sm">
            {% else %}
            <!-- Default Avatar -->
            <div class="w-8 h-8 md:w-9 md:h-9 bg-white bg-opacity-30 rounded-full flex items-center justify-center text-sm md:text-base font-bold text-white border-2 border-white">
                {{ app.user.nama|slice(0, 1)|upper }}
            </div>
            {% endif %}
        </div>

        <!-- Nama & NIP -->
        <div class="text-right flex flex-col items-end min-w-0 max-w-[100px] sm:max-w-[120px] md:max-w-[140px] lg:max-w-[180px]">
            <span class="font-semibold text-xs md:text-sm text-white truncate w-full">
                {{ app.user.nama|default('User') }}
            </span>
            <span class="text-xs text-sky-100 truncate w-full">
                {{ app.user.nip|default('---') }}
            </span>
        </div>

        <!-- Dropdown Arrow -->
        <svg id="dropdownArrow"
             class="flex-shrink-0 w-3 h-3 md:w-4 md:h-4 transition-transform duration-200"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
</div>
```

**Kelebihan:**
- ✅ Lebih personal dan modern
- ✅ Mudah mengenali user yang login
- ✅ Konsisten dengan design modern (seperti Gmail, Facebook, dll)
- ✅ Tidak memakan banyak space

### Option 2: Avatar Besar di Dropdown Menu

```twig
<!-- Dropdown Menu dengan Avatar Besar -->
<div id="userDropdown"
     class="hidden absolute right-0 top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-xl min-w-[200px] md:min-w-[240px] z-[60] transform opacity-0 scale-95 transition-all duration-200">

    <!-- User Info Section dengan Avatar -->
    <div class="p-4 border-b border-gray-200 bg-gradient-to-br from-sky-50 to-blue-50 rounded-t-lg">
        <div class="flex items-center gap-3">
            <!-- Avatar -->
            <div class="flex-shrink-0">
                {% if app.user.fotoPath %}
                <img src="{{ asset('uploads/avatars/' ~ app.user.fotoPath) }}"
                     alt="{{ app.user.nama }}"
                     class="w-12 h-12 rounded-full object-cover border-2 border-sky-400 shadow-md">
                {% else %}
                <!-- Default Avatar -->
                <div class="w-12 h-12 bg-gradient-to-br from-sky-400 to-blue-500 rounded-full flex items-center justify-center text-xl font-bold text-white border-2 border-sky-400 shadow-md">
                    {{ app.user.nama|slice(0, 1)|upper }}
                </div>
                {% endif %}
            </div>

            <!-- User Info -->
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-900 truncate">{{ app.user.nama|default('User') }}</div>
                <div class="text-xs text-gray-500 truncate">{{ app.user.jabatan|default('Pegawai') }}</div>
                {% if app.user.unitKerja %}
                <div class="text-xs text-gray-400 truncate">{{ app.user.unitKerja }}</div>
                {% endif %}
            </div>
        </div>
    </div>

    <!-- Menu Items -->
    <div class="py-1">
        <!-- ... menu items ... -->
    </div>
</div>
```

**Kelebihan:**
- ✅ Avatar lebih jelas terlihat
- ✅ Tidak mengubah header utama
- ✅ Info user lebih lengkap di dropdown

## 🔧 Implementasi Backend

### 1. Tambahkan Field di Entity User

```php
// src/Entity/Pegawai.php

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $fotoPath = null;

public function getFotoPath(): ?string
{
    return $this->fotoPath;
}

public function setFotoPath(?string $fotoPath): self
{
    $this->fotoPath = $fotoPath;
    return $this;
}

/**
 * Get first letter of name for avatar placeholder
 */
public function getInitials(): string
{
    $words = explode(' ', $this->nama);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($this->nama, 0, 1));
}
```

### 2. Upload Handler

```php
// src/Service/AvatarUploadService.php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class AvatarUploadService
{
    private string $uploadDir;

    public function __construct(string $uploadDir)
    {
        $this->uploadDir = $uploadDir;
    }

    public function upload(UploadedFile $file, string $userId): string
    {
        // Validasi file
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg'])) {
            throw new \Exception('Format file harus JPG, JPEG, atau PNG');
        }

        // Max size 2MB
        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new \Exception('Ukuran file maksimal 2MB');
        }

        // Generate unique filename
        $filename = 'avatar_' . $userId . '_' . uniqid() . '.' . $file->guessExtension();

        // Move file
        $file->move($this->uploadDir . '/avatars', $filename);

        return $filename;
    }

    public function delete(string $filename): void
    {
        $filepath = $this->uploadDir . '/avatars/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
```

### 3. Controller untuk Upload Avatar

```php
// src/Controller/ProfileController.php

#[Route('/profile/upload-avatar', name: 'app_profile_upload_avatar', methods: ['POST'])]
public function uploadAvatar(
    Request $request,
    AvatarUploadService $avatarService,
    EntityManagerInterface $em
): JsonResponse {
    $user = $this->getUser();

    $file = $request->files->get('avatar');
    if (!$file) {
        return $this->json(['success' => false, 'message' => 'File tidak ditemukan'], 400);
    }

    try {
        // Delete old avatar if exists
        if ($user->getFotoPath()) {
            $avatarService->delete($user->getFotoPath());
        }

        // Upload new avatar
        $filename = $avatarService->upload($file, $user->getId());

        // Update user entity
        $user->setFotoPath($filename);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Foto profil berhasil diupload',
            'filename' => $filename
        ]);

    } catch (\Exception $e) {
        return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
}
```

## 🎨 Form Upload di Halaman Profil

```twig
{# templates/profile/profil.html.twig #}

<div class="bg-white rounded-lg shadow-sm p-4 mb-4">
    <h3 class="text-lg font-semibold mb-3">📸 Foto Profil</h3>

    <div class="flex items-center gap-4">
        <!-- Avatar Preview -->
        <div id="avatarPreview" class="flex-shrink-0">
            {% if pegawai.fotoPath %}
            <img src="{{ asset('uploads/avatars/' ~ pegawai.fotoPath) }}"
                 alt="{{ pegawai.nama }}"
                 class="w-20 h-20 rounded-full object-cover border-4 border-sky-200 shadow-md">
            {% else %}
            <div class="w-20 h-20 bg-gradient-to-br from-sky-400 to-blue-500 rounded-full flex items-center justify-center text-2xl font-bold text-white border-4 border-sky-200 shadow-md">
                {{ pegawai.initials }}
            </div>
            {% endif %}
        </div>

        <!-- Upload Form -->
        <div class="flex-1">
            <form id="avatarUploadForm" enctype="multipart/form-data">
                <input type="file"
                       id="avatarInput"
                       name="avatar"
                       accept="image/jpeg,image/jpg,image/png"
                       class="hidden"
                       onchange="handleAvatarChange(event)">

                <button type="button"
                        onclick="document.getElementById('avatarInput').click()"
                        class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    📤 Pilih Foto
                </button>

                <p class="text-xs text-gray-500 mt-2">
                    Format: JPG, PNG (Max 2MB)
                </p>
            </form>
        </div>
    </div>
</div>

<script>
async function handleAvatarChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validasi client-side
    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
        alert('Format file harus JPG, JPEG, atau PNG');
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran file maksimal 2MB');
        return;
    }

    // Preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('avatarPreview').innerHTML =
            `<img src="${e.target.result}" class="w-20 h-20 rounded-full object-cover border-4 border-sky-200 shadow-md">`;
    };
    reader.readAsDataURL(file);

    // Upload
    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const response = await fetch('{{ path("app_profile_upload_avatar") }}', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ ' + result.message);
            // Reload page untuk update header
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ ' + result.message);
        }
    } catch (error) {
        alert('❌ Terjadi kesalahan saat upload foto');
    }
}
</script>
```

## 📊 Database Migration

```php
// migrations/VersionXXXXXXXXXXXXXX.php

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE pegawai ADD foto_path VARCHAR(255) DEFAULT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE pegawai DROP foto_path');
}
```

## 🎭 Avatar Styles & Variants

### Circular Avatar (Default)

```css
.avatar {
    border-radius: 50%;
    object-fit: cover;
}
```

### Rounded Square Avatar

```css
.avatar-square {
    border-radius: 0.5rem;
    object-fit: cover;
}
```

### Avatar with Status Indicator

```html
<div class="relative inline-block">
    <img src="..." class="w-10 h-10 rounded-full">
    <!-- Online status -->
    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
</div>
```

## 🚀 Quick Implementation Guide

### Step-by-step:

1. **Tambah field di database:**
   ```bash
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```

2. **Update Entity Pegawai:**
   - Tambah property `fotoPath`
   - Tambah getter/setter
   - Tambah method `getInitials()`

3. **Buat AvatarUploadService:**
   - Handle upload
   - Handle delete
   - Validasi file

4. **Update controller profil:**
   - Route upload avatar
   - Route delete avatar

5. **Update komponen header:**
   - Pilih option 1 atau 2
   - Replace kode di `user_header.html.twig`

6. **Update halaman profil:**
   - Tambah form upload
   - Tambah preview avatar

7. **Test:**
   - Upload foto baru
   - Check header update
   - Check dropdown update
   - Test dengan user tanpa foto (default avatar)

## 📝 Notes

- Default avatar menggunakan initial nama (huruf pertama)
- Warna background avatar bisa di-generate berdasarkan hash nama untuk variasi
- Foto disimpan di `public/uploads/avatars/`
- Security: Validasi MIME type dan size di backend
- Accessibility: Selalu sertakan alt text

---

**Status:** 💡 Optional Enhancement
**Priority:** Low
**Effort:** Medium (2-3 hours)
**Impact:** High (User Experience)
