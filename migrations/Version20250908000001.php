<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_configuration table for maintenance mode and other system settings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE system_configuration (
            id INT AUTO_INCREMENT NOT NULL, 
            config_key VARCHAR(100) NOT NULL, 
            config_value LONGTEXT DEFAULT NULL, 
            description VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME DEFAULT NULL, 
            updated_by INT DEFAULT NULL, 
            UNIQUE INDEX UNIQ_BE4422B7A3B1F76E (config_key), 
            INDEX IDX_BE4422B716FE72E1 (updated_by), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE system_configuration ADD CONSTRAINT FK_BE4422B716FE72E1 FOREIGN KEY (updated_by) REFERENCES admin (id)');
        
        // Insert default maintenance mode settings
        $this->addSql('INSERT INTO system_configuration (config_key, config_value, description, created_at) VALUES 
            (\'maintenance_mode\', \'0\', \'Mode pemeliharaan sistem\', NOW()),
            (\'maintenance_message\', \'Sistem sedang dalam pemeliharaan. Mohon coba beberapa saat lagi.\', \'Pesan mode pemeliharaan\', NOW())');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE system_configuration DROP FOREIGN KEY FK_BE4422B716FE72E1');
        $this->addSql('DROP TABLE system_configuration');
    }
}