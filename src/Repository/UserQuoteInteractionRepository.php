<?php

namespace App\Repository;

use App\Entity\UserQuoteInteraction;
use App\Entity\Pegawai;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserQuoteInteraction>
 */
class UserQuoteInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserQuoteInteraction::class);
    }

    /**
     * Find or create interaction for user and quote
     */
    public function findOrCreateInteraction(Pegawai $user, Quote $quote): UserQuoteInteraction
    {
        $interaction = $this->findOneBy([
            'user' => $user,
            'quote' => $quote
        ]);

        if (!$interaction) {
            $interaction = new UserQuoteInteraction();
            $interaction->setUser($user);
            $interaction->setQuote($quote);
        }

        return $interaction;
    }

    /**
     * Get user's liked quotes
     */
    public function findLikedByUser(Pegawai $user): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.liked = :liked')
            ->setParameter('user', $user)
            ->setParameter('liked', true)
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user's saved quotes
     */
    public function findSavedByUser(Pegawai $user): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.saved = :saved')
            ->setParameter('user', $user)
            ->setParameter('saved', true)
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user has liked a quote
     */
    public function hasUserLiked(Pegawai $user, Quote $quote): bool
    {
        $interaction = $this->findOneBy([
            'user' => $user,
            'quote' => $quote
        ]);

        return $interaction && $interaction->isLiked();
    }

    /**
     * Check if user has saved a quote
     */
    public function hasUserSaved(Pegawai $user, Quote $quote): bool
    {
        $interaction = $this->findOneBy([
            'user' => $user,
            'quote' => $quote
        ]);

        return $interaction && $interaction->isSaved();
    }

    /**
     * Get users who liked a quote (for Facebook-style display)
     * Returns array of user names
     */
    public function getUsersWhoLiked(Quote $quote, int $limit = 3): array
    {
        $interactions = $this->createQueryBuilder('i')
            ->select('p.nama')
            ->join('i.user', 'p')
            ->where('i.quote = :quote')
            ->andWhere('i.liked = :liked')
            ->setParameter('quote', $quote)
            ->setParameter('liked', true)
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_column($interactions, 'nama');
    }
}
