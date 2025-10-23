<?php

namespace App\Entity;

use App\Repository\MonthlyLeaderboardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyLeaderboardRepository::class)]
#[ORM\Table(name: 'monthly_leaderboard')]
#[ORM\UniqueConstraint(name: 'unique_user_month_year', columns: ['user_id', 'month', 'year'])]
#[ORM\Index(columns: ['month', 'year'], name: 'idx_month_year')]
#[ORM\Index(columns: ['xp_monthly'], name: 'idx_xp_monthly')]
#[ORM\Index(columns: ['rank_monthly'], name: 'idx_rank_monthly')]
class MonthlyLeaderboard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $month;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::INTEGER)]
    private int $xp_monthly = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rank_monthly = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Pegawai
    {
        return $this->user;
    }

    public function setUser(?Pegawai $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;
        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getXpMonthly(): int
    {
        return $this->xp_monthly;
    }

    public function setXpMonthly(int $xp_monthly): static
    {
        $this->xp_monthly = $xp_monthly;
        return $this;
    }

    public function getRankMonthly(): ?int
    {
        return $this->rank_monthly;
    }

    public function setRankMonthly(?int $rank_monthly): static
    {
        $this->rank_monthly = $rank_monthly;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}
