-- Migration untuk menambahkan field GPS koordinat ke tabel absensi  
-- Ini diperlukan untuk sistem validasi absensi berbasis lokasi
-- File: add_gps_fields_to_absensi.sql

-- Tambahkan kolom latitude dan longitude untuk GPS tracking yang presisi
ALTER TABLE absensi 
ADD COLUMN latitude DECIMAL(10,8) NULL COMMENT 'Koordinat latitude GPS untuk validasi lokasi yang presisi',
ADD COLUMN longitude DECIMAL(11,8) NULL COMMENT 'Koordinat longitude GPS untuk validasi lokasi yang presisi';

-- Tambahkan index untuk query berdasarkan koordinat (untuk pencarian lokasi cepat)
CREATE INDEX idx_absensi_coordinates ON absensi (latitude, longitude);

-- Catatan:
-- - Kolom latitude: DECIMAL(10,8) - mendukung rentang -90 sampai 90 derajat dengan presisi 8 digit desimal
-- - Kolom longitude: DECIMAL(11,8) - mendukung rentang -180 sampai 180 derajat dengan presisi 8 digit desimal  
-- - Index ditambahkan untuk optimasi query pencarian berdasarkan lokasi
-- - Kolom nullable karena data lama mungkin tidak memiliki koordinat GPS