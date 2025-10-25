-- Add 'viewed' column to user_quotes_interaction table
-- This tracks if a user has viewed a specific quote (unique views only)

ALTER TABLE `user_quotes_interaction`
ADD COLUMN `viewed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Has user viewed this quote' AFTER `saved`;

-- Add index for better performance on viewed queries
CREATE INDEX `idx_viewed` ON `user_quotes_interaction` (`viewed`);
