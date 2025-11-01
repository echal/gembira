<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Find tag by name (case-insensitive)
     */
    public function findOneByName(string $name): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find tag by slug
     */
    public function findOneBySlug(string $slug): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all tags ordered by usage count (most popular first)
     */
    public function findAllByPopularity(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all tags ordered by name
     */
    public function findAllByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tags with at least one quote
     */
    public function findActiveTagsWithQuotes(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.usageCount > 0')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search tags by name (for autocomplete)
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tags that are not used (orphaned tags)
     */
    public function findOrphanedTags(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.usageCount = 0')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tag statistics
     */
    public function getTagStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                COUNT(*) as total_tags,
                SUM(usage_count) as total_usages,
                AVG(usage_count) as avg_usage,
                MAX(usage_count) as max_usage,
                COUNT(CASE WHEN usage_count = 0 THEN 1 END) as orphaned_tags
            FROM tags
        ';

        $result = $conn->executeQuery($sql)->fetchAssociative();

        return [
            'total_tags' => (int) ($result['total_tags'] ?? 0),
            'total_usages' => (int) ($result['total_usages'] ?? 0),
            'avg_usage' => round((float) ($result['avg_usage'] ?? 0), 2),
            'max_usage' => (int) ($result['max_usage'] ?? 0),
            'orphaned_tags' => (int) ($result['orphaned_tags'] ?? 0),
        ];
    }

    /**
     * Delete orphaned tags (tags with no quotes)
     */
    public function deleteOrphanedTags(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.usageCount = 0')
            ->getQuery()
            ->execute();
    }

    /**
     * Recalculate all usage counts
     */
    public function recalculateAllUsageCounts(): void
    {
        $conn = $this->getEntityManager()->getConnection();

        // Update usage_count based on actual quote_tags relationships
        $sql = '
            UPDATE tags t
            SET usage_count = (
                SELECT COUNT(*)
                FROM quote_tags qt
                WHERE qt.tag_id = t.id
            )
        ';

        $conn->executeStatement($sql);
    }
}
