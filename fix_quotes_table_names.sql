-- Fix inkonsistensi penamaan tabel quotes
-- Entity menggunakan 'quotes' (plural) tapi tabel dibuat 'quote' (singular)

-- Cek apakah tabel sudah ada
SELECT 'Checking existing tables...' AS status;

SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME LIKE '%quote%';

-- ============================================================
-- SOLUSI 1: Rename existing tables (jika ada data)
-- ============================================================

-- Rename quote -> quotes (jika tabel quote ada dan quotes belum ada)
SET @rename_quote = (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = 'gembira_db' AND TABLE_NAME = 'quote') > 0
        AND
        (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = 'gembira_db' AND TABLE_NAME = 'quotes') = 0,
        'RENAME TABLE quote TO quotes',
        'SELECT "Table quote already renamed or quotes exists" AS info'
    )
);

PREPARE stmt FROM @rename_quote;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rename user_quote_interaction -> user_quotes_interaction
SET @rename_interaction = (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = 'gembira_db' AND TABLE_NAME = 'user_quote_interaction') > 0
        AND
        (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = 'gembira_db' AND TABLE_NAME = 'user_quotes_interaction') = 0,
        'RENAME TABLE user_quote_interaction TO user_quotes_interaction',
        'SELECT "Table user_quote_interaction already renamed or user_quotes_interaction exists" AS info'
    )
);

PREPARE stmt FROM @rename_interaction;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SOLUSI 2: Create tables with correct names (jika belum ada)
-- ============================================================

-- Tabel quotes (plural)
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_author` (`author`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel user_quotes_interaction (plural)
CREATE TABLE IF NOT EXISTS `user_quotes_interaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `liked` tinyint(1) NOT NULL DEFAULT 0,
  `saved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_interaction` (`pegawai_id`, `quote_id`),
  KEY `IDX_pegawai` (`pegawai_id`),
  KEY `IDX_quote` (`quote_id`),
  CONSTRAINT `FK_user_quotes_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_user_quotes_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VERIFICATION
-- ============================================================

SELECT '✅ TABEL QUOTES FIXED' AS status;

SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME IN ('quotes', 'user_quotes_interaction')
ORDER BY TABLE_NAME;

SELECT CONCAT('✅ Total tabel dengan nama quote: ', COUNT(*)) AS summary
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME LIKE '%quote%';
