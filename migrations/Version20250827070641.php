<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827070641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hari_libur (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, tanggal_libur DATE NOT NULL, nama_libur VARCHAR(100) NOT NULL, jenis_libur VARCHAR(20) NOT NULL, keterangan LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_DADD3CC4B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hari_libur ADD CONSTRAINT FK_DADD3CC4B03A8386 FOREIGN KEY (created_by_id) REFERENCES admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hari_libur DROP FOREIGN KEY FK_DADD3CC4B03A8386');
        $this->addSql('DROP TABLE hari_libur');
    }
}
