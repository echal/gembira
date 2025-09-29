<?php

/**
 * Bootstrap file untuk aplikasi Gembira
 * 
 * File ini dipanggil sebelum aplikasi Symfony dimulai
 * untuk memastikan timezone konsisten di seluruh aplikasi
 */

// Set timezone untuk PHP dan seluruh aplikasi
// WITA = Waktu Indonesia Tengah (UTC+8) 
// Sesuai dengan lokasi Kanwil Kemenag Sulawesi Barat
date_default_timezone_set('Asia/Makassar');

// Verify timezone setting
if (date_default_timezone_get() !== 'Asia/Makassar') {
    throw new RuntimeException('Failed to set timezone to Asia/Makassar');
}

// Optional: Log timezone setting untuk debugging
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'dev') {
    error_log('Gembira App: Timezone set to ' . date_default_timezone_get() . ' (' . date('T') . ')');
}