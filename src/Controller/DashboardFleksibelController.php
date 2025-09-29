<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Pegawai;
use App\Repository\KonfigurasiJadwalAbsensiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard Controller Baru untuk Sistem Absensi Fleksibel
 * 
 * Menggantikan DashboardController lama dengan sistem yang sepenuhnya
 * berdasarkan konfigurasi admin tanpa logika hardcoded.
 * 
 * @author Indonesian Developer
 */
final class DashboardFleksibelController extends AbstractController
{
    public function __construct(
        private KonfigurasiJadwalAbsensiRepository $jadwalRepository
    ) {}

    /**
     * Dashboard utama - redirect berdasarkan role user
     */
    #[Route('/', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Redirect admin ke dashboard admin
        if ($user instanceof Admin) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        
        // Redirect pegawai ke dashboard absensi fleksibel
        if ($user instanceof Pegawai) {
            return $this->redirectToRoute('app_absensi_dashboard');
        }
        
        // Fallback ke login jika user tidak valid
        return $this->redirectToRoute('app_login');
    }

    /**
     * API untuk mendapatkan jadwal terbaru secara real-time (tetap untuk compatibility)
     */
    #[Route('/api/jadwal-update', name: 'app_api_jadwal_update', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function apiJadwalUpdate(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        }

        // Ambil hari saat ini
        $timezone = new \DateTimeZone('Asia/Makassar');
        $today = new \DateTime('now', $timezone);
        $hari = (int)$today->format('N');

        // Ambil jadwal yang tersedia untuk hari ini menggunakan sistem baru
        $jadwalTersedia = $this->jadwalRepository->findJadwalTersediaUntukHari($hari);
        $jadwalTerbuka = $this->jadwalRepository->findJadwalTerbukaSaatIni();

        // Convert ke format untuk frontend
        $kartuAbsensi = [];
        foreach ($jadwalTersedia as $jadwal) {
            $kartuAbsensi[] = [
                'id' => $jadwal->getId(),
                'nama' => $jadwal->getNamaJadwal(),
                'waktu' => $jadwal->getJamMulai()->format('H:i') . ' - ' . $jadwal->getJamSelesai()->format('H:i'),
                'emoji' => $jadwal->getEmoji(),
                'warna' => $jadwal->getWarnaKartu(),
                'aktif' => in_array($jadwal, $jadwalTerbuka),
                'perlu_qr' => $jadwal->isPerluQrCode(),
                'perlu_kamera' => $jadwal->isPerluKamera(),
                'jadwal_id' => $jadwal->getId(),
                'is_aktif' => $jadwal->isAktif(),
                'hari_tersedia' => $jadwal->getNamaHariTersedia(),
                'jam_buka' => in_array($jadwal, $jadwalTerbuka)
            ];
        }

        return new JsonResponse([
            'success' => true,
            'kartu_absensi' => $kartuAbsensi,
            'hari_ini' => $this->getNamaHari($hari),
            'timestamp' => $today->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get nama hari dalam bahasa Indonesia
     */
    private function getNamaHari(int $hari): string
    {
        $namaHari = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];

        return $namaHari[$hari] ?? 'Hari Tidak Valid';
    }
}