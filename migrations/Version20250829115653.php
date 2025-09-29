<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829115653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menambahkan tabel notifikasi untuk sistem notifikasi event dan pengumuman';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notifikasi (id INT AUTO_INCREMENT NOT NULL, pegawai_id INT NOT NULL, event_id INT DEFAULT NULL, judul VARCHAR(200) NOT NULL, pesan LONGTEXT NOT NULL, tipe VARCHAR(50) NOT NULL, sudah_dibaca TINYINT(1) DEFAULT 0 NOT NULL, waktu_dibuat DATETIME NOT NULL, waktu_dibaca DATETIME DEFAULT NULL, INDEX IDX_468B5F3B998300D9 (pegawai_id), INDEX IDX_468B5F3B71F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notifikasi ADD CONSTRAINT FK_468B5F3B998300D9 FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)');
        $this->addSql('ALTER TABLE notifikasi ADD CONSTRAINT FK_468B5F3B71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifikasi DROP FOREIGN KEY FK_468B5F3B998300D9');
        $this->addSql('ALTER TABLE notifikasi DROP FOREIGN KEY FK_468B5F3B71F7E88B');
        $this->addSql('DROP TABLE notifikasi');
    }
}
