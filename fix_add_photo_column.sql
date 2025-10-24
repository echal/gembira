-- Tambah kolom photo ke tabel pegawai
-- Untuk mendukung fitur foto profil di IKHLAS comments

ALTER TABLE `pegawai`
ADD COLUMN `photo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path foto profil pegawai'
AFTER `tanda_tangan_uploaded_at`;

-- Verifikasi
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'pegawai'
  AND COLUMN_NAME = 'photo';
