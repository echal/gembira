<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828082916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kepala_bidang (id INT AUTO_INCREMENT NOT NULL, unit_kerja_id INT NOT NULL, nama VARCHAR(100) NOT NULL, nip VARCHAR(18) NOT NULL, jabatan VARCHAR(100) NOT NULL, pangkat_gol VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_E1D486ED59329EEA (nip), UNIQUE INDEX UNIQ_E1D486EDCBE1A536 (unit_kerja_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kepala_kantor (id INT AUTO_INCREMENT NOT NULL, nama VARCHAR(100) NOT NULL, nip VARCHAR(18) NOT NULL, jabatan VARCHAR(100) NOT NULL, pangkat_gol VARCHAR(50) DEFAULT NULL, periode VARCHAR(50) NOT NULL, is_aktif TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_F73DC8B159329EEA (nip), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE unit_kerja (id INT AUTO_INCREMENT NOT NULL, nama_unit VARCHAR(100) NOT NULL, kode_unit VARCHAR(20) NOT NULL, keterangan VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_5DD8A6A65188C48A (kode_unit), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kepala_bidang ADD CONSTRAINT FK_E1D486EDCBE1A536 FOREIGN KEY (unit_kerja_id) REFERENCES unit_kerja (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kepala_bidang DROP FOREIGN KEY FK_E1D486EDCBE1A536');
        $this->addSql('DROP TABLE kepala_bidang');
        $this->addSql('DROP TABLE kepala_kantor');
        $this->addSql('DROP TABLE unit_kerja');
    }
}
