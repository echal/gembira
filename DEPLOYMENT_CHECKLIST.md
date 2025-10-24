# ✅ Quick Deployment Checklist

## Pre-Deployment

```
□ Backup production database (phpMyAdmin Export)
□ Backup production files (Compress gembira folder)
□ Download backups ke lokal
□ Test localhost: php bin/console cache:clear
□ Verify: php check_missing_columns.php
□ Git pull origin master
```

## Deployment

```
□ Login cPanel → File Manager
□ Navigate to public_html/gembira
□ Backup .env production: cp .env .env.backup

UPLOAD FILES (SELECTIVE):
□ src/Controller/*.php (yang berubah)
□ src/Security/LoginSuccessHandler.php
□ templates/ikhlas/*.twig (yang berubah)
□ *.php scripts (verify_ikhlas_data.php, cleanup_*.php, dll)
□ *.md documentation files

JANGAN UPLOAD:
□ .env (biarkan yang production)
□ var/cache/*
□ var/log/*
□ vendor/* (kecuali ada update)
```

## Post-Deployment

```
□ Check missing columns: php check_missing_columns.php
□ Fix database if needed: php run_database_fix.php
□ Cleanup admin pegawai: php cleanup_admin_pegawai_role.php
□ Clear cache: rm -rf var/cache/prod/*
□ Set permissions: chmod -R 775 var/
□ Test login admin → /admin/dashboard
□ Test login pegawai → /absensi
□ Test IKHLAS tagline
□ Test Leaderboard background
□ Verify data: php verify_ikhlas_data.php
```

## Rollback (If Failed)

```
□ Restore files from backup
□ Restore database from SQL backup
□ Clear cache
□ Test application
```

## Files Changed (This Session)

```
✅ src/Security/LoginSuccessHandler.php
✅ templates/ikhlas/index.html.twig
✅ templates/ikhlas/leaderboard.html.twig
✅ check_admin_with_pegawai_role.php
✅ cleanup_admin_pegawai_role.php
✅ verify_ikhlas_data.php
✅ check_user_faisal.php
✅ STRUKTUR_DATA_USER_YANG_BENAR.md
✅ PENJELASAN_DATA_IKHLAS_LEADERBOARD.md
✅ FIX_REDIRECT_SETELAH_GANTI_PASSWORD.md
✅ CARA_TEST_REDIRECT_FIX.md
```

---

**INGAT:**
- ❌ JANGAN drop/import database production!
- ✅ Upload HANYA file code yang berubah
- ✅ Backup dulu sebelum deploy
- ✅ Clear cache setelah deploy
