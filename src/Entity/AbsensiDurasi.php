<?php

namespace App\Entity;

use App\Repository\AbsensiDurasiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity untuk menyimpan durasi absensi harian setiap pegawai
 *
 * Tabel ini mencatat berapa menit selisih antara jam masuk pegawai dengan jam ideal.
 * Data ini digunakan untuk menghitung ranking harian dan bulanan.
 */
#[ORM\Entity(repositoryClass: AbsensiDurasiRepository::class)]
#[ORM\Table(name: 'absensi_durasi')]
#[ORM\Index(name: 'idx_pegawai_tanggal', columns: ['pegawai_id', 'tanggal'])]
#[ORM\Index(name: 'idx_tanggal', columns: ['tanggal'])]
class AbsensiDurasi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relasi ke pegawai
    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pegawai $pegawai = null;

    // Tanggal absensi
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $tanggal = null;

    // Jam masuk pegawai
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $jamMasuk = null;

    // Durasi dalam menit (selisih dari jam ideal)
    // Nilai positif = terlambat, nilai negatif = lebih awal
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $durasiMenit = null;

    // Timestamp kapan data ini dibuat
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getTanggal(): ?\DateTimeInterface
    {
        return $this->tanggal;
    }

    public function setTanggal(\DateTimeInterface $tanggal): static
    {
        $this->tanggal = $tanggal;
        return $this;
    }

    public function getJamMasuk(): ?\DateTimeInterface
    {
        return $this->jamMasuk;
    }

    public function setJamMasuk(\DateTimeInterface $jamMasuk): static
    {
        $this->jamMasuk = $jamMasuk;
        return $this;
    }

    public function getDurasiMenit(): ?int
    {
        return $this->durasiMenit;
    }

    public function setDurasiMenit(int $durasiMenit): static
    {
        $this->durasiMenit = $durasiMenit;
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

    /**
     * Helper method: Cek apakah pegawai datang tepat waktu atau terlambat
     */
    public function isTerlambat(): bool
    {
        return $this->durasiMenit > 0;
    }

    /**
     * Helper method: Format durasi dalam format yang mudah dibaca
     */
    public function getFormattedDurasi(): string
    {
        $menit = abs($this->durasiMenit);
        $jam = floor($menit / 60);
        $sisa = $menit % 60;

        $status = $this->durasiMenit > 0 ? 'Terlambat' : 'Lebih Awal';

        if ($jam > 0) {
            return "{$status} {$jam} jam {$sisa} menit";
        }

        return "{$status} {$sisa} menit";
    }

    public function __toString(): string
    {
        $pegawai = $this->pegawai ? $this->pegawai->getNama() : 'Unknown';
        $tanggal = $this->tanggal ? $this->tanggal->format('d/m/Y') : 'Unknown';
        return "Durasi Absensi {$pegawai} - {$tanggal}";
    }
}
