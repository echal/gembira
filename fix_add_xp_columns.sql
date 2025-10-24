-- Tambah kolom XP/Gamification ke tabel pegawai
-- Untuk mendukung fitur XP progression system di IKHLAS

-- Tambah kolom total_xp
ALTER TABLE `pegawai`
ADD COLUMN `total_xp` INT NOT NULL DEFAULT 0 COMMENT 'Total XP yang dikumpulkan pegawai'
AFTER `last_login_ip`;

-- Tambah kolom current_level
ALTER TABLE `pegawai`
ADD COLUMN `current_level` INT NOT NULL DEFAULT 1 COMMENT 'Level saat ini berdasarkan total XP'
AFTER `total_xp`;

-- Tambah kolom current_badge
ALTER TABLE `pegawai`
ADD COLUMN `current_badge` VARCHAR(10) NULL DEFAULT '🌱' COMMENT 'Badge/emoji level saat ini'
AFTER `current_level`;

-- Verifikasi kolom berhasil ditambahkan
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'pegawai'
  AND COLUMN_NAME IN ('total_xp', 'current_level', 'current_badge')
ORDER BY ORDINAL_POSITION;
