<?php

namespace App\Controller;

use App\Entity\Pegawai;
use App\Form\TandaTanganType;
use App\Service\GamificationService;
use App\Service\UserXpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/ganti-password', name: 'app_profile_change_password')]
    public function gantiPassword(): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();
        
        if (!$pegawai instanceof Pegawai) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('profile/ganti_password.html.twig', [
            'pegawai' => $pegawai,
        ]);
    }

    #[Route('/update-password', name: 'app_profile_update_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();
        
        if (!$pegawai instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu'
            ]);
        }

        $passwordLama = $request->request->get('password_lama');
        $passwordBaru = $request->request->get('password_baru');
        $konfirmasiPassword = $request->request->get('konfirmasi_password');

        // Validasi input
        if (empty($passwordLama) || empty($passwordBaru) || empty($konfirmasiPassword)) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Semua field harus diisi'
            ]);
        }

        // Cek password lama
        if (!$passwordHasher->isPasswordValid($pegawai, $passwordLama)) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Password lama tidak sesuai'
            ]);
        }

        // Validasi password baru
        if (strlen($passwordBaru) < 6) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Password baru minimal 6 karakter'
            ]);
        }

        // Cek konfirmasi password
        if ($passwordBaru !== $konfirmasiPassword) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Konfirmasi password tidak cocok'
            ]);
        }

        try {
            // Update password
            $hashedPassword = $passwordHasher->hashPassword($pegawai, $passwordBaru);
            $pegawai->setPassword($hashedPassword);
            $pegawai->setUpdatedAt(new \DateTime());
            
            $em->persist($pegawai);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Password berhasil diubah! Silakan login kembali dengan password baru.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/profil', name: 'app_profile_view')]
    public function viewProfile(UserXpService $userXpService): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        if (!$pegawai instanceof Pegawai) {
            return $this->redirectToRoute('app_login');
        }

        // Get current month/year for XP ranking
        $currentDate = new \DateTime();
        $currentMonth = (int) $currentDate->format('n');
        $currentYear = (int) $currentDate->format('Y');

        // Get user's XP ranking
        $userXpRank = $userXpService->getUserRanking($pegawai, $currentMonth, $currentYear);

        return $this->render('profile/profil.html.twig', [
            'pegawai' => $pegawai,
            'userXpRank' => $userXpRank,
        ]);
    }

    #[Route('/profil/edit', name: 'app_profile_edit', methods: ['POST'])]
    public function editProfile(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();

        if (!$pegawai instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu'
            ]);
        }

        $email = trim($request->request->get('email', ''));
        $nomorTelepon = trim($request->request->get('nomor_telepon', ''));

        // Validasi email
        if (empty($email)) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Email tidak boleh kosong'
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Format email tidak valid'
            ]);
        }

        // Validasi nomor telepon (opsional, tapi kalau diisi harus valid)
        if (!empty($nomorTelepon)) {
            if (!preg_match('/^[0-9+\-\s\(\)]+$/', $nomorTelepon)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nomor telepon hanya boleh berisi angka, +, -, spasi, dan tanda kurung'
                ]);
            }
        }

        // Cek apakah email sudah digunakan pegawai lain
        $existingPegawai = $em->getRepository(Pegawai::class)
            ->createQueryBuilder('p')
            ->where('p.email = :email')
            ->andWhere('p.id != :current_id')
            ->setParameter('email', $email)
            ->setParameter('current_id', $pegawai->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingPegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Email sudah digunakan oleh pegawai lain'
            ]);
        }

        try {
            // Update data
            $pegawai->setEmail($email);
            $pegawai->setNomorTelepon($nomorTelepon ?: null);
            $pegawai->setUpdatedAt(new \DateTime());

            $em->persist($pegawai);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Profil berhasil diperbarui!',
                'data' => [
                    'email' => $pegawai->getEmail(),
                    'nomor_telepon' => $pegawai->getNomorTelepon()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

    // TANDA TANGAN MANAGEMENT - Upload tanda tangan statis untuk absensi
    #[Route('/tanda-tangan', name: 'app_profile_tanda_tangan')]
    public function tandaTangan(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();
        
        if (!$pegawai instanceof Pegawai) {
            return $this->redirectToRoute('app_login');
        }

        // Buat form upload tanda tangan
        $form = $this->createForm(TandaTanganType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $tandaTanganFile */
            $tandaTanganFile = $form->get('tandaTangan')->getData();

            if ($tandaTanganFile) {
                try {
                    // Generate nama file berdasarkan NIP pegawai
                    $extension = $tandaTanganFile->guessExtension();
                    $newFilename = $pegawai->generateTandaTanganFilename($extension);

                    // Tentukan direktori upload
                    $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/signatures';
                    
                    // Buat direktori jika belum ada
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }

                    // Hapus file lama jika ada
                    if ($pegawai->getTandaTangan()) {
                        $oldFilePath = $pegawai->getPublicTandaTanganPath();
                        if ($oldFilePath && file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }

                    // Upload file baru
                    $tandaTanganFile->move($uploadDirectory, $newFilename);

                    // Update database
                    $pegawai->setTandaTangan($newFilename);
                    $pegawai->setTandaTanganUploadedAt(new \DateTime());
                    $pegawai->setUpdatedAt(new \DateTime());

                    $em->persist($pegawai);
                    $em->flush();

                    $this->addFlash('success', '✅ Tanda tangan berhasil diupload! Sekarang Anda dapat melakukan absensi dengan tanda tangan digital.');
                    
                    return $this->redirectToRoute('app_profile_tanda_tangan');

                } catch (FileException $e) {
                    $this->addFlash('error', '❌ Gagal mengupload tanda tangan: ' . $e->getMessage());
                }
            }
        }

        return $this->render('profile/tanda_tangan.html.twig', [
            'pegawai' => $pegawai,
            'form' => $form->createView(),
        ]);
    }

    // API endpoint untuk preview tanda tangan
    #[Route('/api/tanda-tangan-preview', name: 'app_profile_api_tanda_tangan_preview')]
    public function tandaTanganPreview(): JsonResponse
    {
        /** @var Pegawai $pegawai */
        $pegawai = $this->getUser();
        
        if (!$pegawai instanceof Pegawai) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return new JsonResponse([
            'success' => true,
            'has_signature' => $pegawai->hasTandaTangan(),
            'signature_path' => $pegawai->getTandaTanganPath(),
            'uploaded_at' => $pegawai->getTandaTanganUploadedAt()?->format('d/m/Y H:i'),
        ]);
    }
}