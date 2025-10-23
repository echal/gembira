<?php

namespace App\Repository;

use App\Entity\MonthlyLeaderboard;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonthlyLeaderboard>
 */
class MonthlyLeaderboardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyLeaderboard::class);
    }

    /**
     * Get top 10 users by XP for specific month/year
     */
    public function findTop10ByMonthYear(int $month, int $year): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->orderBy('m.xp_monthly', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all rankings for specific month/year (for full leaderboard display)
     */
    public function findAllByMonthYear(int $month, int $year, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->orderBy('m.xp_monthly', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find or create monthly leaderboard entry for user
     */
    public function findOrCreateForUser(Pegawai $user, int $month, int $year): MonthlyLeaderboard
    {
        $entry = $this->findOneBy([
            'user' => $user,
            'month' => $month,
            'year' => $year
        ]);

        if (!$entry) {
            $entry = new MonthlyLeaderboard();
            $entry->setUser($user);
            $entry->setMonth($month);
            $entry->setYear($year);
            $entry->setXpMonthly(0);

            $em = $this->getEntityManager();
            $em->persist($entry);
            $em->flush();
        }

        return $entry;
    }

    /**
     * Update user's monthly XP
     */
    public function updateUserXpMonthly(Pegawai $user, int $xp, int $month, int $year): void
    {
        $entry = $this->findOrCreateForUser($user, $month, $year);
        $entry->setXpMonthly($entry->getXpMonthly() + $xp);
        $entry->setUpdatedAt(new \DateTime());

        $em = $this->getEntityManager();
        $em->flush();

        // Recalculate rankings after update
        $this->recalculateRankings($month, $year);
    }

    /**
     * Recalculate rankings for specific month/year
     */
    public function recalculateRankings(int $month, int $year): void
    {
        $entries = $this->createQueryBuilder('m')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->orderBy('m.xp_monthly', 'DESC')
            ->addOrderBy('m.updated_at', 'ASC') // Earlier updater gets better rank in case of tie
            ->getQuery()
            ->getResult();

        $rank = 1;
        $em = $this->getEntityManager();

        foreach ($entries as $entry) {
            $entry->setRankMonthly($rank);
            $rank++;
        }

        $em->flush();
    }

    /**
     * Reset monthly leaderboard (for new month)
     * This doesn't delete entries, just creates new period
     */
    public function resetMonthlyLeaderboard(): void
    {
        // Nothing to do - new month entries will be created automatically
        // Old entries are preserved for historical data
    }

    /**
     * Get user's ranking for specific month/year
     */
    public function getUserRanking(Pegawai $user, int $month, int $year): ?array
    {
        $entry = $this->findOneBy([
            'user' => $user,
            'month' => $month,
            'year' => $year
        ]);

        if (!$entry) {
            return null;
        }

        $totalUsers = $this->count([
            'month' => $month,
            'year' => $year
        ]);

        return [
            'rank' => $entry->getRankMonthly(),
            'xp' => $entry->getXpMonthly(),
            'total_users' => $totalUsers
        ];
    }

    /**
     * Get monthly comparison for user (current vs previous month)
     */
    public function getMonthlyComparison(Pegawai $user): array
    {
        $currentDate = new \DateTime();
        $currentMonth = (int) $currentDate->format('n');
        $currentYear = (int) $currentDate->format('Y');

        $previousDate = (clone $currentDate)->modify('-1 month');
        $previousMonth = (int) $previousDate->format('n');
        $previousYear = (int) $previousDate->format('Y');

        $currentEntry = $this->findOneBy([
            'user' => $user,
            'month' => $currentMonth,
            'year' => $currentYear
        ]);

        $previousEntry = $this->findOneBy([
            'user' => $user,
            'month' => $previousMonth,
            'year' => $previousYear
        ]);

        return [
            'current' => [
                'month' => $currentMonth,
                'year' => $currentYear,
                'xp' => $currentEntry ? $currentEntry->getXpMonthly() : 0,
                'rank' => $currentEntry ? $currentEntry->getRankMonthly() : null
            ],
            'previous' => [
                'month' => $previousMonth,
                'year' => $previousYear,
                'xp' => $previousEntry ? $previousEntry->getXpMonthly() : 0,
                'rank' => $previousEntry ? $previousEntry->getRankMonthly() : null
            ]
        ];
    }

    /**
     * Get historical monthly data for user (last N months)
     */
    public function getMonthlyHistory(Pegawai $user, int $months = 6): array
    {
        $entries = $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.year', 'DESC')
            ->addOrderBy('m.month', 'DESC')
            ->setMaxResults($months)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    /**
     * Get XP by Unit Kerja for specific month/year (for admin dashboard)
     */
    public function getXpByUnitKerja(int $month, int $year): array
    {
        return $this->createQueryBuilder('m')
            ->select('u.namaUnit as unitKerja, SUM(m.xp_monthly) as totalXp, COUNT(DISTINCT m.user) as userCount')
            ->join('m.user', 'p')
            ->leftJoin('p.unitKerjaEntity', 'u')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->groupBy('u.namaUnit')
            ->orderBy('totalXp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total monthly XP for all users in specific month/year
     */
    public function getMonthlyXpTotal(int $month, int $year): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUM(m.xp_monthly) as total')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get count of active users (with XP > 0) for specific month/year
     */
    public function getActiveUsersCount(int $month, int $year): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.user)')
            ->where('m.month = :month')
            ->andWhere('m.year = :year')
            ->andWhere('m.xp_monthly > 0')
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get XP trend for last N months (for chart)
     */
    public function getMonthlyXpTrend(int $months = 6): array
    {
        $currentDate = new \DateTime();

        $results = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = (clone $currentDate)->modify("-{$i} months");
            $month = (int) $date->format('n');
            $year = (int) $date->format('Y');

            $xp = $this->getMonthlyXpTotal($month, $year);
            $activeUsers = $this->getActiveUsersCount($month, $year);

            $results[] = [
                'month' => $month,
                'year' => $year,
                'month_label' => $date->format('M Y'),
                'total_xp' => $xp,
                'active_users' => $activeUsers
            ];
        }

        return $results;
    }
}
