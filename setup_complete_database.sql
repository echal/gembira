-- ============================================================
-- SCRIPT MASTER: Setup Complete Database untuk GEMBIRA
-- ============================================================
-- Script ini menggabungkan semua fix database yang diperlukan
-- Safe to run multiple times (idempotent)
--
-- Menambahkan:
-- 1. Tabel quote & user_quote_interaction (IKHLAS)
-- 2. Tabel ranking_harian & ranking_bulanan (Leaderboard)
-- 3. Kolom photo, total_xp, current_level, current_badge (Pegawai)
-- ============================================================

SET @database_name = 'gembira_db';

-- ============================================================
-- STEP 1: CREATE TABLES (IF NOT EXISTS)
-- ============================================================

-- Tabel quote (untuk IKHLAS quotes)
CREATE TABLE IF NOT EXISTS `quote` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_author` (`author`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel user_quote_interaction (untuk like/save quotes)
CREATE TABLE IF NOT EXISTS `user_quote_interaction` (
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
  CONSTRAINT `FK_user_quote_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_user_quote_quote` FOREIGN KEY (`quote_id`) REFERENCES `quote` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel ranking_harian (untuk leaderboard harian)
CREATE TABLE IF NOT EXISTS `ranking_harian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `skor_harian` int(11) NOT NULL,
  `total_durasi` int(11) DEFAULT NULL,
  `peringkat` int(11) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pegawai_tanggal` (`pegawai_id`, `tanggal`),
  KEY `idx_pegawai_tanggal` (`pegawai_id`, `tanggal`),
  KEY `idx_tanggal_peringkat` (`tanggal`, `peringkat`),
  KEY `IDX_pegawai` (`pegawai_id`),
  CONSTRAINT `FK_ranking_harian_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel ranking_bulanan (untuk leaderboard bulanan)
CREATE TABLE IF NOT EXISTS `ranking_bulanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `periode` varchar(7) NOT NULL,
  `total_durasi` int(11) NOT NULL,
  `rata_rata_durasi` float DEFAULT NULL,
  `peringkat` int(11) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pegawai_periode` (`pegawai_id`, `periode`),
  KEY `idx_pegawai_periode` (`pegawai_id`, `periode`),
  KEY `idx_periode_peringkat` (`periode`, `peringkat`),
  KEY `IDX_pegawai` (`pegawai_id`),
  CONSTRAINT `FK_ranking_bulanan_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 2: ADD MISSING COLUMNS TO PEGAWAI (IF NOT EXISTS)
-- ============================================================

-- Kolom photo
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @database_name
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE pegawai ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL COMMENT ''Path foto profil'' AFTER tanda_tangan_uploaded_at',
    'SELECT ''✅ Column photo already exists'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom total_xp
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @database_name
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'total_xp') = 0,
    'ALTER TABLE pegawai ADD COLUMN total_xp INT NOT NULL DEFAULT 0 COMMENT ''Total XP'' AFTER last_login_ip',
    'SELECT ''✅ Column total_xp already exists'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom current_level
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @database_name
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'current_level') = 0,
    'ALTER TABLE pegawai ADD COLUMN current_level INT NOT NULL DEFAULT 1 COMMENT ''Level saat ini'' AFTER total_xp',
    'SELECT ''✅ Column current_level already exists'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom current_badge
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @database_name
     AND TABLE_NAME = 'pegawai'
     AND COLUMN_NAME = 'current_badge') = 0,
    'ALTER TABLE pegawai ADD COLUMN current_badge VARCHAR(10) NULL DEFAULT ''🌱'' COMMENT ''Badge level'' AFTER current_level',
    'SELECT ''✅ Column current_badge already exists'' AS info'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- STEP 3: VERIFICATION
-- ============================================================

SELECT '🎉 SETUP DATABASE SELESAI!' AS status;
SELECT '';

-- Cek tabel yang ada
SELECT CONCAT('✅ Total tabel: ', COUNT(*)) AS summary
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name;

SELECT '';
SELECT 'Tabel IKHLAS:' AS kategori;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME IN ('quote', 'user_quote_interaction');

SELECT '';
SELECT 'Tabel Ranking:' AS kategori;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME IN ('ranking_harian', 'ranking_bulanan');

SELECT '';
SELECT 'Kolom Pegawai (XP System):' AS kategori;
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = 'pegawai'
  AND COLUMN_NAME IN ('photo', 'total_xp', 'current_level', 'current_badge');

SELECT '';
SELECT '✅ Database siap digunakan!' AS message;
SELECT '📝 Jangan lupa: php bin/console cache:clear' AS reminder;
