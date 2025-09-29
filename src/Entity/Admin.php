<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(name: 'admin')]
class Admin implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Username untuk login admin
    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    // Nama lengkap admin
    #[ORM\Column(length: 100)]
    private ?string $namaLengkap = null;

    // Email admin (optional, tapi unique jika diisi)
    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $email = null;

    // Password untuk login
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    // Role admin (super_admin: akses penuh semua unit kerja | admin: terbatas unit kerja sendiri)
    #[ORM\Column(length: 20)]
    private ?string $role = 'admin';

    // Status admin (aktif/nonaktif)
    #[ORM\Column(length: 20)]
    private ?string $status = 'aktif';

    // Hak akses yang dimiliki (JSON format)
    // Contoh: ["kelola_pegawai", "kelola_jadwal", "validasi_absensi", "laporan"]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $permissions = [];

    // Nomor telepon admin
    #[ORM\Column(length: 15, nullable: true)]
    private ?string $nomorTelepon = null;

    // NIP admin (untuk pegawai yang jadi admin)
    #[ORM\Column(length: 18, nullable: true, unique: true)]
    private ?string $nip = null;

    // Relasi ke entity UnitKerja
    #[ORM\ManyToOne(targetEntity: UnitKerja::class)]
    #[ORM\JoinColumn(name: 'unit_kerja_id', referencedColumnName: 'id', nullable: true)]
    private ?UnitKerja $unitKerjaEntity = null;

    // Relasi ke entity KepalaBidang
    #[ORM\ManyToOne(targetEntity: KepalaBidang::class)]
    #[ORM\JoinColumn(name: 'kepala_bidang_id', referencedColumnName: 'id', nullable: true)]
    private ?KepalaBidang $kepalaBidang = null;

    // Relasi ke entity KepalaKantor
    #[ORM\ManyToOne(targetEntity: KepalaKantor::class)]
    #[ORM\JoinColumn(name: 'kepala_kantor_id', referencedColumnName: 'id', nullable: true)]
    private ?KepalaKantor $kepalaKantor = null;

    // Tanggal login terakhir
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    // IP Address login terakhir
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    // Tanggal dibuat record ini
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    // Tanggal terakhir diubah
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Admin yang membuat akun ini
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $createdBy = null;

    // Relasi ke tabel absensi yang divalidasi oleh admin ini
    #[ORM\OneToMany(targetEntity: Absensi::class, mappedBy: 'validatedBy')]
    private Collection $validatedAbsensi;

    public function __construct()
    {
        $this->validatedAbsensi = new ArrayCollection();
        $this->createdAt = new \DateTime();
        // Default permission untuk Admin biasa
        $this->permissions = [
            'kelola_pegawai_unit',      // Kelola pegawai dalam unit kerjanya
            'kelola_jadwal_unit',       // Kelola jadwal absensi unit kerjanya
            'validasi_absensi_unit',    // Validasi absensi pegawai unit kerjanya
            'laporan_unit',             // Akses laporan unit kerjanya
            'kelola_event_unit'         // Kelola event unit kerjanya
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getNamaLengkap(): ?string
    {
        return $this->namaLengkap;
    }

    public function setNamaLengkap(string $namaLengkap): static
    {
        $this->namaLengkap = $namaLengkap;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
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

    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    public function setPermissions(?array $permissions): static
    {
        $this->permissions = $permissions;
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

    public function getCreatedBy(): ?self
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?self $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, Absensi>
     */
    public function getValidatedAbsensi(): Collection
    {
        return $this->validatedAbsensi;
    }

    public function addValidatedAbsensi(Absensi $validatedAbsensi): static
    {
        if (!$this->validatedAbsensi->contains($validatedAbsensi)) {
            $this->validatedAbsensi->add($validatedAbsensi);
            $validatedAbsensi->setValidatedBy($this);
        }

        return $this;
    }

    public function removeValidatedAbsensi(Absensi $validatedAbsensi): static
    {
        if ($this->validatedAbsensi->removeElement($validatedAbsensi)) {
            // set the owning side to null (unless already changed)
            if ($validatedAbsensi->getValidatedBy() === $this) {
                $validatedAbsensi->setValidatedBy(null);
            }
        }

        return $this;
    }

    // Method untuk mengecek apakah admin memiliki permission tertentu
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    // Method untuk menambah permission
    public function addPermission(string $permission): static
    {
        if (!$this->hasPermission($permission)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }

    // Method untuk menghapus permission
    public function removePermission(string $permission): static
    {
        $this->permissions = array_filter($this->permissions, fn($p) => $p !== $permission);
        return $this;
    }

    // Method untuk mengecek apakah admin adalah super admin
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    // Method untuk mengecek apakah admin adalah admin unit kerja
    public function isAdminUnit(): bool
    {
        return $this->role === 'admin' && $this->unitKerjaEntity !== null;
    }

    // Method untuk mendapatkan permission sesuai role
    public function getAllowedPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            // Super Admin memiliki akses penuh semua fitur
            return [
                'kelola_pegawai_semua',     // Kelola semua pegawai
                'kelola_jadwal_semua',      // Kelola semua jadwal absensi
                'validasi_absensi_semua',   // Validasi semua absensi
                'laporan_semua',            // Akses semua laporan
                'kelola_event_semua',       // Kelola semua event
                'kelola_user_admin',        // Kelola user admin
                'pengaturan_sistem',        // Pengaturan sistem
                'kelola_unit_kerja',        // Kelola unit kerja
                'kelola_struktur_organisasi' // Kelola struktur organisasi
            ];
        } elseif ($this->isAdminUnit()) {
            // Admin Unit - akses terbatas pada unit kerjanya
            return [
                'kelola_pegawai_unit',      // Kelola pegawai dalam unit kerjanya
                'kelola_jadwal_unit',       // Kelola jadwal absensi unit kerjanya
                'validasi_absensi_unit',    // Validasi absensi pegawai unit kerjanya
                'laporan_unit',             // Akses laporan unit kerjanya
                'kelola_event_unit',        // Kelola event unit kerjanya
                'lihat_profil_sendiri'      // Lihat/edit profil sendiri
            ];
        }

        return [];
    }

    // Method untuk mengecek apakah admin dapat mengakses unit kerja tertentu
    public function canAccessUnitKerja(?int $unitKerjaId): bool
    {
        // Super Admin dapat mengakses semua unit kerja
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin Unit hanya dapat mengakses unit kerjanya sendiri
        if ($this->isAdminUnit() && $this->unitKerjaEntity) {
            return $this->unitKerjaEntity->getId() === $unitKerjaId;
        }

        return false;
    }

    // Method untuk mengecek apakah admin dapat mengelola pegawai tertentu
    public function canManagePegawai($pegawai): bool
    {
        // Super Admin dapat mengelola semua pegawai
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin Unit hanya dapat mengelola pegawai dalam unit kerjanya
        if ($this->isAdminUnit() && $this->unitKerjaEntity) {
            // Cek apakah pegawai dalam unit kerja yang sama
            if (method_exists($pegawai, 'getUnitKerjaEntity')) {
                $pegawaiUnitKerja = $pegawai->getUnitKerjaEntity();
                return $pegawaiUnitKerja && $pegawaiUnitKerja->getId() === $this->unitKerjaEntity->getId();
            }
        }

        return false;
    }

    // Method untuk mengecek apakah admin aktif
    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    // Method untuk update informasi login terakhir
    public function updateLastLogin(?string $ipAddress = null): static
    {
        $this->lastLoginAt = new \DateTime();
        $this->lastLoginIp = $ipAddress;
        return $this;
    }

    // Getter dan Setter untuk field baru
    public function getNip(): ?string
    {
        return $this->nip;
    }

    public function setNip(?string $nip): static
    {
        $this->nip = $nip;
        return $this;
    }

    public function getUnitKerjaEntity(): ?UnitKerja
    {
        return $this->unitKerjaEntity;
    }

    public function setUnitKerjaEntity(?UnitKerja $unitKerjaEntity): static
    {
        $this->unitKerjaEntity = $unitKerjaEntity;
        return $this;
    }

    public function getKepalaBidang(): ?KepalaBidang
    {
        return $this->kepalaBidang;
    }

    public function setKepalaBidang(?KepalaBidang $kepalaBidang): static
    {
        $this->kepalaBidang = $kepalaBidang;
        return $this;
    }

    public function getKepalaKantor(): ?KepalaKantor
    {
        return $this->kepalaKantor;
    }

    public function setKepalaKantor(?KepalaKantor $kepalaKantor): static
    {
        $this->kepalaKantor = $kepalaKantor;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER']; // Base role
        
        if ($this->role === 'super_admin') {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }
        $roles[] = 'ROLE_ADMIN';
        
        return array_unique($roles);
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function eraseCredentials(): void
    {
        // Jika ada plaintext password, hapus di sini
        // $this->plainPassword = null;
    }

    public function __toString(): string
    {
        return $this->namaLengkap ?? $this->username ?? 'Admin Tanpa Nama';
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
}