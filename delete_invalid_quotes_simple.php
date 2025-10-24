<?php
/**
 * Script untuk menghapus quotes dari user yang tidak valid
 * User tidak valid: Pak Dedi, Pak Budi, Bu Ani, Anonim
 *
 * Jalankan script ini di production server untuk membersihkan data
 */

// Parse .env.local untuk mendapatkan DATABASE_URL
$envFile = __DIR__ . '/.env.local';
if (!file_exists($envFile)) {
    die("Error: File .env.local tidak ditemukan!\n");
}

$envContent = file_get_contents($envFile);
// Support untuk password kosong: root:@ atau root:password@
preg_match('/DATABASE_URL="mysql:\/\/(.+?):(.*)@(.+?):(\d+)\/(.+?)\?/', $envContent, $matches);

if (empty($matches)) {
    die("Error: DATABASE_URL tidak ditemukan dalam .env.local!\n");
}

$dbUser = $matches[1];
$dbPass = $matches[2]; // Bisa kosong
$dbHost = $matches[3];
$dbPort = $matches[4];
$dbName = $matches[5];

echo "==================================================\n";
echo "HAPUS QUOTES DARI USER TIDAK VALID\n";
echo "==================================================\n\n";

echo "Koneksi ke database: $dbName@$dbHost\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // User yang tidak valid
    $invalidAuthors = ['Pak Dedi', 'Pak Budi', 'Bu Ani', 'Anonim', 'Dedi', 'Budi', 'Ani'];
    $placeholders = implode(',', array_fill(0, count($invalidAuthors), '?'));

    // 1. Cek quotes yang akan dihapus
    echo "📋 STEP 1: Cek quotes yang akan dihapus\n";
    echo "=========================================\n";
    $stmt = $pdo->prepare("
        SELECT id, author, SUBSTRING(content, 1, 100) as content_preview, created_at
        FROM quote
        WHERE author IN ($placeholders)
        ORDER BY created_at DESC
    ");
    $stmt->execute($invalidAuthors);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($quotes)) {
        echo "✅ Tidak ada quotes dari user tidak valid. Database sudah bersih!\n";
        exit(0);
    }

    echo "Total quotes ditemukan: " . count($quotes) . "\n\n";
    foreach ($quotes as $quote) {
        echo "  • ID: {$quote['id']} | Author: {$quote['author']}\n";
        echo "    Content: {$quote['content_preview']}...\n";
        echo "    Created: {$quote['created_at']}\n";
    }

    // 2. Cek interactions
    echo "\n\n🔗 STEP 2: Cek interactions yang akan dihapus\n";
    echo "=============================================\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM user_quote_interaction
        WHERE quote_id IN (
            SELECT id FROM quote WHERE author IN ($placeholders)
        )
    ");
    $stmt->execute($invalidAuthors);
    $interactionCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Total interactions yang akan terhapus: $interactionCount\n";

    // Detail interactions
    if ($interactionCount > 0) {
        $stmt = $pdo->prepare("
            SELECT
                uqi.id as interaction_id,
                p.nama as pegawai_nama,
                q.author as quote_author,
                uqi.liked,
                uqi.saved
            FROM user_quote_interaction uqi
            JOIN pegawai p ON uqi.pegawai_id = p.id
            JOIN quote q ON uqi.quote_id = q.id
            WHERE q.author IN ($placeholders)
            ORDER BY q.author
        ");
        $stmt->execute($invalidAuthors);
        $interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "\nDetail:\n";
        foreach ($interactions as $int) {
            echo "  • {$int['pegawai_nama']} → {$int['quote_author']}";
            echo " | Liked: " . ($int['liked'] ? '❤️' : '—');
            echo " | Saved: " . ($int['saved'] ? '📌' : '—');
            echo "\n";
        }
    }

    // 3. Konfirmasi
    echo "\n\n⚠️  RINGKASAN PENGHAPUSAN\n";
    echo "=========================\n";
    echo "Total quotes yang akan dihapus: " . count($quotes) . "\n";
    echo "Total interactions yang akan dihapus: $interactionCount\n";
    echo "\nApakah Anda yakin ingin menghapus semua data di atas? (yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirmation = trim(strtolower($line));
    fclose($handle);

    if ($confirmation !== 'yes') {
        echo "\n❌ Penghapusan dibatalkan.\n";
        exit(0);
    }

    // 4. Mulai transaksi
    echo "\n🔄 Memulai penghapusan...\n";
    $pdo->beginTransaction();

    try {
        // Hapus interactions terlebih dahulu
        echo "\nMenghapus interactions...\n";
        $stmt = $pdo->prepare("
            DELETE FROM user_quote_interaction
            WHERE quote_id IN (
                SELECT id FROM quote WHERE author IN ($placeholders)
            )
        ");
        $stmt->execute($invalidAuthors);
        $deletedInteractions = $stmt->rowCount();
        echo "✅ Berhasil menghapus $deletedInteractions interactions\n";

        // Hapus quotes
        echo "\nMenghapus quotes...\n";
        $stmt = $pdo->prepare("
            DELETE FROM quote
            WHERE author IN ($placeholders)
        ");
        $stmt->execute($invalidAuthors);
        $deletedQuotes = $stmt->rowCount();
        echo "✅ Berhasil menghapus $deletedQuotes quotes\n";

        // Commit transaksi
        $pdo->commit();

        // 5. Verifikasi
        echo "\n\n✨ VERIFIKASI HASIL\n";
        echo "===================\n";

        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM quote WHERE author IN ($placeholders)");
        $stmt->execute($invalidAuthors);
        $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($remaining == 0) {
            echo "✅ Tidak ada lagi quotes dari user tidak valid\n";
        } else {
            echo "⚠️  Masih ada $remaining quotes dari user tidak valid\n";
        }

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM quote");
        $totalQuotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "📊 Total quotes tersisa: $totalQuotes\n";

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_quote_interaction");
        $totalInteractions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "🔗 Total interactions tersisa: $totalInteractions\n";

        echo "\n🎉 Penghapusan berhasil!\n";
        echo "\n📝 Langkah selanjutnya:\n";
        echo "   1. Jalankan: php bin/console cache:clear\n";
        echo "   2. Refresh halaman /ikhlas/leaderboard\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        echo "Transaksi dibatalkan, tidak ada data yang terhapus.\n";
    }

} catch (PDOException $e) {
    die("Error koneksi database: " . $e->getMessage() . "\n");
}
