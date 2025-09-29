<?php

// Test sederhana untuk debugging import functionality
header('Content-Type: application/json');

try {
    echo json_encode([
        'message' => 'Test debug script works',
        'phpversion' => phpversion(),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'curl_available' => function_exists('curl_init'),
        'json_available' => function_exists('json_encode'),
        'phpspreadsheet_exists' => class_exists('PhpOffice\PhpSpreadsheet\IOFactory'),
        'current_time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}