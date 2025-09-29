<?php

namespace App\Entity;

use App\Repository\KepalaKantorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KepalaKantorRepository::class)]
#[ORM\Table(name: 'kepala_kantor')]
class KepalaKantor
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

    #[ORM\Column(length: 50)]
    private ?string $periode = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $isAktif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isAktif = true;
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

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;
        return $this;
    }

    public function isAktif(): ?bool
    {
        return $this->isAktif;
    }

    public function setIsAktif(bool $isAktif): static
    {
        $this->isAktif = $isAktif;
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

    public function __toString(): string
    {
        return $this->nama ?? 'Kepala Kantor';
    }

    // Helper method to get nama lengkap dengan jabatan dan periode
    public function getNamaLengkapDenganJabatan(): string
    {
        $result = $this->nama ?? 'Kepala Kantor';
        if ($this->jabatan) {
            $result .= ' - ' . $this->jabatan;
        }
        if ($this->pangkatGol) {
            $result .= ' (' . $this->pangkatGol . ')';
        }
        if ($this->periode) {
            $result .= ' - Periode ' . $this->periode;
        }
        return $result;
    }

    // Helper method to get status text
    public function getStatusText(): string
    {
        return $this->isAktif ? 'Aktif' : 'Non-aktif';
    }

    // Method untuk validasi NIP secara static
    public static function validateNip(string $nip): bool
    {
        return strlen($nip) === 18 && ctype_digit($nip);
    }

    // Helper method untuk format periode
    public static function generatePeriodeOptions(): array
    {
        $currentYear = (int) date('Y');
        $options = [];
        
        // Generate opsi periode untuk 10 tahun ke depan dan 5 tahun ke belakang
        for ($year = $currentYear - 5; $year <= $currentYear + 10; $year++) {
            $options["{$year}-" . ($year + 1)] = "Periode {$year}-" . ($year + 1);
        }
        
        return $options;
    }
}