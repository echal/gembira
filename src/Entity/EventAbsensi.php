<?php

namespace App\Entity;

use App\Repository\EventAbsensiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventAbsensiRepository::class)]
#[ORM\Table(name: 'event_absensi')]
#[ORM\UniqueConstraint(name: 'unique_event_user', columns: ['event_id', 'user_id'])]
class EventAbsensi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pegawai $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $waktuAbsen = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'hadir';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $keterangan = null;

    public function __construct()
    {
        $this->waktuAbsen = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
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

    public function getWaktuAbsen(): ?\DateTimeInterface
    {
        return $this->waktuAbsen;
    }

    public function setWaktuAbsen(\DateTimeInterface $waktuAbsen): static
    {
        $this->waktuAbsen = $waktuAbsen;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getKeterangan(): ?string
    {
        return $this->keterangan;
    }

    public function setKeterangan(?string $keterangan): static
    {
        $this->keterangan = $keterangan;
        return $this;
    }

    public function isHadir(): bool
    {
        return $this->status === 'hadir';
    }

    public function getStatusBadge(): string
    {
        return match($this->status) {
            'hadir' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Hadir</span>',
            'tidak_hadir' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Tidak Hadir</span>',
            'izin' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">📝 Izin</span>',
            default => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . ucfirst($this->status) . '</span>'
        };
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->user?->getNama() ?? 'User', 
            $this->event?->getJudulEvent() ?? 'Event',
            $this->status
        );
    }
}