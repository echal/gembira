<?php

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\Table(name: 'quotes')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalLikes = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalComments = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalViews = 0;

    // Field untuk menyimpan path foto (JSON array untuk multiple photos)
    // Contoh: ["inspirasi_1_1234567890.jpg", "inspirasi_1_1234567891.jpg"]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $photos = null;

    #[ORM\OneToMany(targetEntity: UserQuoteInteraction::class, mappedBy: 'quote', orphanRemoval: true)]
    private Collection $interactions;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'quotes', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'quote_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->interactions = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    /**
     * @return Collection<int, UserQuoteInteraction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }

    public function addInteraction(UserQuoteInteraction $interaction): static
    {
        if (!$this->interactions->contains($interaction)) {
            $this->interactions->add($interaction);
            $interaction->setQuote($this);
        }

        return $this;
    }

    public function removeInteraction(UserQuoteInteraction $interaction): static
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getQuote() === $this) {
                $interaction->setQuote(null);
            }
        }

        return $this;
    }

    public function getTotalLikes(): int
    {
        return $this->totalLikes;
    }

    public function setTotalLikes(int $totalLikes): static
    {
        $this->totalLikes = $totalLikes;
        return $this;
    }

    public function incrementLikes(): static
    {
        $this->totalLikes++;
        return $this;
    }

    public function decrementLikes(): static
    {
        $this->totalLikes = max(0, $this->totalLikes - 1);
        return $this;
    }

    public function getTotalComments(): int
    {
        return $this->totalComments;
    }

    public function setTotalComments(int $totalComments): static
    {
        $this->totalComments = $totalComments;
        return $this;
    }

    public function incrementComments(): static
    {
        $this->totalComments++;
        return $this;
    }

    public function getTotalViews(): int
    {
        return $this->totalViews;
    }

    public function setTotalViews(int $totalViews): static
    {
        $this->totalViews = $totalViews;
        return $this;
    }

    public function incrementViews(): static
    {
        $this->totalViews++;
        return $this;
    }

    // Getter dan Setter untuk photos
    public function getPhotos(): ?array
    {
        return $this->photos;
    }

    public function setPhotos(?array $photos): static
    {
        $this->photos = $photos;
        return $this;
    }

    // Helper method untuk menambah foto
    public function addPhoto(string $photoPath): static
    {
        if ($this->photos === null) {
            $this->photos = [];
        }
        $this->photos[] = $photoPath;
        return $this;
    }

    // Helper method untuk cek apakah ada foto
    public function hasPhotos(): bool
    {
        return !empty($this->photos);
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function clearTags(): static
    {
        $this->tags->clear();
        return $this;
    }

    public function hasTags(): bool
    {
        return !$this->tags->isEmpty();
    }

    /**
     * Get tag names as array
     */
    public function getTagNames(): array
    {
        return $this->tags->map(fn(Tag $tag) => $tag->getName())->toArray();
    }
}
