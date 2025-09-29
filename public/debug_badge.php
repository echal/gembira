<?php
// Debug script untuk mengecek badge validasi absen
header('Content-Type: application/json');

try {
    // Connect ke database
    $host = 'localhost';
    $dbname = 'gembira';
    $username = 'root';
    $password = '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query untuk cek total absensi
    $totalQuery = $pdo->query("SELECT COUNT(*) as total FROM absensi");
    $totalAbsensi = $totalQuery->fetch(PDO::FETCH_ASSOC)['total'];

    // Query untuk cek absensi pending
    $pendingQuery = $pdo->prepare("SELECT COUNT(*) as pending FROM absensi WHERE statusValidasi = ?");
    $pendingQuery->execute(['pending']);
    $pendingAbsensi = $pendingQuery->fetch(PDO::FETCH_ASSOC)['pending'];

    // Query untuk cek status validasi yang ada
    $statusQuery = $pdo->query("SELECT statusValidasi, COUNT(*) as jumlah FROM absensi GROUP BY statusValidasi");
    $statusValidasi = $statusQuery->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk cek sample data absensi
    $sampleQuery = $pdo->query("SELECT id, statusValidasi, tanggal, created_at FROM absensi ORDER BY id DESC LIMIT 5");
    $sampleData = $sampleQuery->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'total_absensi' => (int)$totalAbsensi,
            'pending_absensi' => (int)$pendingAbsensi,
            'status_breakdown' => $statusValidasi,
            'sample_data' => $sampleData,
            'expected_badge' => $pendingAbsensi > 0 ? $pendingAbsensi : 'BADGE HARUS HILANG',
            'query_time' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'General error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>