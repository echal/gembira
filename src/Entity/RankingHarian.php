<?php

namespace App\Entity;

use App\Repository\RankingHarianRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity untuk menyimpan ranking harian setiap pegawai
 *
 * Tabel ini mencatat peringkat pegawai setiap hari berdasarkan total durasi absensi.
 * Ranking diupdate secara dinamis setiap kali ada absensi baru.
 */
#[ORM\Entity(repositoryClass: RankingHarianRepository::class)]
#[ORM\Table(name: 'ranking_harian')]
#[ORM\Index(name: 'idx_pegawai_tanggal', columns: ['pegawai_id', 'tanggal'])]
#[ORM\Index(name: 'idx_tanggal_peringkat', columns: ['tanggal', 'peringkat'])]
#[ORM\UniqueConstraint(name: 'unique_pegawai_tanggal', columns: ['pegawai_id', 'tanggal'])]
class RankingHarian
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relasi ke pegawai
    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pegawai $pegawai = null;

    // Tanggal ranking
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $tanggal = null;

    // Jam masuk pegawai
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $jamMasuk = null;

    // Skor harian (maksimal 75, berdasarkan kecepatan absen)
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $skorHarian = null;

    // Total durasi dalam menit untuk hari ini (untuk backward compatibility)
    // Diakumulasi dari semua absensi dalam satu hari
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalDurasi = null;

    // Peringkat pegawai untuk hari ini (1 = terbaik)
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $peringkat = null;

    // Timestamp terakhir kali data ini diupdate
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
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

    public function setJamMasuk(?\DateTimeInterface $jamMasuk): static
    {
        $this->jamMasuk = $jamMasuk;
        return $this;
    }

    public function getSkorHarian(): ?int
    {
        return $this->skorHarian;
    }

    public function setSkorHarian(int $skorHarian): static
    {
        $this->skorHarian = $skorHarian;
        return $this;
    }

    public function getTotalDurasi(): ?int
    {
        return $this->totalDurasi;
    }

    public function setTotalDurasi(int $totalDurasi): static
    {
        $this->totalDurasi = $totalDurasi;
        return $this;
    }

    public function getPeringkat(): ?int
    {
        return $this->peringkat;
    }

    public function setPeringkat(int $peringkat): static
    {
        $this->peringkat = $peringkat;
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
     * Helper method: Format durasi dalam format yang mudah dibaca
     */
    public function getFormattedDurasi(): string
    {
        $menit = abs($this->totalDurasi);
        $jam = floor($menit / 60);
        $sisa = $menit % 60;

        if ($jam > 0) {
            return "{$jam} jam {$sisa} menit";
        }

        return "{$sisa} menit";
    }

    /**
     * Helper method: Dapatkan badge peringkat (untuk top 3)
     */
    public function getPeringkatBadge(): string
    {
        return match($this->peringkat) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => "#{$this->peringkat}"
        };
    }

    public function __toString(): string
    {
        $pegawai = $this->pegawai ? $this->pegawai->getNama() : 'Unknown';
        $tanggal = $this->tanggal ? $this->tanggal->format('d/m/Y') : 'Unknown';
        return "Ranking Harian {$pegawai} - {$tanggal} (#{$this->peringkat})";
    }
}
