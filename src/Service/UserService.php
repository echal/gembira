<?php

namespace App\Service;

use App\Entity\Pegawai;
use App\Entity\Admin;
use App\Repository\PegawaiRepository;
use App\Repository\AdminRepository;

/**
 * UserService - Service layer untuk operasi User/Pegawai
 *
 * Service ini menangani logika bisnis untuk:
 * - Operasi user/pegawai CRUD
 * - Validasi user data
 * - User business logic yang seharusnya tidak di controller
 *
 * CATATAN: Ini adalah skeleton service yang dibuat untuk naming consistency.
 * Implementasi detail akan ditambahkan pada langkah refactor selanjutnya.
 *
 * @author Refactor Assistant
 */
class UserService
{
    public function __construct(
        private PegawaiRepository $pegawaiRepository,
        private AdminRepository $adminRepository
    ) {}

    /**
     * Placeholder method - Find user by ID
     *
     * @param int $id
     * @return Pegawai|null
     */
    public function findUserById(int $id): ?Pegawai
    {
        // TODO: Implement logic - akan diisi saat controller slimming
        return $this->pegawaiRepository->find($id);
    }

    /**
     * Placeholder method - Find user by email
     *
     * @param string $email
     * @return Pegawai|null
     */
    public function findUserByEmail(string $email): ?Pegawai
    {
        // TODO: Implement logic - akan diisi saat controller slimming
        return $this->pegawaiRepository->findOneBy(['email' => $email]);
    }

    /**
     * Placeholder method - Get all active users
     *
     * @return array
     */
    public function getAllUsers(): array
    {
        // TODO: Implement logic - akan diisi saat controller slimming
        return $this->pegawaiRepository->findBy(['statusKepegawaian' => 'aktif']);
    }

    /**
     * Placeholder method - Find admin by ID
     *
     * @param int $id
     * @return Admin|null
     */
    public function findAdminById(int $id): ?Admin
    {
        // TODO: Implement logic - akan diisi saat controller slimming
        return $this->adminRepository->find($id);
    }

    /**
     * Placeholder method - Validate user status
     *
     * @param Pegawai $user
     * @return bool
     */
    public function validateUserStatus(Pegawai $user): bool
    {
        // TODO: Implement validation logic
        return $user->getStatusKepegawaian() === 'aktif';
    }

    /**
     * Placeholder method - Get user statistics
     *
     * @param Pegawai $user
     * @return array
     */
    public function getUserStatistics(Pegawai $user): array
    {
        // TODO: Implement statistics logic - akan diisi saat controller slimming
        return [
            'total_absensi' => 0,
            'total_hadir' => 0,
            'persentase_kehadiran' => 0.0
        ];
    }
}