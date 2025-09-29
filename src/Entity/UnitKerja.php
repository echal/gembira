<?php

namespace App\Entity;

use App\Repository\UnitKerjaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitKerjaRepository::class)]
#[ORM\Table(name: 'unit_kerja')]
class UnitKerja
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $namaUnit = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $kodeUnit = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $keterangan = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Relasi ke Kepala Bidang - simplified, no bidirectional mapping for now
    // Note: Use repository queries to get kepala bidang by unit kerja

    // Relasi ke Pegawai (one unit kerja has many pegawai)
    #[ORM\OneToMany(targetEntity: Pegawai::class, mappedBy: 'unitKerjaEntity')]
    private Collection $pegawai;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->pegawai = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNamaUnit(): ?string
    {
        return $this->namaUnit;
    }

    public function setNamaUnit(string $namaUnit): static
    {
        $this->namaUnit = $namaUnit;
        return $this;
    }

    public function getKodeUnit(): ?string
    {
        return $this->kodeUnit;
    }

    public function setKodeUnit(string $kodeUnit): static
    {
        $this->kodeUnit = strtoupper($kodeUnit);
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

    // Temporary property for kepala bidang (populated by repository)
    public $_kepalaBidang = null;

    // Method for getting kepala bidang through repository
    public function getKepalaBidang(): ?KepalaBidang
    {
        // Return the populated kepala bidang if available
        return $this->_kepalaBidang ?? null;
    }

    /**
     * @return Collection<int, Pegawai>
     */
    public function getPegawai(): Collection
    {
        return $this->pegawai;
    }

    public function addPegawai(Pegawai $pegawai): static
    {
        if (!$this->pegawai->contains($pegawai)) {
            $this->pegawai->add($pegawai);
            $pegawai->setUnitKerjaEntity($this);
        }

        return $this;
    }

    public function removePegawai(Pegawai $pegawai): static
    {
        if ($this->pegawai->removeElement($pegawai)) {
            if ($pegawai->getUnitKerjaEntity() === $this) {
                $pegawai->setUnitKerjaEntity(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->namaUnit ?? 'Unit Kerja';
    }

    // Helper method to get nama kepala bidang
    public function getNamaKepalaBidang(): ?string
    {
        // Use the populated kepala bidang if available
        return $this->_kepalaBidang ? $this->_kepalaBidang->getNama() : null;
    }

    // Helper method to get jumlah pegawai
    public function getJumlahPegawai(): int
    {
        return $this->pegawai->count();
    }
}