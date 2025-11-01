<?php

namespace App\Service;

use App\Entity\Quote;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TagService
{
    private array $blocklist;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private LoggerInterface $logger,
        array $tagBlocklist = []
    ) {
        // Convert blocklist to lowercase for case-insensitive matching
        $this->blocklist = array_map('mb_strtolower', $tagBlocklist);
    }

    /**
     * Extract hashtags from text
     * Matches: #Word #Word123 #Kata_Panjang
     * Returns array of tag names (without #)
     */
    public function extractHashtags(string $text): array
    {
        // Match hashtags: # followed by word characters, numbers, underscores
        // Support Unicode for Indonesian characters
        preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $matches);

        if (empty($matches[1])) {
            return [];
        }

        // Get unique tag names and clean them
        $tagNames = array_unique(array_map(function ($tag) {
            // Remove # if somehow still present, trim, and lowercase
            $cleaned = ltrim(trim($tag), '#');
            return mb_strtolower($cleaned, 'UTF-8');
        }, $matches[1]));

        // Filter out empty or very short tags (less than 2 chars)
        $tagNames = array_filter($tagNames, function ($tag) {
            return mb_strlen($tag, 'UTF-8') >= 2;
        });

        return array_values($tagNames);
    }

    /**
     * Remove hashtags from text content
     * Returns clean text without any hashtags
     */
    public function removeHashtagsFromContent(string $content): string
    {
        // Remove all hashtags but preserve spacing
        $cleaned = preg_replace('/#[\p{L}\p{N}_]+/u', '', $content);

        // Clean up multiple spaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        // Trim leading/trailing spaces
        return trim($cleaned);
    }

    /**
     * Check if tag name is in blocklist
     */
    public function isBlocklisted(string $tagName): bool
    {
        $tagLower = mb_strtolower($tagName, 'UTF-8');

        // Check exact match
        if (in_array($tagLower, $this->blocklist)) {
            return true;
        }

        // Check if tag contains blocklisted word
        foreach ($this->blocklist as $blocked) {
            if (str_contains($tagLower, $blocked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter out blocklisted tags
     */
    public function filterBlocklistedTags(array $tagNames): array
    {
        $filtered = [];
        $blocked = [];

        foreach ($tagNames as $tagName) {
            if ($this->isBlocklisted($tagName)) {
                $blocked[] = $tagName;
                $this->logger->warning('Blocklisted tag filtered out', [
                    'tag' => $tagName
                ]);
            } else {
                $filtered[] = $tagName;
            }
        }

        if (!empty($blocked)) {
            $this->logger->info('Tags filtered', [
                'blocked' => $blocked,
                'allowed' => $filtered
            ]);
        }

        return $filtered;
    }

    /**
     * Process quote and sync its tags
     * This will:
     * 1. Extract hashtags from content
     * 2. Remove hashtags from content (clean the text)
     * 3. Find or create Tag entities
     * 4. Update quote's tags relationship
     */
    public function processQuoteTags(Quote $quote): void
    {
        $content = $quote->getContent();

        if (empty($content)) {
            return;
        }

        // Extract hashtags from content BEFORE cleaning
        $hashtagNames = $this->extractHashtags($content);

        // Filter out blocklisted tags
        $hashtagNames = $this->filterBlocklistedTags($hashtagNames);

        // Clean the content (remove hashtags)
        $cleanContent = $this->removeHashtagsFromContent($content);

        // Update quote content with cleaned version
        if ($cleanContent !== $content) {
            $quote->setContent($cleanContent);
            $this->logger->info('Cleaned hashtags from content', [
                'quote_id' => $quote->getId(),
                'original' => $content,
                'cleaned' => $cleanContent
            ]);
        }

        $this->logger->info('Extracted hashtags', [
            'quote_id' => $quote->getId(),
            'hashtags' => $hashtagNames
        ]);

        // If no hashtags found (after filtering), clear existing tags
        if (empty($hashtagNames)) {
            $this->clearQuoteTags($quote);
            return;
        }

        // Get existing tags for this quote
        $existingTags = $quote->getTags()->toArray();
        $existingTagNames = array_map(fn(Tag $tag) => mb_strtolower($tag->getName(), 'UTF-8'), $existingTags);

        // Find tags to add and remove
        $tagsToAdd = array_diff($hashtagNames, $existingTagNames);
        $tagsToRemove = array_diff($existingTagNames, $hashtagNames);

        // Remove tags that are no longer in content
        foreach ($existingTags as $tag) {
            if (in_array(mb_strtolower($tag->getName(), 'UTF-8'), $tagsToRemove)) {
                $quote->removeTag($tag);
                $tag->removeQuote($quote);
                $this->logger->info('Removed tag from quote', [
                    'tag' => $tag->getName(),
                    'quote_id' => $quote->getId()
                ]);
            }
        }

        // Add new tags
        foreach ($tagsToAdd as $tagName) {
            $tag = $this->findOrCreateTag($tagName);
            $quote->addTag($tag);
            $tag->addQuote($quote);

            $this->logger->info('Added tag to quote', [
                'tag' => $tag->getName(),
                'quote_id' => $quote->getId()
            ]);
        }

        // Persist changes
        $this->entityManager->flush();
    }

    /**
     * Find existing tag or create new one
     */
    public function findOrCreateTag(string $name): Tag
    {
        // Clean the name
        $cleanName = ltrim(trim($name), '#');
        $cleanName = mb_strtolower($cleanName, 'UTF-8');

        // Try to find existing tag (case-insensitive)
        $tag = $this->tagRepository->findOneByName($cleanName);

        if ($tag) {
            return $tag;
        }

        // Create new tag
        $tag = new Tag();
        $tag->setName($cleanName);

        $this->entityManager->persist($tag);

        $this->logger->info('Created new tag', ['tag' => $cleanName]);

        return $tag;
    }

    /**
     * Clear all tags from a quote
     */
    public function clearQuoteTags(Quote $quote): void
    {
        foreach ($quote->getTags() as $tag) {
            $tag->removeQuote($quote);
        }
        $quote->clearTags();
        $this->entityManager->flush();

        $this->logger->info('Cleared all tags from quote', [
            'quote_id' => $quote->getId()
        ]);
    }

    /**
     * Get content with clickable hashtags (for display)
     * Converts #hashtag to <a href="/tag/hashtag">#hashtag</a>
     */
    public function formatContentWithLinks(string $content, string $baseUrl = '/tag'): string
    {
        return preg_replace_callback(
            '/#([\p{L}\p{N}_]+)/u',
            function ($matches) use ($baseUrl) {
                $tagName = $matches[1];
                $slug = $this->generateSlug($tagName);
                return sprintf(
                    '<a href="%s/%s" class="hashtag-link">#%s</a>',
                    $baseUrl,
                    $slug,
                    htmlspecialchars($tagName)
                );
            },
            $content
        );
    }

    /**
     * Generate URL-friendly slug from tag name
     */
    private function generateSlug(string $name): string
    {
        $slug = ltrim($name, '#');
        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Delete a tag and remove all relationships
     */
    public function deleteTag(Tag $tag): void
    {
        // Remove tag from all quotes
        foreach ($tag->getQuotes() as $quote) {
            $quote->removeTag($tag);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        $this->logger->info('Deleted tag', ['tag' => $tag->getName()]);
    }

    /**
     * Clean up orphaned tags (tags with no quotes)
     */
    public function cleanupOrphanedTags(): int
    {
        $count = $this->tagRepository->deleteOrphanedTags();

        $this->logger->info('Cleaned up orphaned tags', ['count' => $count]);

        return $count;
    }

    /**
     * Recalculate usage counts for all tags
     */
    public function recalculateUsageCounts(): void
    {
        $this->tagRepository->recalculateAllUsageCounts();

        $this->logger->info('Recalculated all tag usage counts');
    }

    /**
     * Get popular tags
     */
    public function getPopularTags(int $limit = 10): array
    {
        return $this->tagRepository->findAllByPopularity($limit);
    }

    /**
     * Search tags by name
     */
    public function searchTags(string $query, int $limit = 10): array
    {
        return $this->tagRepository->searchByName($query, $limit);
    }
}
