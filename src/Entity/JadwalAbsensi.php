<?php

namespace App\Entity;

use App\Repository\JadwalAbsensiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JadwalAbsensiRepository::class)]
#[ORM\Table(name: 'jadwal_absensi')]
class JadwalAbsensi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Jenis absensi: ibadah_pagi, bbaq, apel_pagi, upacara_nasional
    #[ORM\Column(length: 30)]
    private ?string $jenisAbsensi = null;

    // Hari yang diizinkan untuk absensi (JSON array)
    // Contoh: ["1","2","3","4"] untuk Senin-Kamis
    // Contoh: ["1"] untuk Senin saja (Apel Pagi)
    #[ORM\Column(type: Types::JSON)]
    private array $hariDiizinkan = [];

    // Jam mulai absensi
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $jamMulai = null;

    // Jam selesai absensi
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $jamSelesai = null;

    // QR Code untuk Apel Pagi dan Upacara Nasional
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCode = null;

    // Tanggal khusus untuk upacara nasional (nullable untuk jadwal tetap)
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tanggalKhusus = null;

    // Status aktif/nonaktif
    #[ORM\Column]
    private ?bool $isAktif = true;

    // Keterangan tambahan
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $keterangan = null;

    // Nama custom untuk kategori absensi baru
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $namaCustom = null;

    // Emoji custom untuk kategori absensi baru
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $emojiCustom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Admin yang membuat/mengubah jadwal
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $createdBy = null;

    // Relasi ke absensi yang menggunakan jadwal ini
    // PERBAIKAN: Tambah cascade remove dan orphanRemoval untuk hard delete
    #[ORM\OneToMany(targetEntity: Absensi::class, mappedBy: 'jadwalAbsensi', cascade: ['remove'], orphanRemoval: true)]
    private Collection $absensiList;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->absensiList = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJenisAbsensi(): ?string
    {
        return $this->jenisAbsensi;
    }

    public function setJenisAbsensi(string $jenisAbsensi): static
    {
        $this->jenisAbsensi = $jenisAbsensi;
        return $this;
    }

    public function getHariDiizinkan(): array
    {
        return $this->hariDiizinkan;
    }

    public function setHariDiizinkan(array $hariDiizinkan): static
    {
        $this->hariDiizinkan = $hariDiizinkan;
        return $this;
    }

    public function getJamMulai(): ?\DateTimeInterface
    {
        return $this->jamMulai;
    }

    public function setJamMulai(\DateTimeInterface $jamMulai): static
    {
        $this->jamMulai = $jamMulai;
        return $this;
    }

    public function getJamSelesai(): ?\DateTimeInterface
    {
        return $this->jamSelesai;
    }

    public function setJamSelesai(\DateTimeInterface $jamSelesai): static
    {
        $this->jamSelesai = $jamSelesai;
        return $this;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): static
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getTanggalKhusus(): ?\DateTimeInterface
    {
        return $this->tanggalKhusus;
    }

    public function setTanggalKhusus(?\DateTimeInterface $tanggalKhusus): static
    {
        $this->tanggalKhusus = $tanggalKhusus;
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

    public function getCreatedBy(): ?Admin
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Admin $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    // Utility methods
    // PERBAIKAN: Konsistensi pengecekan hari dengan support integer dan string
    public function isHariDiizinkan(int $hari): bool
    {
        // Pastikan kompatibilitas dengan format database (string) dan input (integer)
        // Database menyimpan format string: ["1","2"] 
        // Input dari controller format integer: 1, 2
        $stringCheck = in_array((string)$hari, $this->hariDiizinkan);
        $intCheck = in_array($hari, $this->hariDiizinkan);
        
        return $stringCheck || $intCheck;
    }

    public function isJamAbsensiTerbuka(?\DateTimeInterface $waktu = null): bool
    {
        // PERBAIKAN: Cek null pada jam mulai dan selesai
        if (!$this->jamMulai || !$this->jamSelesai) {
            error_log("ERROR: JamMulai atau JamSelesai is NULL for jadwal {$this->getId()}");
            return false; // Tidak bisa dibuka jika jam tidak valid
        }
        
        // PERBAIKAN: Gunakan timezone Asia/Makassar untuk konsistensi waktu Indonesia
        $timezone = new \DateTimeZone('Asia/Makassar');
        
        if (!$waktu) {
            $waktu = new \DateTime('now', $timezone);
        }

        // PERBAIKAN: Pastikan semua DateTime menggunakan timezone yang sama
        // Ambil waktu sekarang dengan timezone Asia/Makassar
        $jamSekarang = new \DateTime($waktu->format('H:i:s'), $timezone);
        
        // Ambil jam mulai dan selesai dari jadwal dengan timezone Asia/Makassar
        $jamMulai = new \DateTime($this->jamMulai->format('H:i:s'), $timezone);
        $jamSelesai = new \DateTime($this->jamSelesai->format('H:i:s'), $timezone);
        
        // PERBAIKAN: Logika khusus untuk jam absensi yang melintasi tengah malam
        // Contoh: 22:00 - 05:00 (malam sampai pagi hari berikutnya)
        $melintasiTengahMalam = $jamSelesai < $jamMulai;
        
        if ($melintasiTengahMalam) {
            // Jika jam melintasi tengah malam, ada 2 kondisi valid:
            // 1. Waktu sekarang >= jam mulai (contoh: 22:00-23:59)
            // 2. Waktu sekarang <= jam selesai (contoh: 00:00-05:00)
            $result = ($jamSekarang >= $jamMulai) || ($jamSekarang <= $jamSelesai);
            
            error_log("DEBUG isJamAbsensiTerbuka (MELINTASI MALAM) - Jadwal: {$this->getNamaJenisAbsensi()}: " . 
                      "Sekarang=" . $jamSekarang->format('H:i:s') . 
                      ", Mulai=" . $jamMulai->format('H:i:s') . 
                      ", Selesai=" . $jamSelesai->format('H:i:s') . 
                      ", Kondisi1(>=mulai)=" . ($jamSekarang >= $jamMulai ? 'YES' : 'NO') . 
                      ", Kondisi2(<=selesai)=" . ($jamSekarang <= $jamSelesai ? 'YES' : 'NO') .
                      ", Result=" . ($result ? 'TERBUKA' : 'TUTUP'));
        } else {
            // Jam normal dalam satu hari (contoh: 08:00 - 17:00)
            $result = ($jamSekarang >= $jamMulai) && ($jamSekarang <= $jamSelesai);
            
            error_log("DEBUG isJamAbsensiTerbuka (NORMAL) - Jadwal: {$this->getNamaJenisAbsensi()}: " . 
                      "Sekarang=" . $jamSekarang->format('H:i:s') . 
                      ", Mulai=" . $jamMulai->format('H:i:s') . 
                      ", Selesai=" . $jamSelesai->format('H:i:s') . 
                      ", Result=" . ($result ? 'TERBUKA' : 'TUTUP'));
        }
        
        return $result;
    }

    public function requiresQrCode(): bool
    {
        // Debug logging
        error_log("DEBUG requiresQrCode() - Jadwal: " . $this->getNamaJenisAbsensi() . 
                  ", jenisAbsensi: " . $this->jenisAbsensi . 
                  ", namaCustom: " . ($this->namaCustom ?? 'null') . 
                  ", qrCode: " . ($this->qrCode ?? 'null'));
        
        // Jika custom dan ada QR code, return true
        if ($this->namaCustom && $this->qrCode) {
            error_log("DEBUG requiresQrCode() - Custom dengan QR: TRUE");
            return true;
        }
        
        // Daftar jenis absensi yang memerlukan QR code
        $qrRequiredTypes = [
            'apel_pagi', 
            'upacara_nasional',
            'sholat_malam',      // Sholat Malam perlu QR
            'sholat_tahajud',    // Sholat Tahajud perlu QR  
            'dzikir_malam'       // Dzikir Malam perlu QR
        ];
        
        $result = in_array($this->jenisAbsensi, $qrRequiredTypes);
        error_log("DEBUG requiresQrCode() - Preset check for '" . $this->jenisAbsensi . "': " . ($result ? 'TRUE' : 'FALSE'));
        
        return $result;
    }

    public function getNamaJenisAbsensi(): string
    {
        // Jika ada nama custom, gunakan itu
        if ($this->namaCustom) {
            return $this->namaCustom;
        }
        
        return match($this->jenisAbsensi) {
            'ibadah_pagi' => 'Ibadah Pagi',
            'bbaq' => 'BBAQ (Baca Buku Al-Qur\'an)',
            'apel_pagi' => 'Apel Pagi',
            'upacara_nasional' => 'Upacara Nasional',
            default => 'Absensi'
        };
    }

    public function getEmojiJenisAbsensi(): string
    {
        // Jika ada emoji custom, gunakan itu
        if ($this->emojiCustom) {
            return $this->emojiCustom;
        }
        
        return match($this->jenisAbsensi) {
            'ibadah_pagi' => '🤲',
            'bbaq' => '📖',
            'apel_pagi' => '🏢',
            'upacara_nasional' => '🇮🇩',
            default => '✅'
        };
    }

    public function getHariDiizinkanText(): string
    {
        $namaHari = [
            '1' => 'Senin',
            '2' => 'Selasa', 
            '3' => 'Rabu',
            '4' => 'Kamis',
            '5' => 'Jumat',
            '6' => 'Sabtu',
            '7' => 'Minggu'
        ];

        $hariText = [];
        foreach ($this->hariDiizinkan as $hari) {
            $hariText[] = $namaHari[$hari] ?? $hari;
        }

        return implode(', ', $hariText);
    }

    public function getNamaCustom(): ?string
    {
        return $this->namaCustom;
    }

    public function setNamaCustom(?string $namaCustom): static
    {
        $this->namaCustom = $namaCustom;
        return $this;
    }

    public function getEmojiCustom(): ?string
    {
        return $this->emojiCustom;
    }

    public function setEmojiCustom(?string $emojiCustom): static
    {
        $this->emojiCustom = $emojiCustom;
        return $this;
    }

    /**
     * @return Collection<int, Absensi>
     */
    public function getAbsensiList(): Collection
    {
        return $this->absensiList;
    }

    public function addAbsensi(Absensi $absensi): static
    {
        if (!$this->absensiList->contains($absensi)) {
            $this->absensiList->add($absensi);
            $absensi->setJadwalAbsensi($this);
        }

        return $this;
    }

    public function removeAbsensi(Absensi $absensi): static
    {
        if ($this->absensiList->removeElement($absensi)) {
            // set the owning side to null (unless already changed)
            if ($absensi->getJadwalAbsensi() === $this) {
                $absensi->setJadwalAbsensi(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getNamaJenisAbsensi();
    }
}