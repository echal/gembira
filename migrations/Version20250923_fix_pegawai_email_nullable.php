<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix pegawai email column to be nullable to prevent import errors
 */
final class Version20250923FixPegawaiEmailNullable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make email column nullable in pegawai table to fix import Excel errors';
    }

    public function up(Schema $schema): void
    {
        // Modify email column to allow NULL values
        $this->addSql('ALTER TABLE pegawai MODIFY email VARCHAR(100) NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert back to NOT NULL (but this might fail if there are NULL values)
        $this->addSql('ALTER TABLE pegawai MODIFY email VARCHAR(100) NOT NULL');
    }
}