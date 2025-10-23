<?php

namespace App\Service;

use App\Repository\UserQuoteInteractionRepository;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IkhlasLeaderboardService
{
    private const CACHE_TTL = 60; // 1 minute cache
    private const LIKE_POINTS = 1;
    private const SAVE_POINTS = 2;

    public function __construct(
        private EntityManagerInterface $em,
        private UserQuoteInteractionRepository $interactionRepo,
        private QuoteRepository $quoteRepo,
        private CacheInterface $cache
    ) {}

    /**
     * Get top users leaderboard
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return $this->cache->get('ikhlas_leaderboard_top_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);

            $qb = $this->em->createQueryBuilder();
            $qb->select('p.id', 'p.nama', 'p.jabatan', 'p.unitKerja')
                ->addSelect('
                    SUM(CASE WHEN i.liked = 1 THEN ' . self::LIKE_POINTS . ' ELSE 0 END) as likePoints
                ')
                ->addSelect('
                    SUM(CASE WHEN i.saved = 1 THEN ' . self::SAVE_POINTS . ' ELSE 0 END) as savePoints
                ')
                ->addSelect('
                    (SUM(CASE WHEN i.liked = 1 THEN ' . self::LIKE_POINTS . ' ELSE 0 END) +
                     SUM(CASE WHEN i.saved = 1 THEN ' . self::SAVE_POINTS . ' ELSE 0 END)) as totalPoints
                ')
                ->addSelect('COUNT(i.id) as totalInteractions')
                ->addSelect('COALESCE(up.pointsTotal, 0) as gamificationPoints')
                ->addSelect('COALESCE(up.level, 1) as userLevel')
                ->from('App\Entity\Pegawai', 'p')
                ->leftJoin('App\Entity\UserQuoteInteraction', 'i', 'WITH', 'i.user = p.id')
                ->leftJoin('App\Entity\UserPoints', 'up', 'WITH', 'up.user = p.id')
                ->groupBy('p.id', 'p.nama', 'p.jabatan', 'p.unitKerja', 'up.pointsTotal', 'up.level')
                ->having('totalPoints > 0')
                ->orderBy('gamificationPoints', 'DESC')
                ->addOrderBy('totalPoints', 'DESC')
                ->addOrderBy('p.nama', 'ASC')
                ->setMaxResults($limit);

            $results = $qb->getQuery()->getResult();

            // Add rank and badge
            $leaderboard = [];
            foreach ($results as $index => $user) {
                $rank = $index + 1;
                $leaderboard[] = [
                    'rank' => $rank,
                    'userId' => $user['id'],
                    'nama' => $user['nama'],
                    'jabatan' => $user['jabatan'] ?? 'Pegawai',
                    'unitKerja' => $user['unitKerja'] ?? '-',
                    'totalPoints' => (int) $user['totalPoints'],
                    'likePoints' => (int) $user['likePoints'],
                    'savePoints' => (int) $user['savePoints'],
                    'totalInteractions' => (int) $user['totalInteractions'],
                    'gamificationPoints' => (int) $user['gamificationPoints'],
                    'level' => (int) $user['userLevel'],
                    'badge' => $this->getBadge($rank),
                    'badgeColor' => $this->getBadgeColor($rank)
                ];
            }

            return $leaderboard;
        });
    }

    /**
     * Get user rank
     */
    public function getUserRank(int $userId): ?array
    {
        $leaderboard = $this->getLeaderboard(100); // Get top 100

        foreach ($leaderboard as $entry) {
            if ($entry['userId'] === $userId) {
                return $entry;
            }
        }

        // User not in top 100, calculate their actual rank
        $qb = $this->em->createQueryBuilder();
        $qb->select('
                (SUM(CASE WHEN i.liked = 1 THEN ' . self::LIKE_POINTS . ' ELSE 0 END) +
                 SUM(CASE WHEN i.saved = 1 THEN ' . self::SAVE_POINTS . ' ELSE 0 END)) as totalPoints
            ')
            ->from('App\Entity\UserQuoteInteraction', 'i')
            ->where('i.user = :userId')
            ->setParameter('userId', $userId);

        $result = $qb->getQuery()->getOneOrNullResult();
        $userPoints = $result ? (int) $result['totalPoints'] : 0;

        if ($userPoints === 0) {
            return null;
        }

        // Count how many users have more points
        $qb2 = $this->em->createQueryBuilder();
        $qb2->select('COUNT(DISTINCT p.id)')
            ->from('App\Entity\Pegawai', 'p')
            ->leftJoin('App\Entity\UserQuoteInteraction', 'i', 'WITH', 'i.user = p.id')
            ->groupBy('p.id')
            ->having('
                (SUM(CASE WHEN i.liked = 1 THEN ' . self::LIKE_POINTS . ' ELSE 0 END) +
                 SUM(CASE WHEN i.saved = 1 THEN ' . self::SAVE_POINTS . ' ELSE 0 END)) > :userPoints
            ')
            ->setParameter('userPoints', $userPoints);

        $rank = $qb2->getQuery()->getSingleScalarResult() + 1;

        return [
            'rank' => $rank,
            'totalPoints' => $userPoints,
            'badge' => $this->getBadge($rank),
            'badgeColor' => $this->getBadgeColor($rank)
        ];
    }

    /**
     * Get top quotes by interactions
     */
    public function getTopQuotes(int $limit = 5): array
    {
        return $this->cache->get('ikhlas_top_quotes_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);

            $qb = $this->em->createQueryBuilder();
            $qb->select('q.id', 'q.content', 'q.author', 'q.category')
                ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as totalLikes')
                ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as totalSaves')
                ->addSelect('COUNT(i.id) as totalInteractions')
                ->from('App\Entity\Quote', 'q')
                ->leftJoin('App\Entity\UserQuoteInteraction', 'i', 'WITH', 'i.quote = q.id')
                ->where('q.isActive = 1')
                ->groupBy('q.id', 'q.content', 'q.author', 'q.category')
                ->having('totalInteractions > 0')
                ->orderBy('totalLikes', 'DESC')
                ->addOrderBy('totalSaves', 'DESC')
                ->setMaxResults($limit);

            return $qb->getQuery()->getResult();
        });
    }

    /**
     * Get global statistics
     */
    public function getGlobalStats(): array
    {
        return $this->cache->get('ikhlas_global_stats', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            // Total quotes
            $totalQuotes = $this->quoteRepo->count(['isActive' => true]);

            // Total interactions
            $qb = $this->em->createQueryBuilder();
            $qb->select('COUNT(i.id) as totalInteractions')
                ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as totalLikes')
                ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as totalSaves')
                ->from('App\Entity\UserQuoteInteraction', 'i');

            $interactionStats = $qb->getQuery()->getOneOrNullResult();

            // Total active users
            $qb2 = $this->em->createQueryBuilder();
            $qb2->select('COUNT(DISTINCT i.user)')
                ->from('App\Entity\UserQuoteInteraction', 'i');

            $totalActiveUsers = $qb2->getQuery()->getSingleScalarResult();

            return [
                'totalQuotes' => $totalQuotes,
                'totalInteractions' => (int) ($interactionStats['totalInteractions'] ?? 0),
                'totalLikes' => (int) ($interactionStats['totalLikes'] ?? 0),
                'totalSaves' => (int) ($interactionStats['totalSaves'] ?? 0),
                'totalActiveUsers' => (int) $totalActiveUsers
            ];
        });
    }

    /**
     * Get user personal stats
     */
    public function getUserStats(int $userId): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(i.id) as totalInteractions')
            ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as totalLikes')
            ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as totalSaves')
            ->from('App\Entity\UserQuoteInteraction', 'i')
            ->where('i.user = :userId')
            ->setParameter('userId', $userId);

        $stats = $qb->getQuery()->getOneOrNullResult();

        $userRank = $this->getUserRank($userId);

        return [
            'totalInteractions' => (int) ($stats['totalInteractions'] ?? 0),
            'totalLikes' => (int) ($stats['totalLikes'] ?? 0),
            'totalSaves' => (int) ($stats['totalSaves'] ?? 0),
            'rank' => $userRank['rank'] ?? null,
            'totalPoints' => $userRank['totalPoints'] ?? 0,
            'badge' => $userRank['badge'] ?? null
        ];
    }

    /**
     * Get daily activity data (for charts)
     */
    public function getDailyActivity(int $days = 7): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('DATE(i.createdAt) as date')
            ->addSelect('COUNT(i.id) as totalInteractions')
            ->addSelect('SUM(CASE WHEN i.liked = 1 THEN 1 ELSE 0 END) as likes')
            ->addSelect('SUM(CASE WHEN i.saved = 1 THEN 1 ELSE 0 END) as saves')
            ->from('App\Entity\UserQuoteInteraction', 'i')
            ->where('i.createdAt >= :startDate')
            ->setParameter('startDate', new \DateTime("-{$days} days"))
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get badge emoji based on rank
     */
    private function getBadge(int $rank): string
    {
        return match($rank) {
            1 => '👑',
            2 => '🥈',
            3 => '🥉',
            default => '🏅'
        };
    }

    /**
     * Get badge color based on rank
     */
    private function getBadgeColor(int $rank): string
    {
        return match($rank) {
            1 => 'bg-gradient-to-r from-amber-600 to-yellow-700',
            2 => 'bg-gradient-to-r from-slate-500 to-gray-600',
            3 => 'bg-gradient-to-r from-orange-600 to-amber-700',
            default => 'bg-gradient-to-r from-blue-500 to-indigo-600'
        };
    }
}
