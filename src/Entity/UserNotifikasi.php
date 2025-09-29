<?php

namespace App\Entity;

use App\Repository\UserNotifikasiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotifikasiRepository::class)]
#[ORM\Table(name: 'user_notifikasi')]
class UserNotifikasi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // # ENTITY RELATION: relasi ke pegawai (user yang menerima notifikasi)
    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(name: 'pegawai_id', referencedColumnName: 'id', nullable: false)]
    private ?Pegawai $pegawai = null;

    // # ENTITY RELATION: relasi ke notifikasi yang diterima user
    #[ORM\ManyToOne(targetEntity: Notifikasi::class)]
    #[ORM\JoinColumn(name: 'notifikasi_id', referencedColumnName: 'id', nullable: false)]
    private ?Notifikasi $notifikasi = null;

    // Status apakah notifikasi sudah dibaca atau belum
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $isRead = false;

    // Waktu notifikasi diterima oleh user (untuk tracking)
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $receivedAt = null;

    // Waktu notifikasi dibaca oleh user (null jika belum dibaca)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    // Prioritas notifikasi untuk user (low, normal, high)
    #[ORM\Column(length: 20, options: ['default' => 'normal'])]
    private ?string $priority = 'normal';

    public function __construct()
    {
        $this->receivedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // # ENTITY RELATION: getter dan setter untuk relasi pegawai
    public function getPegawai(): ?Pegawai
    {
        return $this->pegawai;
    }

    public function setPegawai(?Pegawai $pegawai): static
    {
        $this->pegawai = $pegawai;
        return $this;
    }

    // # ENTITY RELATION: getter dan setter untuk relasi notifikasi
    public function getNotifikasi(): ?Notifikasi
    {
        return $this->notifikasi;
    }

    public function setNotifikasi(?Notifikasi $notifikasi): static
    {
        $this->notifikasi = $notifikasi;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        
        // Otomatis set waktu dibaca ketika status diubah menjadi read
        if ($isRead && !$this->readAt) {
            $this->readAt = new \DateTime();
        }
        
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeInterface $receivedAt): static
    {
        $this->receivedAt = $receivedAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    // Helper method untuk mendapatkan ikon prioritas
    public function getPriorityIcon(): string
    {
        return match($this->priority) {
            'high' => '🔴',
            'normal' => '🔵',
            'low' => '⚪',
            default => '📋'
        };
    }

    // Helper method untuk mendapatkan class CSS prioritas
    public function getPriorityClass(): string
    {
        return match($this->priority) {
            'high' => 'border-l-red-400 bg-red-50',
            'normal' => 'border-l-sky-400 bg-sky-50',
            'low' => 'border-l-gray-400 bg-gray-50',
            default => 'border-l-sky-400 bg-sky-50'
        };
    }

    // Helper method untuk waktu relatif sejak diterima
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $interval = $now->diff($this->receivedAt);
        
        if ($interval->days > 0) {
            return $interval->days . ' hari yang lalu';
        } elseif ($interval->h > 0) {
            return $interval->h . ' jam yang lalu';
        } elseif ($interval->i > 0) {
            return $interval->i . ' menit yang lalu';
        } else {
            return 'Baru saja';
        }
    }

    public function __toString(): string
    {
        return $this->notifikasi?->getJudul() ?? 'User Notifikasi';
    }
}