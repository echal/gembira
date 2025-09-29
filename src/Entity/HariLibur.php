<?php

namespace App\Entity;

use App\Repository\HariLiburRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HariLiburRepository::class)]
#[ORM\Table(name: 'hari_libur')]
class HariLibur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Tanggal libur
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $tanggalLibur = null;

    // Nama hari libur (contoh: "Hari Kemerdekaan RI", "Idul Fitri")
    #[ORM\Column(length: 100)]
    private ?string $namaLibur = null;

    // Jenis libur (nasional/cuti_bersama/khusus)
    #[ORM\Column(length: 20)]
    private ?string $jenisLibur = 'nasional';

    // Keterangan tambahan
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $keterangan = null;

    // Status aktif/nonaktif
    #[ORM\Column(length: 20)]
    private ?string $status = 'aktif';

    // Tanggal dibuat
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    // Admin yang membuat
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTanggalLibur(): ?\DateTimeInterface
    {
        return $this->tanggalLibur;
    }

    public function setTanggalLibur(\DateTimeInterface $tanggalLibur): static
    {
        $this->tanggalLibur = $tanggalLibur;
        return $this;
    }

    public function getNamaLibur(): ?string
    {
        return $this->namaLibur;
    }

    public function setNamaLibur(string $namaLibur): static
    {
        $this->namaLibur = $namaLibur;
        return $this;
    }

    public function getJenisLibur(): ?string
    {
        return $this->jenisLibur;
    }

    public function setJenisLibur(string $jenisLibur): static
    {
        $this->jenisLibur = $jenisLibur;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getCreatedBy(): ?Admin
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Admin $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function __toString(): string
    {
        return $this->namaLibur ?? 'Hari Libur';
    }
}
