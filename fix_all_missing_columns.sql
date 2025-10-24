-- Script komprehensif untuk menambahkan semua kolom yang hilang
-- Untuk sinkronisasi antara Entity dan Database Schema
-- Safe to run multiple times (menggunakan IF NOT EXISTS check via procedure)

-- ============================================================
-- TABEL: pegawai
-- ============================================================

-- Kolom photo (untuk foto profil)
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'gembira_db'
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE pegawai ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL COMMENT ''Path foto profil pegawai'' AFTER tanda_tangan_uploaded_at',
    'SELECT ''Column photo already exists'' AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom total_xp (untuk XP system)
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'gembira_db'
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'total_xp') = 0,
    'ALTER TABLE pegawai ADD COLUMN total_xp INT NOT NULL DEFAULT 0 COMMENT ''Total XP yang dikumpulkan'' AFTER last_login_ip',
    'SELECT ''Column total_xp already exists'' AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom current_level (untuk level system)
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'gembira_db'
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'current_level') = 0,
    'ALTER TABLE pegawai ADD COLUMN current_level INT NOT NULL DEFAULT 1 COMMENT ''Level saat ini'' AFTER total_xp',
    'SELECT ''Column current_level already exists'' AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom current_badge (untuk badge/emoji)
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'gembira_db'
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'current_badge') = 0,
    'ALTER TABLE pegawai ADD COLUMN current_badge VARCHAR(10) NULL DEFAULT ''🌱'' COMMENT ''Badge level saat ini'' AFTER current_level',
    'SELECT ''Column current_badge already exists'' AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- VERIFIKASI
-- ============================================================

SELECT
    '✅ VERIFIKASI KOLOM PEGAWAI' AS status;

SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'pegawai'
  AND COLUMN_NAME IN ('photo', 'total_xp', 'current_level', 'current_badge')
ORDER BY ORDINAL_POSITION;

SELECT
    CONCAT('✅ Total kolom di tabel pegawai: ', COUNT(*)) AS summary
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'pegawai';
