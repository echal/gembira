<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828034039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB31679E05A');
        $this->addSql('DROP INDEX IDX_352EBEB31679E05A ON absensi');
        $this->addSql('ALTER TABLE absensi ADD jadwal_absensi_id INT DEFAULT NULL, DROP jadwal_id');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB3A9D7348A FOREIGN KEY (jadwal_absensi_id) REFERENCES jadwal_absensi (id)');
        $this->addSql('CREATE INDEX IDX_352EBEB3A9D7348A ON absensi (jadwal_absensi_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absensi DROP FOREIGN KEY FK_352EBEB3A9D7348A');
        $this->addSql('DROP INDEX IDX_352EBEB3A9D7348A ON absensi');
        $this->addSql('ALTER TABLE absensi ADD jadwal_id INT NOT NULL, DROP jadwal_absensi_id');
        $this->addSql('ALTER TABLE absensi ADD CONSTRAINT FK_352EBEB31679E05A FOREIGN KEY (jadwal_id) REFERENCES jadwal (id)');
        $this->addSql('CREATE INDEX IDX_352EBEB31679E05A ON absensi (jadwal_id)');
    }
}
