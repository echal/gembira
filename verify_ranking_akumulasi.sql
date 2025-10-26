-- ============================================================
-- SCRIPT VERIFIKASI SISTEM AKUMULASI SKOR BULANAN
-- File: verify_ranking_akumulasi.sql
-- ============================================================

-- 1. CEK STRUKTUR TABEL ranking_harian
-- Pastikan field jam_masuk dan skor_harian ada
-- ============================================================
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'ranking_harian'
ORDER BY ORDINAL_POSITION;

-- Expected Output:
-- - jam_masuk: TIME, NULL
-- - skor_harian: INT, NOT NULL
-- - total_durasi: INT, NULL (nullable untuk backward compatibility)

-- ============================================================
-- 2. CEK RANKING HARIAN HARI INI
-- Verifikasi skor harian dihitung dengan benar
-- ============================================================
SELECT
    p.nama AS 'Nama Pegawai',
    rh.jam_masuk AS 'Jam Masuk',
    rh.skor_harian AS 'Skor Hari Ini',
    rh.peringkat AS 'Peringkat Harian',
    rh.tanggal AS 'Tanggal'
FROM ranking_harian rh
JOIN pegawai p ON p.id = rh.pegawai_id
WHERE rh.tanggal = CURDATE()
ORDER BY rh.peringkat ASC;

-- Expected:
-- - Pegawai yang absen lebih awal memiliki skor lebih tinggi
-- - Skor maksimal 75 (absen pada 07:00)
-- - Skor berkurang 1 poin per menit keterlambatan

-- ============================================================
-- 3. CEK RANKING BULANAN BULAN INI (TOP 10)
-- Verifikasi akumulasi skor dari awal bulan
-- ============================================================
SELECT
    rb.peringkat AS '#',
    p.nama AS 'Nama Pegawai',
    u.nama_unit AS 'Unit Kerja',
    rb.total_durasi AS 'Total Skor (Akumulasi)',
    ROUND(rb.rata_rata_durasi, 2) AS 'Rata-rata Skor',
    rb.periode AS 'Periode',
    rb.updated_at AS 'Last Update'
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
LEFT JOIN unit_kerja u ON u.id = p.unit_kerja_entity_id
WHERE rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY rb.peringkat ASC
LIMIT 10;

-- Expected:
-- - Total Skor = jumlah akumulasi skor harian dari awal bulan
-- - Peringkat 1 = pegawai dengan total skor tertinggi
-- - Updated_at ter-update setiap kali ada absensi baru

-- ============================================================
-- 4. DETAIL AKUMULASI PER PEGAWAI (Contoh: Pegawai ID 1)
-- Melihat detail skor harian per hari untuk 1 pegawai
-- ============================================================
SELECT
    p.nama AS 'Nama Pegawai',
    rh.tanggal AS 'Tanggal',
    rh.jam_masuk AS 'Jam Masuk',
    rh.skor_harian AS 'Skor Hari Ini',
    (
        SELECT SUM(rh2.skor_harian)
        FROM ranking_harian rh2
        WHERE rh2.pegawai_id = rh.pegawai_id
          AND DATE_FORMAT(rh2.tanggal, '%Y-%m') = DATE_FORMAT(rh.tanggal, '%Y-%m')
          AND rh2.tanggal <= rh.tanggal
    ) AS 'Total Akumulasi Sampai Hari Ini'
FROM ranking_harian rh
JOIN pegawai p ON p.id = rh.pegawai_id
WHERE rh.pegawai_id = 1  -- Ganti dengan ID pegawai yang ingin dicek
  AND DATE_FORMAT(rh.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY rh.tanggal ASC;

-- Expected:
-- - Kolom "Total Akumulasi" bertambah setiap hari
-- - Skor hari ini = 75 - (menit terlambat dari 07:00)

-- ============================================================
-- 5. VERIFIKASI KONSISTENSI RANKING BULANAN vs HARIAN
-- Memastikan total skor di ranking_bulanan = SUM skor harian
-- ============================================================
SELECT
    p.nama AS 'Nama Pegawai',
    rb.total_durasi AS 'Total di ranking_bulanan',
    (
        SELECT COALESCE(SUM(rh.skor_harian), 0)
        FROM ranking_harian rh
        WHERE rh.pegawai_id = p.id
          AND DATE_FORMAT(rh.tanggal, '%Y-%m') = rb.periode
    ) AS 'Total SUM dari ranking_harian',
    (
        rb.total_durasi - (
            SELECT COALESCE(SUM(rh.skor_harian), 0)
            FROM ranking_harian rh
            WHERE rh.pegawai_id = p.id
              AND DATE_FORMAT(rh.tanggal, '%Y-%m') = rb.periode
        )
    ) AS 'Selisih (Harus 0)'
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
WHERE rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY rb.peringkat ASC
LIMIT 10;

-- Expected:
-- - Kolom "Selisih" harus bernilai 0 untuk semua pegawai
-- - Jika ada selisih, berarti ada inconsistency

-- ============================================================
-- 6. CEK PEGAWAI YANG BELUM MASUK RANKING BULANAN
-- Pegawai yang sudah absen tapi belum ada di ranking_bulanan
-- ============================================================
SELECT
    p.nama AS 'Nama Pegawai',
    COUNT(rh.id) AS 'Jumlah Hari Absen',
    SUM(rh.skor_harian) AS 'Total Skor Seharusnya'
FROM ranking_harian rh
JOIN pegawai p ON p.id = rh.pegawai_id
WHERE DATE_FORMAT(rh.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
  AND NOT EXISTS (
      SELECT 1
      FROM ranking_bulanan rb
      WHERE rb.pegawai_id = rh.pegawai_id
        AND rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
  )
GROUP BY p.id, p.nama;

-- Expected:
-- - Seharusnya tidak ada hasil (semua pegawai yang absen sudah ada di ranking_bulanan)
-- - Jika ada hasil, jalankan: php bin/console app:update-ranking-harian

-- ============================================================
-- 7. CEK TIMELINE ABSENSI HARI INI
-- Melihat urutan absensi berdasarkan waktu
-- ============================================================
SELECT
    a.id AS 'ID Absensi',
    p.nama AS 'Nama Pegawai',
    a.waktu_absensi AS 'Waktu Absensi',
    rh.skor_harian AS 'Skor',
    rh.peringkat AS 'Peringkat'
FROM absensi a
JOIN pegawai p ON p.id = a.pegawai_id
LEFT JOIN ranking_harian rh ON rh.pegawai_id = a.pegawai_id
    AND rh.tanggal = DATE(a.waktu_absensi)
WHERE DATE(a.waktu_absensi) = CURDATE()
  AND a.status = 'hadir'
ORDER BY a.waktu_absensi ASC;

-- Expected:
-- - Pegawai yang absen lebih awal memiliki peringkat lebih baik
-- - Skor menurun seiring waktu absensi semakin terlambat

-- ============================================================
-- 8. BENCHMARK QUERY PERFORMANCE
-- Ukur waktu eksekusi query ranking bulanan
-- ============================================================
SET profiling = 1;

SELECT
    rb.peringkat,
    p.nama,
    rb.total_durasi,
    rb.rata_rata_durasi
FROM ranking_bulanan rb
JOIN pegawai p ON p.id = rb.pegawai_id
WHERE rb.periode = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY rb.peringkat ASC
LIMIT 10;

SHOW PROFILES;
SET profiling = 0;

-- Expected:
-- - Query time < 0.01 second (dengan index yang tepat)
-- - Jika > 0.05 second, pertimbangkan tambah index atau caching

-- ============================================================
-- 9. CEK INDEX YANG ADA
-- Memastikan index optimal untuk query ranking
-- ============================================================
SELECT
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS 'Columns',
    INDEX_TYPE,
    NON_UNIQUE
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('ranking_harian', 'ranking_bulanan')
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;

-- Expected Index untuk ranking_harian:
-- - idx_pegawai_tanggal (pegawai_id, tanggal)
-- - idx_tanggal_peringkat (tanggal, peringkat)
-- - unique_pegawai_tanggal (pegawai_id, tanggal) UNIQUE

-- Expected Index untuk ranking_bulanan:
-- - idx_periode (periode)
-- - idx_peringkat (peringkat)

-- ============================================================
-- 10. SIMULATE RANKING CHANGE
-- Simulasi perubahan ranking setelah absensi baru
-- ============================================================
-- UNCOMMENT UNTUK TESTING (JANGAN DI PRODUCTION!)
/*
-- Backup data dulu
CREATE TEMPORARY TABLE temp_rh_backup AS SELECT * FROM ranking_harian;
CREATE TEMPORARY TABLE temp_rb_backup AS SELECT * FROM ranking_bulanan;

-- Simulasi absensi baru untuk pegawai ID 1 dengan skor tinggi (07:02)
INSERT INTO ranking_harian (pegawai_id, tanggal, jam_masuk, skor_harian, peringkat, updated_at)
VALUES (1, CURDATE(), '07:02:00', 73, 999, NOW())
ON DUPLICATE KEY UPDATE
    jam_masuk = '07:02:00',
    skor_harian = 73,
    updated_at = NOW();

-- Recalculate ranking (manual simulation)
SET @rank = 0;
UPDATE ranking_harian rh
JOIN (
    SELECT
        id,
        @rank := @rank + 1 AS new_rank
    FROM ranking_harian
    WHERE tanggal = CURDATE()
    ORDER BY skor_harian DESC, jam_masuk ASC
) ranked ON ranked.id = rh.id
SET rh.peringkat = ranked.new_rank;

-- Lihat hasil
SELECT * FROM ranking_harian WHERE tanggal = CURDATE() ORDER BY peringkat ASC;

-- Restore data
DELETE FROM ranking_harian WHERE tanggal = CURDATE();
INSERT INTO ranking_harian SELECT * FROM temp_rh_backup WHERE tanggal = CURDATE();
DROP TEMPORARY TABLE temp_rh_backup;
DROP TEMPORARY TABLE temp_rb_backup;
*/

-- ============================================================
-- DONE!
-- ============================================================
-- Jika semua query di atas berjalan tanpa error dan hasilnya
-- sesuai expected, maka sistem akumulasi skor sudah berjalan
-- dengan baik.
--
-- Next: Lakukan testing manual dengan absensi real di aplikasi
-- ============================================================
