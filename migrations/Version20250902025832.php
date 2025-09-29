<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902025832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Membuat tabel konfigurasi_jadwal_absensi untuk sistem absensi fleksibel baru';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE konfigurasi_jadwal_absensi (id INT AUTO_INCREMENT NOT NULL, dibuat_oleh_id INT NOT NULL, nama_jadwal VARCHAR(100) NOT NULL, deskripsi LONGTEXT DEFAULT NULL, hari_mulai SMALLINT NOT NULL, hari_selesai SMALLINT NOT NULL, jam_mulai TIME NOT NULL, jam_selesai TIME NOT NULL, perlu_qr_code TINYINT(1) NOT NULL, perlu_kamera TINYINT(1) NOT NULL, qr_code VARCHAR(255) DEFAULT NULL, emoji VARCHAR(10) DEFAULT NULL, warna_kartu VARCHAR(7) DEFAULT NULL, is_aktif TINYINT(1) NOT NULL, keterangan LONGTEXT DEFAULT NULL, dibuat DATETIME NOT NULL, diubah DATETIME DEFAULT NULL, INDEX IDX_2434BBFFAD547146 (dibuat_oleh_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE konfigurasi_jadwal_absensi ADD CONSTRAINT FK_2434BBFFAD547146 FOREIGN KEY (dibuat_oleh_id) REFERENCES admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE konfigurasi_jadwal_absensi DROP FOREIGN KEY FK_2434BBFFAD547146');
        $this->addSql('DROP TABLE konfigurasi_jadwal_absensi');
    }
}
