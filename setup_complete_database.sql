-- ============================================================
-- SCRIPT MASTER: Setup Complete Database untuk GEMBIRA
-- ============================================================
-- Script ini menggabungkan semua fix database yang diperlukan
-- Safe to run multiple times (idempotent)
--
-- Menambahkan:
-- 1. Tabel quotes & user_quotes_interaction (IKHLAS)
-- 2. Tabel ranking_harian & ranking_bulanan (Leaderboard Absensi)
-- 3. Tabel monthly_leaderboard (Leaderboard XP IKHLAS)
-- 4. Tabel user_points (Points & Level System)
-- 5. Tabel user_xp_log (Log History XP)
-- 6. Kolom photo, total_xp, current_level, current_badge (Pegawai)
-- ============================================================

SET @database_name = 'gembira_db';

-- ============================================================
-- STEP 1: CREATE TABLES (IF NOT EXISTS)
-- ============================================================

-- Tabel quotes (untuk IKHLAS quotes) - PLURAL sesuai Entity
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'Kategori quote',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status aktif',
  `content` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `total_likes` int(11) NOT NULL DEFAULT 0 COMMENT 'Total likes',
  `total_comments` int(11) NOT NULL DEFAULT 0 COMMENT 'Total comments',
  `total_views` int(11) NOT NULL DEFAULT 0 COMMENT 'Total views',
  PRIMARY KEY (`id`),
  KEY `idx_author` (`author`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_category` (`category`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_total_likes` (`total_likes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel user_quotes_interaction (untuk like/save quotes) - PLURAL sesuai Entity
CREATE TABLE IF NOT EXISTS `user_quotes_interaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Foreign key ke pegawai',
  `quote_id` int(11) NOT NULL,
  `liked` tinyint(1) NOT NULL DEFAULT 0,
  `saved` tinyint(1) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL COMMENT 'Komentar user pada quote',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_interaction` (`user_id`, `quote_id`),
  KEY `IDX_user` (`user_id`),
  KEY `IDX_quote` (`quote_id`),
  KEY `idx_user_quote` (`user_id`, `quote_id`),
  CONSTRAINT `FK_user_quotes_pegawai` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_user_quotes_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
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

-- Tabel user_points (untuk points & level system)
CREATE TABLE IF NOT EXISTS `user_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points_total` int(11) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 1,
  `last_updated` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_user` (`user_id`),
  KEY `IDX_user` (`user_id`),
  KEY `idx_level` (`level`),
  KEY `idx_points` (`points_total`),
  CONSTRAINT `FK_user_points_pegawai` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel monthly_leaderboard (untuk IKHLAS XP leaderboard bulanan)
CREATE TABLE IF NOT EXISTS `monthly_leaderboard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `month` int(11) NOT NULL COMMENT 'Bulan (1-12)',
  `year` int(11) NOT NULL COMMENT 'Tahun',
  `xp_monthly` int(11) NOT NULL DEFAULT 0 COMMENT 'Total XP bulan ini',
  `rank_monthly` int(11) DEFAULT NULL COMMENT 'Peringkat bulan ini',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_month_year` (`user_id`, `month`, `year`),
  KEY `idx_month_year` (`month`, `year`),
  KEY `idx_xp_monthly` (`xp_monthly`),
  KEY `idx_rank_monthly` (`rank_monthly`),
  KEY `IDX_user` (`user_id`),
  CONSTRAINT `FK_monthly_leaderboard_pegawai` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel user_xp_log (log history perolehan XP)
CREATE TABLE IF NOT EXISTS `user_xp_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Foreign key ke pegawai',
  `xp_earned` int(11) NOT NULL DEFAULT 0 COMMENT 'XP yang didapat',
  `activity_type` varchar(100) NOT NULL COMMENT 'Jenis aktivitas (like_quote, comment_quote, view_quote, etc)',
  `description` text DEFAULT NULL COMMENT 'Deskripsi aktivitas',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID terkait (quote_id, comment_id, etc)',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_xp_log_user` (`user_id`),
  KEY `idx_user_xp_log_created` (`created_at`),
  KEY `idx_activity_type` (`activity_type`),
  CONSTRAINT `FK_user_xp_log_pegawai` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
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
  AND TABLE_NAME IN ('quotes', 'user_quotes_interaction');

SELECT '';
SELECT 'Tabel Ranking:' AS kategori;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME IN ('ranking_harian', 'ranking_bulanan');

SELECT '';
SELECT 'Tabel Points & XP System:' AS kategori;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME IN ('user_points', 'monthly_leaderboard', 'user_xp_log')
ORDER BY TABLE_NAME;

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
