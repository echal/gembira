-- Fix Level Names in Database
-- Update existing records to use new level names without "Ikhlas"
-- Run this after updating the PHP code

-- Update Level 1 users
UPDATE pegawai
SET level_title = 'Pemula'
WHERE current_level = 1;

-- Update Level 4 users
UPDATE pegawai
SET level_title = 'Inspirator Gembira'
WHERE current_level = 4;

-- Update Level 5 users (also remove "Ikhlas" reference)
UPDATE pegawai
SET level_title = 'Teladan Kinerja'
WHERE current_level = 5;

-- Verify the changes
SELECT
    current_level,
    level_title,
    COUNT(*) as total_users
FROM pegawai
GROUP BY current_level, level_title
ORDER BY current_level;
