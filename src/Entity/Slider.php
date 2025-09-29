<?php

namespace App\Entity;

use App\Repository\SliderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SliderRepository::class)]
#[ORM\Table(name: 'sliders')]
class Slider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $orderNo = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'aktif'])]
    private string $status = 'aktif';

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getOrderNo(): int
    {
        return $this->orderNo;
    }

    public function setOrderNo(int $orderNo): static
    {
        $this->orderNo = $orderNo;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function hasLink(): bool
    {
        return !empty($this->link);
    }

    /**
     * Get full image URL for display
     */
    public function getImageUrl(): string
    {
        return '/uploads/sliders/' . $this->imagePath;
    }

    /**
     * Get full image path for file operations
     */
    public function getFullImagePath(): string
    {
        return 'public/uploads/sliders/' . $this->imagePath;
    }
}