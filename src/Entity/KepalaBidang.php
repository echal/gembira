<?php

namespace App\Entity;

use App\Repository\KepalaBidangRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KepalaBidangRepository::class)]
#[ORM\Table(name: 'kepala_bidang')]
class KepalaBidang
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nama = null;

    #[ORM\Column(length: 18, unique: true)]
    private ?string $nip = null;

    #[ORM\Column(length: 100)]
    private ?string $jabatan = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pangkatGol = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Relasi ke Unit Kerja (one kepala bidang belongs to one unit kerja)
    #[ORM\ManyToOne(targetEntity: UnitKerja::class)]
    #[ORM\JoinColumn(name: 'unit_kerja_id', referencedColumnName: 'id', nullable: false)]
    private ?UnitKerja $unitKerja = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNama(): ?string
    {
        return $this->nama;
    }

    public function setNama(string $nama): static
    {
        $this->nama = $nama;
        return $this;
    }

    public function getNip(): ?string
    {
        return $this->nip;
    }

    public function setNip(string $nip): static
    {
        // Validasi panjang NIP (18 digit)
        if (strlen($nip) !== 18 || !ctype_digit($nip)) {
            throw new \InvalidArgumentException('NIP harus berupa 18 digit angka');
        }
        
        $this->nip = $nip;
        return $this;
    }

    public function getJabatan(): ?string
    {
        return $this->jabatan;
    }

    public function setJabatan(string $jabatan): static
    {
        $this->jabatan = $jabatan;
        return $this;
    }

    public function getPangkatGol(): ?string
    {
        return $this->pangkatGol;
    }

    public function setPangkatGol(?string $pangkatGol): static
    {
        $this->pangkatGol = $pangkatGol;
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

    public function getUnitKerja(): ?UnitKerja
    {
        return $this->unitKerja;
    }

    public function setUnitKerja(?UnitKerja $unitKerja): static
    {
        $this->unitKerja = $unitKerja;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nama ?? 'Kepala Bidang';
    }

    // Helper method to get nama unit kerja
    public function getNamaUnitKerja(): ?string
    {
        return $this->unitKerja ? $this->unitKerja->getNamaUnit() : null;
    }

    // Helper method to get nama lengkap dengan jabatan
    public function getNamaLengkapDenganJabatan(): string
    {
        $result = $this->nama ?? 'Kepala Bidang';
        if ($this->jabatan) {
            $result .= ' - ' . $this->jabatan;
        }
        if ($this->pangkatGol) {
            $result .= ' (' . $this->pangkatGol . ')';
        }
        return $result;
    }

    // Method untuk validasi NIP secara static
    public static function validateNip(string $nip): bool
    {
        return strlen($nip) === 18 && ctype_digit($nip);
    }
}