# Konfigurasi Keamanan GEMBIRA - Localhost Only

## 📋 Ringkasan
Aplikasi GEMBIRA telah dikonfigurasi untuk **hanya dapat diakses dari komputer/laptop lokal** dan **tidak dapat diakses dari perangkat lain di jaringan WiFi**.

## ⚙️ Perubahan Konfigurasi

### 1. Apache HTTP Configuration (`C:\xampp\apache\conf\httpd.conf`)
```apache
# Dari: Listen 80
# Menjadi:
Listen 127.0.0.1:80
```

### 2. Apache SSL Configuration (`C:\xampp\apache\conf\extra\httpd-ssl.conf`)
```apache
# Dari: Listen 443  
# Menjadi:
Listen 127.0.0.1:443
```

### 3. GEMBIRA Security Configuration (`C:\xampp\apache\conf\extra\httpd-gembira.conf`)
```apache
<Directory "C:/xampp/htdocs/gembira">
    Require local
    <RequireAll>
        Require ip 127.0.0.1
        Require ip ::1
    </RequireAll>
</Directory>
```

### 4. Application .htaccess (`C:\xampp\htdocs\gembira\.htaccess`)
```apache
<RequireAll>
    Require local
    Require ip 127.0.0.1
    Require ip ::1
</RequireAll>
```

## 🔐 Cara Akses

### ✅ DIIZINKAN (dari komputer ini):
- `http://127.0.0.1/gembira/public`
- `http://localhost/gembira/public`

### ❌ DIBLOKIR (dari perangkat lain):
- `http://192.168.1.100/gembira/public` (IP lokal komputer ini)
- Akses dari HP, tablet, komputer lain di WiFi yang sama

## 🔧 Cara Restart Apache

### Opsi 1: Menggunakan Script
```cmd
double-click: C:\xampp\htdocs\gembira\restart_apache.bat
```

### Opsi 2: Manual via Command Prompt (Admin)
```cmd
net stop Apache2.4
net start Apache2.4
```

### Opsi 3: XAMPP Control Panel
1. Buka XAMPP Control Panel
2. Stop Apache
3. Start Apache

## 📱 Testing Keamanan

### Test dari komputer ini:
1. Buka browser
2. Akses: `http://127.0.0.1/gembira/public`
3. ✅ Harus berhasil masuk

### Test dari perangkat lain (HP/tablet):
1. Cari IP komputer ini: `ipconfig` (misal: 192.168.1.100)
2. Di HP, buka browser dan akses: `http://192.168.1.100/gembira/public`
3. ❌ Harus muncul error "Forbidden" atau "Access Denied"

## 🔄 Mengembalikan ke Mode Terbuka (jika diperlukan)

### 1. Edit `C:\xampp\apache\conf\httpd.conf`
```apache
# Ubah dari:
Listen 127.0.0.1:80
# Kembali ke:
Listen 80
```

### 2. Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`
```apache
# Ubah dari:
Listen 127.0.0.1:443
# Kembali ke:
Listen 443
```

### 3. Comment/disable include di `C:\xampp\apache\conf\httpd.conf`
```apache
# GEMBIRA security settings
# Include "conf/extra/httpd-gembira.conf"
```

### 4. Rename/hapus .htaccess
```cmd
ren C:\xampp\htdocs\gembira\.htaccess .htaccess.backup
```

### 5. Restart Apache
```cmd
net stop Apache2.4 && net start Apache2.4
```

## ⚠️ Catatan Penting
- Pastikan Windows Firewall tetap aktif untuk keamanan tambahan
- Jika perlu akses dari perangkat lain, gunakan VPN atau remote desktop
- Konfigurasi ini tidak mempengaruhi aplikasi XAMPP lainnya
- Backup konfigurasi sebelum melakukan perubahan