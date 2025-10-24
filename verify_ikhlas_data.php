<?php
/**
 * Script untuk verify data IKHLAS di database
 * Memastikan data quotes dan interactions tersedia
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Load .env
(new Dotenv())->bootEnv(__DIR__ . '/.env');

// Boot Symfony kernel
$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

// Get entity manager
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

echo "=================================================\n";
echo "VERIFY DATA IKHLAS\n";
echo "=================================================\n\n";

// 1. Check Quotes
echo "1. CHECKING QUOTES TABLE\n";
echo "-------------------------------------------\n";
$quotesCount = $entityManager->createQueryBuilder()
    ->select('COUNT(q.id)')
    ->from('App\Entity\Quote', 'q')
    ->where('q.isActive = 1')
    ->getQuery()
    ->getSingleScalarResult();

echo "Total Quotes (Active): " . $quotesCount . "\n";

if ($quotesCount > 0) {
    // Show sample quotes
    $sampleQuotes = $entityManager->createQueryBuilder()
        ->select('q.id', 'q.content', 'q.author', 'q.category')
        ->from('App\Entity\Quote', 'q')
        ->where('q.isActive = 1')
        ->setMaxResults(3)
        ->getQuery()
        ->getResult();

    echo "\nSample Quotes:\n";
    foreach ($sampleQuotes as $quote) {
        echo "  - ID: {$quote['id']}\n";
        echo "    Author: {$quote['author']}\n";
        echo "    Content: " . substr($quote['content'], 0, 100) . "...\n";
        echo "    Category: {$quote['category']}\n\n";
    }
} else {
    echo "⚠️  TIDAK ADA QUOTES! Perlu insert data quotes terlebih dahulu.\n\n";
}

// 2. Check Interactions
echo "2. CHECKING USER QUOTE INTERACTIONS\n";
echo "-------------------------------------------\n";
$interactionsCount = $entityManager->createQueryBuilder()
    ->select('COUNT(i.id)')
    ->from('App\Entity\UserQuoteInteraction', 'i')
    ->getQuery()
    ->getSingleScalarResult();

echo "Total Interactions: " . $interactionsCount . "\n";

if ($interactionsCount > 0) {
    // Get stats
    $stats = $entityManager->createQueryBuilder()
        ->select('COUNT(i.id) as total')
        ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as likes')
        ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as saves')
        ->from('App\Entity\UserQuoteInteraction', 'i')
        ->getQuery()
        ->getOneOrNullResult();

    echo "  - Total Likes: " . ($stats['likes'] ?? 0) . " ❤️\n";
    echo "  - Total Saves: " . ($stats['saves'] ?? 0) . " 📌\n";
    echo "  - Total Interactions: " . ($stats['total'] ?? 0) . "\n\n";

    // Show top users
    echo "Top 5 Users dengan Interaksi Terbanyak:\n";
    $topUsers = $entityManager->createQueryBuilder()
        ->select('p.nama')
        ->addSelect('COUNT(i.id) as total_interactions')
        ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as total_likes')
        ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as total_saves')
        ->from('App\Entity\Pegawai', 'p')
        ->leftJoin('App\Entity\UserQuoteInteraction', 'i', 'WITH', 'i.user = p.id')
        ->groupBy('p.id', 'p.nama')
        ->having('total_interactions > 0')
        ->orderBy('total_interactions', 'DESC')
        ->setMaxResults(5)
        ->getQuery()
        ->getResult();

    foreach ($topUsers as $index => $user) {
        $rank = $index + 1;
        echo "  {$rank}. {$user['nama']}\n";
        echo "     Interactions: {$user['total_interactions']} | Likes: {$user['total_likes']} | Saves: {$user['total_saves']}\n";
    }
    echo "\n";

} else {
    echo "⚠️  TIDAK ADA INTERACTIONS! User belum like/save quotes.\n\n";
}

// 3. Check Top Quotes
echo "3. CHECKING TOP QUOTES BY INTERACTIONS\n";
echo "-------------------------------------------\n";

$topQuotes = $entityManager->createQueryBuilder()
    ->select('q.id', 'q.content', 'q.author')
    ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as total_likes')
    ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as total_saves')
    ->addSelect('COUNT(i.id) as total_interactions')
    ->from('App\Entity\Quote', 'q')
    ->leftJoin('App\Entity\UserQuoteInteraction', 'i', 'WITH', 'i.quote = q.id')
    ->where('q.isActive = 1')
    ->groupBy('q.id', 'q.content', 'q.author')
    ->having('total_interactions > 0')
    ->orderBy('total_likes', 'DESC')
    ->addOrderBy('total_saves', 'DESC')
    ->setMaxResults(5)
    ->getQuery()
    ->getResult();

if (count($topQuotes) > 0) {
    echo "Top 5 Quotes Terpopuler:\n";
    foreach ($topQuotes as $index => $quote) {
        $rank = $index + 1;
        echo "  {$rank}. \"{$quote['content']}\"\n";
        echo "     - {$quote['author']}\n";
        echo "     Likes: {$quote['total_likes']} ❤️ | Saves: {$quote['total_saves']} 📌 | Total: {$quote['total_interactions']}\n\n";
    }
} else {
    echo "⚠️  Belum ada quotes dengan interactions\n\n";
}

// 4. Summary
echo "=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n";

$hasData = $quotesCount > 0 && $interactionsCount > 0;

if ($hasData) {
    echo "✅ DATA LENGKAP - Statistik Global dan Top Quotes sudah ada data\n";
    echo "✅ Leaderboard akan menampilkan data REAL dari database\n";
    echo "\n";
    echo "Data yang akan ditampilkan:\n";
    echo "- Total Quotes: " . $quotesCount . "\n";
    echo "- Total Interactions: " . $interactionsCount . "\n";
    echo "- Total Likes: " . ($stats['likes'] ?? 0) . " ❤️\n";
    echo "- Total Saves: " . ($stats['saves'] ?? 0) . " 📌\n";
} else {
    echo "⚠️  DATA BELUM LENGKAP\n";
    echo "\n";
    if ($quotesCount == 0) {
        echo "❌ Quotes kosong - Perlu insert quotes ke database\n";
    }
    if ($interactionsCount == 0) {
        echo "❌ Interactions kosong - User perlu like/save quotes\n";
    }
    echo "\n";
    echo "REKOMENDASI:\n";
    echo "1. Pastikan ada quotes di database (table: quote)\n";
    echo "2. User perlu like/save quotes untuk generate interactions\n";
    echo "3. Setelah ada data, clear cache: php bin/console cache:clear\n";
}

echo "=================================================\n";
