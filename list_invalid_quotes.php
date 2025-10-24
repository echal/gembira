<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env file
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

echo "🔍 MENCARI QUOTES DARI USER TIDAK VALID\n";
echo "=========================================\n\n";

// User yang tidak valid (tidak ada dalam daftar pegawai)
$invalidAuthors = ['Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani'];

echo "User tidak valid:\n";
foreach ($invalidAuthors as $author) {
    echo "  • $author\n";
}
echo "\n";

// Identifikasi quotes yang akan dihapus
$qb = $em->createQueryBuilder();
$qb->select('q')
   ->from('App\Entity\Quote', 'q')
   ->where($qb->expr()->in('q.author', ':authors'))
   ->setParameter('authors', $invalidAuthors)
   ->orderBy('q.createdAt', 'DESC');

$quotes = $qb->getQuery()->getResult();

$totalQuotes = count($quotes);
echo "📊 Total quotes ditemukan: $totalQuotes\n\n";

if ($totalQuotes === 0) {
    echo "✅ Tidak ada quotes dari user tidak valid. Database sudah bersih!\n";
    exit(0);
}

// Tampilkan detail quotes
echo "DETAIL QUOTES YANG AKAN DIHAPUS:\n";
echo "=================================\n";
foreach($quotes as $quote) {
    echo "\n📝 Quote ID: " . $quote->getId() . "\n";
    echo "   Author: " . $quote->getAuthor() . "\n";
    echo "   Category: " . ($quote->getCategory() ?? 'N/A') . "\n";
    echo "   Active: " . ($quote->isActive() ? 'Yes' : 'No') . "\n";
    echo "   Content: " . substr($quote->getContent(), 0, 100) . "...\n";
    echo "   Created: " . $quote->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
}

// Cek interactions terkait
$qbInteractions = $em->createQueryBuilder();
$qbInteractions->select('i')
    ->from('App\Entity\UserQuoteInteraction', 'i')
    ->where($qbInteractions->expr()->in('i.quote', ':quotes'))
    ->setParameter('quotes', $quotes);

$interactions = $qbInteractions->getQuery()->getResult();
$totalInteractions = count($interactions);

echo "\n\n🔗 INTERACTIONS TERKAIT:\n";
echo "========================\n";
echo "Total interactions yang akan terhapus: $totalInteractions\n";

if ($totalInteractions > 0) {
    echo "\nDetail interactions:\n";
    foreach($interactions as $interaction) {
        echo "  • Pegawai: " . $interaction->getPegawai()->getNama();
        echo " | Quote Author: " . $interaction->getQuote()->getAuthor();
        echo " | Liked: " . ($interaction->isLiked() ? '❤️' : '—');
        echo " | Saved: " . ($interaction->isSaved() ? '📌' : '—');
        echo "\n";
    }
}

echo "\n\n📋 RINGKASAN\n";
echo "============\n";
echo "Total quotes yang akan dihapus: $totalQuotes\n";
echo "Total interactions yang akan dihapus: $totalInteractions\n";

echo "\n✨ Untuk menghapus, jalankan: php delete_invalid_user_quotes.php\n";
