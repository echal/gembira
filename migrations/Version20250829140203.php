<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829140203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make pegawai_id nullable in notifikasi table for UserNotifikasi pivot architecture';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifikasi CHANGE pegawai_id pegawai_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifikasi CHANGE pegawai_id pegawai_id INT NOT NULL');
    }
}
