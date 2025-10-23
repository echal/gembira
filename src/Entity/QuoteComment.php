<?php

namespace App\Entity;

use App\Repository\QuoteCommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteCommentRepository::class)]
#[ORM\Table(name: 'quote_comments')]
#[ORM\Index(name: 'idx_quote_id', columns: ['quote_id'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_parent_id', columns: ['parent_id'])]
class QuoteComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quote::class)]
    #[ORM\JoinColumn(name: 'quote_id', nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?Pegawai $user = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $replies;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?Pegawai
    {
        return $this->user;
    }

    public function setUser(?Pegawai $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(self $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }

        return $this;
    }

    public function removeReply(self $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParent() === $this) {
                $reply->setParent(null);
            }
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;
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

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Check if this is a top-level comment (not a reply)
     */
    public function isTopLevel(): bool
    {
        return $this->parent === null;
    }

    /**
     * Get count of replies
     */
    public function getReplyCount(): int
    {
        return $this->replies->count();
    }

    /**
     * Get formatted created date
     */
    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt->format('d M Y H:i');
    }

    /**
     * Get time ago string
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);

        if ($diff->y > 0) {
            return $diff->y . ' tahun lalu';
        } elseif ($diff->m > 0) {
            return $diff->m . ' bulan lalu';
        } elseif ($diff->d > 0) {
            return $diff->d . ' hari lalu';
        } elseif ($diff->h > 0) {
            return $diff->h . ' jam lalu';
        } elseif ($diff->i > 0) {
            return $diff->i . ' menit lalu';
        } else {
            return 'Baru saja';
        }
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(bool $includeReplies = false): array
    {
        $data = [
            'id' => $this->id,
            'comment' => $this->comment,
            'created_at' => $this->getFormattedCreatedAt(),
            'time_ago' => $this->getTimeAgo(),
            'user' => [
                'id' => $this->user->getId(),
                'name' => $this->user->getNama(),
                'photo' => $this->user->getPhoto() ?? '/images/default-user.png',
                'jabatan' => $this->user->getJabatan() ?? '-',
                'unit_kerja' => $this->user->getUnitKerja() ?? '-'
            ],
            'parent_id' => $this->parent ? $this->parent->getId() : null,
            'reply_count' => $this->getReplyCount()
        ];

        if ($includeReplies && $this->getReplyCount() > 0) {
            $data['replies'] = array_map(
                fn($reply) => $reply->toArray(false),
                $this->replies->toArray()
            );
        }

        return $data;
    }
}
