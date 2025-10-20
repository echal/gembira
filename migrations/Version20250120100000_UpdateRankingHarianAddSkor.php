<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration untuk menambahkan field skor_harian dan jam_masuk ke tabel ranking_harian
 */
final class Version20250120100000_UpdateRankingHarianAddSkor extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menambahkan field skor_harian dan jam_masuk ke tabel ranking_harian untuk sistem ranking baru (07:00-08:15)';
    }

    public function up(Schema $schema): void
    {
        // Tambah field jam_masuk jika belum ada
        $this->addSql('ALTER TABLE ranking_harian ADD COLUMN IF NOT EXISTS jam_masuk TIME NULL AFTER tanggal');

        // Tambah field skor_harian jika belum ada (NOT NULL dengan default 0)
        $this->addSql('ALTER TABLE ranking_harian ADD COLUMN IF NOT EXISTS skor_harian INT NOT NULL DEFAULT 0 AFTER jam_masuk');

        // Ubah field total_durasi menjadi nullable (untuk backward compatibility)
        $this->addSql('ALTER TABLE ranking_harian MODIFY COLUMN total_durasi INT NULL');
    }

    public function down(Schema $schema): void
    {
        // Hapus field yang ditambahkan
        $this->addSql('ALTER TABLE ranking_harian DROP COLUMN skor_harian');
        $this->addSql('ALTER TABLE ranking_harian DROP COLUMN jam_masuk');

        // Kembalikan total_durasi menjadi NOT NULL
        $this->addSql('ALTER TABLE ranking_harian MODIFY COLUMN total_durasi INT NOT NULL');
    }
}
