-- Create quote_comments table for QuoteComment entity
-- Diperlukan untuk fitur komentar dan delete quote

USE gembira_db;

CREATE TABLE IF NOT EXISTS `quote_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL COMMENT 'Foreign key ke quotes',
  `user_id` int(11) NOT NULL COMMENT 'Foreign key ke pegawai',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Foreign key ke parent comment (untuk reply)',
  `content` text NOT NULL COMMENT 'Isi komentar',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quote_id` (`quote_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `FK_quote_comments_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_quote_comments_user` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_quote_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `quote_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify
SHOW CREATE TABLE quote_comments;
