<?php

namespace App\Controller;

use App\Entity\Pegawai;
use App\Service\AttendanceCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Dashboard User (Menggunakan Service Baru)
 *
 * Contoh implementasi penggunaan AttendanceCalculationService
 * di sisi user untuk memastikan perhitungan yang konsisten dengan admin
 *
 * @author Indonesian Developer
 */
#[Route('/user/attendance')]
#[IsGranted('ROLE_USER')]
final class UserAttendanceController extends AbstractController
{
    public function __construct(
        private AttendanceCalculationService $attendanceService
    ) {}

    /**
     * Dashboard kehadiran user dengan perhitungan yang konsisten
     */
    #[Route('/dashboard', name: 'app_user_attendance_dashboard')]
    public function dashboard(Request $request): Response
    {
        $user = $this->getUser();

        // Pastikan user adalah pegawai
        if (!$user instanceof Pegawai) {
            throw $this->createAccessDeniedException('Akses hanya untuk pegawai');
        }

        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));

        // Gunakan service untuk mendapatkan data kehadiran
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
            $user,
            $tahun,
            $bulan
        );

        return $this->render('user/attendance/dashboard.html.twig', [
            'data_kehadiran' => $dataKehadiran,
            'pegawai' => $user
        ]);
    }

    /**
     * API untuk mendapatkan statistik kehadiran user (AJAX)
     */
    #[Route('/api/statistik', name: 'app_user_attendance_api_statistik', methods: ['GET'])]
    public function apiStatistik(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Pegawai) {
            return $this->json([
                'success' => false,
                'message' => 'User bukan pegawai'
            ], 403);
        }

        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));

        try {
            // Gunakan service untuk perhitungan yang konsisten
            $dataKehadiran = $this->attendanceService->getPersentaseKehadiran(
                $user,
                $tahun,
                $bulan
            );

            return $this->json([
                'success' => true,
                'data' => $dataKehadiran
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perbandingan kehadiran bulanan user
     */
    #[Route('/perbandingan-bulanan', name: 'app_user_attendance_comparison')]
    public function perbandinganBulanan(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Pegawai) {
            throw $this->createAccessDeniedException('Akses hanya untuk pegawai');
        }

        $tahun = (int) $request->query->get('tahun', date('Y'));

        // Dapatkan data kehadiran untuk 12 bulan terakhir
        $dataPerbandingan = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            // Skip bulan yang belum terjadi di tahun ini
            if ($tahun == date('Y') && $bulan > date('n')) {
                continue;
            }

            $persentase = $this->attendanceService->getSimplePersentaseKehadiran(
                $user,
                $tahun,
                $bulan
            );

            $dataPerbandingan[] = [
                'bulan' => $bulan,
                'nama_bulan' => $this->getNamaBulan($bulan),
                'persentase' => $persentase
            ];
        }

        return $this->render('user/attendance/comparison.html.twig', [
            'data_perbandingan' => $dataPerbandingan,
            'tahun' => $tahun,
            'pegawai' => $user
        ]);
    }

    /**
     * Widget kehadiran untuk dashboard utama
     */
    #[Route('/widget', name: 'app_user_attendance_widget')]
    public function widget(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Pegawai) {
            return new Response('<!-- User bukan pegawai -->');
        }

        // Ambil data bulan ini menggunakan service
        $dataKehadiran = $this->attendanceService->getPersentaseKehadiran($user);

        return $this->render('user/attendance/widget.html.twig', [
            'data_kehadiran' => $dataKehadiran
        ]);
    }

    /**
     * Helper method untuk nama bulan
     */
    private function getNamaBulan(int $bulan): string
    {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $namaBulan[$bulan] ?? 'Unknown';
    }
}