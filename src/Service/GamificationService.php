<?php

namespace App\Service;

use App\Entity\Pegawai;
use App\Entity\UserPoints;
use App\Entity\UserBadges;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GamificationService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    // Level thresholds - poin minimum untuk setiap level
    private array $levelThresholds = [
        1 => 0,      // Level 1: 0-50 poin
        2 => 51,     // Level 2: 51-150 poin
        3 => 151,    // Level 3: 151-300 poin
        4 => 301,    // Level 4: 301-600 poin
        5 => 601,    // Level 5: 601+ poin (max)
    ];

    // Badge definitions per level
    private array $badges = [
        1 => ['name' => 'Pemula', 'icon' => '🌱'],
        2 => ['name' => 'Penyemangat', 'icon' => '💡'],
        3 => ['name' => 'Inspirator', 'icon' => '🔥'],
        4 => ['name' => 'Teladan Inspirasi', 'icon' => '🌟'],
        5 => ['name' => 'Duta Inspirasi', 'icon' => '👑'],
    ];

    // Point rewards for different actions
    public const POINTS_LIKE_QUOTE = 2;
    public const POINTS_SAVE_QUOTE = 5;
    public const POINTS_VIEW_QUOTE = 1;
    public const POINTS_SHARE_QUOTE = 10;
    public const POINTS_DAILY_LOGIN = 5;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * Add points to user and check for level up
     * Returns array with level_up status and new level/badge info
     */
    public function addPoints(Pegawai $user, int $points, string $reason = ''): array
    {
        $this->logger->info("Adding {$points} points to user {$user->getId()} for: {$reason}");

        // Get or create user points record
        $pointsRecord = $this->getUserPoints($user);
        $oldLevel = $pointsRecord->getLevel();
        $oldTotal = $pointsRecord->getPointsTotal();

        // Add points
        $pointsRecord->addPoints($points);
        $newTotal = $pointsRecord->getPointsTotal();

        // Check for level up
        $levelUpInfo = $this->checkAndHandleLevelUp($user, $pointsRecord, $oldLevel);

        // Persist changes
        $this->em->persist($pointsRecord);
        $this->em->flush();

        $this->logger->info("Points updated: {$oldTotal} -> {$newTotal}, Level: {$oldLevel} -> {$pointsRecord->getLevel()}");

        return [
            'success' => true,
            'points_added' => $points,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'old_level' => $oldLevel,
            'new_level' => $pointsRecord->getLevel(),
            'level_up' => $levelUpInfo['level_up'],
            'badge_earned' => $levelUpInfo['badge_earned'],
            'badge_info' => $levelUpInfo['badge_info'],
        ];
    }

    /**
     * Get or create user points record
     */
    public function getUserPoints(Pegawai $user): UserPoints
    {
        $repo = $this->em->getRepository(UserPoints::class);
        $pointsRecord = $repo->findOneBy(['user' => $user]);

        if (!$pointsRecord) {
            $pointsRecord = new UserPoints();
            $pointsRecord->setUser($user);
            $pointsRecord->setPointsTotal(0);
            $pointsRecord->setLevel(1);

            $this->em->persist($pointsRecord);
            $this->em->flush();

            // Grant first badge
            $this->grantBadge($user, 1);
        }

        return $pointsRecord;
    }

    /**
     * Check if user should level up and handle it
     */
    private function checkAndHandleLevelUp(Pegawai $user, UserPoints $pointsRecord, int $oldLevel): array
    {
        $currentTotal = $pointsRecord->getPointsTotal();
        $newLevel = $this->calculateLevel($currentTotal);

        $result = [
            'level_up' => false,
            'badge_earned' => false,
            'badge_info' => null,
        ];

        if ($newLevel > $oldLevel) {
            $pointsRecord->setLevel($newLevel);

            // Grant badge for new level
            $badgeInfo = $this->grantBadge($user, $newLevel);

            $result['level_up'] = true;
            $result['badge_earned'] = $badgeInfo !== null;
            $result['badge_info'] = $badgeInfo;

            $this->logger->info("User {$user->getId()} leveled up: {$oldLevel} -> {$newLevel}");
        }

        return $result;
    }

    /**
     * Calculate level based on total points
     */
    private function calculateLevel(int $totalPoints): int
    {
        $level = 1;

        foreach ($this->levelThresholds as $lvl => $threshold) {
            if ($totalPoints >= $threshold) {
                $level = $lvl;
            } else {
                break;
            }
        }

        return $level;
    }

    /**
     * Grant badge to user for specific level
     * Returns badge info if granted, null if already exists
     */
    private function grantBadge(Pegawai $user, int $level): ?array
    {
        if (!isset($this->badges[$level])) {
            $this->logger->warning("Badge not defined for level {$level}");
            return null;
        }

        $badgeData = $this->badges[$level];
        $badgeRepo = $this->em->getRepository(UserBadges::class);

        // Check if user already has this badge
        $existingBadge = $badgeRepo->findOneBy([
            'user' => $user,
            'badgeLevel' => $level
        ]);

        if ($existingBadge) {
            $this->logger->info("User {$user->getId()} already has badge for level {$level}");
            return null;
        }

        // Create new badge
        $badge = new UserBadges();
        $badge->setUser($user);
        $badge->setBadgeName($badgeData['name']);
        $badge->setBadgeIcon($badgeData['icon']);
        $badge->setBadgeLevel($level);
        $badge->setEarnedDate(new \DateTime());

        $this->em->persist($badge);
        $this->em->flush();

        $this->logger->info("Granted badge '{$badgeData['name']}' to user {$user->getId()}");

        return [
            'name' => $badgeData['name'],
            'icon' => $badgeData['icon'],
            'level' => $level,
        ];
    }

    /**
     * Get all badges for a user
     */
    public function getUserBadges(Pegawai $user): array
    {
        $repo = $this->em->getRepository(UserBadges::class);
        return $repo->findBy(['user' => $user], ['badgeLevel' => 'ASC']);
    }

    /**
     * Get current badge for user's level
     */
    public function getCurrentBadge(Pegawai $user): ?array
    {
        $pointsRecord = $this->getUserPoints($user);
        $currentLevel = $pointsRecord->getLevel();

        if (!isset($this->badges[$currentLevel])) {
            return null;
        }

        return [
            'name' => $this->badges[$currentLevel]['name'],
            'icon' => $this->badges[$currentLevel]['icon'],
            'level' => $currentLevel,
        ];
    }

    /**
     * Get level thresholds
     */
    public function getLevelThresholds(): array
    {
        return $this->levelThresholds;
    }

    /**
     * Get all available badges
     */
    public function getAllBadges(): array
    {
        return $this->badges;
    }

    /**
     * Get user stats including points, level, badges, progress
     */
    public function getUserStats(Pegawai $user): array
    {
        $pointsRecord = $this->getUserPoints($user);
        $badges = $this->getUserBadges($user);
        $currentBadge = $this->getCurrentBadge($user);

        return [
            'points_total' => $pointsRecord->getPointsTotal(),
            'level' => $pointsRecord->getLevel(),
            'progress_percent' => $pointsRecord->getProgressToNextLevel($this->levelThresholds),
            'points_to_next_level' => $pointsRecord->getPointsNeededForNextLevel($this->levelThresholds),
            'current_badge' => $currentBadge,
            'badges' => $badges,
            'badges_count' => count($badges),
        ];
    }

    /**
     * Award daily login bonus
     * Only awards once per day
     */
    public function awardDailyLogin(Pegawai $user): ?array
    {
        $pointsRecord = $this->getUserPoints($user);
        $lastUpdated = $pointsRecord->getLastUpdated();
        $today = new \DateTime('today');

        // Check if already awarded today
        if ($lastUpdated && $lastUpdated >= $today) {
            return null; // Already awarded today
        }

        return $this->addPoints($user, self::POINTS_DAILY_LOGIN, 'Daily login bonus');
    }
}
