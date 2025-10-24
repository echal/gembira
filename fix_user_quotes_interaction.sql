-- Fix user_quotes_interaction table
-- Masalah:
-- 1. Entity menggunakan property 'user' yang map ke kolom 'user_id'
--    tapi tabel menggunakan 'pegawai_id'
-- 2. Entity punya kolom 'comment' yang tidak ada di tabel

-- Rename kolom pegawai_id menjadi user_id
ALTER TABLE user_quotes_interaction
CHANGE pegawai_id user_id INT(11) NOT NULL COMMENT 'Foreign key ke pegawai';

-- Tambah kolom comment
ALTER TABLE user_quotes_interaction
ADD COLUMN IF NOT EXISTS comment TEXT NULL COMMENT 'Komentar user pada quote'
AFTER saved;

-- Verifikasi struktur
DESCRIBE user_quotes_interaction;
