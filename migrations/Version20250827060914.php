<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827060914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absensi (id INT AUTO_INCREMENT NOT NULL, pegawai_id INT NOT NULL, jadwal_id INT NOT NULL, validated_by_id INT DEFAULT NULL, tanggal_absensi DATE NOT NULL, waktu_masuk DATETIME DEFAULT NULL, waktu_keluar DATETIME DEFAULT NULL, status_kehadiran VARCHAR(20) NOT NULL, foto_selfie VARCHAR(255) DEFAULT NULL, lokasi_absensi VARCHAR(100) DEFAULT NULL, qr_code_scanned VARCHAR(255) DEFAULT NULL, keterangan LONGTEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, status_validasi VARCHAR(20) NOT NULL, catatan_admin LONGTEXT DEFAULT NULL, tanggal_validasi DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_352EBEB3998300D9 (pegawai_id), INDEX IDX_352EBEB31679E05A (jadwal_id), INDEX IDX_352EBEB3C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, username VARCHAR(50) NOT NULL, nama_lengkap VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, permissions JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', nomor_telepon VARCHAR(15) DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, last_login_ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_880E0D76F85E0677 (username), UNIQUE INDEX UNIQ_880E0D76E7927C74 (email), INDEX IDX_880E0D76B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE jadwal (id INT AUTO_INCREMENT NOT NULL, nama_jadwal VARCHAR(100) NOT NULL, jam_masuk TIME NOT NULL, jam_keluar TIME NOT NULL, batas_terlambat INT NOT NULL, batas_pulang_cepat INT NOT NULL, hari_kerja VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, keterangan LONGTEXT DEFAULT NULL, lokasi_kantor VARCHAR(255) DEFAULT NULL, qr_code VARCHAR(255) DEFAULT NULL, berlaku_dari DATE NOT NULL, berlaku_sampai DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_61E7BA767D8B1FB5 (qr_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pegawai (id INT AUTO_INCREMENT NOT NULL, nip VARCHAR(18) NOT NULL, nama VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, jabatan VARCHAR(100) NOT NULL, unit_kerja VARCHAR(100) NOT NULL, status_kepegawaian VARCHAR(20) NOT NULL, tanggal_mulai_kerja DATE NOT NULL, nomor_telepon VARCHAR(15) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_9883574859329EEA (nip), UNIQUE INDEX UNIQ_98835748E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB3998300D9 FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB31679E05A FOREIGN KEY (jadwal_id) REFERENCES jadwal (id)');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB3C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES admin (id)');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76B03A8386 FOREIGN KEY (created_by_id) REFERENCES admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB3998300D9');
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB31679E05A');
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB3C69DE5E5');
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D76B03A8386');
        $this->addSql('DROP TABLE absensi');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE jadwal');
        $this->addSql('DROP TABLE pegawai');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
