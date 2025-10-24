-- Script untuk membuat tabel ranking_harian dan ranking_bulanan
-- Untuk fitur leaderboard dan gamification system

-- ============================================================
-- TABEL: ranking_harian
-- ============================================================

CREATE TABLE IF NOT EXISTS `ranking_harian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL COMMENT 'Waktu absen masuk',
  `skor_harian` int(11) NOT NULL COMMENT 'Skor harian maksimal 75 berdasarkan kecepatan absen',
  `total_durasi` int(11) DEFAULT NULL COMMENT 'Total durasi dalam menit (backward compatibility)',
  `peringkat` int(11) NOT NULL COMMENT 'Peringkat untuk hari ini (1=terbaik)',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pegawai_tanggal` (`pegawai_id`, `tanggal`),
  KEY `idx_pegawai_tanggal` (`pegawai_id`, `tanggal`),
  KEY `idx_tanggal_peringkat` (`tanggal`, `peringkat`),
  KEY `IDX_pegawai` (`pegawai_id`),
  CONSTRAINT `FK_ranking_harian_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL: ranking_bulanan
-- ============================================================

CREATE TABLE IF NOT EXISTS `ranking_bulanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `periode` varchar(7) NOT NULL COMMENT 'Format YYYY-MM (contoh: 2025-01)',
  `total_durasi` int(11) NOT NULL COMMENT 'Total durasi akumulasi dalam menit',
  `rata_rata_durasi` float DEFAULT NULL COMMENT 'Rata-rata durasi per hari',
  `peringkat` int(11) NOT NULL COMMENT 'Peringkat untuk bulan ini (1=terbaik)',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pegawai_periode` (`pegawai_id`, `periode`),
  KEY `idx_pegawai_periode` (`pegawai_id`, `periode`),
  KEY `idx_periode_peringkat` (`periode`, `peringkat`),
  KEY `IDX_pegawai` (`pegawai_id`),
  CONSTRAINT `FK_ranking_bulanan_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VERIFIKASI
-- ============================================================

SELECT '✅ TABEL RANKING BERHASIL DIBUAT' AS status;

-- Cek tabel ranking_harian
SELECT
    CONCAT('✅ Tabel ranking_harian: ', COUNT(*), ' kolom') AS info
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'ranking_harian';

-- Cek tabel ranking_bulanan
SELECT
    CONCAT('✅ Tabel ranking_bulanan: ', COUNT(*), ' kolom') AS info
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'ranking_bulanan';

-- List semua kolom ranking_harian
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'ranking_harian'
ORDER BY ORDINAL_POSITION;

-- List semua kolom ranking_bulanan
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'gembira_db'
  AND TABLE_NAME = 'ranking_bulanan'
ORDER BY ORDINAL_POSITION;
