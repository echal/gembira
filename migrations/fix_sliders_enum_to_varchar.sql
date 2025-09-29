-- Migration untuk mengubah tipe data ENUM ke VARCHAR pada tabel sliders
-- Ini diperlukan untuk kompatibilitas dengan Doctrine DBAL
-- File: fix_sliders_enum_to_varchar.sql

-- Ubah kolom status dari ENUM ke VARCHAR untuk kompatibilitas Doctrine
ALTER TABLE sliders MODIFY status VARCHAR(20) DEFAULT 'aktif';

-- Tambahkan constraint untuk memastikan nilai status tetap valid
-- ALTER TABLE sliders ADD CONSTRAINT chk_slider_status CHECK (status IN ('aktif', 'nonaktif'));

-- Catatan: 
-- - Perubahan ini untuk mengatasi error "Unknown database type enum" di Doctrine DBAL
-- - Kolom status sekarang menggunakan VARCHAR(20) yang kompatibel dengan Types::STRING
-- - Constraint bisa diaktifkan jika ingin validasi nilai pada level database