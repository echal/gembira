-- ================================================================
-- SCRIPT MENGHAPUS QUOTES DARI USER TIDAK VALID
-- ================================================================
-- User yang tidak valid (tidak ada dalam daftar pegawai):
--   - Pak Dedi
--   - Pak Budi
--   - Bu Ani
--   - Anonim
--
-- PENTING: Backup database sebelum menjalankan script ini!
-- ================================================================

-- 1. CEK QUOTES YANG AKAN DIHAPUS
SELECT
    id,
    author,
    SUBSTRING(content, 1, 100) as content_preview,
    created_at,
    updated_at
FROM quote
WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani')
ORDER BY created_at DESC;

-- Hasil yang diharapkan berdasarkan verify_ikhlas_data.php:
-- - 2 quotes dari "Anonim" (ID 1, 2)
-- - 1 quote dari "Bu Ani" (ID 3)
-- - 1 quote dari "Pak Budi"
-- - 1 quote dari "Pak Dedi"
-- Total: 5 quotes

-- 2. CEK JUMLAH INTERACTIONS YANG AKAN TERHAPUS
SELECT COUNT(*) as total_interactions_to_delete
FROM user_quote_interaction
WHERE quote_id IN (
    SELECT id FROM quote
    WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani')
);

-- Berdasarkan data: 24 total interactions, kemungkinan 12-15 interactions akan terhapus

-- 3. DETAIL INTERACTIONS YANG AKAN TERHAPUS
SELECT
    uqi.id as interaction_id,
    p.nama as pegawai_nama,
    q.id as quote_id,
    q.author as quote_author,
    SUBSTRING(q.content, 1, 60) as quote_preview,
    uqi.liked,
    uqi.saved,
    uqi.created_at
FROM user_quote_interaction uqi
JOIN pegawai p ON uqi.pegawai_id = p.id
JOIN quote q ON uqi.quote_id = q.id
WHERE q.author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani')
ORDER BY q.author, uqi.created_at DESC;

-- ================================================================
-- PERINGATAN:
-- 1. Pastikan Anda sudah BACKUP database!
-- 2. Periksa hasil query SELECT di atas sebelum menjalankan DELETE!
-- 3. Uncomment query DELETE di bawah untuk menjalankan penghapusan
-- ================================================================

-- STEP 4: HAPUS INTERACTIONS TERLEBIH DAHULU (foreign key constraint)
-- UNCOMMENT 3 BARIS DI BAWAH INI UNTUK MENJALANKAN:
-- DELETE FROM user_quote_interaction
-- WHERE quote_id IN (
--     SELECT id FROM quote
--     WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani')
-- );

-- STEP 5: HAPUS QUOTES DARI USER TIDAK VALID
-- UNCOMMENT 1 BARIS DI BAWAH INI UNTUK MENJALANKAN:
-- DELETE FROM quote
-- WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani');

-- ================================================================
-- VERIFIKASI SETELAH PENGHAPUSAN
-- ================================================================

-- 6. CEK APAKAH MASIH ADA QUOTES DARI USER TIDAK VALID
SELECT COUNT(*) as remaining_invalid_quotes
FROM quote
WHERE author IN ('Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani');

-- Hasil yang diharapkan: 0

-- 7. CEK TOTAL QUOTES YANG TERSISA
SELECT COUNT(*) as total_remaining_quotes FROM quote;

-- Hasil yang diharapkan: 3 quotes (8 - 5 = 3)

-- 8. CEK TOTAL INTERACTIONS YANG TERSISA
SELECT COUNT(*) as total_remaining_interactions FROM user_quote_interaction;

-- 9. CEK QUOTES YANG TERSISA (harus hanya dari pegawai valid)
SELECT
    q.id,
    q.author,
    SUBSTRING(q.content, 1, 80) as content_preview,
    COUNT(uqi.id) as total_interactions
FROM quote q
LEFT JOIN user_quote_interaction uqi ON uqi.quote_id = q.id
GROUP BY q.id, q.author, q.content
ORDER BY total_interactions DESC;

-- Hasil yang diharapkan: Semua author adalah nama pegawai yang valid dari tabel pegawai
