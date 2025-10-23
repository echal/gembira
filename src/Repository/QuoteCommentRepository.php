<?php

namespace App\Repository;

use App\Entity\QuoteComment;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuoteComment>
 */
class QuoteCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuoteComment::class);
    }

    /**
     * Find all top-level comments for a quote (no parent)
     *
     * @return QuoteComment[]
     */
    public function findTopLevelByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.quote = :quote')
            ->andWhere('c.parent IS NULL')
            ->setParameter('quote', $quote)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all comments for a quote (including replies)
     *
     * @return QuoteComment[]
     */
    public function findAllByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find replies for a specific comment
     *
     * @return QuoteComment[]
     */
    public function findRepliesByParent(QuoteComment $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count comments for a quote (excluding replies)
     */
    public function countTopLevelByQuote(Quote $quote): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.quote = :quote')
            ->andWhere('c.parent IS NULL')
            ->setParameter('quote', $quote)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all comments including replies
     */
    public function countAllByQuote(Quote $quote): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.quote = :quote')
            ->setParameter('quote', $quote)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find recent comments by user
     *
     * @return QuoteComment[]
     */
    public function findRecentByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get comments with user data (optimized query)
     *
     * @return QuoteComment[]
     */
    public function findByQuoteWithUser(Quote $quote): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'u')
            ->join('c.user', 'u')
            ->andWhere('c.quote = :quote')
            ->andWhere('c.parent IS NULL')
            ->setParameter('quote', $quote)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete comment and all its replies
     */
    public function deleteWithReplies(QuoteComment $comment): void
    {
        $em = $this->getEntityManager();

        // Cascade delete will handle replies automatically
        $em->remove($comment);
        $em->flush();
    }
}
