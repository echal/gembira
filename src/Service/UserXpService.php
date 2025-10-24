<?php

namespace App\Service;

use App\Entity\Pegawai;
use App\Entity\UserXpLog;
use App\Repository\UserXpLogRepository;
use App\Repository\MonthlyLeaderboardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserXpService
{
    // XP Constants - Sesuai dengan GamificationService
    public const XP_CREATE_QUOTE = 20;
    public const XP_LIKE_QUOTE = 3;
    public const XP_COMMENT_QUOTE = 5;
    public const XP_SHARE_QUOTE = 8;
    public const XP_VIEW_QUOTE = 1; // Max 3x per day

    // Level ranges - Updated dengan progression yang lebih realistis
    // Target: Level 1→2 = 20 hari, Level 2→3 = 30 hari, Level 3→4 = 45 hari, Level 4→5 = 60 hari
    // Asumsi aktivitas normal: 187 XP/hari
    private const LEVEL_RANGES = [
        1 => ['min' => 0, 'max' => 3740, 'badge' => '🌱', 'title' => 'Pemula', 'julukan' => 'Penanam Niat Baik'],
        2 => ['min' => 3741, 'max' => 9350, 'badge' => '🌿', 'title' => 'Aktor Kebaikan', 'julukan' => 'Penyemai Semangat'],
        3 => ['min' => 9351, 'max' => 17765, 'badge' => '🌺', 'title' => 'Penggerak Semangat', 'julukan' => 'Inspirator Harian'],
        4 => ['min' => 17766, 'max' => 29985, 'badge' => '🌞', 'title' => 'Inspirator Gembira', 'julukan' => 'Teladan Komunitas'],
        5 => ['min' => 29986, 'max' => 999999, 'badge' => '🏆', 'title' => 'Teladan Kinerja', 'julukan' => 'Legenda Inspirasi'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserXpLogRepository $xpLogRepository,
        private MonthlyLeaderboardRepository $leaderboardRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Add XP to user and update level/badge/leaderboard
     *
     * @param Pegawai $user User to add XP to
     * @param int $xp Amount of XP to add
     * @param string $activityType Type of activity (e.g., 'create_quote', 'like_quote')
     * @param string|null $description Optional description
     * @param int|null $relatedId Related entity ID (e.g., quote_id)
     * @return array Result with level_up info if applicable
     */
    public function addXp(
        Pegawai $user,
        int $xp,
        string $activityType,
        ?string $description = null,
        ?int $relatedId = null
    ): array {
        try {
            // Get current level before adding XP
            $oldLevel = $user->getCurrentLevel();
            $oldXp = $user->getTotalXp();

            // Create XP log entry
            $xpLog = new UserXpLog();
            $xpLog->setUser($user);
            $xpLog->setXpEarned($xp);
            $xpLog->setActivityType($activityType);
            $xpLog->setDescription($description);
            $xpLog->setRelatedId($relatedId);

            $this->em->persist($xpLog);

            // Update user's total XP
            $newXp = $oldXp + $xp;
            $user->setTotalXp($newXp);

            // Calculate new level
            $newLevel = $this->calculateLevel($newXp);
            $levelUp = false;

            if ($newLevel > $oldLevel) {
                $user->setCurrentLevel($newLevel);
                $user->setCurrentBadge($this->getBadgeForLevel($newLevel));
                $levelUp = true;
            }

            // Update monthly leaderboard
            $currentDate = new \DateTime();
            $month = (int) $currentDate->format('n');
            $year = (int) $currentDate->format('Y');

            $this->leaderboardRepository->updateUserXpMonthly($user, $xp, $month, $year);

            // Flush all changes
            $this->em->flush();

            $result = [
                'success' => true,
                'xp_earned' => $xp,
                'total_xp' => $newXp,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'level_up' => $levelUp,
                'current_badge' => $user->getCurrentBadge(),
                'level_title' => $this->getTitleForLevel($newLevel),
                'level_julukan' => $this->getJulukanForLevel($newLevel)
            ];

            if ($levelUp) {
                $this->logger->info('User leveled up', [
                    'user_id' => $user->getId(),
                    'old_level' => $oldLevel,
                    'new_level' => $newLevel,
                    'total_xp' => $newXp
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Error adding XP', [
                'user_id' => $user->getId(),
                'xp' => $xp,
                'activity' => $activityType,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate level based on total XP
     */
    public function calculateLevel(int $totalXp): int
    {
        foreach (self::LEVEL_RANGES as $level => $range) {
            if ($totalXp >= $range['min'] && $totalXp <= $range['max']) {
                return $level;
            }
        }

        return 5; // Max level
    }

    /**
     * Get badge emoji for level
     */
    public function getBadgeForLevel(int $level): string
    {
        return self::LEVEL_RANGES[$level]['badge'] ?? '🌱';
    }

    /**
     * Get title for level
     */
    public function getTitleForLevel(int $level): string
    {
        return self::LEVEL_RANGES[$level]['title'] ?? 'Pemula';
    }

    /**
     * Get julukan (subtitle) for level
     */
    public function getJulukanForLevel(int $level): string
    {
        return self::LEVEL_RANGES[$level]['julukan'] ?? 'Penanam Niat Baik';
    }

    /**
     * Get level info for specific level
     */
    public function getLevelInfo(int $level): array
    {
        return self::LEVEL_RANGES[$level] ?? self::LEVEL_RANGES[1];
    }

    /**
     * Get all level ranges (for UI display)
     */
    public function getAllLevelRanges(): array
    {
        return self::LEVEL_RANGES;
    }

    /**
     * Format XP number for display (e.g., 3740 → "3.7K", 29985 → "30K")
     */
    public function formatXp(int $xp): string
    {
        if ($xp >= 1000) {
            $k = round($xp / 1000, 1);
            // Remove .0 if whole number
            return (floor($k) == $k) ? floor($k) . 'K' : $k . 'K';
        }
        return (string) $xp;
    }

    /**
     * Get XP needed for next level
     */
    public function getXpToNextLevel(Pegawai $user): ?int
    {
        $currentXp = $user->getTotalXp();
        $currentLevel = $user->getCurrentLevel();

        if ($currentLevel >= 5) {
            return null; // Already max level
        }

        $nextLevelRange = self::LEVEL_RANGES[$currentLevel + 1] ?? null;
        if (!$nextLevelRange) {
            return null;
        }

        return $nextLevelRange['min'] - $currentXp;
    }

    /**
     * Get progress percentage to next level
     */
    public function getProgressToNextLevel(Pegawai $user): float
    {
        $currentXp = $user->getTotalXp();
        $currentLevel = $user->getCurrentLevel();

        if ($currentLevel >= 5) {
            return 100.0; // Max level
        }

        $currentLevelRange = self::LEVEL_RANGES[$currentLevel];
        $nextLevelRange = self::LEVEL_RANGES[$currentLevel + 1] ?? null;

        if (!$nextLevelRange) {
            return 100.0;
        }

        $currentLevelMin = $currentLevelRange['min'];
        $nextLevelMin = $nextLevelRange['min'];
        $rangeSize = $nextLevelMin - $currentLevelMin;
        $progress = $currentXp - $currentLevelMin;

        return min(100.0, max(0.0, ($progress / $rangeSize) * 100));
    }

    /**
     * Get user's XP progress details
     */
    public function getUserXpProgress(Pegawai $user): array
    {
        return $user->getXpProgress();
    }

    /**
     * Get user's XP history (paginated)
     */
    public function getUserXpHistory(Pegawai $user, int $limit = 20, int $offset = 0): array
    {
        return $this->xpLogRepository->getUserXpLogs($user, $limit, $offset);
    }

    /**
     * Get XP breakdown by activity type
     */
    public function getXpBreakdown(Pegawai $user): array
    {
        return $this->xpLogRepository->getXpBreakdownByType($user);
    }

    /**
     * Get daily XP chart data for user
     */
    public function getDailyXpChart(Pegawai $user, int $days = 30): array
    {
        return $this->xpLogRepository->getDailyXpChart($user, $days);
    }

    /**
     * Get monthly leaderboard (top 10)
     */
    public function getMonthlyLeaderboard(?int $month = null, ?int $year = null): array
    {
        $currentDate = new \DateTime();
        $month = $month ?? (int) $currentDate->format('n');
        $year = $year ?? (int) $currentDate->format('Y');

        return $this->leaderboardRepository->findTop10ByMonthYear($month, $year);
    }

    /**
     * Get full monthly leaderboard (up to 50 users)
     */
    public function getFullMonthlyLeaderboard(?int $month = null, ?int $year = null, int $limit = 50): array
    {
        $currentDate = new \DateTime();
        $month = $month ?? (int) $currentDate->format('n');
        $year = $year ?? (int) $currentDate->format('Y');

        return $this->leaderboardRepository->findAllByMonthYear($month, $year, $limit);
    }

    /**
     * Get user's ranking for current/specific month
     */
    public function getUserRanking(Pegawai $user, ?int $month = null, ?int $year = null): ?array
    {
        $currentDate = new \DateTime();
        $month = $month ?? (int) $currentDate->format('n');
        $year = $year ?? (int) $currentDate->format('Y');

        return $this->leaderboardRepository->getUserRanking($user, $month, $year);
    }

    /**
     * Get monthly comparison for user (current vs previous month)
     */
    public function getMonthlyComparison(Pegawai $user): array
    {
        return $this->leaderboardRepository->getMonthlyComparison($user);
    }

    /**
     * Recalculate user's level based on total XP (for data repair/migration)
     */
    public function recalculateUserLevel(Pegawai $user): void
    {
        $totalXp = $this->xpLogRepository->getTotalXpByUser($user);
        $newLevel = $this->calculateLevel($totalXp);
        $newBadge = $this->getBadgeForLevel($newLevel);

        $user->setTotalXp($totalXp);
        $user->setCurrentLevel($newLevel);
        $user->setCurrentBadge($newBadge);

        $this->em->flush();
    }

    /**
     * Sync all users' XP from logs (for migration or data repair)
     */
    public function syncAllUsersXp(): array
    {
        $users = $this->em->getRepository(Pegawai::class)->findAll();
        $synced = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $this->recalculateUserLevel($user);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $this->logger->error('Error syncing user XP', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'total_users' => count($users),
            'synced' => $synced,
            'errors' => $errors
        ];
    }

    /**
     * Check if user has reached daily limit for view_quote activity (max 3x/day)
     */
    public function canAwardViewXp(Pegawai $user): bool
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $viewCount = $this->xpLogRepository->countActivityByTypeAndDate(
            $user,
            'view_quote',
            $today,
            $tomorrow
        );

        return $viewCount < 3; // Max 3 views per day
    }

    /**
     * Award XP for specific activity (convenience method)
     */
    public function awardXpForActivity(Pegawai $user, string $activity, ?int $relatedId = null): array
    {
        // Check daily limit for view_quote
        if ($activity === 'view_quote' && !$this->canAwardViewXp($user)) {
            return [
                'success' => false,
                'error' => 'Daily view limit reached (max 3x/day)',
                'xp_earned' => 0,
                'level_up' => false
            ];
        }

        $xpAmount = match($activity) {
            'create_quote' => self::XP_CREATE_QUOTE,
            'like_quote' => self::XP_LIKE_QUOTE,
            'comment_quote' => self::XP_COMMENT_QUOTE,
            'share_quote' => self::XP_SHARE_QUOTE,
            'view_quote' => self::XP_VIEW_QUOTE,
            default => 0
        };

        $description = match($activity) {
            'create_quote' => 'Membuat quote baru',
            'like_quote' => 'Menyukai quote',
            'comment_quote' => 'Mengomentari quote',
            'share_quote' => 'Membagikan quote',
            'view_quote' => 'Melihat quote',
            default => $activity
        };

        if ($xpAmount > 0) {
            return $this->addXp($user, $xpAmount, $activity, $description, $relatedId);
        }

        return ['success' => false, 'error' => 'Invalid activity type'];
    }
}
