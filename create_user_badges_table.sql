-- Create user_badges table for old gamification system
-- This table is used by GamificationService for backward compatibility

CREATE TABLE IF NOT EXISTS `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to pegawai table',
  `badgeName` varchar(100) NOT NULL COMMENT 'Name of the badge',
  `badgeIcon` varchar(50) NOT NULL COMMENT 'Icon/emoji for the badge',
  `badgeLevel` int(11) NOT NULL DEFAULT 1 COMMENT 'Badge level (1-5)',
  `earnedDate` datetime NOT NULL COMMENT 'When the badge was earned',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_badge_level` (`badgeLevel`),
  KEY `idx_earned_date` (`earnedDate`),
  CONSTRAINT `FK_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User badges from old gamification system';
