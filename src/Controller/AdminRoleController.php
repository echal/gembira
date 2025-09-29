<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Pengaturan Role dan Permission
 * 
 * Mengelola role dan permission untuk user admin.
 * Termasuk pengaturan hak akses per role dan per user.
 * 
 * @author Indonesian Developer
 */
#[Route('/admin/role')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRoleController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Halaman utama pengaturan role
     */
    #[Route('/', name: 'app_admin_role')]
    public function index(AdminRepository $adminRepository): Response
    {
        $admin = $this->getUser();
        
        // Ambil statistik role
        $roleStats = [
            'super_admin' => $adminRepository->countByRole('super_admin'),
            'admin' => $adminRepository->countByRole('admin'),
            'pegawai' => $adminRepository->countByRole('pegawai')
        ];

        return $this->render('admin/role/index.html.twig', [
            'admin' => $admin,
            'role_stats' => $roleStats,
            'available_roles' => $this->getAvailableRoles(),
            'available_permissions' => $this->getAvailablePermissions(),
            'role_permissions' => $this->getDefaultRolePermissions()
        ]);
    }

    /**
     * Update permission untuk role tertentu
     */
    #[Route('/update-role-permissions', name: 'app_admin_role_update_permissions', methods: ['POST'])]
    public function updateRolePermissions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $role = $data['role'] ?? '';
            $permissions = $data['permissions'] ?? [];

            if (!in_array($role, ['super_admin', 'admin', 'pegawai'])) {
                return new JsonResponse(['success' => false, 'message' => 'Role tidak valid'], 400);
            }

            // Update semua user dengan role tersebut
            $users = $this->entityManager->getRepository(Admin::class)
                ->findBy(['role' => $role]);

            foreach ($users as $user) {
                $user->setPermissions($permissions);
                $user->setUpdatedAt(new \DateTime());
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Permission untuk role ' . $role . ' berhasil diupdate',
                'updated_users' => count($users)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update permission untuk user spesifik
     */
    #[Route('/update-user-permissions/{id}', name: 'app_admin_role_update_user_permissions', methods: ['POST'])]
    public function updateUserPermissions(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->entityManager->getRepository(Admin::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $permissions = $data['permissions'] ?? [];

            $user->setPermissions($permissions);
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Permission untuk ' . $user->getNamaLengkap() . ' berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get daftar user berdasarkan role
     */
    #[Route('/users-by-role/{role}', name: 'app_admin_role_users_by_role', methods: ['GET'])]
    public function getUsersByRole(string $role): JsonResponse
    {
        $users = $this->entityManager->getRepository(Admin::class)
            ->findBy(['role' => $role, 'status' => 'aktif'], ['namaLengkap' => 'ASC']);

        $userData = [];
        foreach ($users as $user) {
            $userData[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'namaLengkap' => $user->getNamaLengkap(),
                'email' => $user->getEmail(),
                'permissions' => $user->getPermissions() ?? [],
                'lastLoginAt' => $user->getLastLoginAt() ? $user->getLastLoginAt()->format('d/m/Y H:i') : '-'
            ];
        }

        return new JsonResponse([
            'success' => true,
            'users' => $userData
        ]);
    }

    /**
     * Reset permission user ke default role
     */
    #[Route('/reset-user-permissions/{id}', name: 'app_admin_role_reset_user_permissions', methods: ['POST'])]
    public function resetUserPermissions(int $id): JsonResponse
    {
        try {
            $user = $this->entityManager->getRepository(Admin::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
            }

            $defaultPermissions = $this->getDefaultRolePermissions()[$user->getRole()] ?? [];
            
            $user->setPermissions($defaultPermissions);
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Permission untuk ' . $user->getNamaLengkap() . ' berhasil direset ke default'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Daftar role yang tersedia
     */
    private function getAvailableRoles(): array
    {
        return [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke semua fitur sistem',
                'color' => 'red'
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Akses ke fitur manajemen dan laporan',
                'color' => 'blue'
            ],
            'pegawai' => [
                'name' => 'Pegawai',
                'description' => 'Akses untuk absensi dan kelola profile',
                'color' => 'green'
            ]
        ];
    }

    /**
     * Daftar permission yang tersedia
     */
    private function getAvailablePermissions(): array
    {
        return [
            'kelola_pegawai' => [
                'name' => 'Kelola Pegawai',
                'description' => 'Menambah, edit, dan hapus data pegawai'
            ],
            'kelola_jadwal' => [
                'name' => 'Kelola Jadwal Absensi',
                'description' => 'Mengelola jadwal dan konfigurasi absensi'
            ],
            'validasi_absensi' => [
                'name' => 'Validasi Absensi',
                'description' => 'Memvalidasi dan menyetujui absensi pegawai'
            ],
            'laporan' => [
                'name' => 'Akses Laporan',
                'description' => 'Melihat dan download laporan kehadiran'
            ],
            'kelola_event' => [
                'name' => 'Kelola Event',
                'description' => 'Mengelola event dan kegiatan khusus'
            ],
            'kelola_user' => [
                'name' => 'Kelola User Admin',
                'description' => 'Menambah, edit, dan hapus user admin'
            ],
            'pengaturan_sistem' => [
                'name' => 'Pengaturan Sistem',
                'description' => 'Mengubah konfigurasi dan pengaturan sistem'
            ],
            'absensi_pegawai' => [
                'name' => 'Akses Absensi Pegawai',
                'description' => 'Melakukan absensi dan melihat riwayat'
            ],
            'profile_pegawai' => [
                'name' => 'Kelola Profile Pegawai',
                'description' => 'Mengubah data profile dan password'
            ]
        ];
    }

    /**
     * Default permission untuk setiap role
     */
    private function getDefaultRolePermissions(): array
    {
        return [
            'super_admin' => [
                'kelola_pegawai',
                'kelola_jadwal',
                'validasi_absensi',
                'laporan',
                'kelola_event',
                'kelola_user',
                'pengaturan_sistem',
                'absensi_pegawai',
                'profile_pegawai'
            ],
            'admin' => [
                'kelola_pegawai',
                'kelola_jadwal',
                'validasi_absensi',
                'laporan',
                'kelola_event'
            ],
            'pegawai' => [
                'absensi_pegawai',
                'profile_pegawai'
            ]
        ];
    }
}