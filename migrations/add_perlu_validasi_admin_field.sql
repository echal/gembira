-- Migration untuk menambahkan field perlu_validasi_admin ke tabel konfigurasi_jadwal_absensi
-- Ini diperlukan untuk fitur validasi absensi admin
-- File: add_perlu_validasi_admin_field.sql

-- Tambahkan kolom perlu_validasi_admin dengan default FALSE
ALTER TABLE konfigurasi_jadwal_absensi 
ADD COLUMN perlu_validasi_admin BOOLEAN NOT NULL DEFAULT FALSE 
COMMENT 'Apakah jadwal ini memerlukan validasi admin sebelum absensi dianggap sah';

-- Update beberapa jadwal penting yang perlu validasi
-- (Admin bisa mengubah ini melalui interface web nanti)
-- UPDATE konfigurasi_jadwal_absensi 
-- SET perlu_validasi_admin = TRUE 
-- WHERE nama_jadwal IN ('Doa Bersama', 'Apel Pagi', 'Rapat Koordinasi');

-- Catatan:
-- - Field ini menentukan apakah absensi pada jadwal tertentu perlu divalidasi admin
-- - Default: FALSE (absensi langsung sah)
-- - Jika TRUE: absensi masuk dengan status 'pending' dan perlu approval admin
-- - Admin dapat mengubah setting ini melalui interface manajemen jadwal