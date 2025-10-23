<?php

namespace App\Controller;

use App\Repository\PegawaiRepository;
use App\Repository\UserXpLogRepository;
use App\Repository\MonthlyLeaderboardRepository;
use App\Service\UserXpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminXpDashboardController extends AbstractController
{
    public function __construct(
        private PegawaiRepository $pegawaiRepository,
        private UserXpLogRepository $xpLogRepository,
        private MonthlyLeaderboardRepository $leaderboardRepository,
        private UserXpService $userXpService
    ) {}

    #[Route('/xp-dashboard', name: 'admin_xp_dashboard')]
    public function index(): Response
    {
        // Get current month/year
        $currentDate = new \DateTime();
        $currentMonth = (int) $currentDate->format('n');
        $currentYear = (int) $currentDate->format('Y');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        // 1. Global Statistics
        $totalUsers = $this->pegawaiRepository->count([]);
        $totalXpGlobal = $this->pegawaiRepository->getTotalXpGlobal();

        // 2. Top 10 Users (Monthly)
        $topUsers = $this->leaderboardRepository->findTop10ByMonthYear($currentMonth, $currentYear);

        // Top user for current month
        $topUserThisMonth = !empty($topUsers) ? $topUsers[0] : null;

        // 3. Recent XP Activities
        $recentActivities = $this->xpLogRepository->getRecentActivities(20);

        // 4. Level Distribution
        $levelDistribution = $this->pegawaiRepository->getCountByLevel();

        // 5. XP by Unit Kerja (Monthly)
        $unitStats = $this->leaderboardRepository->getXpByUnitKerja($currentMonth, $currentYear);

        // 6. Monthly XP Total
        $monthlyXpTotal = $this->leaderboardRepository->getMonthlyXpTotal($currentMonth, $currentYear);

        // 7. Active users this month (users with XP > 0 this month)
        $activeUsersThisMonth = $this->leaderboardRepository->getActiveUsersCount($currentMonth, $currentYear);

        return $this->render('admin/xp_dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalXpGlobal' => $totalXpGlobal,
            'monthlyXpTotal' => $monthlyXpTotal,
            'activeUsersThisMonth' => $activeUsersThisMonth,
            'topUsers' => $topUsers,
            'topUserThisMonth' => $topUserThisMonth,
            'recentActivities' => $recentActivities,
            'levelDistribution' => $levelDistribution,
            'unitStats' => $unitStats,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
            'monthName' => $monthNames[$currentMonth],
        ]);
    }

    #[Route('/xp-dashboard/export', name: 'admin_xp_dashboard_export')]
    public function export(): Response
    {
        // TODO: Implement CSV/Excel export functionality
        $this->addFlash('info', 'Fitur export akan segera tersedia');
        return $this->redirectToRoute('admin_xp_dashboard');
    }
}
