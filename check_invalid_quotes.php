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

// Cari quotes dari author yang tidak valid
$invalidAuthors = ['Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani'];

$qb = $em->createQueryBuilder();
$qb->select('q.id', 'q.author', 'q.content', 'q.category', 'q.createdAt', 'q.isActive')
   ->from('App\Entity\Quote', 'q')
   ->where($qb->expr()->in('q.author', ':authors'))
   ->setParameter('authors', $invalidAuthors)
   ->orderBy('q.createdAt', 'DESC');

$quotes = $qb->getQuery()->getResult();

echo "Total quotes ditemukan: " . count($quotes) . "\n\n";

$quoteIds = [];

if (count($quotes) > 0) {
    echo "DETAIL QUOTES:\n";
    echo "==============\n";
    foreach($quotes as $quote) {
        $quoteIds[] = $quote['id'];
        echo "\n📝 Quote ID: " . $quote['id'] . "\n";
        echo "   Author: " . $quote['author'] . "\n";
        echo "   Category: " . ($quote['category'] ?? 'N/A') . "\n";
        echo "   Active: " . ($quote['isActive'] ? 'Yes' : 'No') . "\n";
        echo "   Content: " . substr($quote['content'], 0, 100) . "...\n";
        echo "   Created: " . $quote['createdAt']->format('Y-m-d H:i:s') . "\n";
    }

    // Cek interactions terkait
    if (!empty($quoteIds)) {
        echo "\n\n🔗 CHECKING RELATED INTERACTIONS\n";
        echo "=================================\n";

        $qbInteractions = $em->createQueryBuilder();
        $qbInteractions->select('COUNT(i.id) as total')
            ->from('App\Entity\UserQuoteInteraction', 'i')
            ->where($qbInteractions->expr()->in('i.quote', ':quoteIds'))
            ->setParameter('quoteIds', $quoteIds);

        $interactionCount = $qbInteractions->getQuery()->getSingleScalarResult();
        echo "Total interactions yang akan terhapus: " . $interactionCount . "\n";

        // Detail interactions
        $qbInteractionDetails = $em->createQueryBuilder();
        $qbInteractionDetails->select('i.id', 'p.nama as pegawai_nama', 'q.author', 'i.liked', 'i.saved')
            ->from('App\Entity\UserQuoteInteraction', 'i')
            ->join('i.pegawai', 'p')
            ->join('i.quote', 'q')
            ->where($qbInteractionDetails->expr()->in('i.quote', ':quoteIds'))
            ->setParameter('quoteIds', $quoteIds);

        $interactions = $qbInteractionDetails->getQuery()->getResult();

        foreach($interactions as $interaction) {
            echo "\n  • Pegawai: " . $interaction['pegawai_nama'];
            echo " | Quote Author: " . $interaction['author'];
            echo " | Liked: " . ($interaction['liked'] ? '❤️' : '—');
            echo " | Saved: " . ($interaction['saved'] ? '📌' : '—');
            echo "\n";
        }
    }

    echo "\n\n⚠️  SUMMARY\n";
    echo "===========\n";
    echo "Total quotes to delete: " . count($quotes) . "\n";
    echo "Total interactions to delete: " . ($interactionCount ?? 0) . "\n";
    echo "\nQuote IDs: [" . implode(', ', $quoteIds) . "]\n";

} else {
    echo "✅ Tidak ada quotes dari user tidak valid.\n";
}

echo "\n✨ Done!\n";
