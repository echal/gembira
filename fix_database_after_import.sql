-- ============================================
-- FIX DATABASE SETELAH IMPORT BACKUP
-- Menambahkan kolom-kolom yang hilang
-- ============================================

USE gembira;

-- ============================================
-- 1. FIX TABEL ABSENSI - Tambah kolom latitude & longitude
-- ============================================
ALTER TABLE `absensi`
ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10,8) NULL AFTER `lokasi_absensi`,
ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11,8) NULL AFTER `latitude`;

-- ============================================
-- 2. FIX TABEL PEGAWAI - Tambah kolom XP & Level
-- ============================================
ALTER TABLE `pegawai`
ADD COLUMN IF NOT EXISTS `photo` VARCHAR(255) NULL AFTER `email`,
ADD COLUMN IF NOT EXISTS `tanda_tangan` VARCHAR(255) NULL AFTER `nomor_telepon`,
ADD COLUMN IF NOT EXISTS `tanda_tangan_uploaded_at` DATETIME NULL AFTER `tanda_tangan`,
ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL AFTER `tanda_tangan_uploaded_at`,
ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(45) NULL AFTER `last_login_at`,
ADD COLUMN IF NOT EXISTS `total_xp` INT DEFAULT 0 AFTER `last_login_ip`,
ADD COLUMN IF NOT EXISTS `current_level` INT DEFAULT 1 AFTER `total_xp`,
ADD COLUMN IF NOT EXISTS `current_badge` VARCHAR(10) DEFAULT '🌱' AFTER `current_level`,
ADD COLUMN IF NOT EXISTS `level_title` VARCHAR(50) DEFAULT 'Pemula Ikhlas' AFTER `current_badge`;

-- ============================================
-- 3. CREATE TABEL QUOTE (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `quote` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `author` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_author` (`author`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CREATE TABEL QUOTE_COMMENT (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `quote_comment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_id` INT NOT NULL,
  `author_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  FOREIGN KEY (`quote_id`) REFERENCES `quote`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  INDEX `idx_quote_id` (`quote_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CREATE TABEL USER_QUOTE_INTERACTION (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `user_quote_interaction` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `quote_id` INT NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'like, favorite',
  `created_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quote_id`) REFERENCES `quote`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_interaction` (`user_id`, `quote_id`, `type`),
  INDEX `idx_user_quote` (`user_id`, `quote_id`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. CREATE TABEL USER_XP_LOG (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `user_xp_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `xp_amount` INT NOT NULL,
  `activity_type` VARCHAR(50) NOT NULL COMMENT 'create_quote, like_quote, receive_like, etc',
  `reference_id` INT NULL COMMENT 'ID dari quote/comment yang terkait',
  `created_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CREATE TABEL MONTHLY_LEADERBOARD (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `monthly_leaderboard` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `year` INT NOT NULL,
  `month` INT NOT NULL,
  `xp` INT DEFAULT 0,
  `rank` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_month` (`user_id`, `year`, `month`),
  INDEX `idx_year_month` (`year`, `month`),
  INDEX `idx_rank` (`rank`),
  INDEX `idx_xp` (`xp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. CREATE TABEL USER_POINTS (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `user_points` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `points` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. CREATE TABEL USER_BADGES (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `user_badges` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `badge_type` VARCHAR(50) NOT NULL,
  `earned_at` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_badge_type` (`badge_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. CREATE TABEL DOCTRINE_MIGRATION_VERSIONS (jika belum ada)
-- ============================================
CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` VARCHAR(191) NOT NULL,
  `executed_at` DATETIME DEFAULT NULL,
  `execution_time` INT DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. INSERT MIGRATION VERSION
-- ============================================
INSERT IGNORE INTO `doctrine_migration_versions`
(`version`, `executed_at`, `execution_time`)
VALUES
('DoctrineMigrations\\Version20250124000000', NOW(), 1000);

-- ============================================
-- 12. FIX TABEL KONFIGURASI_JADWAL_ABSENSI
-- ============================================
ALTER TABLE `konfigurasi_jadwal_absensi`
ADD COLUMN IF NOT EXISTS `perlu_validasi_admin` TINYINT(1) DEFAULT 0;

-- ============================================
-- SELESAI!
-- ============================================

SELECT 'Database fix completed successfully!' AS status;
