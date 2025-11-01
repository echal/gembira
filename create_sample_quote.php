<?php
/**
 * Create sample quote data for testing
 */

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

try {
    $conn = DriverManager::getConnection([
        'dbname' => 'gembira_db',
        'user' => 'root',
        'password' => '',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
        'charset' => 'utf8mb4',
    ]);

    echo "=== Creating Sample Quote ===\n\n";

    // Sample quote data
    $content = "Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.";
    $author = "Colin Powell";
    $category = "Motivasi";
    $createdAt = date('Y-m-d H:i:s');

    // Insert quote
    $affected = $conn->executeStatement(
        "INSERT INTO quotes (content, author, category, is_active, created_at, total_likes, total_comments, total_views)
         VALUES (?, ?, ?, 1, ?, 0, 0, 0)",
        [$content, $author, $category, $createdAt]
    );

    if ($affected > 0) {
        $quoteId = $conn->lastInsertId();

        echo "✅ Sample quote created successfully!\n\n";
        echo "Quote ID: $quoteId\n";
        echo "Content: $content\n";
        echo "Author: $author\n";
        echo "Category: $category\n";
        echo "Created At: $createdAt\n";

        // Verify
        echo "\n--- Verification ---\n";
        $quote = $conn->fetchAssociative(
            "SELECT * FROM quotes WHERE id = ?",
            [$quoteId]
        );

        if ($quote) {
            echo "✓ Quote verified in database\n";
            echo "  Content: {$quote['content']}\n";
            echo "  Author: {$quote['author']}\n";
            echo "  Category: {$quote['category']}\n";
        }

        echo "\n✅ Done! You can now see this quote at /ikhlas\n";
    } else {
        echo "❌ Failed to create quote\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
