<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829091327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_absensi (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, waktu_absen DATETIME NOT NULL, status VARCHAR(20) NOT NULL, keterangan LONGTEXT DEFAULT NULL, INDEX IDX_B079171C71F7E88B (event_id), INDEX IDX_B079171CA76ED395 (user_id), UNIQUE INDEX unique_event_user (event_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_absensi ADD CONSTRAINT FK_B079171C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_absensi ADD CONSTRAINT FK_B079171CA76ED395 FOREIGN KEY (user_id) REFERENCES pegawai (id)');
        $this->addSql('ALTER TABLE event ADD butuh_absensi TINYINT(1) DEFAULT 0 NOT NULL, ADD link_meeting VARCHAR(500) DEFAULT NULL, CHANGE status status VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_absensi DROP FOREIGN KEY FK_B079171C71F7E88B');
        $this->addSql('ALTER TABLE event_absensi DROP FOREIGN KEY FK_B079171CA76ED395');
        $this->addSql('DROP TABLE event_absensi');
        $this->addSql('ALTER TABLE event DROP butuh_absensi, DROP link_meeting, CHANGE status status VARCHAR(20) DEFAULT \'aktif\' NOT NULL');
    }
}
