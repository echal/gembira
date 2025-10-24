-- Tambah kolom yang hilang di tabel quotes
-- Entity Quote memiliki lebih banyak kolom daripada yang ada di database

-- Tambah kolom category
ALTER TABLE `quotes`
ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NULL DEFAULT NULL
COMMENT 'Kategori quote (Motivasi, Inspirasi, dll)'
AFTER `author`;

-- Tambah kolom is_active
ALTER TABLE `quotes`
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1
COMMENT 'Status aktif quote'
AFTER `category`;

-- Tambah kolom total_likes
ALTER TABLE `quotes`
ADD COLUMN IF NOT EXISTS `total_likes` INT NOT NULL DEFAULT 0
COMMENT 'Total likes dari users'
AFTER `updated_at`;

-- Tambah kolom total_comments
ALTER TABLE `quotes`
ADD COLUMN IF NOT EXISTS `total_comments` INT NOT NULL DEFAULT 0
COMMENT 'Total comments'
AFTER `total_likes`;

-- Tambah kolom total_views
ALTER TABLE `quotes`
ADD COLUMN IF NOT EXISTS `total_views` INT NOT NULL DEFAULT 0
COMMENT 'Total views/impressions'
AFTER `total_comments`;

-- Tambah index untuk performa
CREATE INDEX IF NOT EXISTS `idx_category` ON `quotes` (`category`);
CREATE INDEX IF NOT EXISTS `idx_is_active` ON `quotes` (`is_active`);
CREATE INDEX IF NOT EXISTS `idx_total_likes` ON `quotes` (`total_likes`);

-- Verifikasi
SELECT '✅ KOLOM QUOTES UPDATED' AS status;

SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'quotes'
ORDER BY ORDINAL_POSITION;

SELECT CONCAT('✅ Total kolom di tabel quotes: ', COUNT(*)) AS summary
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'quotes';
