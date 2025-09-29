<?php

namespace App\Controller;

use App\Entity\Notifikasi;
use App\Entity\Pegawai;
use App\Entity\UserNotifikasi;
use App\Repository\UserNotifikasiRepository;
use App\Service\NotifikasiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifikasi')]
final class NotifikasiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private NotifikasiService $notifikasiService;

    public function __construct(EntityManagerInterface $entityManager, NotifikasiService $notifikasiService)
    {
        $this->entityManager = $entityManager;
        $this->notifikasiService = $notifikasiService;
    }

    #[Route('/', name: 'app_notifikasi_index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // hubungan: pastikan user adalah pegawai, bukan admin
        if (!$user instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat mengakses notifikasi.');
        }

        // # CONTROLLER UPDATE STATUS: otomatis mark semua notifikasi unread sebagai read saat view halaman
        $userNotifikasiRepo = $this->entityManager->getRepository(UserNotifikasi::class);
        $this->markNotificationsAsReadOnView($user, $userNotifikasiRepo);

        // hubungan: ambil notifikasi melalui UserNotifikasi pivot
        $userNotifications = $userNotifikasiRepo->findNotificationsForUser($user, 50);
        $jumlahBelumDibaca = $userNotifikasiRepo->countUnreadForUser($user);

        return $this->render('notifikasi/index.html.twig', [
            'pegawai' => $user,
            'userNotifications' => $userNotifications,
            'jumlah_belum_dibaca' => $jumlahBelumDibaca,
        ]);
    }

    #[Route('/api/count', name: 'app_notifikasi_api_count')]
    #[IsGranted('ROLE_USER')]
    public function getUnreadCount(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse(['count' => 0]);
        }

        // # CONTROLLER UPDATE STATUS: menggunakan UserNotifikasi untuk count yang akurat
        $userNotifikasiRepo = $this->entityManager->getRepository(UserNotifikasi::class);
        $count = $userNotifikasiRepo->countUnreadForUser($user);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/api/mark-read/{id}', name: 'app_notifikasi_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAsRead(Notifikasi $notifikasi): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User tidak valid'
            ], 403);
        }

        try {
            // # CONTROLLER UPDATE STATUS: mark as read melalui UserNotifikasi pivot
            $userNotifikasiRepo = $this->entityManager->getRepository(UserNotifikasi::class);
            $userNotification = $userNotifikasiRepo->findUserNotification($user, $notifikasi);
            
            if (!$userNotification) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Notifikasi tidak ditemukan untuk user ini'
                ], 404);
            }

            $userNotification->setIsRead(true);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Notifikasi telah ditandai sudah dibaca'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }
    }

    #[Route('/api/mark-all-read', name: 'app_notifikasi_mark_all_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User tidak valid'
            ], 403);
        }

        try {
            // # CONTROLLER UPDATE STATUS: mark all as read melalui UserNotifikasi
            $userNotifikasiRepo = $this->entityManager->getRepository(UserNotifikasi::class);
            $userNotifikasiRepo->markAllAsReadForUser($user);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Semua notifikasi telah ditandai sudah dibaca'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }
    }

    #[Route('/api/latest', name: 'app_notifikasi_api_latest')]
    #[IsGranted('ROLE_USER')]
    public function getLatestNotifications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User tidak valid'
            ], 403);
        }

        $limit = $request->query->getInt('limit', 10);
        
        // # CONTROLLER UPDATE STATUS: ambil notifikasi melalui UserNotifikasi dengan status read/unread yang akurat
        $userNotifikasiRepo = $this->entityManager->getRepository(UserNotifikasi::class);
        $userNotifications = $userNotifikasiRepo->findNotificationsForUser($user, $limit);

        $data = [];
        foreach ($userNotifications as $un) {
            $n = $un->getNotifikasi();
            $data[] = [
                'id' => $n->getId(),
                'judul' => $n->getJudul(),
                'pesan' => $n->getPesan(),
                'tipe' => $n->getTipe(),
                'tipe_icon' => $n->getTipeIcon(),
                'tipe_badge' => $n->getTipeBadgeClass(),
                'sudah_dibaca' => $un->isRead(), // menggunakan status dari UserNotifikasi
                'priority' => $un->getPriority(),
                'priority_icon' => $un->getPriorityIcon(),
                'priority_class' => $un->getPriorityClass(),
                'waktu_relatif' => $un->getTimeAgo(),
                'waktu_dibuat' => $un->getReceivedAt()->format('d/m/Y H:i'),
                'waktu_dibaca' => $un->getReadAt()?->format('d/m/Y H:i'),
                'event_id' => $n->getEvent()?->getId(),
                'event_judul' => $n->getEvent()?->getJudulEvent()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ]);
    }

    // # CONTROLLER UPDATE STATUS: helper method untuk otomatis mark as read saat view
    private function markNotificationsAsReadOnView(Pegawai $user, UserNotifikasiRepository $userNotifikasiRepo): void
    {
        // Ambil notifikasi yang belum dibaca untuk user ini (limit 20 terbaru)
        $unreadNotifications = $userNotifikasiRepo->findUnreadForUser($user, 20);
        
        if (empty($unreadNotifications)) {
            return;
        }

        // Mark sebagai read ketika user membuka halaman notifikasi
        foreach ($unreadNotifications as $userNotification) {
            $userNotification->setIsRead(true);
        }
        
        $this->entityManager->flush();
    }
}