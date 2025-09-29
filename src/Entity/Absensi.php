<?php

namespace App\Entity;

use App\Repository\AbsensiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsensiRepository::class)]
#[ORM\Table(name: 'absensi')]
class Absensi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relasi ke pegawai (many absensi to one pegawai)
    #[ORM\ManyToOne(targetEntity: Pegawai::class, inversedBy: 'absensi')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pegawai $pegawai = null;

    // Relasi ke jadwal absensi LAMA (untuk backward compatibility)
    #[ORM\ManyToOne(targetEntity: JadwalAbsensi::class, inversedBy: 'absensiList')]
    #[ORM\JoinColumn(nullable: true)]
    private ?JadwalAbsensi $jadwalAbsensi = null;

    // Relasi ke konfigurasi jadwal absensi BARU (sistem fleksibel)
    #[ORM\ManyToOne(targetEntity: KonfigurasiJadwalAbsensi::class, inversedBy: 'daftarAbsensi')]
    #[ORM\JoinColumn(nullable: true)]
    private ?KonfigurasiJadwalAbsensi $konfigurasiJadwal = null;

    // Tanggal absensi
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $tanggal = null;

    // Waktu absensi (untuk sistem baru - satu waktu saja)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $waktuAbsensi = null;

    // Alias untuk waktuAbsensi (untuk konsistensi dengan controller)
    public function getWaktuAbsen(): ?\DateTimeInterface
    {
        return $this->waktuAbsensi;
    }

    public function setWaktuAbsen(?\DateTimeInterface $waktuAbsen): static
    {
        $this->waktuAbsensi = $waktuAbsen;
        return $this;
    }

    // Waktu masuk kerja (untuk backward compatibility)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $waktuMasuk = null;

    // Waktu keluar kerja (untuk backward compatibility)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $waktuKeluar = null;

    // Status kehadiran untuk sistem baru (hadir/tidak_hadir/izin/sakit)
    #[ORM\Column(length: 20)]
    private ?string $status = 'hadir';

    // Status kehadiran lama (untuk backward compatibility)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statusKehadiran = null;

    // Path file foto absensi (untuk sistem baru)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoPath = null;

    // QR Code yang digunakan saat absensi (untuk tracking)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCodeUsed = null;

    // Foto selfie saat masuk (untuk backward compatibility)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotoSelfie = null;

    // Lokasi saat absensi masuk (GPS coordinates)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lokasiAbsensi = null;

    // Latitude GPS untuk validasi lokasi yang presisi
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    // Longitude GPS untuk validasi lokasi yang presisi
    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    // QR Code yang dipindai saat absensi
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCodeScanned = null;

    // Keterangan tambahan (alasan terlambat, izin, dll)
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $keterangan = null;

    // IP Address untuk tracking lokasi
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    // User Agent browser/device
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    // Status validasi admin (pending/disetujui/ditolak)
    #[ORM\Column(length: 20)]
    private ?string $statusValidasi = 'pending';

    // Catatan dari admin saat validasi (keterangan untuk approve/reject)
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $catatanAdmin = null;

    // Keterangan validasi (alias untuk catatanAdmin - untuk konsistensi API)
    public function getKeteranganValidasi(): ?string
    {
        return $this->catatanAdmin;
    }

    public function setKeteranganValidasi(?string $keteranganValidasi): static
    {
        $this->catatanAdmin = $keteranganValidasi;
        return $this;
    }

    // Admin yang melakukan validasi
    #[ORM\ManyToOne(targetEntity: Admin::class, inversedBy: 'validatedAbsensi')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $validatedBy = null;

    // Tanggal dan waktu validasi oleh admin
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tanggalValidasi = null;

    // Alias untuk tanggalValidasi (untuk konsistensi API)
    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->tanggalValidasi;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->tanggalValidasi = $validatedAt;
        return $this;
    }

    // Tanggal dibuat record ini
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    // Tanggal terakhir diubah
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // Jenis absensi untuk mendukung kategori custom
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $jenisAbsensi = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->tanggal = new \DateTime();
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

    public function getJadwalAbsensi(): ?JadwalAbsensi
    {
        return $this->jadwalAbsensi;
    }

    public function setJadwalAbsensi(?JadwalAbsensi $jadwalAbsensi): static
    {
        $this->jadwalAbsensi = $jadwalAbsensi;
        return $this;
    }

    // Deprecated: gunakan getTanggal() untuk kompatibilitas
    public function getTanggalAbsensi(): ?\DateTimeInterface
    {
        return $this->tanggal;
    }

    // Deprecated: gunakan setTanggal() untuk kompatibilitas
    public function setTanggalAbsensi(\DateTimeInterface $tanggalAbsensi): static
    {
        $this->tanggal = $tanggalAbsensi;
        return $this;
    }

    public function getWaktuMasuk(): ?\DateTimeInterface
    {
        return $this->waktuMasuk;
    }

    public function setWaktuMasuk(?\DateTimeInterface $waktuMasuk): static
    {
        $this->waktuMasuk = $waktuMasuk;
        return $this;
    }

    public function getWaktuKeluar(): ?\DateTimeInterface
    {
        return $this->waktuKeluar;
    }

    public function setWaktuKeluar(?\DateTimeInterface $waktuKeluar): static
    {
        $this->waktuKeluar = $waktuKeluar;
        return $this;
    }

    public function getStatusKehadiran(): ?string
    {
        return $this->statusKehadiran;
    }

    public function setStatusKehadiran(string $statusKehadiran): static
    {
        $this->statusKehadiran = $statusKehadiran;
        return $this;
    }

    public function getFotoSelfie(): ?string
    {
        return $this->fotoSelfie;
    }

    public function setFotoSelfie(?string $fotoSelfie): static
    {
        $this->fotoSelfie = $fotoSelfie;
        return $this;
    }

    public function getLokasiAbsensi(): ?string
    {
        return $this->lokasiAbsensi;
    }

    public function setLokasiAbsensi(?string $lokasiAbsensi): static
    {
        $this->lokasiAbsensi = $lokasiAbsensi;
        return $this;
    }

    public function getQrCodeScanned(): ?string
    {
        return $this->qrCodeScanned;
    }

    public function setQrCodeScanned(?string $qrCodeScanned): static
    {
        $this->qrCodeScanned = $qrCodeScanned;
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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getStatusValidasi(): ?string
    {
        return $this->statusValidasi;
    }

    public function setStatusValidasi(string $statusValidasi): static
    {
        $this->statusValidasi = $statusValidasi;
        return $this;
    }

    public function getCatatanAdmin(): ?string
    {
        return $this->catatanAdmin;
    }

    public function setCatatanAdmin(?string $catatanAdmin): static
    {
        $this->catatanAdmin = $catatanAdmin;
        return $this;
    }

    public function getValidatedBy(): ?Admin
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?Admin $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getTanggalValidasi(): ?\DateTimeInterface
    {
        return $this->tanggalValidasi;
    }

    public function setTanggalValidasi(?\DateTimeInterface $tanggalValidasi): static
    {
        $this->tanggalValidasi = $tanggalValidasi;
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


    // Method untuk menghitung jam kerja
    public function getJamKerja(): ?int
    {
        if (!$this->waktuMasuk || !$this->waktuKeluar) {
            return null;
        }

        $diff = $this->waktuKeluar->diff($this->waktuMasuk);
        return ($diff->h * 60) + $diff->i; // dalam menit
    }

    public function getJenisAbsensi(): ?string
    {
        return $this->jenisAbsensi;
    }

    public function setJenisAbsensi(?string $jenisAbsensi): static
    {
        $this->jenisAbsensi = $jenisAbsensi;
        return $this;
    }

    // === GETTER DAN SETTER UNTUK SISTEM BARU ===

    public function getKonfigurasiJadwal(): ?KonfigurasiJadwalAbsensi
    {
        return $this->konfigurasiJadwal;
    }

    public function setKonfigurasiJadwal(?KonfigurasiJadwalAbsensi $konfigurasiJadwal): static
    {
        $this->konfigurasiJadwal = $konfigurasiJadwal;
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

    public function getWaktuAbsensi(): ?\DateTimeInterface
    {
        return $this->waktuAbsensi;
    }

    public function setWaktuAbsensi(?\DateTimeInterface $waktuAbsensi): static
    {
        $this->waktuAbsensi = $waktuAbsensi;
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

    public function getFotoPath(): ?string
    {
        return $this->fotoPath;
    }

    public function setFotoPath(?string $fotoPath): static
    {
        $this->fotoPath = $fotoPath;
        return $this;
    }

    public function getQrCodeUsed(): ?string
    {
        return $this->qrCodeUsed;
    }

    public function setQrCodeUsed(?string $qrCodeUsed): static
    {
        $this->qrCodeUsed = $qrCodeUsed;
        return $this;
    }

    /**
     * Mendapatkan koordinat latitude GPS
     *
     * @return string|null Koordinat latitude dalam format decimal degrees
     */
    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    /**
     * Mengatur koordinat latitude GPS
     *
     * @param string|null $latitude Koordinat latitude dalam format decimal degrees
     * @return static
     */
    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * Mendapatkan koordinat longitude GPS
     *
     * @return string|null Koordinat longitude dalam format decimal degrees
     */
    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    /**
     * Mengatur koordinat longitude GPS
     *
     * @param string|null $longitude Koordinat longitude dalam format decimal degrees
     * @return static
     */
    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * Cek apakah absensi ini memerlukan validasi admin
     *
     * @return bool
     */
    public function isRequiresValidation(): bool
    {
        // Jika ada konfigurasi jadwal, ikuti setting jadwal
        if ($this->konfigurasiJadwal) {
            return $this->konfigurasiJadwal->isPerluValidasiAdmin();
        }

        // Jika tidak ada konfigurasi jadwal, cek berdasarkan status validasi
        // Jika statusValidasi bukan 'disetujui', berarti pernah perlu validasi
        return $this->statusValidasi !== 'disetujui' || $this->statusValidasi === 'pending';
    }

    /**
     * Cek apakah absensi sudah divalidasi (disetujui atau ditolak)
     *
     * @return bool
     */
    public function isValidated(): bool
    {
        return in_array($this->statusValidasi, ['disetujui', 'ditolak']);
    }

    /**
     * Dapatkan badge HTML untuk status validasi
     *
     * @return string
     */
    public function getStatusValidasiBadge(): string
    {
        $badges = [
            'pending' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⏳ Menunggu</span>',
            'disetujui' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Disetujui</span>',
            'ditolak' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Ditolak</span>'
        ];

        return $badges[$this->statusValidasi] ?? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">❓ Tidak Diketahui</span>';
    }

    /**
     * Mengembalikan representasi string dari object Absensi
     *
     * @return string Format: "Absensi [Nama Pegawai] - [Tanggal]"
     */
    public function __toString(): string
    {
        $pegawai = $this->pegawai ? $this->pegawai->getNama() : 'Unknown';
        $tanggal = $this->tanggal ? $this->tanggal->format('d/m/Y') : 'Unknown';
        return "Absensi {$pegawai} - {$tanggal}";
    }
}