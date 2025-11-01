<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tags')]
#[ORM\Index(columns: ['name'], name: 'idx_tag_name')]
#[UniqueEntity(fields: ['name'], message: 'Tag ini sudah ada')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToMany(targetEntity: Quote::class, mappedBy: 'tags')]
    private Collection $quotes;

    #[ORM\Column(options: ['default' => 0])]
    private int $usageCount = 0;

    public function __construct()
    {
        $this->quotes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->usageCount = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        // Auto-generate slug from name
        $this->slug = $this->generateSlug($name);
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Quote>
     */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): static
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->addTag($this);
            $this->incrementUsageCount();
        }

        return $this;
    }

    public function removeQuote(Quote $quote): static
    {
        if ($this->quotes->removeElement($quote)) {
            $quote->removeTag($this);
            $this->decrementUsageCount();
        }

        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function decrementUsageCount(): static
    {
        if ($this->usageCount > 0) {
            $this->usageCount--;
        }
        return $this;
    }

    /**
     * Recalculate usage count from actual quotes
     */
    public function recalculateUsageCount(): static
    {
        $this->usageCount = $this->quotes->count();
        return $this;
    }

    /**
     * Generate URL-friendly slug from name
     */
    private function generateSlug(string $name): string
    {
        // Remove # if present
        $slug = ltrim($name, '#');

        // Convert to lowercase
        $slug = mb_strtolower($slug, 'UTF-8');

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start and end
        $slug = trim($slug, '-');

        return $slug;
    }

    public function __toString(): string
    {
        return '#' . ($this->name ?? 'unknown');
    }
}
