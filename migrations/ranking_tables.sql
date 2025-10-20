-- ============================================================
-- SQL Migration untuk Sistem Ranking Dinamis
-- Aplikasi GEMBIRA (Gerakan Munajat Bersama Untuk Kinerja)
-- ============================================================
--
-- File ini berisi SQL untuk membuat 3 tabel baru:
-- 1. absensi_durasi - Menyimpan durasi absensi harian
-- 2. ranking_harian - Menyimpan ranking pegawai setiap hari
-- 3. ranking_bulanan - Menyimpan ranking pegawai setiap bulan
--
-- Cara menjalankan:
-- 1. Buka phpMyAdmin atau MySQL client
-- 2. Pilih database aplikasi gembira
-- 3. Jalankan script ini
--
-- Atau via command line:
-- mysql -u root -p gembira < ranking_tables.sql
-- ============================================================

-- Tabel 1: absensi_durasi
-- Menyimpan durasi absensi harian setiap pegawai
CREATE TABLE IF NOT EXISTS absensi_durasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NOT NULL,
    durasi_menit INT NOT NULL COMMENT 'Durasi dalam menit (positif = terlambat, negatif = lebih awal)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Index untuk performa query
    INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
    INDEX idx_tanggal (tanggal),

    -- Foreign key constraint
    CONSTRAINT fk_absensi_durasi_pegawai
        FOREIGN KEY (pegawai_id)
        REFERENCES pegawai(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Menyimpan durasi absensi harian setiap pegawai';

-- ============================================================

-- Tabel 2: ranking_harian
-- Menyimpan ranking pegawai setiap hari
CREATE TABLE IF NOT EXISTS ranking_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    tanggal DATE NOT NULL,
    total_durasi INT NOT NULL COMMENT 'Total durasi dalam menit untuk hari ini',
    peringkat INT NOT NULL COMMENT 'Peringkat pegawai (1 = terbaik)',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Index untuk performa query
    INDEX idx_pegawai_tanggal (pegawai_id, tanggal),
    INDEX idx_tanggal_peringkat (tanggal, peringkat),

    -- Unique constraint: satu pegawai hanya punya satu ranking per hari
    UNIQUE KEY unique_pegawai_tanggal (pegawai_id, tanggal),

    -- Foreign key constraint
    CONSTRAINT fk_ranking_harian_pegawai
        FOREIGN KEY (pegawai_id)
        REFERENCES pegawai(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Menyimpan ranking pegawai setiap hari';

-- ============================================================

-- Tabel 3: ranking_bulanan
-- Menyimpan ranking pegawai setiap bulan
CREATE TABLE IF NOT EXISTS ranking_bulanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pegawai_id INT NOT NULL,
    periode VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM (contoh: 2025-01)',
    total_durasi INT NOT NULL COMMENT 'Total durasi akumulasi selama sebulan (dalam menit)',
    rata_rata_durasi FLOAT DEFAULT NULL COMMENT 'Rata-rata durasi per hari (dalam menit)',
    peringkat INT NOT NULL COMMENT 'Peringkat pegawai (1 = terbaik)',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Index untuk performa query
    INDEX idx_pegawai_periode (pegawai_id, periode),
    INDEX idx_periode_peringkat (periode, peringkat),

    -- Unique constraint: satu pegawai hanya punya satu ranking per periode
    UNIQUE KEY unique_pegawai_periode (pegawai_id, periode),

    -- Foreign key constraint
    CONSTRAINT fk_ranking_bulanan_pegawai
        FOREIGN KEY (pegawai_id)
        REFERENCES pegawai(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Menyimpan ranking pegawai setiap bulan (akumulasi dari ranking harian)';

-- ============================================================
-- Selesai! Tabel ranking berhasil dibuat.
-- ============================================================

-- VERIFIKASI: Cek apakah tabel sudah dibuat
SHOW TABLES LIKE '%ranking%';
SHOW TABLES LIKE '%durasi%';

-- Lihat struktur tabel
DESCRIBE absensi_durasi;
DESCRIBE ranking_harian;
DESCRIBE ranking_bulanan;

-- ============================================================
-- CATATAN PENTING:
-- ============================================================
--
-- 1. Setelah menjalankan migration ini, jalankan command berikut
--    untuk menghitung ranking bulan ini:
--
--    php bin/console app:reset-ranking
--
-- 2. Setup cron job untuk auto-reset setiap awal bulan:
--
--    0 0 1 * * cd /path/to/project && php bin/console app:reset-ranking
--
-- 3. Jika ingin menghapus semua tabel ranking (hati-hati!):
--
--    DROP TABLE IF EXISTS ranking_bulanan;
--    DROP TABLE IF EXISTS ranking_harian;
--    DROP TABLE IF EXISTS absensi_durasi;
--
-- ============================================================
