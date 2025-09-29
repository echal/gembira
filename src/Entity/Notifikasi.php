<?php

namespace App\Entity;

use App\Repository\NotifikasiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotifikasiRepository::class)]
#[ORM\Table(name: 'notifikasi')]
class Notifikasi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // # ENTITY PIVOT FIX: hubungan pegawai sekarang opsional karena menggunakan UserNotifikasi pivot
    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pegawai $pegawai = null;

    // hubungan: notifikasi bisa terkait dengan event tertentu (opsional)
    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Event $event = null;

    // Judul notifikasi (bahasa Indonesia)
    #[ORM\Column(length: 200)]
    private ?string $judul = null;

    // Isi pesan notifikasi (bahasa Indonesia)
    #[ORM\Column(type: Types::TEXT)]
    private ?string $pesan = null;

    // Tipe notifikasi: event_baru, absensi, pengumuman, dll
    #[ORM\Column(length: 50)]
    private ?string $tipe = 'event_baru';

    // Status dibaca atau belum
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $sudahDibaca = false;

    // Waktu notifikasi dibuat
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $waktuDibuat = null;

    // Waktu notifikasi dibaca (nullable) - DEPRECATED: gunakan UserNotifikasi
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $waktuDibaca = null;

    // # ENTITY RELATION: relasi ke user notifikasi (pivot table untuk tracking read status)
    #[ORM\OneToMany(targetEntity: UserNotifikasi::class, mappedBy: 'notifikasi', cascade: ['persist', 'remove'])]
    private Collection $userNotifikasi;

    public function __construct()
    {
        $this->waktuDibuat = new \DateTime();
        $this->userNotifikasi = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPegawai(): ?Pegawai
    {
        return $this->pegawai;
    }

    public function setPegawai(?Pegawai $pegawai): static
    {
        $this->pegawai = $pegawai;
        return $this;
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

    public function getJudul(): ?string
    {
        return $this->judul;
    }

    public function setJudul(string $judul): static
    {
        $this->judul = $judul;
        return $this;
    }

    public function getPesan(): ?string
    {
        return $this->pesan;
    }

    public function setPesan(string $pesan): static
    {
        $this->pesan = $pesan;
        return $this;
    }

    public function getTipe(): ?string
    {
        return $this->tipe;
    }

    public function setTipe(string $tipe): static
    {
        $this->tipe = $tipe;
        return $this;
    }

    public function isSudahDibaca(): ?bool
    {
        return $this->sudahDibaca;
    }

    public function setSudahDibaca(bool $sudahDibaca): static
    {
        $this->sudahDibaca = $sudahDibaca;
        if ($sudahDibaca && !$this->waktuDibaca) {
            $this->waktuDibaca = new \DateTime();
        }
        return $this;
    }

    public function getWaktuDibuat(): ?\DateTimeInterface
    {
        return $this->waktuDibuat;
    }

    public function setWaktuDibuat(\DateTimeInterface $waktuDibuat): static
    {
        $this->waktuDibuat = $waktuDibuat;
        return $this;
    }

    public function getWaktuDibaca(): ?\DateTimeInterface
    {
        return $this->waktuDibaca;
    }

    public function setWaktuDibaca(?\DateTimeInterface $waktuDibaca): static
    {
        $this->waktuDibaca = $waktuDibaca;
        return $this;
    }

    // Utility methods untuk tampilan

    public function getTipeIcon(): string
    {
        return match($this->tipe) {
            'event_baru' => '📅',
            'absensi' => '✅',
            'pengumuman' => '📢',
            'reminder' => '⏰',
            default => '📋'
        };
    }

    public function getTipeBadgeClass(): string
    {
        return match($this->tipe) {
            'event_baru' => 'bg-sky-100 text-sky-800',
            'absensi' => 'bg-green-100 text-green-800',
            'pengumuman' => 'bg-blue-100 text-blue-800',
            'reminder' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getWaktuRelatif(): string
    {
        $now = new \DateTime();
        $interval = $now->diff($this->waktuDibuat);
        
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

    // # ENTITY RELATION: getter dan setter untuk relasi user notifikasi
    /**
     * @return Collection<int, UserNotifikasi>
     */
    public function getUserNotifikasi(): Collection
    {
        return $this->userNotifikasi;
    }

    public function addUserNotifikasi(UserNotifikasi $userNotifikasi): static
    {
        if (!$this->userNotifikasi->contains($userNotifikasi)) {
            $this->userNotifikasi->add($userNotifikasi);
            $userNotifikasi->setNotifikasi($this);
        }

        return $this;
    }

    public function removeUserNotifikasi(UserNotifikasi $userNotifikasi): static
    {
        if ($this->userNotifikasi->removeElement($userNotifikasi)) {
            if ($userNotifikasi->getNotifikasi() === $this) {
                $userNotifikasi->setNotifikasi(null);
            }
        }

        return $this;
    }

    // Helper method untuk mendapatkan user notifikasi untuk pegawai tertentu
    public function getUserNotifikasiForPegawai(Pegawai $pegawai): ?UserNotifikasi
    {
        foreach ($this->userNotifikasi as $userNotif) {
            if ($userNotif->getPegawai() === $pegawai) {
                return $userNotif;
            }
        }
        return null;
    }

    // Helper method untuk cek apakah sudah dibaca oleh pegawai tertentu
    public function isReadByPegawai(Pegawai $pegawai): bool
    {
        $userNotif = $this->getUserNotifikasiForPegawai($pegawai);
        return $userNotif ? $userNotif->isRead() : false;
    }

    public function __toString(): string
    {
        return $this->judul ?? 'Notifikasi';
    }
}