<?php

namespace App\Service;

use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Entity\UnitKerja;

/**
 * Service untuk mengelola permission admin secara konsisten
 *
 * Service ini memastikan bahwa semua controller menggunakan
 * aturan permission yang sama untuk Role Super Admin dan Admin Unit Kerja.
 *
 * Aturan Permission:
 * - Super Admin: Akses penuh ke semua unit kerja dan semua fitur
 * - Admin Unit: Akses terbatas hanya ke unit kerjanya sendiri
 *
 * @author Indonesian Developer
 */
class AdminPermissionService
{
    /**
     * Mengecek apakah admin dapat mengakses menu tertentu
     *
     * @param Admin $admin Admin yang sedang login
     * @param string $menu Nama menu yang ingin diakses
     * @return bool True jika boleh akses, false jika tidak
     */
    public function canAccessMenu(Admin $admin, string $menu): bool
    {
        // Admin tidak aktif tidak bisa akses apapun
        if (!$admin->isAktif()) {
            return false;
        }

        // Super Admin bisa akses semua menu
        if ($admin->isSuperAdmin()) {
            return true;
        }

        // Admin Unit kerja, cek menu yang diizinkan
        $allowedMenus = [
            'dashboard',
            'pegawai',              // Kelola pegawai unit kerjanya
            'jadwal_absensi',       // Kelola jadwal absensi unit kerjanya
            'validasi_absen',       // Validasi absensi pegawai unit kerjanya
            'laporan_kehadiran',    // Laporan kehadiran unit kerjanya
            'laporan_bulanan',      // Laporan bulanan unit kerjanya
            'event',                // Event unit kerjanya
            'profile'               // Profil sendiri
        ];

        return in_array($menu, $allowedMenus);
    }

    /**
     * Mengecek apakah admin dapat mengelola pegawai tertentu
     *
     * @param Admin $admin Admin yang sedang login
     * @param Pegawai|null $pegawai Pegawai yang ingin dikelola
     * @return bool True jika boleh kelola, false jika tidak
     */
    public function canManagePegawai(Admin $admin, ?Pegawai $pegawai = null): bool
    {
        // Admin tidak aktif tidak bisa kelola apapun
        if (!$admin->isAktif()) {
            return false;
        }

        // Super Admin bisa kelola semua pegawai
        if ($admin->isSuperAdmin()) {
            return true;
        }

        // Admin Unit hanya bisa kelola pegawai dalam unit kerjanya
        if ($admin->isAdminUnit()) {
            // Jika tidak ada pegawai spesifik, cek apakah admin punya unit kerja
            if ($pegawai === null) {
                return $admin->getUnitKerjaEntity() !== null;
            }

            // Cek apakah pegawai dalam unit kerja yang sama
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            $pegawaiUnitKerja = $pegawai->getUnitKerjaEntity();

            return $adminUnitKerja && $pegawaiUnitKerja &&
                   $adminUnitKerja->getId() === $pegawaiUnitKerja->getId();
        }

        return false;
    }

    /**
     * Mengecek apakah admin dapat mengakses unit kerja tertentu
     *
     * @param Admin $admin Admin yang sedang login
     * @param UnitKerja|int|null $unitKerja Unit kerja yang ingin diakses
     * @return bool True jika boleh akses, false jika tidak
     */
    public function canAccessUnitKerja(Admin $admin, $unitKerja = null): bool
    {
        // Admin tidak aktif tidak bisa akses apapun
        if (!$admin->isAktif()) {
            return false;
        }

        // Super Admin bisa akses semua unit kerja
        if ($admin->isSuperAdmin()) {
            return true;
        }

        // Admin Unit hanya bisa akses unit kerjanya sendiri
        if ($admin->isAdminUnit()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                return false;
            }

            // Jika tidak ada unit kerja spesifik, berarti akses unit sendiri
            if ($unitKerja === null) {
                return true;
            }

            // Konversi ke ID jika perlu
            $unitKerjaId = $unitKerja instanceof UnitKerja ? $unitKerja->getId() : (int)$unitKerja;

            return $adminUnitKerja->getId() === $unitKerjaId;
        }

        return false;
    }

    /**
     * Filter pegawai berdasarkan permission admin
     *
     * @param Admin $admin Admin yang sedang login
     * @param array $allPegawai Semua pegawai
     * @return array Pegawai yang boleh diakses admin
     */
    public function filterPegawaiByPermission(Admin $admin, array $allPegawai): array
    {
        // Super Admin bisa lihat semua pegawai
        if ($admin->isSuperAdmin()) {
            return $allPegawai;
        }

        // Admin Unit hanya bisa lihat pegawai dalam unit kerjanya
        if ($admin->isAdminUnit()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                return [];
            }

            return array_filter($allPegawai, function($pegawai) use ($adminUnitKerja) {
                $pegawaiUnitKerja = $pegawai->getUnitKerjaEntity();
                return $pegawaiUnitKerja && $pegawaiUnitKerja->getId() === $adminUnitKerja->getId();
            });
        }

        return [];
    }

    /**
     * Mendapatkan unit kerja yang dapat diakses admin
     *
     * @param Admin $admin Admin yang sedang login
     * @param array $allUnitKerja Semua unit kerja
     * @return array Unit kerja yang boleh diakses
     */
    public function getAccessibleUnitKerja(Admin $admin, array $allUnitKerja): array
    {
        // Super Admin bisa akses semua unit kerja
        if ($admin->isSuperAdmin()) {
            return $allUnitKerja;
        }

        // Admin Unit hanya bisa akses unit kerjanya sendiri
        if ($admin->isAdminUnit()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                return [];
            }

            // Cari unit kerja admin dalam array
            foreach ($allUnitKerja as $unitKerja) {
                if ($unitKerja->getId() === $adminUnitKerja->getId()) {
                    return [$unitKerja];
                }
            }
        }

        return [];
    }

    /**
     * Mengecek apakah admin dapat mengakses fitur tertentu
     *
     * @param Admin $admin Admin yang sedang login
     * @param string $feature Nama fitur
     * @return bool True jika boleh akses, false jika tidak
     */
    public function canAccessFeature(Admin $admin, string $feature): bool
    {
        // Admin tidak aktif tidak bisa akses apapun
        if (!$admin->isAktif()) {
            return false;
        }

        // Super Admin bisa akses semua fitur
        if ($admin->isSuperAdmin()) {
            return true;
        }

        // Admin Unit, cek fitur yang diizinkan
        $allowedFeatures = [
            'kelola_pegawai_unit',      // Kelola pegawai dalam unit kerjanya
            'kelola_jadwal_unit',       // Kelola jadwal absensi unit kerjanya
            'validasi_absensi_unit',    // Validasi absensi pegawai unit kerjanya
            'laporan_unit',             // Akses laporan unit kerjanya
            'kelola_event_unit',        // Kelola event unit kerjanya
            'lihat_profil_sendiri'      // Lihat/edit profil sendiri
        ];

        // Fitur khusus Super Admin
        $superAdminFeatures = [
            'kelola_user_admin',        // Kelola user admin
            'pengaturan_sistem',        // Pengaturan sistem
            'kelola_unit_kerja',        // Kelola unit kerja
            'kelola_struktur_organisasi' // Kelola struktur organisasi
        ];

        // Jika fitur adalah fitur Super Admin, admin unit tidak boleh akses
        if (in_array($feature, $superAdminFeatures)) {
            return false;
        }

        return in_array($feature, $allowedFeatures);
    }

    /**
     * Mendapatkan pesan error jika admin tidak memiliki akses
     *
     * @param Admin $admin Admin yang sedang login
     * @param string $action Aksi yang ingin dilakukan
     * @return string Pesan error yang mudah dipahami
     */
    public function getAccessDeniedMessage(Admin $admin, string $action): string
    {
        if (!$admin->isAktif()) {
            return 'Akun admin Anda tidak aktif. Silakan hubungi Super Admin.';
        }

        if ($admin->isAdminUnit()) {
            return "Maaf, sebagai Admin Unit Kerja, Anda hanya dapat {$action} dalam unit kerja Anda sendiri.";
        }

        return 'Anda tidak memiliki permission untuk melakukan aksi ini.';
    }

    /**
     * Mendapatkan info role admin dalam bahasa Indonesia
     *
     * @param Admin $admin Admin yang ingin dicek
     * @return array Info role dan permission
     */
    public function getAdminRoleInfo(Admin $admin): array
    {
        if ($admin->isSuperAdmin()) {
            return [
                'role_name' => 'Super Admin',
                'description' => 'Memiliki akses penuh ke semua fitur dan semua unit kerja',
                'permissions' => [
                    'Kelola semua pegawai di semua unit kerja',
                    'Kelola semua jadwal absensi',
                    'Validasi semua absensi',
                    'Akses semua laporan',
                    'Kelola semua event',
                    'Kelola user admin',
                    'Pengaturan sistem',
                    'Kelola struktur organisasi'
                ]
            ];
        } elseif ($admin->isAdminUnit()) {
            $unitKerja = $admin->getUnitKerjaEntity();
            $unitKerjaName = $unitKerja ? $unitKerja->getNamaUnit() : 'Unit Kerja Tidak Diketahui';

            return [
                'role_name' => 'Admin Unit Kerja',
                'description' => "Akses terbatas hanya pada unit kerja: {$unitKerjaName}",
                'permissions' => [
                    'Kelola pegawai dalam unit kerja sendiri',
                    'Kelola jadwal absensi unit kerja sendiri',
                    'Validasi absensi pegawai unit kerja sendiri',
                    'Akses laporan unit kerja sendiri',
                    'Kelola event unit kerja sendiri',
                    'Lihat dan edit profil sendiri'
                ]
            ];
        }

        return [
            'role_name' => 'Tidak Diketahui',
            'description' => 'Role tidak valid',
            'permissions' => []
        ];
    }
}