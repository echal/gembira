<?php

namespace App\Entity;

use App\Repository\UserXpLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserXpLogRepository::class)]
#[ORM\Table(name: 'user_xp_log')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_xp_log_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_user_xp_log_created')]
class UserXpLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $xp_earned = 0;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $activity_type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $related_id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
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

    public function getXpEarned(): int
    {
        return $this->xp_earned;
    }

    public function setXpEarned(int $xp_earned): static
    {
        $this->xp_earned = $xp_earned;
        return $this;
    }

    public function getActivityType(): string
    {
        return $this->activity_type;
    }

    public function setActivityType(string $activity_type): static
    {
        $this->activity_type = $activity_type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getRelatedId(): ?int
    {
        return $this->related_id;
    }

    public function setRelatedId(?int $related_id): static
    {
        $this->related_id = $related_id;
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
}
