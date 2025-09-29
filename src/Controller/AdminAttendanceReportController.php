<?php

namespace App\Controller;

use App\Entity\Pegawai;
use App\Entity\UnitKerja;
use App\Service\AttendanceCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk Laporan Kehadiran Admin (Menggunakan Service Baru)
 *
 * Contoh implementasi penggunaan AttendanceCalculationService
 * untuk perhitungan persentase kehadiran yang konsisten
 *
 * @author Indonesian Developer
 */
#[Route('/admin/attendance-report')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAttendanceReportController extends AbstractController
{
    public function __construct(
        private AttendanceCalculationService $attendanceService,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Halaman laporan kehadiran dengan perhitungan persentase yang konsisten
     */
    #[Route('/', name: 'app_admin_attendance_report')]
    public function index(Request $request): Response
    {
        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));
        $unitKerjaId = $request->query->get('unit_kerja', null);

        // Ambil statistik kehadiran menggunakan service
        $statistikKehadiran = $this->attendanceService->getStatistikKehadiranUnitKerja(
            $unitKerjaId,
            $tahun,
            $bulan
        );

        // Ambil daftar unit kerja untuk filter
        $unitKerjaList = $this->entityManager->getRepository(UnitKerja::class)
            ->findBy([], ['namaUnit' => 'ASC']);

        return $this->render('admin/attendance_report/index.html.twig', [
            'statistik' => $statistikKehadiran,
            'unit_kerja_list' => $unitKerjaList,
            'filter' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'unit_kerja' => $unitKerjaId
            ]
        ]);
    }

    /**
     * API untuk mendapatkan detail kehadiran pegawai tertentu
     */
    #[Route('/pegawai/{id}', name: 'app_admin_attendance_report_pegawai', methods: ['GET'])]
    public function detailPegawai(
        Pegawai $pegawai,
        Request $request
    ): JsonResponse {
        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));

        // Gunakan service untuk mendapatkan detail kehadiran
        $detailKehadiran = $this->attendanceService->getPersentaseKehadiran(
            $pegawai,
            $tahun,
            $bulan
        );

        return $this->json([
            'success' => true,
            'data' => $detailKehadiran
        ]);
    }

    /**
     * Perbandingan persentase kehadiran (contoh penggunaan fungsi sederhana)
     */
    #[Route('/comparison', name: 'app_admin_attendance_comparison')]
    public function perbandinganKehadiran(Request $request): Response
    {
        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));

        // Ambil beberapa pegawai untuk perbandingan
        $pegawaiList = $this->entityManager->getRepository(Pegawai::class)
            ->findBy(['statusKepegawaian' => 'aktif'], ['nama' => 'ASC'], 10);

        $dataPerbandingan = [];

        foreach ($pegawaiList as $pegawai) {
            // Gunakan fungsi sederhana untuk mendapatkan persentase
            $persentase = $this->attendanceService->getSimplePersentaseKehadiran(
                $pegawai,
                $tahun,
                $bulan
            );

            $dataPerbandingan[] = [
                'pegawai' => $pegawai,
                'persentase' => $persentase
            ];
        }

        // Urutkan berdasarkan persentase tertinggi
        usort($dataPerbandingan, function ($a, $b) {
            return $b['persentase'] <=> $a['persentase'];
        });

        return $this->render('admin/attendance_report/comparison.html.twig', [
            'data_perbandingan' => $dataPerbandingan,
            'periode' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'nama_bulan' => $this->getNamaBulan($bulan)
            ]
        ]);
    }

    /**
     * Export laporan kehadiran dengan perhitungan yang konsisten
     */
    #[Route('/export', name: 'app_admin_attendance_export')]
    public function exportLaporan(Request $request): Response
    {
        $tahun = (int) $request->query->get('tahun', date('Y'));
        $bulan = (int) $request->query->get('bulan', date('n'));
        $unitKerjaId = $request->query->get('unit_kerja', null);

        // Ambil statistik menggunakan service
        $statistik = $this->attendanceService->getStatistikKehadiranUnitKerja(
            $unitKerjaId,
            $tahun,
            $bulan
        );

        // Generate CSV
        $csvData = [];
        $csvData[] = [
            'Nama Pegawai',
            'NIP',
            'Unit Kerja',
            'Total Absen Tercatat',
            'Total Hadir',
            'Total Terlambat',
            'Total Izin',
            'Total Sakit',
            'Total Tidak Hadir',
            'Persentase Kehadiran (%)',
            'Status Kehadiran'
        ];

        foreach ($statistik['detail_pegawai'] as $detail) {
            $pegawai = $this->entityManager->getRepository(Pegawai::class)
                ->find($detail['pegawai_id']);

            $csvData[] = [
                $detail['pegawai_nama'],
                $detail['pegawai_nip'],
                $pegawai->getNamaUnitKerja(),
                $detail['data_absensi']['total_absen_tercatat'],
                $detail['data_absensi']['total_hadir'],
                0, // izin atau sakit
                $detail['data_absensi']['total_izin'],
                $detail['data_absensi']['total_sakit'],
                $detail['data_absensi']['total_tidak_hadir'],
                $detail['perhitungan']['persentase_kehadiran'],
                $detail['perhitungan']['status_kehadiran']
            ];
        }

        // Buat response CSV
        $response = new Response();
        $filename = sprintf(
            'Laporan_Kehadiran_%s_%s.csv',
            $tahun,
            str_pad($bulan, 2, '0', STR_PAD_LEFT)
        );

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        $output = fopen('php://temp', 'r+');
        fputs($output, "\xEF\xBB\xBF"); // BOM untuk UTF-8

        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
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