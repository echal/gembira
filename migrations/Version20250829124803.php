<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829124803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_notifikasi pivot table for tracking read/unread status per user';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_notifikasi (id INT AUTO_INCREMENT NOT NULL, pegawai_id INT NOT NULL, notifikasi_id INT NOT NULL, is_read TINYINT(1) DEFAULT 0 NOT NULL, received_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, priority VARCHAR(20) DEFAULT \'normal\' NOT NULL, INDEX IDX_E1EC99D7998300D9 (pegawai_id), INDEX IDX_E1EC99D7936D434 (notifikasi_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_notifikasi ADD CONSTRAINT FK_E1EC99D7998300D9 FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)');
        $this->addSql('ALTER TABLE user_notifikasi ADD CONSTRAINT FK_E1EC99D7936D434 FOREIGN KEY (notifikasi_id) REFERENCES notifikasi (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_notifikasi DROP FOREIGN KEY FK_E1EC99D7998300D9');
        $this->addSql('ALTER TABLE user_notifikasi DROP FOREIGN KEY FK_E1EC99D7936D434');
        $this->addSql('DROP TABLE user_notifikasi');
    }
}
