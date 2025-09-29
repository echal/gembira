<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902025952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update Entity Absensi untuk mendukung sistem jadwal absensi fleksibel baru';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absensi ADD konfigurasi_jadwal_id INT DEFAULT NULL, ADD waktu_absensi DATETIME DEFAULT NULL, ADD status VARCHAR(20) NOT NULL, ADD foto_path VARCHAR(255) DEFAULT NULL, ADD qr_code_used VARCHAR(255) DEFAULT NULL, CHANGE status_kehadiran status_kehadiran VARCHAR(20) DEFAULT NULL, CHANGE tanggal_absensi tanggal DATE NOT NULL');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB3C26A3AC9 FOREIGN KEY (konfigurasi_jadwal_id) REFERENCES konfigurasi_jadwal_absensi (id)');
        $this->addSql('CREATE INDEX IDX_352EBEB3C26A3AC9 ON absensi (konfigurasi_jadwal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB3C26A3AC9');
        $this->addSql('DROP INDEX IDX_352EBEB3C26A3AC9 ON absensi');
        $this->addSql('ALTER TABLE absensi DROP konfigurasi_jadwal_id, DROP waktu_absensi, DROP status, DROP foto_path, DROP qr_code_used, CHANGE status_kehadiran status_kehadiran VARCHAR(20) NOT NULL, CHANGE tanggal tanggal_absensi DATE NOT NULL');
    }
}
