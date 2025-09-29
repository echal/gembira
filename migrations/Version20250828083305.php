<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828083305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pegawai ADD unit_kerja_id INT DEFAULT NULL, CHANGE unit_kerja unit_kerja VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE pegawai ADD CONSTRAINT FK_98835748CBE1A536 FOREIGN KEY (unit_kerja_id) REFERENCES unit_kerja (id)');
        $this->addSql('CREATE INDEX IDX_98835748CBE1A536 ON pegawai (unit_kerja_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pegawai DROP FOREIGN KEY FK_98835748CBE1A536');
        $this->addSql('DROP INDEX IDX_98835748CBE1A536 ON pegawai');
        $this->addSql('ALTER TABLE pegawai DROP unit_kerja_id, CHANGE unit_kerja unit_kerja VARCHAR(100) NOT NULL');
    }
}
