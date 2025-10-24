<?php
require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection([
    'dbname' => 'gembira_db',
    'user' => 'root',
    'password' => '',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
]);

echo "=== PEGAWAI TABLE COLUMNS ===\n\n";
$columns = $conn->fetchAllAssociative('SHOW COLUMNS FROM pegawai');

foreach ($columns as $col) {
    echo sprintf("%-30s | %-20s | %s\n",
        $col['Field'],
        $col['Type'],
        $col['Default'] ?? 'NULL'
    );
}
