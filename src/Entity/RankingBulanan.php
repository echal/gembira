<?php

namespace App\Entity;

use App\Repository\RankingBulananRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity untuk menyimpan ranking bulanan setiap pegawai
 *
 * Tabel ini mencatat peringkat pegawai setiap bulan berdasarkan akumulasi total durasi harian.
 * Ranking dihitung dari agregasi data ranking_harian selama satu bulan penuh.
 */
#[ORM\Entity(repositoryClass: RankingBulananRepository::class)]
#[ORM\Table(name: 'ranking_bulanan')]
#[ORM\Index(name: 'idx_pegawai_periode', columns: ['pegawai_id', 'periode'])]
#[ORM\Index(name: 'idx_periode_peringkat', columns: ['periode', 'peringkat'])]
#[ORM\UniqueConstraint(name: 'unique_pegawai_periode', columns: ['pegawai_id', 'periode'])]
class RankingBulanan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relasi ke pegawai
    #[ORM\ManyToOne(targetEntity: Pegawai::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pegawai $pegawai = null;

    // Periode dalam format YYYY-MM (contoh: 2025-01)
    #[ORM\Column(length: 7)]
    private ?string $periode = null;

    // Total durasi akumulasi selama sebulan (dalam menit)
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $totalDurasi = null;

    // Rata-rata durasi per hari (dalam menit)
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $rataRataDurasi = null;

    // Peringkat pegawai untuk bulan ini (1 = terbaik)
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

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;
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

    public function getRataRataDurasi(): ?float
    {
        return $this->rataRataDurasi;
    }

    public function setRataRataDurasi(?float $rataRataDurasi): static
    {
        $this->rataRataDurasi = $rataRataDurasi;
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
     * Helper method: Format total durasi dalam format yang mudah dibaca
     */
    public function getFormattedTotalDurasi(): string
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
     * Helper method: Format rata-rata durasi dalam format yang mudah dibaca
     */
    public function getFormattedRataRata(): string
    {
        if ($this->rataRataDurasi === null) {
            return 'N/A';
        }

        $menit = abs((int)$this->rataRataDurasi);
        $jam = floor($menit / 60);
        $sisa = $menit % 60;

        if ($jam > 0) {
            return "{$jam} jam {$sisa} menit";
        }

        return "{$sisa} menit";
    }

    /**
     * Helper method: Dapatkan nama bulan dalam bahasa Indonesia
     */
    public function getNamaBulan(): string
    {
        $namaBulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        if (!$this->periode) {
            return 'Unknown';
        }

        $parts = explode('-', $this->periode);
        $tahun = $parts[0] ?? '';
        $bulan = $parts[1] ?? '';

        return ($namaBulan[$bulan] ?? 'Unknown') . ' ' . $tahun;
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
        return "Ranking Bulanan {$pegawai} - {$this->getNamaBulan()} (#{$this->peringkat})";
    }
}
