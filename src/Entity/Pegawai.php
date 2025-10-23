<?php

namespace App\Entity;

use App\Repository\PegawaiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: PegawaiRepository::class)]
#[ORM\Table(name: 'pegawai')]
class Pegawai implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // NIP pegawai ASN (wajib dan unik)
    #[ORM\Column(length: 18, unique: true)]
    private ?string $nip = null;

    // Nama lengkap pegawai
    #[ORM\Column(length: 100)]
    private ?string $nama = null;

    // Email untuk komunikasi
    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $email = null;

    // Foto profil pegawai
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    // Password untuk login aplikasi
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    // Jabatan pegawai
    #[ORM\Column(length: 100)]
    private ?string $jabatan = null;

    // Unit kerja/departemen (legacy text field)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $unitKerja = null;

    // Relasi ke entity UnitKerja (new relation)
    #[ORM\ManyToOne(targetEntity: UnitKerja::class, inversedBy: 'pegawai')]
    #[ORM\JoinColumn(name: 'unit_kerja_id', referencedColumnName: 'id', nullable: true)]
    private ?UnitKerja $unitKerjaEntity = null;

    // Status kepegawaian (aktif/nonaktif/cuti)
    #[ORM\Column(length: 20)]
    private ?string $statusKepegawaian = 'aktif';

    // Tanggal mulai bekerja
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $tanggalMulaiKerja = null;

    // Nomor telepon untuk komunikasi darurat
    #[ORM\Column(length: 15, nullable: true)]
    private ?string $nomorTelepon = null;

    // Tanggal dibuat record ini
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    // Tanggal terakhir diubah
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Roles untuk sistem security
    #[ORM\Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    // Relasi ke tabel absensi (satu pegawai bisa punya banyak record absensi)
    #[ORM\OneToMany(targetEntity: Absensi::class, mappedBy: 'pegawai')]
    private Collection $absensi;

    // Field tanda tangan statis untuk absensi (menyimpan path file signature)
    // Format: signatures/pegawai_{nip}.png atau .svg, ukuran < 100KB
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tandaTangan = null;

    // Tanggal upload tanda tangan terakhir (untuk tracking)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tandaTanganUploadedAt = null;

    // Tanggal login terakhir (untuk tracking aktivitas pegawai)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    // IP Address login terakhir (untuk keamanan)
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    // ===== XP PROGRESSION SYSTEM FIELDS =====

    // Total XP yang dikumpulkan sepanjang waktu
    #[ORM\Column(type: Types::INTEGER)]
    private int $total_xp = 0;

    // Level saat ini (1-5)
    #[ORM\Column(type: Types::INTEGER)]
    private int $current_level = 1;

    // Badge saat ini berdasarkan level
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $current_badge = '🌱';

    public function __construct()
    {
        $this->absensi = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNip(): ?string
    {
        return $this->nip;
    }

    public function setNip(string $nip): static
    {
        $this->nip = $nip;
        return $this;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
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

    public function getUnitKerja(): ?string
    {
        return $this->unitKerja;
    }

    public function setUnitKerja(string $unitKerja): static
    {
        $this->unitKerja = $unitKerja;
        return $this;
    }

    public function getStatusKepegawaian(): ?string
    {
        return $this->statusKepegawaian;
    }

    public function setStatusKepegawaian(string $statusKepegawaian): static
    {
        $this->statusKepegawaian = $statusKepegawaian;
        return $this;
    }

    public function getTanggalMulaiKerja(): ?\DateTimeInterface
    {
        return $this->tanggalMulaiKerja;
    }

    public function setTanggalMulaiKerja(\DateTimeInterface $tanggalMulaiKerja): static
    {
        $this->tanggalMulaiKerja = $tanggalMulaiKerja;
        return $this;
    }

    public function getNomorTelepon(): ?string
    {
        return $this->nomorTelepon;
    }

    public function setNomorTelepon(?string $nomorTelepon): static
    {
        $this->nomorTelepon = $nomorTelepon;
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

    /**
     * @return Collection<int, Absensi>
     */
    public function getAbsensi(): Collection
    {
        return $this->absensi;
    }

    public function addAbsensi(Absensi $absensi): static
    {
        if (!$this->absensi->contains($absensi)) {
            $this->absensi->add($absensi);
            $absensi->setPegawai($this);
        }

        return $this;
    }

    public function removeAbsensi(Absensi $absensi): static
    {
        if ($this->absensi->removeElement($absensi)) {
            // set the owning side to null (unless already changed)
            if ($absensi->getPegawai() === $this) {
                $absensi->setPegawai(null);
            }
        }

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->nip;
    }

    public function eraseCredentials(): void
    {
        // Jika ada plaintext password, hapus di sini
        // $this->plainPassword = null;
    }

    // Method untuk menampilkan nama pegawai (berguna untuk debugging)
    public function __toString(): string
    {
        return $this->nama ?? 'Pegawai Tanpa Nama';
    }

    // Methods for new unitKerjaEntity relation
    public function getUnitKerjaEntity(): ?UnitKerja
    {
        return $this->unitKerjaEntity;
    }

    public function setUnitKerjaEntity(?UnitKerja $unitKerjaEntity): static
    {
        $this->unitKerjaEntity = $unitKerjaEntity;
        return $this;
    }

    // Helper method to get unit kerja name (from relation or legacy field)
    public function getNamaUnitKerja(): ?string
    {
        if ($this->unitKerjaEntity) {
            return $this->unitKerjaEntity->getNamaUnit();
        }
        return $this->unitKerja;
    }

    // Helper method to get kepala bidang from unit kerja
    public function getKepalaBidang(): ?KepalaBidang
    {
        return $this->unitKerjaEntity ? $this->unitKerjaEntity->getKepalaBidang() : null;
    }

    // Helper method to get nama kepala bidang
    public function getNamaKepalaBidang(): ?string
    {
        $kepalaBidang = $this->getKepalaBidang();
        return $kepalaBidang ? $kepalaBidang->getNama() : null;
    }

    // SIGNATURE MANAGEMENT METHODS - untuk field tanda tangan statis
    
    public function getTandaTangan(): ?string
    {
        return $this->tandaTangan;
    }

    public function setTandaTangan(?string $tandaTangan): static
    {
        $this->tandaTangan = $tandaTangan;
        return $this;
    }

    public function getTandaTanganUploadedAt(): ?\DateTimeInterface
    {
        return $this->tandaTanganUploadedAt;
    }

    public function setTandaTanganUploadedAt(?\DateTimeInterface $tandaTanganUploadedAt): static
    {
        $this->tandaTanganUploadedAt = $tandaTanganUploadedAt;
        return $this;
    }

    // Helper method: cek apakah pegawai sudah punya tanda tangan
    public function hasTandaTangan(): bool
    {
        return !empty($this->tandaTangan) && file_exists($this->getPublicTandaTanganPath());
    }

    // Helper method: dapatkan path lengkap tanda tangan untuk display
    public function getTandaTanganPath(): ?string
    {
        if (!$this->tandaTangan) {
            return null;
        }
        return '/uploads/signatures/' . $this->tandaTangan;
    }

    // Helper method: dapatkan path fisik file tanda tangan
    public function getPublicTandaTanganPath(): ?string
    {
        if (!$this->tandaTangan) {
            return null;
        }
        return __DIR__ . '/../../public/uploads/signatures/' . $this->tandaTangan;
    }

    // Helper method: generate nama file tanda tangan berdasarkan NIP
    public function generateTandaTanganFilename(string $extension = 'png'): string
    {
        return 'pegawai_' . $this->nip . '.' . $extension;
    }

    // GETTER DAN SETTER UNTUK TRACKING LOGIN
    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): static
    {
        $this->lastLoginIp = $lastLoginIp;
        return $this;
    }

    // Helper method: dapatkan waktu login terakhir dalam format yang mudah dibaca
    public function getLastLoginFormatted(): string
    {
        if (!$this->lastLoginAt) {
            return 'Belum pernah';
        }

        // Format: "Hari, dd Bulan yyyy - HH:mm WITA"
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $namaHari = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];

        $hari = $namaHari[$this->lastLoginAt->format('l')];
        $tanggal = $this->lastLoginAt->format('d');
        $bulan = $namaBulan[(int)$this->lastLoginAt->format('n')];
        $tahun = $this->lastLoginAt->format('Y');
        $waktu = $this->lastLoginAt->format('H:i');

        return "{$hari}, {$tanggal} {$bulan} {$tahun} - {$waktu} WITA";
    }

    // ===== XP PROGRESSION SYSTEM METHODS =====

    public function getTotalXp(): int
    {
        return $this->total_xp;
    }

    public function setTotalXp(int $total_xp): static
    {
        $this->total_xp = $total_xp;
        return $this;
    }

    public function getCurrentLevel(): int
    {
        return $this->current_level;
    }

    public function setCurrentLevel(int $current_level): static
    {
        $this->current_level = $current_level;
        return $this;
    }

    public function getCurrentBadge(): ?string
    {
        return $this->current_badge;
    }

    public function setCurrentBadge(?string $current_badge): static
    {
        $this->current_badge = $current_badge;
        return $this;
    }

    // Helper method: get XP progress for next level
    public function getXpProgress(): array
    {
        $levelRanges = [
            1 => ['min' => 0, 'max' => 200, 'title' => 'Pemula'],
            2 => ['min' => 201, 'max' => 400, 'title' => 'Bersemangat'],
            3 => ['min' => 401, 'max' => 700, 'title' => 'Berdedikasi'],
            4 => ['min' => 701, 'max' => 1100, 'title' => 'Ahli'],
            5 => ['min' => 1101, 'max' => 9999, 'title' => 'Master'],
        ];

        $currentLevelRange = $levelRanges[$this->current_level];
        $currentXpInLevel = $this->total_xp - $currentLevelRange['min'];
        $xpNeededForLevel = $currentLevelRange['max'] - $currentLevelRange['min'];
        $percentageProgress = ($currentXpInLevel / $xpNeededForLevel) * 100;

        return [
            'current_xp' => $this->total_xp,
            'current_level' => $this->current_level,
            'level_title' => $currentLevelRange['title'],
            'current_xp_in_level' => $currentXpInLevel,
            'xp_needed_for_next_level' => $xpNeededForLevel,
            'xp_to_next_level' => $currentLevelRange['max'] - $this->total_xp,
            'percentage_progress' => min(100, max(0, $percentageProgress)),
            'next_level' => $this->current_level < 5 ? $this->current_level + 1 : 5,
            'is_max_level' => $this->current_level >= 5
        ];
    }

    // Helper method: get level title
    public function getLevelTitle(): string
    {
        $titles = [
            1 => 'Pemula',
            2 => 'Bersemangat',
            3 => 'Berdedikasi',
            4 => 'Ahli',
            5 => 'Master',
        ];

        return $titles[$this->current_level] ?? 'Pemula';
    }
}