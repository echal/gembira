-- Migration for creating sliders table
-- Run this SQL in your MySQL database

CREATE TABLE sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    order_no INT DEFAULT 0,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_order (status, order_no),
    INDEX idx_order (order_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
INSERT INTO sliders (title, description, image_path, order_no, status) VALUES
('Selamat Datang di GEMBIRA', 'Gerakan Munajat Bersama Untuk Kinerja - Aplikasi Absensi Modern', 'sample-banner-1.jpg', 1, 'aktif'),
('Absensi Digital Mudah', 'Sistem absensi dengan QR Code dan GPS untuk kemudahan pegawai', 'sample-banner-2.jpg', 2, 'aktif');