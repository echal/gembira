<?php

namespace App\Entity;

use App\Repository\KonfigurasiJadwalAbsensiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity untuk Konfigurasi Jadwal Absensi yang Fleksibel
 * 
 * Sistem absensi baru yang sepenuhnya dikonfigurasi oleh admin
 * tanpa ada logika hardcoded untuk jenis absensi tertentu.
 * 
 * @author Indonesian Developer
 */
#[ORM\Entity(repositoryClass: KonfigurasiJadwalAbsensiRepository::class)]
#[ORM\Table(name: 'konfigurasi_jadwal_absensi')]
class KonfigurasiJadwalAbsensi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nama jadwal absensi (ditentukan oleh admin)
     * Contoh: "Apel Pagi", "Rapat Mingguan", "Kegiatan Ekoteologi"
     */
    #[ORM\Column(length: 100)]
    private ?string $namaJadwal = null;

    /**
     * Deskripsi jadwal absensi (opsional)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deskripsi = null;

    /**
     * Hari mulai (1-7, dimana 1=Senin, 7=Minggu)
     * Admin dapat memilih hari mulai jadwal absensi
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $hariMulai = null;

    /**
     * Hari selesai (1-7, dimana 1=Senin, 7=Minggu)
     * Admin dapat memilih hari selesai jadwal absensi
     * Jika sama dengan hariMulai, berarti hanya satu hari
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $hariSelesai = null;

    /**
     * Jam mulai absensi
     * Ditentukan oleh admin sesuai kebutuhan
     */
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $jamMulai = null;

    /**
     * Jam selesai absensi
     * Ditentukan oleh admin sesuai kebutuhan
     */
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $jamSelesai = null;

    /**
     * Apakah jadwal ini membutuhkan QR Code?
     * true = Perlu scan QR code dan kamera
     * false = Cukup tombol hadir saja
     */
    #[ORM\Column]
    private ?bool $perluQrCode = false;

    /**
     * Apakah jadwal ini membutuhkan foto/kamera?
     * true = Perlu ambil foto saat absensi
     * false = Tidak perlu foto
     */
    #[ORM\Column]
    private ?bool $perluKamera = false;

    /**
     * Apakah jadwal ini memerlukan validasi admin?
     * true = Absensi perlu persetujuan admin sebelum dianggap sah
     * false = Absensi langsung sah tanpa validasi
     */
    #[ORM\Column]
    private ?bool $perluValidasiAdmin = false;

    /**
     * QR Code unik untuk jadwal ini (jika diperlukan)
     * Akan di-generate otomatis jika perluQrCode = true
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCode = null;

    /**
     * Emoji untuk jadwal (ditentukan admin)
     * Contoh: 🏢, 📖, 🤲, 🇮🇩
     */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $emoji = '✅';

    /**
     * Warna untuk tampilan kartu absensi (hex color)
     * Contoh: #3B82F6, #10B981, #8B5CF6
     */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $warnaKartu = '#3B82F6';

    /**
     * Status aktif/nonaktif jadwal
     * true = Jadwal aktif dan bisa digunakan pegawai
     * false = Jadwal nonaktif/diarsipkan
     */
    #[ORM\Column]
    private ?bool $isAktif = true;

    /**
     * Keterangan tambahan dari admin
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $keterangan = null;

    /**
     * Tanggal dibuat
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dibuat = null;

    /**
     * Tanggal terakhir diubah
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $diubah = null;

    /**
     * Admin yang membuat jadwal
     */
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Admin $dibuatOleh = null;

    /**
     * Relasi ke data absensi pegawai (sistem fleksibel baru)
     * PERBAIKAN: Tambah cascade remove dan orphanRemoval untuk hard delete
     */
    #[ORM\OneToMany(targetEntity: Absensi::class, mappedBy: 'konfigurasiJadwal', cascade: ['remove'], orphanRemoval: true)]
    private Collection $daftarAbsensi;

    public function __construct()
    {
        $this->dibuat = new \DateTime();
        $this->daftarAbsensi = new ArrayCollection();
    }

    // === GETTER DAN SETTER ===

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNamaJadwal(): ?string
    {
        return $this->namaJadwal;
    }

    public function setNamaJadwal(string $namaJadwal): static
    {
        $this->namaJadwal = $namaJadwal;
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

    public function getHariMulai(): ?int
    {
        return $this->hariMulai;
    }

    public function setHariMulai(int $hariMulai): static
    {
        $this->hariMulai = $hariMulai;
        return $this;
    }

    public function getHariSelesai(): ?int
    {
        return $this->hariSelesai;
    }

    public function setHariSelesai(int $hariSelesai): static
    {
        $this->hariSelesai = $hariSelesai;
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

    public function isPerluQrCode(): ?bool
    {
        return $this->perluQrCode;
    }

    public function setPerluQrCode(bool $perluQrCode): static
    {
        $this->perluQrCode = $perluQrCode;
        return $this;
    }

    public function isPerluKamera(): ?bool
    {
        return $this->perluKamera;
    }

    public function setPerluKamera(bool $perluKamera): static
    {
        $this->perluKamera = $perluKamera;
        return $this;
    }

    public function isPerluValidasiAdmin(): ?bool
    {
        return $this->perluValidasiAdmin;
    }

    public function setPerluValidasiAdmin(bool $perluValidasiAdmin): static
    {
        $this->perluValidasiAdmin = $perluValidasiAdmin;
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

    public function getEmoji(): ?string
    {
        return $this->emoji;
    }

    public function setEmoji(?string $emoji): static
    {
        $this->emoji = $emoji;
        return $this;
    }

    public function getWarnaKartu(): ?string
    {
        return $this->warnaKartu;
    }

    public function setWarnaKartu(?string $warnaKartu): static
    {
        $this->warnaKartu = $warnaKartu;
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

    public function getDibuat(): ?\DateTimeInterface
    {
        return $this->dibuat;
    }

    public function setDibuat(\DateTimeInterface $dibuat): static
    {
        $this->dibuat = $dibuat;
        return $this;
    }

    public function getDiubah(): ?\DateTimeInterface
    {
        return $this->diubah;
    }

    public function setDiubah(?\DateTimeInterface $diubah): static
    {
        $this->diubah = $diubah;
        return $this;
    }

    public function getDibuatOleh(): ?Admin
    {
        return $this->dibuatOleh;
    }

    public function setDibuatOleh(?Admin $dibuatOleh): static
    {
        $this->dibuatOleh = $dibuatOleh;
        return $this;
    }

    /**
     * @return Collection<int, Absensi>
     */
    public function getDaftarAbsensi(): Collection
    {
        return $this->daftarAbsensi;
    }

    public function addAbsensi(Absensi $absensi): static
    {
        if (!$this->daftarAbsensi->contains($absensi)) {
            $this->daftarAbsensi->add($absensi);
            $absensi->setKonfigurasiJadwal($this);
        }

        return $this;
    }

    public function removeAbsensi(Absensi $absensi): static
    {
        if ($this->daftarAbsensi->removeElement($absensi)) {
            if ($absensi->getKonfigurasiJadwal() === $this) {
                $absensi->setKonfigurasiJadwal(null);
            }
        }

        return $this;
    }

    // === METODE UTILITAS ===

    /**
     * Cek apakah hari tertentu termasuk dalam rentang jadwal
     * 
     * @param int $hari Hari dalam format ISO (1=Senin, 7=Minggu)
     * @return bool
     */
    public function isHariTersedia(int $hari): bool
    {
        // Jika hariMulai dan hariSelesai sama, berarti hanya satu hari
        if ($this->hariMulai === $this->hariSelesai) {
            return $hari === $this->hariMulai;
        }

        // Jika rentang hari tidak melintasi minggu (contoh: Senin-Jumat)
        if ($this->hariMulai <= $this->hariSelesai) {
            return $hari >= $this->hariMulai && $hari <= $this->hariSelesai;
        }

        // Jika rentang hari melintasi minggu (contoh: Sabtu-Senin)
        // Hari yang valid: Sabtu, Minggu, Senin
        return $hari >= $this->hariMulai || $hari <= $this->hariSelesai;
    }

    /**
     * Cek apakah jam absensi sedang terbuka
     * 
     * @param \DateTimeInterface|null $waktu Waktu yang dicek (default: sekarang)
     * @return bool
     */
    public function isJamTerbuka(?\DateTimeInterface $waktu = null): bool
    {
        // Validasi jam mulai dan selesai tidak null
        if (!$this->jamMulai || !$this->jamSelesai) {
            return false;
        }

        // Gunakan waktu sekarang jika tidak ada parameter
        $timezone = new \DateTimeZone('Asia/Makassar');
        if (!$waktu) {
            $waktu = new \DateTime('now', $timezone);
        }

        // Ambil jam sekarang, jam mulai, dan jam selesai
        $jamSekarang = new \DateTime($waktu->format('H:i:s'), $timezone);
        $jamMulaiJadwal = new \DateTime($this->jamMulai->format('H:i:s'), $timezone);
        $jamSelesaiJadwal = new \DateTime($this->jamSelesai->format('H:i:s'), $timezone);

        // Jika jam absensi melintasi tengah malam (contoh: 22:00 - 05:00)
        if ($jamSelesaiJadwal < $jamMulaiJadwal) {
            return ($jamSekarang >= $jamMulaiJadwal) || ($jamSekarang <= $jamSelesaiJadwal);
        }

        // Jam normal dalam satu hari (contoh: 08:00 - 17:00)
        return ($jamSekarang >= $jamMulaiJadwal) && ($jamSekarang <= $jamSelesaiJadwal);
    }

    /**
     * Cek apakah jadwal tersedia untuk absensi saat ini
     * (gabungan pengecekan hari dan jam)
     * 
     * @return bool
     */
    public function isTersediaSaatIni(): bool
    {
        // Cek apakah jadwal aktif
        if (!$this->isAktif) {
            return false;
        }

        // Ambil hari dan waktu saat ini
        $sekarang = new \DateTime('now', new \DateTimeZone('Asia/Makassar'));
        $hariIni = (int)$sekarang->format('N');

        // Cek hari dan jam
        return $this->isHariTersedia($hariIni) && $this->isJamTerbuka($sekarang);
    }

    /**
     * Dapatkan nama hari dalam bahasa Indonesia
     * 
     * @return string
     */
    public function getNamaHariTersedia(): string
    {
        $namaHari = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];

        if ($this->hariMulai === $this->hariSelesai) {
            return $namaHari[$this->hariMulai] ?? 'Tidak Valid';
        }

        $hariMulaiNama = $namaHari[$this->hariMulai] ?? 'Tidak Valid';
        $hariSelesaiNama = $namaHari[$this->hariSelesai] ?? 'Tidak Valid';

        return "{$hariMulaiNama} - {$hariSelesaiNama}";
    }

    /**
     * Generate QR Code unik untuk jadwal ini
     * 
     * @return string
     */
    public function generateQrCode(): string
    {
        if ($this->qrCode) {
            return $this->qrCode;
        }

        // Generate QR code dengan format: JDW_[ID]_[TANGGAL]_[RANDOM]
        $qrCode = 'JDW_' . $this->id . '_' . date('Ymd') . '_' . substr(md5(uniqid()), 0, 6);
        $this->setQrCode($qrCode);

        return $qrCode;
    }

    /**
     * String representation untuk debugging
     */
    public function __toString(): string
    {
        return $this->namaJadwal ?? 'Jadwal Absensi #' . $this->id;
    }
}