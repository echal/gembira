<?php

namespace App\Service;

use App\Entity\Absensi;

/**
 * AbsensiFormatterService
 *
 * Service untuk formatting data absensi yang sering digunakan:
 * - Format data absensi detail
 * - Status badge formatting
 * - Location information formatting
 * - Time formatting
 * - QR code information
 * - Validation status
 *
 * REFACTOR: Dipindahkan dari duplicate methods di multiple controllers
 *
 * @author Refactor Assistant
 */
class AbsensiFormatterService
{
    /**
     * Format absensi detail untuk display
     */
    public function formatAbsensiDetail(Absensi $absensi): array
    {
        return [
            'id' => $absensi->getId(),
            'pegawai' => [
                'id' => $absensi->getPegawai()->getId(),
                'nama' => $absensi->getPegawai()->getNamaLengkap(),
                'nip' => $absensi->getPegawai()->getNip(),
                'jabatan' => $absensi->getPegawai()->getJabatan(),
                'unitKerja' => $absensi->getPegawai()->getUnitKerja()?->getNama() ?? 'Tidak Ada Unit'
            ],
            'tanggal' => $absensi->getTanggal()->format('Y-m-d'),
            'tanggal_formatted' => $absensi->getTanggal()->format('d F Y'),
            'waktu' => $this->getWaktuAbsensiFormatted($absensi),
            'status' => $this->getStatusFormatted($absensi),
            'lokasi' => $this->getLokasiInfo($absensi),
            'jadwal' => $this->getJadwalInfo($absensi),
            'qrCode' => $this->getQrCodeInfo($absensi),
            'teknis' => $this->getTeknisInfo($absensi),
            'validasi' => $this->getValidasiInfo($absensi),
            'foto' => $this->generateFotoUrl($absensi)
        ];
    }

    /**
     * Get status information with badge and description
     */
    public function getStatusInfo(string $status): array
    {
        $statusMap = [
            'hadir' => [
                'badge' => '🟢',
                'text' => 'Hadir',
                'class' => 'success',
                'description' => 'Pegawai hadir tepat waktu'
            ],
            'izin' => [
                'badge' => '🔵',
                'text' => 'Izin',
                'class' => 'info',
                'description' => 'Pegawai izin dengan alasan'
            ],
            'sakit' => [
                'badge' => '🟠',
                'text' => 'Sakit',
                'class' => 'warning',
                'description' => 'Pegawai sakit'
            ],
            'alpha' => [
                'badge' => '🔴',
                'text' => 'Alpha',
                'class' => 'danger',
                'description' => 'Pegawai tidak hadir tanpa keterangan'
            ],
            'tidak_hadir' => [
                'badge' => '🔴',
                'text' => 'Tidak Hadir',
                'class' => 'danger',
                'description' => 'Pegawai tidak hadir'
            ]
        ];

        return $statusMap[$status] ?? [
            'badge' => '❓',
            'text' => 'Unknown',
            'class' => 'secondary',
            'description' => 'Status tidak diketahui'
        ];
    }

    /**
     * Get formatted waktu absensi
     */
    public function getWaktuAbsensiFormatted(Absensi $absensi): array
    {
        return [
            'jam_masuk' => $absensi->getJamMasuk()?->format('H:i:s') ?? '-',
            'jam_masuk_formatted' => $absensi->getJamMasuk()?->format('H:i') ?? '-',
            'jam_keluar' => $absensi->getJamKeluar()?->format('H:i:s') ?? '-',
            'jam_keluar_formatted' => $absensi->getJamKeluar()?->format('H:i') ?? '-',
            'durasi_kerja' => $this->calculateDurasiKerja($absensi),
        ];
    }

    /**
     * Get formatted status information
     */
    public function getStatusFormatted(Absensi $absensi): array
    {
        $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran() ?? 'tidak_hadir';
        $statusInfo = $this->getStatusInfo($status);

        return [
            'raw' => $status,
            'badge' => $statusInfo['badge'],
            'text' => $statusInfo['text'],
            'class' => $statusInfo['class'],
            'description' => $statusInfo['description'],
            'full' => $statusInfo['badge'] . ' ' . $statusInfo['text']
        ];
    }

    /**
     * Get location information
     */
    public function getLokasiInfo(Absensi $absensi): array
    {
        return [
            'latitude' => $absensi->getLatitude(),
            'longitude' => $absensi->getLongitude(),
            'alamat' => $absensi->getAlamat(),
            'jarak_kantor' => $absensi->getJarakKantor(),
            'status_lokasi' => $this->getStatusLokasi($absensi)
        ];
    }

    /**
     * Get jadwal information (compatible with both old and new structure)
     */
    public function getJadwalInfo($absensi): array
    {
        // Handle both Entity and array data
        if (is_object($absensi)) {
            $jadwal = $absensi->getJadwal();
        } else {
            $jadwal = $absensi['jadwal'] ?? null;
        }

        if (!$jadwal) {
            return [
                'jam_masuk' => '-',
                'jam_keluar' => '-',
                'tipe' => 'Tidak Ada Jadwal',
                'keterangan' => 'Jadwal tidak ditemukan'
            ];
        }

        return [
            'jam_masuk' => is_object($jadwal) ? $jadwal->getJamMasuk()?->format('H:i') : ($jadwal['jam_masuk'] ?? '-'),
            'jam_keluar' => is_object($jadwal) ? $jadwal->getJamKeluar()?->format('H:i') : ($jadwal['jam_keluar'] ?? '-'),
            'tipe' => is_object($jadwal) ? $jadwal->getTipe() : ($jadwal['tipe'] ?? 'Regular'),
            'keterangan' => is_object($jadwal) ? $jadwal->getKeterangan() : ($jadwal['keterangan'] ?? '')
        ];
    }

    /**
     * Get QR code information
     */
    public function getQrCodeInfo(Absensi $absensi): array
    {
        return [
            'qr_code_masuk' => $absensi->getQrCodeMasuk(),
            'qr_code_keluar' => $absensi->getQrCodeKeluar(),
            'status_qr' => $this->getStatusQR($absensi)
        ];
    }

    /**
     * Get technical information
     */
    public function getTeknisInfo(Absensi $absensi): array
    {
        return [
            'ip_address' => $absensi->getIpAddress(),
            'user_agent' => $absensi->getUserAgent(),
            'created_at' => $absensi->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $absensi->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get validation information
     */
    public function getValidasiInfo(Absensi $absensi): array
    {
        return [
            'perlu_validasi_admin' => $absensi->getPerluValidasiAdmin() ?? false,
            'is_validated' => $absensi->getIsValidated() ?? false,
            'validated_by' => $absensi->getValidatedBy()?->getNamaLengkap(),
            'validated_at' => $absensi->getValidatedAt()?->format('Y-m-d H:i:s'),
            'validation_notes' => $absensi->getValidationNotes(),
            'status_validasi' => $this->getStatusValidasi($absensi)
        ];
    }

    /**
     * Generate foto URL if exists
     */
    public function generateFotoUrl(Absensi $absensi): ?string
    {
        $fotoPath = $absensi->getFotoPath();
        if (!$fotoPath) {
            return null;
        }

        // Ensure path starts with /uploads/
        if (!str_starts_with($fotoPath, '/uploads/')) {
            $fotoPath = '/uploads/' . ltrim($fotoPath, '/');
        }

        return $fotoPath;
    }

    /**
     * Calculate durasi kerja
     */
    private function calculateDurasiKerja(Absensi $absensi): ?string
    {
        $jamMasuk = $absensi->getJamMasuk();
        $jamKeluar = $absensi->getJamKeluar();

        if (!$jamMasuk || !$jamKeluar) {
            return null;
        }

        $diff = $jamMasuk->diff($jamKeluar);
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;

        return sprintf('%d jam %d menit', $hours, $minutes);
    }


    /**
     * Get status lokasi
     */
    private function getStatusLokasi(Absensi $absensi): string
    {
        $jarakKantor = $absensi->getJarakKantor();

        if ($jarakKantor === null) {
            return 'Tidak Diketahui';
        }

        if ($jarakKantor <= 100) {
            return 'Dalam Radius';
        } elseif ($jarakKantor <= 500) {
            return 'Dekat Kantor';
        } else {
            return 'Jauh dari Kantor';
        }
    }

    /**
     * Get status QR
     */
    private function getStatusQR(Absensi $absensi): string
    {
        $qrMasuk = $absensi->getQrCodeMasuk();
        $qrKeluar = $absensi->getQrCodeKeluar();

        if ($qrMasuk && $qrKeluar) {
            return 'Lengkap';
        } elseif ($qrMasuk) {
            return 'Hanya Masuk';
        } elseif ($qrKeluar) {
            return 'Hanya Keluar';
        } else {
            return 'Tidak Ada';
        }
    }

    /**
     * Get status validasi
     */
    private function getStatusValidasi(Absensi $absensi): array
    {
        $perluValidasi = $absensi->getPerluValidasiAdmin() ?? false;
        $isValidated = $absensi->getIsValidated() ?? false;

        if (!$perluValidasi) {
            return [
                'text' => 'Tidak Perlu Validasi',
                'badge' => '✅',
                'class' => 'success'
            ];
        }

        if ($isValidated) {
            return [
                'text' => 'Sudah Divalidasi',
                'badge' => '✅',
                'class' => 'success'
            ];
        }

        return [
            'text' => 'Menunggu Validasi',
            'badge' => '⏳',
            'class' => 'warning'
        ];
    }
}