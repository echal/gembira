<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_points')]
class UserPoints
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pointsTotal = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUpdated = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Pegawai
    {
        return $this->user;
    }

    public function setUser(?Pegawai $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPointsTotal(): int
    {
        return $this->pointsTotal;
    }

    public function setPointsTotal(int $pointsTotal): self
    {
        $this->pointsTotal = $pointsTotal;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Add points to the total
     */
    public function addPoints(int $points): self
    {
        $this->pointsTotal += $points;
        $this->lastUpdated = new \DateTime();
        return $this;
    }

    /**
     * Get progress percentage to next level
     */
    public function getProgressToNextLevel(array $levelThresholds): float
    {
        $currentLevelThreshold = $levelThresholds[$this->level] ?? 0;
        $nextLevel = $this->level + 1;
        $nextLevelThreshold = $levelThresholds[$nextLevel] ?? $currentLevelThreshold;

        if ($nextLevelThreshold == $currentLevelThreshold) {
            return 100.0; // Max level reached
        }

        $pointsInCurrentLevel = $this->pointsTotal - $currentLevelThreshold;
        $pointsNeededForNextLevel = $nextLevelThreshold - $currentLevelThreshold;

        return min(100.0, ($pointsInCurrentLevel / $pointsNeededForNextLevel) * 100);
    }

    /**
     * Get points needed for next level
     */
    public function getPointsNeededForNextLevel(array $levelThresholds): int
    {
        $nextLevel = $this->level + 1;
        $nextLevelThreshold = $levelThresholds[$nextLevel] ?? null;

        if ($nextLevelThreshold === null) {
            return 0; // Max level reached
        }

        return max(0, $nextLevelThreshold - $this->pointsTotal);
    }
}
