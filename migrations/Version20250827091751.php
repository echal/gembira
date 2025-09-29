<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827091751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE jadwal_absensi (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, jenis_absensi VARCHAR(30) NOT NULL, hari_diizinkan JSON NOT NULL COMMENT \'(DC2Type:json)\', jam_mulai TIME NOT NULL, jam_selesai TIME NOT NULL, qr_code VARCHAR(255) DEFAULT NULL, tanggal_khusus DATE DEFAULT NULL, is_aktif TINYINT(1) NOT NULL, keterangan LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_4FA2F0B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE jadwal_absensi ADD CONSTRAINT FK_4FA2F0B03A8386 FOREIGN KEY (created_by_id) REFERENCES admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jadwal_absensi DROP FOREIGN KEY FK_4FA2F0B03A8386');
        $this->addSql('DROP TABLE jadwal_absensi');
    }
}
