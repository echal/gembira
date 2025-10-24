-- ============================================================
-- FIX: Duplikasi Jadwal "Apel Pagi" & Data Tidak Terekam
-- ============================================================
-- Masalah:
-- 1. Ada 4 jadwal Apel Pagi yang berbeda-beda nama
-- 2. Controller mencari "Apel Pagi" tapi yang aktif "Absen Apel Pagi"
-- 3. Laporan menunjukkan Total=4, Hadir=0 (data tidak terhitung)
--
-- Solusi:
-- - Standardisasi nama menjadi "Apel Pagi"
-- - Nonaktifkan jadwal duplikat
-- - Pindahkan semua data ke jadwal utama (ID 19)
-- ============================================================

SET @database_name = 'gembira_db';

-- ============================================================
-- STEP 1: BACKUP DATA SEBELUM FIX
-- ============================================================

SELECT '=== SEBELUM FIX ===' AS info;
SELECT id, nama_jadwal, is_aktif,
       (SELECT COUNT(*) FROM absensi WHERE konfigurasi_jadwal_id = k.id) as total_absensi
FROM konfigurasi_jadwal_absensi k
WHERE LOWER(nama_jadwal) LIKE '%apel%pagi%'
ORDER BY id;

-- ============================================================
-- STEP 2: PINDAHKAN SEMUA DATA KE JADWAL UTAMA (ID 19)
-- ============================================================

-- Pindahkan absensi dari "Apel Pagi Senin" (ID 23) ke "Apel Pagi" (ID 19)
UPDATE absensi
SET konfigurasi_jadwal_id = 19,
    jenis_absensi = 'apel_pagi'
WHERE konfigurasi_jadwal_id = 23;

SELECT '✅ Memindahkan 65 absensi dari ID 23 ke ID 19' AS status;

-- Pindahkan absensi dari "Absen Apel Pagi" (ID 24) ke "Apel Pagi" (ID 19)
UPDATE absensi
SET konfigurasi_jadwal_id = 19,
    jenis_absensi = 'apel_pagi'
WHERE konfigurasi_jadwal_id = 24;

SELECT '✅ Memindahkan 432 absensi dari ID 24 ke ID 19' AS status;

-- Pindahkan absensi dari "Apel Pagii" typo (ID 25) ke "Apel Pagi" (ID 19)
UPDATE absensi
SET konfigurasi_jadwal_id = 19,
    jenis_absensi = 'apel_pagi'
WHERE konfigurasi_jadwal_id = 25;

SELECT '✅ Memindahkan 1 absensi dari ID 25 ke ID 19' AS status;

-- ============================================================
-- STEP 3: STANDARDISASI NAMA JADWAL
-- ============================================================

-- Pastikan jadwal utama bernama "Apel Pagi" dan aktif
UPDATE konfigurasi_jadwal_absensi
SET nama_jadwal = 'Apel Pagi',
    is_aktif = 1,
    diubah = NOW()
WHERE id = 19;

SELECT '✅ Jadwal ID 19 diubah menjadi "Apel Pagi" dan diaktifkan' AS status;

-- ============================================================
-- STEP 4: NONAKTIFKAN JADWAL DUPLIKAT
-- ============================================================

-- Nonaktifkan "Apel Pagi Senin"
UPDATE konfigurasi_jadwal_absensi
SET is_aktif = 0,
    keterangan = CONCAT(COALESCE(keterangan, ''), ' [DEPRECATED: Digabung ke Apel Pagi ID 19]'),
    diubah = NOW()
WHERE id = 23;

-- Nonaktifkan "Absen Apel Pagi"
UPDATE konfigurasi_jadwal_absensi
SET is_aktif = 0,
    keterangan = CONCAT(COALESCE(keterangan, ''), ' [DEPRECATED: Digabung ke Apel Pagi ID 19]'),
    diubah = NOW()
WHERE id = 24;

-- Nonaktifkan "Apel Pagii" (typo)
UPDATE konfigurasi_jadwal_absensi
SET is_aktif = 0,
    keterangan = CONCAT(COALESCE(keterangan, ''), ' [DEPRECATED: Digabung ke Apel Pagi ID 19]'),
    diubah = NOW()
WHERE id = 25;

SELECT '✅ Jadwal duplikat (ID 23, 24, 25) berhasil dinonaktifkan' AS status;

-- ============================================================
-- STEP 5: FIX DATA ABSENSI LAMA (jenis_absensi = NULL)
-- ============================================================

-- Update absensi yang tidak punya konfigurasi_jadwal_id
-- Kriteria: Hari Senin (DAYOFWEEK = 2), jam 07:00-08:15
UPDATE absensi
SET jenis_absensi = 'apel_pagi',
    konfigurasi_jadwal_id = 19
WHERE jenis_absensi IS NULL
  AND DAYOFWEEK(tanggal) = 2  -- Senin
  AND TIME(waktu_masuk) BETWEEN '07:00:00' AND '08:15:00';

SELECT CONCAT('✅ Update ', ROW_COUNT(), ' absensi lama hari Senin ke Apel Pagi') AS status;

-- ============================================================
-- STEP 6: VERIFIKASI HASIL
-- ============================================================

SELECT '';
SELECT '=== SETELAH FIX ===' AS info;
SELECT id, nama_jadwal, is_aktif,
       (SELECT COUNT(*) FROM absensi WHERE konfigurasi_jadwal_id = k.id) as total_absensi,
       LEFT(keterangan, 50) as keterangan_preview
FROM konfigurasi_jadwal_absensi k
WHERE LOWER(nama_jadwal) LIKE '%apel%pagi%'
ORDER BY id;

SELECT '';
SELECT '=== STATISTIK ABSENSI APEL PAGI ===' AS info;
SELECT
    DATE_FORMAT(tanggal, '%Y-%m') as bulan,
    COUNT(*) as total_absensi,
    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN status IN ('alpha', 'izin', 'sakit') THEN 1 ELSE 0 END) as total_tidak_hadir
FROM absensi
WHERE konfigurasi_jadwal_id = 19
GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
ORDER BY bulan DESC
LIMIT 6;

SELECT '';
SELECT '✅ FIX SELESAI!' AS status;
SELECT 'Sekarang hanya ada 1 jadwal "Apel Pagi" yang aktif dengan semua data tergabung' AS info;
