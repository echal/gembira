<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_badges')]
class UserBadges
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $badgeName = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $badgeIcon = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $badgeLevel = 1;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $earnedDate = null;

    public function __construct()
    {
        $this->earnedDate = new \DateTime();
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

    public function getBadgeName(): ?string
    {
        return $this->badgeName;
    }

    public function setBadgeName(string $badgeName): self
    {
        $this->badgeName = $badgeName;
        return $this;
    }

    public function getBadgeIcon(): ?string
    {
        return $this->badgeIcon;
    }

    public function setBadgeIcon(string $badgeIcon): self
    {
        $this->badgeIcon = $badgeIcon;
        return $this;
    }

    public function getBadgeLevel(): int
    {
        return $this->badgeLevel;
    }

    public function setBadgeLevel(int $badgeLevel): self
    {
        $this->badgeLevel = $badgeLevel;
        return $this;
    }

    public function getEarnedDate(): ?\DateTimeInterface
    {
        return $this->earnedDate;
    }

    public function setEarnedDate(\DateTimeInterface $earnedDate): self
    {
        $this->earnedDate = $earnedDate;
        return $this;
    }

    /**
     * Get formatted earned date
     */
    public function getFormattedEarnedDate(): string
    {
        return $this->earnedDate ? $this->earnedDate->format('d M Y') : '';
    }

    /**
     * Get days since earned
     */
    public function getDaysSinceEarned(): int
    {
        if (!$this->earnedDate) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->earnedDate);
        return (int) $diff->days;
    }
}
