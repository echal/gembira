<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env file
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

echo "🗑️  HAPUS QUOTES DARI USER TIDAK VALID\n";
echo "========================================\n\n";

// User yang tidak valid (tidak ada dalam daftar pegawai)
$invalidAuthors = ['Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani'];

echo "User tidak valid yang akan dihapus:\n";
foreach ($invalidAuthors as $author) {
    echo "  • $author\n";
}
echo "\n";

// Step 1: Identifikasi quotes yang akan dihapus
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
$quoteIds = [];
foreach($quotes as $quote) {
    $quoteIds[] = $quote->getId();
    echo "\n📝 Quote ID: " . $quote->getId() . "\n";
    echo "   Author: " . $quote->getAuthor() . "\n";
    echo "   Category: " . ($quote->getCategory() ?? 'N/A') . "\n";
    echo "   Active: " . ($quote->isActive() ? 'Yes' : 'No') . "\n";
    echo "   Content: " . substr($quote->getContent(), 0, 100) . "...\n";
    echo "   Created: " . $quote->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
}

// Step 2: Cek interactions terkait
$qbInteractions = $em->createQueryBuilder();
$qbInteractions->select('i')
    ->from('App\Entity\UserQuoteInteraction', 'i')
    ->where($qbInteractions->expr()->in('i.quote', ':quotes'))
    ->setParameter('quotes', $quotes);

$interactions = $qbInteractions->getQuery()->getResult();
$totalInteractions = count($interactions);

echo "\n\n🔗 INTERACTIONS TERKAIT:\n";
echo "========================\n";
echo "Total interactions yang akan terhapus: $totalInteractions\n\n";

if ($totalInteractions > 0) {
    echo "Detail interactions:\n";
    foreach($interactions as $interaction) {
        echo "  • Pegawai: " . $interaction->getPegawai()->getNama();
        echo " | Quote Author: " . $interaction->getQuote()->getAuthor();
        echo " | Liked: " . ($interaction->isLiked() ? '❤️' : '—');
        echo " | Saved: " . ($interaction->isSaved() ? '📌' : '—');
        echo "\n";
    }
}

// Step 3: Konfirmasi penghapusan
echo "\n\n⚠️  RINGKASAN PENGHAPUSAN\n";
echo "========================\n";
echo "Total quotes yang akan dihapus: $totalQuotes\n";
echo "Total interactions yang akan dihapus: $totalInteractions\n";
echo "\nApakah Anda yakin ingin menghapus semua data di atas? (yes/no): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$confirmation = trim(strtolower($line));
fclose($handle);

if ($confirmation !== 'yes') {
    echo "\n❌ Penghapusan dibatalkan.\n";
    exit(0);
}

// Step 4: Hapus interactions terlebih dahulu (foreign key constraint)
echo "\n🔄 Menghapus interactions...\n";
$deletedInteractions = 0;
foreach($interactions as $interaction) {
    try {
        $em->remove($interaction);
        $deletedInteractions++;
    } catch (\Exception $e) {
        echo "❌ Error menghapus interaction ID " . $interaction->getId() . ": " . $e->getMessage() . "\n";
    }
}
$em->flush();
echo "✅ Berhasil menghapus $deletedInteractions interactions\n";

// Step 5: Hapus quotes
echo "\n🔄 Menghapus quotes...\n";
$deletedQuotes = 0;
foreach($quotes as $quote) {
    try {
        $em->remove($quote);
        $deletedQuotes++;
        echo "  ✅ Menghapus quote ID " . $quote->getId() . " dari " . $quote->getAuthor() . "\n";
    } catch (\Exception $e) {
        echo "  ❌ Error menghapus quote ID " . $quote->getId() . ": " . $e->getMessage() . "\n";
    }
}
$em->flush();

// Step 6: Verifikasi
echo "\n\n✨ HASIL PENGHAPUSAN\n";
echo "====================\n";
echo "✅ Berhasil menghapus $deletedQuotes quotes\n";
echo "✅ Berhasil menghapus $deletedInteractions interactions\n";

// Verifikasi tidak ada lagi quotes dari user tidak valid
$qbVerify = $em->createQueryBuilder();
$qbVerify->select('COUNT(q.id)')
   ->from('App\Entity\Quote', 'q')
   ->where($qbVerify->expr()->in('q.author', ':authors'))
   ->setParameter('authors', $invalidAuthors);

$remaining = $qbVerify->getQuery()->getSingleScalarResult();

if ($remaining == 0) {
    echo "\n✅ Verifikasi: Tidak ada lagi quotes dari user tidak valid dalam database.\n";
} else {
    echo "\n⚠️  Peringatan: Masih ada $remaining quotes dari user tidak valid.\n";
}

echo "\n🎉 Selesai!\n";
echo "\n📝 Langkah selanjutnya:\n";
echo "   1. Jalankan: php bin/console cache:clear\n";
echo "   2. Refresh halaman /ikhlas/leaderboard untuk melihat data terbaru\n";
