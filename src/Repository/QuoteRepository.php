<?php

namespace App\Repository;

use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Get a random active quote
     */
    public function findRandomQuote(?int $excludeId = null): ?Quote
    {
        $qb = $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->setParameter('active', true);

        if ($excludeId !== null) {
            $qb->andWhere('q.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $quotes = $qb->getQuery()->getResult();

        if (empty($quotes)) {
            return null;
        }

        return $quotes[array_rand($quotes)];
    }

    /**
     * Get all active quotes
     */
    public function findActiveQuotes(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next quote after given ID
     */
    public function findNextQuote(int $currentId): ?Quote
    {
        $current = $this->find($currentId);
        if (!$current) {
            return $this->findRandomQuote();
        }

        $next = $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->andWhere('q.id > :currentId')
            ->setParameter('active', true)
            ->setParameter('currentId', $currentId)
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // If no next, wrap around to first
        if (!$next) {
            $next = $this->createQueryBuilder('q')
                ->where('q.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('q.id', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $next;
    }

    /**
     * Get previous quote before given ID
     */
    public function findPreviousQuote(int $currentId): ?Quote
    {
        $current = $this->find($currentId);
        if (!$current) {
            return $this->findRandomQuote();
        }

        $prev = $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->andWhere('q.id < :currentId')
            ->setParameter('active', true)
            ->setParameter('currentId', $currentId)
            ->orderBy('q.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // If no previous, wrap around to last
        if (!$prev) {
            $prev = $this->createQueryBuilder('q')
                ->where('q.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('q.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $prev;
    }

    /**
     * Get quotes by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->andWhere('q.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
