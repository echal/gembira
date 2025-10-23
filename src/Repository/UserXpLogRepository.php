<?php

namespace App\Repository;

use App\Entity\Pegawai;
use App\Entity\UserXpLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserXpLog>
 */
class UserXpLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserXpLog::class);
    }

    /**
     * Get total XP earned by user
     */
    public function getTotalXpByUser(Pegawai $user): int
    {
        $result = $this->createQueryBuilder('x')
            ->select('SUM(x.xp_earned) as total_xp')
            ->where('x.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get monthly XP earned by user for specific month/year
     */
    public function getMonthlyXpByUser(Pegawai $user, int $month, int $year): int
    {
        $startDate = new \DateTime("$year-$month-01 00:00:00");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('x')
            ->select('SUM(x.xp_earned) as monthly_xp')
            ->where('x.user = :user')
            ->andWhere('x.created_at >= :start_date')
            ->andWhere('x.created_at <= :end_date')
            ->setParameter('user', $user)
            ->setParameter('start_date', $startDate)
            ->setParameter('end_date', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get XP logs for user with pagination
     */
    public function getUserXpLogs(Pegawai $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('x')
            ->where('x.user = :user')
            ->setParameter('user', $user)
            ->orderBy('x.created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent XP activities (all users) for feed
     */
    public function getRecentActivities(int $limit = 10): array
    {
        return $this->createQueryBuilder('x')
            ->orderBy('x.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count XP logs by activity type for user
     */
    public function countActivitiesByType(Pegawai $user, string $activityType): int
    {
        return (int) $this->createQueryBuilder('x')
            ->select('COUNT(x.id)')
            ->where('x.user = :user')
            ->andWhere('x.activity_type = :activity_type')
            ->setParameter('user', $user)
            ->setParameter('activity_type', $activityType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get XP breakdown by activity type for user
     */
    public function getXpBreakdownByType(Pegawai $user): array
    {
        return $this->createQueryBuilder('x')
            ->select('x.activity_type', 'SUM(x.xp_earned) as total_xp', 'COUNT(x.id) as count')
            ->where('x.user = :user')
            ->setParameter('user', $user)
            ->groupBy('x.activity_type')
            ->orderBy('total_xp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get daily XP for user (last 30 days)
     */
    public function getDailyXpChart(Pegawai $user, int $days = 30): array
    {
        $startDate = new \DateTime("-$days days");

        return $this->createQueryBuilder('x')
            ->select('DATE(x.created_at) as date', 'SUM(x.xp_earned) as daily_xp')
            ->where('x.user = :user')
            ->andWhere('x.created_at >= :start_date')
            ->setParameter('user', $user)
            ->setParameter('start_date', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count activities by type within a date range (for daily limits)
     */
    public function countActivityByTypeAndDate(
        Pegawai $user,
        string $activityType,
        \DateTime $startDate,
        \DateTime $endDate
    ): int {
        return (int) $this->createQueryBuilder('x')
            ->select('COUNT(x.id)')
            ->where('x.user = :user')
            ->andWhere('x.activity_type = :activity_type')
            ->andWhere('x.created_at >= :start_date')
            ->andWhere('x.created_at < :end_date')
            ->setParameter('user', $user)
            ->setParameter('activity_type', $activityType)
            ->setParameter('start_date', $startDate)
            ->setParameter('end_date', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
