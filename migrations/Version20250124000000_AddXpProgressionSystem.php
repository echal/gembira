<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * XP Progression System Migration
 * Adds: user_xp_log table, monthly_leaderboard table, and XP columns to pegawai table
 */
final class Version20250124000000_AddXpProgressionSystem extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add XP Progression System with user_xp_log, monthly_leaderboard tables and XP columns in pegawai';
    }

    public function up(Schema $schema): void
    {
        // Create user_xp_log table
        $this->addSql('CREATE TABLE user_xp_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            xp_earned INT NOT NULL,
            activity_type VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            related_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_user_xp_log_user (user_id),
            INDEX idx_user_xp_log_created (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE user_xp_log ADD CONSTRAINT FK_user_xp_log_user
            FOREIGN KEY (user_id) REFERENCES pegawai (id) ON DELETE CASCADE');

        // Create monthly_leaderboard table
        $this->addSql('CREATE TABLE monthly_leaderboard (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            month INT NOT NULL,
            year INT NOT NULL,
            xp_monthly INT NOT NULL DEFAULT 0,
            rank_monthly INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_month_year (month, year),
            INDEX idx_xp_monthly (xp_monthly),
            INDEX idx_rank_monthly (rank_monthly),
            UNIQUE INDEX unique_user_month_year (user_id, month, year),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE monthly_leaderboard ADD CONSTRAINT FK_monthly_leaderboard_user
            FOREIGN KEY (user_id) REFERENCES pegawai (id) ON DELETE CASCADE');

        // Add XP columns to pegawai table
        $this->addSql('ALTER TABLE pegawai
            ADD total_xp INT NOT NULL DEFAULT 0,
            ADD current_level INT NOT NULL DEFAULT 1,
            ADD current_badge VARCHAR(10) DEFAULT "🌱"');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE user_xp_log DROP FOREIGN KEY FK_user_xp_log_user');
        $this->addSql('ALTER TABLE monthly_leaderboard DROP FOREIGN KEY FK_monthly_leaderboard_user');

        // Drop tables
        $this->addSql('DROP TABLE user_xp_log');
        $this->addSql('DROP TABLE monthly_leaderboard');

        // Remove XP columns from pegawai
        $this->addSql('ALTER TABLE pegawai
            DROP total_xp,
            DROP current_level,
            DROP current_badge');
    }
}
