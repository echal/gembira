<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration untuk menambahkan kolom photos ke tabel quotes
 * untuk fitur upload foto inspirasi
 */
final class Version20251028000000_AddPhotosToQuotes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tambah kolom photos (JSON) ke tabel quotes untuk fitur upload foto inspirasi';
    }

    public function up(Schema $schema): void
    {
        // Tambah kolom photos dengan tipe JSON, nullable
        // Kolom ini akan menyimpan array path foto (maksimal 2 foto)
        // Contoh: ["inspirasi_2_1730115600_abc123.jpg", "inspirasi_2_1730115605_def456.png"]
        $this->addSql('ALTER TABLE quotes ADD photos JSON DEFAULT NULL COMMENT \'Array path foto (max 2)\'');
    }

    public function down(Schema $schema): void
    {
        // Rollback: Hapus kolom photos
        $this->addSql('ALTER TABLE quotes DROP photos');
    }
}
