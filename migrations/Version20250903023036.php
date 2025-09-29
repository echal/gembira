<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250903023036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin ADD unit_kerja_id INT DEFAULT NULL, ADD kepala_bidang_id INT DEFAULT NULL, ADD kepala_kantor_id INT DEFAULT NULL, ADD nip VARCHAR(18) DEFAULT NULL');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76CBE1A536 FOREIGN KEY (unit_kerja_id) REFERENCES unit_kerja (id)');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D762B1CAF22 FOREIGN KEY (kepala_bidang_id) REFERENCES kepala_bidang (id)');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76F99640B7 FOREIGN KEY (kepala_kantor_id) REFERENCES kepala_kantor (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_880E0D7659329EEA ON admin (nip)');
        $this->addSql('CREATE INDEX IDX_880E0D76CBE1A536 ON admin (unit_kerja_id)');
        $this->addSql('CREATE INDEX IDX_880E0D762B1CAF22 ON admin (kepala_bidang_id)');
        $this->addSql('CREATE INDEX IDX_880E0D76F99640B7 ON admin (kepala_kantor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D76CBE1A536');
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D762B1CAF22');
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D76F99640B7');
        $this->addSql('DROP INDEX UNIQ_880E0D7659329EEA ON admin');
        $this->addSql('DROP INDEX IDX_880E0D76CBE1A536 ON admin');
        $this->addSql('DROP INDEX IDX_880E0D762B1CAF22 ON admin');
        $this->addSql('DROP INDEX IDX_880E0D76F99640B7 ON admin');
        $this->addSql('ALTER TABLE admin DROP unit_kerja_id, DROP kepala_bidang_id, DROP kepala_kantor_id, DROP nip');
    }
}
