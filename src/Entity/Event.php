<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Judul event (sesuai requirement)
    #[ORM\Column(length: 150)]
    private ?string $judulEvent = null;

    // Tanggal mulai event (datetime)
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $tanggalMulai = null;

    // Tanggal selesai event (datetime, nullable)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tanggalSelesai = null;

    // Lokasi event
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $lokasi = null;

    // Deskripsi lengkap event
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deskripsi = null;

    // Status event: aktif, selesai, dibatalkan (sesuai requirement)
    #[ORM\Column(length: 20)]
    private ?string $status = 'aktif';

    // Kategori event: kegiatan_kantor, kegiatan_pusat, kegiatan_internal, kegiatan_external
    #[ORM\Column(length: 50)]
    private ?string $kategoriEvent = 'kegiatan_kantor';

    // Warna untuk tampilan kalender (hex color)
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $warna = '#87CEEB';

    // Admin yang membuat event
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Field untuk absensi event
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $butuhAbsensi = false;

    // Jam mulai absensi (untuk validasi waktu absensi)
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $jamMulaiAbsensi = null;

    // Jam selesai absensi (untuk validasi waktu absensi)
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $jamSelesaiAbsensi = null;

    // Link meeting untuk event online (Zoom, Google Meet, Teams, dll)
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $linkMeeting = null;

    // Target audience untuk notifikasi: 'all' atau 'custom'
    #[ORM\Column(length: 10, options: ['default' => 'all'])]
    private ?string $targetAudience = 'all';

    // Daftar unit kerja target (JSON array)
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $targetUnits = null;

    // Relasi ke event absensi
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventAbsensi::class)]
    private Collection $eventAbsensis;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->eventAbsensis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJudulEvent(): ?string
    {
        return $this->judulEvent;
    }

    public function setJudulEvent(string $judulEvent): static
    {
        $this->judulEvent = $judulEvent;
        return $this;
    }

    public function getTanggalMulai(): ?\DateTimeInterface
    {
        return $this->tanggalMulai;
    }

    public function setTanggalMulai(\DateTimeInterface $tanggalMulai): static
    {
        $this->tanggalMulai = $tanggalMulai;
        return $this;
    }

    public function getTanggalSelesai(): ?\DateTimeInterface
    {
        return $this->tanggalSelesai;
    }

    public function setTanggalSelesai(?\DateTimeInterface $tanggalSelesai): static
    {
        $this->tanggalSelesai = $tanggalSelesai;
        return $this;
    }

    public function getLokasi(): ?string
    {
        return $this->lokasi;
    }

    public function setLokasi(?string $lokasi): static
    {
        $this->lokasi = $lokasi;
        return $this;
    }

    public function getDeskripsi(): ?string
    {
        return $this->deskripsi;
    }

    public function setDeskripsi(?string $deskripsi): static
    {
        $this->deskripsi = $deskripsi;
        return $this;
    }

    public function getKategoriEvent(): ?string
    {
        return $this->kategoriEvent;
    }

    public function setKategoriEvent(string $kategoriEvent): static
    {
        $this->kategoriEvent = $kategoriEvent;
        return $this;
    }

    public function getWarna(): ?string
    {
        return $this->warna;
    }

    public function setWarna(?string $warna): static
    {
        $this->warna = $warna;
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

    public function getCreatedBy(): ?Admin
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Admin $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    // Utility methods dengan kategori yang disesuaikan
    public function getKategoriIcon(): string
    {
        return match($this->kategoriEvent) {
            'kegiatan_kantor' => '🏢',
            'kegiatan_pusat' => '🏛️',
            'kegiatan_internal' => '🔒',
            'kegiatan_external' => '🌐',
            // Legacy support
            'hari_besar' => '🇮🇩',
            'hari_khusus' => '📅',
            default => '📆'
        };
    }

    public function getKategoriNama(): string
    {
        return match($this->kategoriEvent) {
            'kegiatan_kantor' => 'Kegiatan Kantor',
            'kegiatan_pusat' => 'Kegiatan Pusat', 
            'kegiatan_internal' => 'Kegiatan Internal',
            'kegiatan_external' => 'Kegiatan External',
            // Legacy support
            'hari_besar' => 'Hari Besar',
            'hari_khusus' => 'Hari Khusus',
            default => 'Event'
        };
    }

    // Method untuk badge warna kategori sesuai requirement
    public function getKategoriBadgeClass(): string
    {
        return match($this->kategoriEvent) {
            'kegiatan_kantor' => 'bg-blue-100 text-blue-800',
            'kegiatan_pusat' => 'bg-green-100 text-green-800',
            'kegiatan_internal' => 'bg-purple-100 text-purple-800',
            'kegiatan_external' => 'bg-orange-100 text-orange-800',
            // Legacy support
            'hari_besar' => 'bg-red-100 text-red-800',
            'hari_khusus' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Method untuk emoji badge kategori
    public function getKategoriBadgeEmoji(): string
    {
        return match($this->kategoriEvent) {
            'kegiatan_kantor' => '🔵',
            'kegiatan_pusat' => '🟢',
            'kegiatan_internal' => '🟣',
            'kegiatan_external' => '🟠',
            // Legacy support  
            'hari_besar' => '🔴',
            'hari_khusus' => '🟡',
            default => '⚪'
        };
    }

    // Utility methods
    public function getStatusBadge(): string
    {
        return match($this->status) {
            'aktif' => '<span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded-full">Aktif</span>',
            'selesai' => '<span class="bg-gray-100 text-gray-800 text-xs font-medium px-2 py-1 rounded-full">Selesai</span>',
            'dibatalkan' => '<span class="bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded-full">Dibatalkan</span>',
            default => '<span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">Unknown</span>'
        };
    }

    public function getStatusIcon(): string
    {
        return match($this->status) {
            'aktif' => '✅',
            'selesai' => '✔️',
            'dibatalkan' => '❌',
            default => '📅'
        };
    }

    // Backward compatibility methods
    public function getNamaEvent(): ?string
    {
        return $this->judulEvent;
    }

    public function getTanggal(): ?\DateTimeInterface
    {
        return $this->tanggalMulai;
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    public function getButuhAbsensi(): ?bool
    {
        return $this->butuhAbsensi;
    }

    public function setButuhAbsensi(bool $butuhAbsensi): static
    {
        $this->butuhAbsensi = $butuhAbsensi;
        return $this;
    }

    public function isButuhAbsensi(): bool
    {
        return $this->butuhAbsensi === true;
    }

    public function getLinkMeeting(): ?string
    {
        return $this->linkMeeting;
    }

    public function setLinkMeeting(?string $linkMeeting): static
    {
        $this->linkMeeting = $linkMeeting;
        return $this;
    }

    public function hasLinkMeeting(): bool
    {
        return !empty($this->linkMeeting);
    }

    /**
     * Cek apakah meeting sudah berlalu (setelah tanggal selesai atau tanggal mulai jika tidak ada tanggal selesai)
     */
    public function isMeetingExpired(): bool
    {
        $now = new \DateTime();
        
        // Jika ada tanggal selesai, gunakan tanggal selesai
        if ($this->tanggalSelesai) {
            return $now > $this->tanggalSelesai;
        }
        
        // Jika tidak ada tanggal selesai, gunakan tanggal mulai
        if ($this->tanggalMulai) {
            return $now > $this->tanggalMulai;
        }
        
        // Jika tidak ada tanggal sama sekali, anggap sudah kadaluarsa
        return true;
    }

    /**
     * Cek apakah meeting bisa diakses (belum berlalu dan memiliki link meeting)
     */
    public function canJoinMeeting(): bool
    {
        return $this->hasLinkMeeting() && !$this->isMeetingExpired();
    }

    /**
     * @return Collection<int, EventAbsensi>
     */
    public function getEventAbsensis(): Collection
    {
        return $this->eventAbsensis;
    }

    public function addEventAbsensi(EventAbsensi $eventAbsensi): static
    {
        if (!$this->eventAbsensis->contains($eventAbsensi)) {
            $this->eventAbsensis->add($eventAbsensi);
            $eventAbsensi->setEvent($this);
        }

        return $this;
    }

    public function removeEventAbsensi(EventAbsensi $eventAbsensi): static
    {
        if ($this->eventAbsensis->removeElement($eventAbsensi)) {
            // set the owning side to null (unless already changed)
            if ($eventAbsensi->getEvent() === $this) {
                $eventAbsensi->setEvent(null);
            }
        }

        return $this;
    }

    public function getUserAbsensi(Pegawai $user): ?EventAbsensi
    {
        foreach ($this->eventAbsensis as $absensi) {
            if ($absensi->getUser() === $user) {
                return $absensi;
            }
        }
        return null;
    }

    public function hasUserAttended(Pegawai $user): bool
    {
        return $this->getUserAbsensi($user) !== null;
    }

    public function getTargetAudience(): ?string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(string $targetAudience): static
    {
        $this->targetAudience = $targetAudience;
        return $this;
    }

    public function getTargetUnits(): ?array
    {
        return $this->targetUnits;
    }

    public function setTargetUnits(?array $targetUnits): static
    {
        $this->targetUnits = $targetUnits;
        return $this;
    }

    public function isTargetAll(): bool
    {
        return $this->targetAudience === 'all';
    }

    public function isTargetCustom(): bool
    {
        return $this->targetAudience === 'custom';
    }

    public function isUserTargeted(Pegawai $user): bool
    {
        if ($this->targetAudience === 'all') {
            return true;
        }

        if ($this->targetAudience === 'custom' && $this->targetUnits) {
            $userUnitId = $user->getUnitKerjaEntity()?->getId();
            return $userUnitId && in_array($userUnitId, $this->targetUnits);
        }

        return false;
    }

    public function getJamMulaiAbsensi(): ?\DateTimeInterface
    {
        return $this->jamMulaiAbsensi;
    }

    public function setJamMulaiAbsensi(?\DateTimeInterface $jamMulaiAbsensi): static
    {
        $this->jamMulaiAbsensi = $jamMulaiAbsensi;
        return $this;
    }

    public function getJamSelesaiAbsensi(): ?\DateTimeInterface
    {
        return $this->jamSelesaiAbsensi;
    }

    public function setJamSelesaiAbsensi(?\DateTimeInterface $jamSelesaiAbsensi): static
    {
        $this->jamSelesaiAbsensi = $jamSelesaiAbsensi;
        return $this;
    }

    /**
     * Validasi apakah waktu sekarang dalam rentang jam absensi
     * 
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateAbsensiTime(?\DateTimeInterface $currentTime = null): array
    {
        if (!$this->isButuhAbsensi()) {
            return ['valid' => true, 'message' => ''];
        }

        if (!$this->jamMulaiAbsensi || !$this->jamSelesaiAbsensi) {
            return ['valid' => false, 'message' => 'Jam absensi belum ditentukan untuk event ini.'];
        }

        // Ensure we use Makassar timezone for consistency (WITA - UTC+8)
        // Sesuai dengan lokasi Kanwil Kemenag Sulawesi Barat
        $timezone = new \DateTimeZone('Asia/Makassar');
        $now = $currentTime ?? new \DateTime('now', $timezone);

        // PERBAIKAN: Cek tanggal event terlebih dahulu sebelum cek jam
        // Jika event sudah kadaluarsa (tanggal sudah lewat), nonaktifkan absensi
        if ($this->isEventExpired($now)) {
            $expiredDate = $this->getEventEndDate();
            $expiredDateStr = $expiredDate ? $expiredDate->format('d/m/Y') : $this->tanggalMulai->format('d/m/Y');

            error_log("Event ID {$this->getId()} - EVENT SUDAH KADALUARSA: Event ended on {$expiredDateStr}");
            return [
                'valid' => false,
                'message' => 'Event sudah berakhir pada tanggal ' . $expiredDateStr . '. Absensi tidak lagi tersedia.'
            ];
        }

        // PERBAIKAN: Cek apakah hari ini adalah hari event
        // Absensi hanya bisa dilakukan pada hari event berlangsung
        if (!$this->isEventToday($now)) {
            $eventDateStr = $this->tanggalMulai->format('d/m/Y');

            error_log("Event ID {$this->getId()} - BUKAN HARI EVENT: Event date is {$eventDateStr}, today is {$now->format('d/m/Y')}");
            return [
                'valid' => false,
                'message' => 'Absensi hanya dapat dilakukan pada hari event (' . $eventDateStr . ').'
            ];
        }

        // Get current time in H:i format for comparison
        $currentTimeStr = $now->format('H:i');
        $jamMulaiStr = $this->jamMulaiAbsensi->format('H:i');
        $jamSelesaiStr = $this->jamSelesaiAbsensi->format('H:i');

        // Debug logging
        error_log("Event ID {$this->getId()} - Time validation: Current={$currentTimeStr}, Start={$jamMulaiStr}, End={$jamSelesaiStr}");

        // Convert to comparable format (minutes since midnight)
        $currentMinutes = $this->timeToMinutes($currentTimeStr);
        $jamMulaiMinutes = $this->timeToMinutes($jamMulaiStr);
        $jamSelesaiMinutes = $this->timeToMinutes($jamSelesaiStr);

        if ($currentMinutes < $jamMulaiMinutes) {
            error_log("Event ID {$this->getId()} - Absensi BELUM DIBUKA: {$currentMinutes} < {$jamMulaiMinutes}");
            return [
                'valid' => false,
                'message' => 'Absensi belum dibuka. Silakan lakukan absensi mulai pukul ' . $jamMulaiStr . ' WITA.'
            ];
        }

        if ($currentMinutes > $jamSelesaiMinutes) {
            error_log("Event ID {$this->getId()} - Absensi SUDAH DITUTUP: {$currentMinutes} > {$jamSelesaiMinutes}");
            return [
                'valid' => false,
                'message' => 'Absensi sudah ditutup pada pukul ' . $jamSelesaiStr . ' WITA.'
            ];
        }

        error_log("Event ID {$this->getId()} - Absensi TERBUKA: {$currentMinutes} between {$jamMulaiMinutes} and {$jamSelesaiMinutes}");
        return ['valid' => true, 'message' => ''];
    }

    /**
     * Convert time string (H:i) to minutes since midnight
     */
    private function timeToMinutes(string $timeStr): int
    {
        $parts = explode(':', $timeStr);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }

    /**
     * Helper method untuk mengecek apakah absensi terbuka
     */
    public function isAbsensiTerbuka(?\DateTimeInterface $currentTime = null): bool
    {
        $validation = $this->validateAbsensiTime($currentTime);
        return $validation['valid'];
    }

    /**
     * Helper method untuk mendapatkan status absensi dalam bentuk teks
     */
    public function getStatusAbsensi(?\DateTimeInterface $currentTime = null): string
    {
        if (!$this->isButuhAbsensi()) {
            return 'Tidak memerlukan absensi';
        }

        if (!$this->jamMulaiAbsensi || !$this->jamSelesaiAbsensi) {
            return 'Jam absensi belum ditentukan';
        }

        $now = $currentTime ?? new \DateTime();
        $currentTimeOnly = \DateTime::createFromFormat('H:i:s', $now->format('H:i:s'));
        $jamMulai = \DateTime::createFromFormat('H:i:s', $this->jamMulaiAbsensi->format('H:i:s'));
        $jamSelesai = \DateTime::createFromFormat('H:i:s', $this->jamSelesaiAbsensi->format('H:i:s'));

        if ($currentTimeOnly < $jamMulai) {
            return 'Akan dibuka pukul ' . $this->jamMulaiAbsensi->format('H:i');
        }

        if ($currentTimeOnly > $jamSelesai) {
            return 'Sudah ditutup (pukul ' . $this->jamSelesaiAbsensi->format('H:i') . ')';
        }

        return 'Sedang dibuka (tutup pukul ' . $this->jamSelesaiAbsensi->format('H:i') . ')';
    }

    /**
     * PERBAIKAN: Cek apakah event sudah kadaluarsa/berakhir
     */
    public function isEventExpired(?\DateTimeInterface $currentTime = null): bool
    {
        $timezone = new \DateTimeZone('Asia/Makassar');
        $now = $currentTime ?? new \DateTime('now', $timezone);

        // Jika ada tanggal selesai, gunakan tanggal selesai
        if ($this->tanggalSelesai) {
            // Event berakhir jika sekarang sudah lewat dari tanggal selesai
            return $now->format('Y-m-d') > $this->tanggalSelesai->format('Y-m-d');
        }

        // Jika tidak ada tanggal selesai, gunakan tanggal mulai
        if ($this->tanggalMulai) {
            // Event berakhir jika sekarang sudah lewat dari tanggal mulai
            return $now->format('Y-m-d') > $this->tanggalMulai->format('Y-m-d');
        }

        // Jika tidak ada tanggal sama sekali, anggap sudah kadaluarsa
        return true;
    }

    /**
     * PERBAIKAN: Cek apakah hari ini adalah hari event
     */
    public function isEventToday(?\DateTimeInterface $currentTime = null): bool
    {
        $timezone = new \DateTimeZone('Asia/Makassar');
        $now = $currentTime ?? new \DateTime('now', $timezone);

        if (!$this->tanggalMulai) {
            return false;
        }

        $todayStr = $now->format('Y-m-d');
        $eventStartStr = $this->tanggalMulai->format('Y-m-d');

        // Jika event multi-hari, cek apakah hari ini berada dalam rentang event
        if ($this->tanggalSelesai) {
            $eventEndStr = $this->tanggalSelesai->format('Y-m-d');
            return $todayStr >= $eventStartStr && $todayStr <= $eventEndStr;
        }

        // Jika event satu hari, cek apakah hari ini sama dengan hari event
        return $todayStr === $eventStartStr;
    }

    /**
     * PERBAIKAN: Dapatkan tanggal akhir event untuk pesan error
     */
    public function getEventEndDate(): ?\DateTimeInterface
    {
        return $this->tanggalSelesai ?? $this->tanggalMulai;
    }

    public function __toString(): string
    {
        return $this->judulEvent ?? 'Event';
    }
}