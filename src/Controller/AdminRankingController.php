<?php

namespace App\Controller;

use App\Service\RankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller untuk halaman admin "Lihat Ranking"
 *
 * Menampilkan 3 jenis ranking:
 * 1. Ranking Harian - Berdasarkan skor harian (07:00-08:15)
 * 2. Ranking Bulanan - Akumulasi skor selama sebulan
 * 3. Ranking Unit Kerja - Rata-rata skor per unit kerja
 */
#[Route('/admin/ranking', name: 'admin_ranking_')]
#[IsGranted('ROLE_ADMIN')]
class AdminRankingController extends AbstractController
{
    public function __construct(
        private RankingService $rankingService
    ) {}

    /**
     * Halaman utama "Lihat Ranking"
     *
     * @Route('/', name='index', methods=['GET'])
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $timezone = new \DateTimeZone('Asia/Makassar');

        // Parameter tanggal untuk ranking harian (default: hari ini)
        $tanggalParam = $request->query->get('tanggal');
        $tanggal = $tanggalParam
            ? \DateTime::createFromFormat('Y-m-d', $tanggalParam, $timezone)
            : new \DateTime('now', $timezone);

        if (!$tanggal) {
            $tanggal = new \DateTime('now', $timezone);
        }
        $tanggal->setTime(0, 0, 0);

        // Parameter periode untuk ranking bulanan (default: bulan ini)
        $periodeParam = $request->query->get('periode');
        $periode = $periodeParam ?: (new \DateTime('now', $timezone))->format('Y-m');

        // Ambil data ranking dari service
        $rankingHarian = $this->rankingService->getAllDailyRanking($tanggal);
        $rankingBulanan = $this->rankingService->getAllMonthlyRanking($periode);
        $rankingGroup = $this->rankingService->getAllGroupRanking($tanggal);

        return $this->render('admin/ranking/index.html.twig', [
            'page_title' => 'Lihat Ranking',
            'ranking_harian' => $rankingHarian,
            'ranking_bulanan' => $rankingBulanan,
            'ranking_group' => $rankingGroup,
            'tanggal_dipilih' => $tanggal,
            'periode_dipilih' => $periode,
        ]);
    }

    /**
     * API endpoint untuk mendapatkan ranking harian (AJAX)
     *
     * @Route('/api/harian', name='api_harian', methods=['GET'])
     */
    #[Route('/api/harian', name: 'api_harian', methods: ['GET'])]
    public function apiRankingHarian(Request $request): Response
    {
        $timezone = new \DateTimeZone('Asia/Makassar');
        $tanggalParam = $request->query->get('tanggal');

        $tanggal = $tanggalParam
            ? \DateTime::createFromFormat('Y-m-d', $tanggalParam, $timezone)
            : new \DateTime('now', $timezone);

        if (!$tanggal) {
            $tanggal = new \DateTime('now', $timezone);
        }
        $tanggal->setTime(0, 0, 0);

        $rankingHarian = $this->rankingService->getAllDailyRanking($tanggal);

        // Format data untuk JSON response
        $data = [];
        foreach ($rankingHarian as $ranking) {
            $data[] = [
                'peringkat' => $ranking->getPeringkat(),
                'nama' => $ranking->getPegawai()->getNama(),
                'nip' => $ranking->getPegawai()->getNip(),
                'unit_kerja' => $ranking->getPegawai()->getNamaUnitKerja(),
                'jam_masuk' => $ranking->getJamMasuk() ? $ranking->getJamMasuk()->format('H:i') : '-',
                'skor_harian' => $ranking->getSkorHarian(),
                'badge' => $ranking->getPeringkatBadge()
            ];
        }

        return $this->json([
            'success' => true,
            'tanggal' => $tanggal->format('Y-m-d'),
            'total_pegawai' => count($data),
            'data' => $data
        ]);
    }

    /**
     * API endpoint untuk mendapatkan ranking bulanan (AJAX)
     *
     * @Route('/api/bulanan', name='api_bulanan', methods=['GET'])
     */
    #[Route('/api/bulanan', name: 'api_bulanan', methods: ['GET'])]
    public function apiRankingBulanan(Request $request): Response
    {
        $periodeParam = $request->query->get('periode');
        $periode = $periodeParam ?: (new \DateTime())->format('Y-m');

        $rankingBulanan = $this->rankingService->getAllMonthlyRanking($periode);

        // Format data untuk JSON response
        $data = [];
        foreach ($rankingBulanan as $ranking) {
            $data[] = [
                'peringkat' => $ranking->getPeringkat(),
                'nama' => $ranking->getPegawai()->getNama(),
                'nip' => $ranking->getPegawai()->getNip(),
                'unit_kerja' => $ranking->getPegawai()->getNamaUnitKerja(),
                'total_skor' => $ranking->getTotalDurasi(), // Field ini menyimpan total skor
                'rata_rata_skor' => round($ranking->getRataRataDurasi(), 2),
                'badge' => $ranking->getPeringkatBadge()
            ];
        }

        return $this->json([
            'success' => true,
            'periode' => $periode,
            'total_pegawai' => count($data),
            'data' => $data
        ]);
    }

    /**
     * API endpoint untuk mendapatkan ranking unit kerja (AJAX)
     *
     * @Route('/api/group', name='api_group', methods=['GET'])
     */
    #[Route('/api/group', name: 'api_group', methods: ['GET'])]
    public function apiRankingGroup(Request $request): Response
    {
        $timezone = new \DateTimeZone('Asia/Makassar');
        $tanggalParam = $request->query->get('tanggal');

        $tanggal = $tanggalParam
            ? \DateTime::createFromFormat('Y-m-d', $tanggalParam, $timezone)
            : new \DateTime('now', $timezone);

        if (!$tanggal) {
            $tanggal = new \DateTime('now', $timezone);
        }
        $tanggal->setTime(0, 0, 0);

        $rankingGroup = $this->rankingService->getAllGroupRanking($tanggal);

        return $this->json([
            'success' => true,
            'tanggal' => $tanggal->format('Y-m-d'),
            'total_unit' => count($rankingGroup),
            'data' => $rankingGroup
        ]);
    }
}
