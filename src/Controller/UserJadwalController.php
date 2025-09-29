<?php

namespace App\Controller;

use App\Entity\JadwalAbsensi;
use App\Repository\JadwalAbsensiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-jadwal')]
final class UserJadwalController extends AbstractController
{
    #[Route('/', name: 'app_user_jadwal_index')]
    #[IsGranted('ROLE_USER')]
    public function index(JadwalAbsensiRepository $jadwalRepo): Response
    {
        /** @var \Symfony\Component\Security\Core\User\UserInterface $user */
        $user = $this->getUser();
        
        $jadwalAbsensi = $jadwalRepo->findAll();

        return $this->render('user/jadwal.html.twig', [
            'jadwal_absensi' => $jadwalAbsensi,
            'user' => $user,
        ]);
    }

    #[Route('/jadwal-absensi/{id}/view', name: 'app_user_view_jadwal', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function viewJadwalAbsensi(JadwalAbsensi $jadwal): JsonResponse
    {
        /** @var \Symfony\Component\Security\Core\User\UserInterface $user */
        $user = $this->getUser();
        
        // User dapat melihat semua field tapi tidak bisa edit time
        $canEditTime = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN');
        
        return new JsonResponse([
            'id' => $jadwal->getId(),
            'jenis_absensi' => $jadwal->getJenisAbsensi(),
            'nama_jenis_absensi' => $jadwal->getNamaJenisAbsensi(),
            'hari_diizinkan' => $jadwal->getHariDiizinkan(),
            'jam_mulai' => $jadwal->getJamMulai()->format('H:i'),
            'jam_selesai' => $jadwal->getJamSelesai()->format('H:i'),
            'keterangan' => $jadwal->getKeterangan(),
            'is_aktif' => $jadwal->isAktif(),
            'qr_code' => $jadwal->getQrCode(),
            'can_edit_time' => $canEditTime,
            'user_role' => $user instanceof \App\Entity\Admin ? 'admin' : 'user',
            'can_edit_schedule' => true // User bisa edit beberapa field kecuali time
        ]);
    }

    #[Route('/jadwal-absensi/{id}/update-limited', name: 'app_user_update_jadwal', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateJadwalLimited(
        JadwalAbsensi $jadwal,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            /** @var \Symfony\Component\Security\Core\User\UserInterface $user */
            $user = $this->getUser();
            
            // Check user permissions
            $canEditTime = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN');
            
            // Get form data
            $hariDiizinkan = [];
            if ($request->request->has('edit_hari_diizinkan')) {
                $rawData = $request->request->get('edit_hari_diizinkan');
                $hariDiizinkan = is_array($rawData) ? $rawData : [$rawData];
            }
            
            $jamMulai = $request->request->get('edit_jam_mulai');
            $jamSelesai = $request->request->get('edit_jam_selesai');
            $keterangan = $request->request->get('edit_keterangan');

            // RBAC: Strict validation - users CANNOT modify time fields
            $originalJamMulai = $jadwal->getJamMulai();
            $originalJamSelesai = $jadwal->getJamSelesai();
            
            if (!$canEditTime && (
                $jamMulai !== $originalJamMulai->format('H:i') || 
                $jamSelesai !== $originalJamSelesai->format('H:i')
            )) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ AKSES DITOLAK: Anda tidak memiliki izin untuk mengubah jam mulai/selesai. Hanya admin yang dapat mengubah waktu jadwal.',
                    'error_type' => 'permission_denied'
                ]);
            }

            // Log the attempt for security monitoring
            error_log("User jadwal update - User: " . $user->getUserIdentifier() . 
                     ", Role: " . ($user instanceof \App\Entity\Admin ? 'Admin' : 'User') . 
                     ", Can Edit Time: " . ($canEditTime ? 'Yes' : 'No') . 
                     ", Attempted Time Change: " . ($jamMulai !== $originalJamMulai->format('H:i') || $jamSelesai !== $originalJamSelesai->format('H:i') ? 'Yes' : 'No'));

            // Validate basic input
            if (empty($hariDiizinkan)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Hari absensi harus dipilih minimal 1'
                ]);
            }

            // Update fields that user can modify (excluding time)
            $jadwal->setHariDiizinkan($hariDiizinkan);
            $jadwal->setKeterangan($keterangan);
            
            // Only admin can update time fields
            if ($canEditTime) {
                if (empty($jamMulai) || empty($jamSelesai)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Jam mulai dan jam selesai wajib diisi'
                    ]);
                }

                $mulaiTime = new \DateTime($jamMulai);
                $selesaiTime = new \DateTime($jamSelesai);
                
                if ($mulaiTime >= $selesaiTime) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Jam selesai harus lebih besar dari jam mulai'
                    ]);
                }

                $jadwal->setJamMulai($mulaiTime);
                $jadwal->setJamSelesai($selesaiTime);
            }
            
            $jadwal->setUpdatedAt(new \DateTime());
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Jadwal absensi berhasil diperbarui' . ($canEditTime ? '' : ' (kecuali waktu - hanya admin yang dapat mengubah waktu)'),
                'jadwal' => [
                    'id' => $jadwal->getId(),
                    'jenis' => $jadwal->getNamaJenisAbsensi(),
                    'emoji' => $jadwal->getEmojiJenisAbsensi(),
                    'hari' => $jadwal->getHariDiizinkanText(),
                    'jam' => $jadwal->getJamMulai()->format('H:i') . ' - ' . $jadwal->getJamSelesai()->format('H:i')
                ],
                'can_edit_time' => $canEditTime
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal: ' . $e->getMessage()
            ]);
        }
    }
}