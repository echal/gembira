<?php

namespace App\Entity;

use App\Repository\UserQuoteInteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserQuoteInteractionRepository::class)]
#[ORM\Table(name: 'user_quotes_interaction')]
#[ORM\Index(columns: ['user_id', 'quote_id'], name: 'idx_user_quote')]
class UserQuoteInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $liked = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $saved = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $viewed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;
        return $this;
    }

    public function isLiked(): bool
    {
        return $this->liked;
    }

    public function setLiked(bool $liked): static
    {
        $this->liked = $liked;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isSaved(): bool
    {
        return $this->saved;
    }

    public function setSaved(bool $saved): static
    {
        $this->saved = $saved;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isViewed(): bool
    {
        return $this->viewed;
    }

    public function setViewed(bool $viewed): static
    {
        $this->viewed = $viewed;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function hasComment(): bool
    {
        return !empty($this->comment);
    }
}
