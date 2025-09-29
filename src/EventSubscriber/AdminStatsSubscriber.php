<?php

namespace App\EventSubscriber;

use App\Repository\AbsensiRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * EventSubscriber untuk menambahkan statistik validasi absen ke semua halaman admin
 *
 * Subscriber ini akan:
 * 1. Menghitung jumlah absensi pending yang perlu validasi
 * 2. Menambahkan data sebagai global variable Twig
 * 3. Memastikan konsistensi data antara badge sidebar dan halaman validasi
 */
class AdminStatsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private Environment $twig,
        private AbsensiRepository $absensiRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Skip jika bukan main request
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Hanya untuk halaman admin
        if (!$route || !str_starts_with($route, 'app_admin_')) {
            return;
        }

        // Hanya untuk admin yang sudah login
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        try {
            // Hitung statistik dengan query yang sama seperti di halaman validasi
            $stats = $this->hitungStatistikValidasi();

            // Tambahkan sebagai global Twig variable
            $this->twig->addGlobal('stats', $stats);

            // Debug logging untuk troubleshooting
            if ($stats['pending'] > 0) {
                error_log(sprintf(
                    '[ADMIN_STATS] Found %d pending validations on route %s',
                    $stats['pending'],
                    $route
                ));
            }

        } catch (\Exception $e) {
            error_log('Error loading admin stats: ' . $e->getMessage());

            // Fallback stats jika error
            $this->twig->addGlobal('stats', [
                'pending' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'approval_rate' => 0
            ]);
        }
    }

    /**
     * Hitung Statistik Validasi - Query yang sama dengan AdminValidasiAbsenController
     *
     * PENTING: Query ini harus SAMA dengan yang ada di AdminValidasiAbsenController::hitungStatistikValidasi()
     * untuk memastikan konsistensi data antara badge dan tabel.
     */
    private function hitungStatistikValidasi(): array
    {
        try {
            // Query untuk pending - SEDERHANA dan KONSISTEN
            // Jika absensi sudah berstatus 'pending', berarti memang perlu validasi
            // Tidak perlu filter jadwal karena status pending sudah menandakan perlu validasi
            $pendingQuery = $this->absensiRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_pending')
                ->setParameter('status_pending', 'pending');

            $pending = (int) $pendingQuery->getQuery()->getSingleScalarResult();

            // Hitung approved hari ini
            $today = new \DateTime();
            $approved = $this->absensiRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_approved')
                ->andWhere('DATE(a.validatedAt) = :tanggal')
                ->setParameter('status_approved', 'disetujui')
                ->setParameter('tanggal', $today->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult();

            // Hitung rejected hari ini
            $rejected = $this->absensiRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_rejected')
                ->andWhere('DATE(a.validatedAt) = :tanggal')
                ->setParameter('status_rejected', 'ditolak')
                ->setParameter('tanggal', $today->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult();

            // Hitung approval rate
            $totalValidated = $approved + $rejected;
            $approvalRate = $totalValidated > 0 ? round(($approved / $totalValidated) * 100) : 0;

            return [
                'pending' => $pending,
                'approved_today' => (int)$approved,
                'rejected_today' => (int)$rejected,
                'approval_rate' => $approvalRate
            ];

        } catch (\Exception $e) {
            error_log('Error calculating admin stats: ' . $e->getMessage());

            return [
                'pending' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'approval_rate' => 0
            ];
        }
    }
}