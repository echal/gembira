-- Update existing interaction records to mark as viewed
-- If user has liked or saved a quote, they must have viewed it

UPDATE user_quotes_interaction
SET viewed = 1
WHERE (liked = 1 OR saved = 1) AND viewed = 0;

-- Show results
SELECT 'Updated records with liked=1 or saved=1 to viewed=1' as message;
