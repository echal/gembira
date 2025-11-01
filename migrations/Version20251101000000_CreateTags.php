<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101000000_CreateTags extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tags table and quote_tags junction table for hashtag feature';
    }

    public function up(Schema $schema): void
    {
        // Create tags table
        $this->addSql('CREATE TABLE tags (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            usage_count INT DEFAULT 0 NOT NULL,
            UNIQUE INDEX UNIQ_6FBC94265E237E06 (name),
            UNIQUE INDEX UNIQ_6FBC9426989D9B62 (slug),
            INDEX idx_tag_name (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create quote_tags junction table (many-to-many)
        $this->addSql('CREATE TABLE quote_tags (
            quote_id INT NOT NULL,
            tag_id INT NOT NULL,
            INDEX IDX_A9E9E0F5DB805178 (quote_id),
            INDEX IDX_A9E9E0F5BAD26311 (tag_id),
            PRIMARY KEY(quote_id, tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign keys
        $this->addSql('ALTER TABLE quote_tags ADD CONSTRAINT FK_A9E9E0F5DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quote_tags ADD CONSTRAINT FK_A9E9E0F5BAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE quote_tags DROP FOREIGN KEY FK_A9E9E0F5DB805178');
        $this->addSql('ALTER TABLE quote_tags DROP FOREIGN KEY FK_A9E9E0F5BAD26311');

        // Drop tables
        $this->addSql('DROP TABLE quote_tags');
        $this->addSql('DROP TABLE tags');
    }
}
