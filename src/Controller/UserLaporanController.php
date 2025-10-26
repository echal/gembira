<?php

namespace App\Controller;

use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Entity\KonfigurasiJadwalAbsensi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Controller untuk Laporan Pegawai
 * 
 * Menampilkan riwayat absensi pegawai dengan menggunakan
 * sistem baru yang fleksibel dan backward compatible.
 * 
 * @author Indonesian Developer
 */
#[Route('/laporan')]
final class UserLaporanController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->twig = $twig;
    }

    /**
     * Halaman riwayat absensi pegawai
     * Menampilkan absensi pegawai yang login untuk BULAN BERJALAN saja
     */
    #[Route('/', name: 'app_user_laporan')]
    #[IsGranted('ROLE_USER')]
    public function riwayatAbsensi(Request $request): Response
    {
        $pengguna = $this->getUser();
        
        // Pastikan pengguna adalah pegawai, bukan admin
        if (!$pengguna instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat mengakses riwayat absensi.');
        }

        // KEAMANAN: Deteksi dan blokir parameter mencurigakan yang mencoba akses data pegawai lain
        $suspiciousParams = ['pegawai_id', 'employee_id', 'user_id', 'id', 'nip'];
        foreach ($suspiciousParams as $param) {
            if ($request->query->has($param) || $request->request->has($param)) {
                // Log percobaan akses tidak sah
                error_log(sprintf(
                    'SECURITY WARNING: User %s (NIP: %s) mencoba mengakses laporan dengan parameter %s=%s', 
                    $pengguna->getNama(),
                    $pengguna->getNip(),
                    $param,
                    $request->get($param)
                ));
                
                throw $this->createAccessDeniedException('Parameter tidak diizinkan untuk akses laporan.');
            }
        }

        // KEAMANAN: Ambil riwayat absensi HANYA untuk pegawai yang login
        // FILTER: Hanya tampilkan data BULAN BERJALAN (bulan ini)
        // Query ini sudah aman karena menggunakan WHERE dengan parameter binding
        // Support untuk sistem lama dan sistem baru (fleksibel)
        
        // Hitung tanggal awal dan akhir bulan ini + siapkan nama bulan
        $now = new \DateTime();
        $bulanIni = $now->format('Y-m');
        $tanggalAwalBulan = new \DateTime($bulanIni . '-01 00:00:00');
        $tanggalAkhirBulan = new \DateTime($bulanIni . '-' . $now->format('t') . ' 23:59:59');
        
        // Siapkan informasi bulan
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $bulanSekarang = $namaBulan[(int)$now->format('n')];
        $tahunSekarang = $now->format('Y');
        
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $riwayatAbsensi = $absensiRepo->createQueryBuilder('a')
            ->leftJoin('a.jadwalAbsensi', 'ja')  // Sistem lama
            ->leftJoin('a.konfigurasiJadwal', 'kj')  // Sistem baru (fleksibel)
            ->where('a.pegawai = :pegawai')  // KEAMANAN: HANYA data pegawai yang login
            ->andWhere('a.tanggal >= :tanggal_awal')  // FILTER: Awal bulan ini
            ->andWhere('a.tanggal <= :tanggal_akhir')  // FILTER: Akhir bulan ini
            ->andWhere('(a.statusKehadiran IN (:status_lama) OR a.status IN (:status_baru))')
            ->setParameter('pegawai', $pengguna)  // KEAMANAN: Parameter binding aman dari SQL injection
            ->setParameter('tanggal_awal', $tanggalAwalBulan)  // Parameter tanggal awal bulan
            ->setParameter('tanggal_akhir', $tanggalAkhirBulan)  // Parameter tanggal akhir bulan
            ->setParameter('status_lama', ['hadir'])  // Format lama
            ->setParameter('status_baru', ['hadir'])   // Format baru
            ->orderBy('a.tanggal', 'DESC')
            ->addOrderBy('a.waktuMasuk', 'DESC')
            ->addOrderBy('a.waktuAbsensi', 'DESC')
            ->getQuery()
            ->getResult();

        // FITUR BARU: Hitung statistik kehadiran bulanan untuk menentukan status pegawai
        // Menghitung persentase kehadiran berdasarkan jadwal absensi yang dibuat admin
        $totalHariKerja = $this->hitungHariKerjaBerdasarkanJadwalAdmin($tanggalAwalBulan, $tanggalAkhirBulan);
        $totalHadir = 0;
        $totalTerlambat = 0;
        $totalAlpha = 0;

        // PERHITUNGAN: Hitung kehadiran dari data absensi yang ada
        foreach ($riwayatAbsensi as $absensi) {
            $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran();
            if ($status === 'hadir') {
                $totalHadir++;
            }
        }

        // PERHITUNGAN: Alpha = hari kerja - hadir
        $totalKehadiran = $totalHadir + $totalTerlambat;
        $totalAlpha = max(0, $totalHariKerja - $totalKehadiran);
        
        // PERHITUNGAN: Persentase kehadiran
        $persentaseKehadiran = $totalHariKerja > 0 ? round(($totalKehadiran / $totalHariKerja) * 100, 1) : 0;

        // LOGIKA STATUS: Tentukan status berdasarkan persentase kehadiran
        // Status akan reset setiap bulan (sesuai permintaan)
        $statusKehadiran = $this->tentukanStatusKehadiran($persentaseKehadiran);

        // Handle AJAX request untuk check data baru (juga menggunakan filter bulan berjalan)
        if ($request->isXmlHttpRequest() && $request->query->get('ajax') === '1') {
            return $this->json([
                'count' => count($riwayatAbsensi),
                'success' => true,
                'periode' => $bulanSekarang . ' ' . $tahunSekarang,
                'statistik' => [
                    'total_hadir' => $totalHadir,
                    'total_alpha' => $totalAlpha,
                    'persentase' => $persentaseKehadiran,
                    'status' => $statusKehadiran
                ]
            ]);
        }

        $response = $this->render('user/laporan/riwayat.html.twig', [
            'riwayat_absensi' => $riwayatAbsensi,
            'pegawai' => $pengguna,
            'bulan_nama' => $bulanSekarang,
            'tahun' => $tahunSekarang,
            'periode_filter' => $bulanSekarang . ' ' . $tahunSekarang,
            // FITUR BARU: Data statistik kehadiran bulanan untuk status pegawai
            'statistik_kehadiran' => [
                'total_hari_kerja' => $totalHariKerja,
                'total_hadir' => $totalHadir,
                'total_terlambat' => $totalTerlambat,
                'total_alpha' => $totalAlpha,
                'total_kehadiran' => $totalKehadiran,
                'persentase_kehadiran' => $persentaseKehadiran,
                'status_kehadiran' => $statusKehadiran
            ]
        ]);

        // KEAMANAN: Header untuk mencegah caching data sensitif
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    /**
     * HELPER METHOD: Hitung jumlah hari kerja berdasarkan jadwal absensi yang dibuat admin
     * 
     * Method ini menghitung total hari kerja dengan menjumlahkan semua jadwal
     * absensi yang telah dikonfigurasi oleh admin dalam periode bulan.
     * 
     * LOGIKA BARU:
     * - Jika admin membuat jadwal Senin-Jumat = 22 hari kerja
     * - Jika admin menambah jadwal Sabtu 1x = 23 hari kerja
     * - Jika admin menambah jadwal Minggu 2x = 25 hari kerja
     * - Total hari kerja = jumlah semua jadwal yang dibuat admin
     * 
     * @param \DateTime $tanggalAwal Tanggal awal bulan
     * @param \DateTime $tanggalAkhir Tanggal akhir bulan
     * @return int Jumlah total hari kerja berdasarkan jadwal admin
     */
    private function hitungHariKerjaBerdasarkanJadwalAdmin(\DateTime $tanggalAwal, \DateTime $tanggalAkhir): int
    {
        $totalHariKerja = 0;
        $tanggalIterator = clone $tanggalAwal;
        
        // QUERY: Ambil semua jadwal absensi yang aktif
        $jadwalAbsensiRepo = $this->entityManager->getRepository(KonfigurasiJadwalAbsensi::class);
        $daftarJadwalAktif = $jadwalAbsensiRepo->createQueryBuilder('j')
            ->where('j.isAktif = :aktif')
            ->setParameter('aktif', true)
            ->getQuery()
            ->getResult();
        
        // PERHITUNGAN: Loop setiap hari dalam bulan
        while ($tanggalIterator <= $tanggalAkhir) {
            $nomorHariSaatIni = (int)$tanggalIterator->format('N'); // 1=Senin, 7=Minggu
            
            // CEK: Apakah ada jadwal absensi untuk hari ini?
            foreach ($daftarJadwalAktif as $jadwal) {
                if ($this->cekHariMasukJadwal($nomorHariSaatIni, $jadwal)) {
                    $totalHariKerja++;
                    // PENTING: Satu hari bisa ada multiple jadwal, masing-masing dihitung
                    // Contoh: Senin ada "Apel Pagi" + "Rapat Sore" = 2 hari kerja
                }
            }
            
            // ITERASI: Pindah ke hari berikutnya
            $tanggalIterator->modify('+1 day');
        }
        
        return $totalHariKerja;
    }

    /**
     * HELPER METHOD: Cek apakah hari tertentu masuk dalam rentang jadwal
     * 
     * Method ini mengecek apakah nomor hari (1-7) masuk dalam rentang
     * hariMulai dan hariSelesai dari jadwal absensi.
     * 
     * @param int $nomorHari Nomor hari (1=Senin, 7=Minggu)
     * @param KonfigurasiJadwalAbsensi $jadwal Jadwal absensi
     * @return bool True jika hari masuk jadwal
     */
    private function cekHariMasukJadwal(int $nomorHari, KonfigurasiJadwalAbsensi $jadwal): bool
    {
        $hariMulai = $jadwal->getHariMulai();
        $hariSelesai = $jadwal->getHariSelesai();
        
        // CASE 1: Jadwal dalam seminggu normal (Senin-Jumat)
        // Contoh: hariMulai=1 (Senin), hariSelesai=5 (Jumat)
        if ($hariMulai <= $hariSelesai) {
            return $nomorHari >= $hariMulai && $nomorHari <= $hariSelesai;
        }
        
        // CASE 2: Jadwal lintas minggu (Jumat-Senin)
        // Contoh: hariMulai=5 (Jumat), hariSelesai=1 (Senin)
        // Artinya: Jumat, Sabtu, Minggu, Senin
        return $nomorHari >= $hariMulai || $nomorHari <= $hariSelesai;
    }

    /**
     * HELPER METHOD: Tentukan status kehadiran berdasarkan persentase
     *
     * Method ini menentukan status pegawai berdasarkan persentase kehadiran
     * dalam bulan berjalan. Status akan reset setiap bulan baru.
     *
     * @param float $persentase Persentase kehadiran (0-100)
     * @return array Status dengan informasi warna dan teks
     */
    private function tentukanStatusKehadiran(float $persentase): array
    {
        // LOGIKA STATUS: Sesuai dengan sistem admin laporan bulanan
        if ($persentase >= 90) {
            return [
                'text' => 'Luar Biasa',
                'emoji' => '🟢',
                'warna_bg' => 'bg-green-100',
                'warna_text' => 'text-green-800',
                'warna_border' => 'border-green-500',
                'kategori' => 'excellent'
            ];
        } elseif ($persentase >= 75) {
            return [
                'text' => 'Bagus',
                'emoji' => '🟡',
                'warna_bg' => 'bg-yellow-100',
                'warna_text' => 'text-yellow-800',
                'warna_border' => 'border-yellow-500',
                'kategori' => 'good'
            ];
        } else {
            return [
                'text' => 'Perlu Perhatian',
                'emoji' => '🔴',
                'warna_bg' => 'bg-red-100',
                'warna_text' => 'text-red-800',
                'warna_border' => 'border-red-500',
                'kategori' => 'needs_attention'
            ];
        }
    }

    /**
     * Download Laporan Absensi dalam format PDF
     * Hanya menampilkan data pegawai yang login untuk bulan berjalan
     */
    #[Route('/download-pdf', name: 'app_user_laporan_download_pdf')]
    #[IsGranted('ROLE_USER')]
    public function downloadPDF(): Response
    {
        $pengguna = $this->getUser();

        if (!$pengguna instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat download laporan.');
        }

        // Hitung tanggal bulan ini
        $now = new \DateTime();
        $bulanIni = $now->format('Y-m');
        $tanggalAwalBulan = new \DateTime($bulanIni . '-01 00:00:00');
        $tanggalAkhirBulan = new \DateTime($bulanIni . '-' . $now->format('t') . ' 23:59:59');

        // Ambil data absensi bulan ini
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $riwayatAbsensi = $absensiRepo->createQueryBuilder('a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->andWhere('(a.statusKehadiran IN (:status_lama) OR a.status IN (:status_baru))')
            ->setParameter('pegawai', $pengguna)
            ->setParameter('tanggal_awal', $tanggalAwalBulan)
            ->setParameter('tanggal_akhir', $tanggalAkhirBulan)
            ->setParameter('status_lama', ['hadir'])
            ->setParameter('status_baru', ['hadir'])
            ->orderBy('a.tanggal', 'ASC')
            ->addOrderBy('a.waktuAbsensi', 'ASC')
            ->getQuery()
            ->getResult();

        // Hitung statistik
        $totalHariKerja = $this->hitungHariKerjaBerdasarkanJadwalAdmin($tanggalAwalBulan, $tanggalAkhirBulan);
        $totalHadir = count($riwayatAbsensi);
        $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100, 1) : 0;

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $periode = $namaBulan[(int)$now->format('n')] . ' ' . $now->format('Y');

        // Render HTML untuk PDF (printable HTML)
        $html = $this->twig->render('laporan/pegawai_pdf.html.twig', [
            'pegawai' => $pengguna,
            'riwayat_absensi' => $riwayatAbsensi,
            'periode' => $periode,
            'statistik' => [
                'total_hari_kerja' => $totalHariKerja,
                'total_hadir' => $totalHadir,
                'persentase' => $persentase
            ]
        ]);

        // Return sebagai HTML yang bisa di-print sebagai PDF
        $response = new Response($html);
        $filename = sprintf('Laporan_Absensi_%s_%s.html', $pengguna->getNip(), $now->format('Ymd'));
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Download Laporan Absensi dalam format Excel
     * Hanya menampilkan data pegawai yang login untuk bulan berjalan
     */
    #[Route('/download-excel', name: 'app_user_laporan_download_excel')]
    #[IsGranted('ROLE_USER')]
    public function downloadExcel(): Response
    {
        $pengguna = $this->getUser();

        if (!$pengguna instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat download laporan.');
        }

        // Hitung tanggal bulan ini
        $now = new \DateTime();
        $bulanIni = $now->format('Y-m');
        $tanggalAwalBulan = new \DateTime($bulanIni . '-01 00:00:00');
        $tanggalAkhirBulan = new \DateTime($bulanIni . '-' . $now->format('t') . ' 23:59:59');

        // Ambil data absensi bulan ini
        $absensiRepo = $this->entityManager->getRepository(Absensi::class);
        $riwayatAbsensi = $absensiRepo->createQueryBuilder('a')
            ->where('a.pegawai = :pegawai')
            ->andWhere('a.tanggal >= :tanggal_awal')
            ->andWhere('a.tanggal <= :tanggal_akhir')
            ->andWhere('(a.statusKehadiran IN (:status_lama) OR a.status IN (:status_baru))')
            ->setParameter('pegawai', $pengguna)
            ->setParameter('tanggal_awal', $tanggalAwalBulan)
            ->setParameter('tanggal_akhir', $tanggalAkhirBulan)
            ->setParameter('status_lama', ['hadir'])
            ->setParameter('status_baru', ['hadir'])
            ->orderBy('a.tanggal', 'ASC')
            ->addOrderBy('a.waktuAbsensi', 'ASC')
            ->getQuery()
            ->getResult();

        // Hitung statistik
        $totalHariKerja = $this->hitungHariKerjaBerdasarkanJadwalAdmin($tanggalAwalBulan, $tanggalAkhirBulan);
        $totalHadir = count($riwayatAbsensi);
        $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100, 1) : 0;

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $periode = $namaBulan[(int)$now->format('n')] . ' ' . $now->format('Y');

        // Generate CSV (kompatibel dengan Excel)
        $response = new StreamedResponse(function() use ($pengguna, $riwayatAbsensi, $periode, $totalHariKerja, $totalHadir, $persentase) {
            $output = fopen('php://output', 'w');

            // Header CSV
            fputcsv($output, ['LAPORAN ABSENSI - ' . $periode]);
            fputcsv($output, ['Nama: ' . $pengguna->getNama()]);
            fputcsv($output, ['NIP: ' . $pengguna->getNip()]);
            fputcsv($output, ['Jabatan: ' . $pengguna->getJabatan()]);
            fputcsv($output, ['Unit Kerja: ' . $pengguna->getUnitKerja()]);
            fputcsv($output, []);
            fputcsv($output, ['Total Hari Kerja: ' . $totalHariKerja]);
            fputcsv($output, ['Total Hadir: ' . $totalHadir]);
            fputcsv($output, ['Persentase Kehadiran: ' . $persentase . '%']);
            fputcsv($output, []);

            // Header tabel
            fputcsv($output, ['No', 'Tanggal', 'Jam Absensi', 'Jenis Absensi', 'Status']);

            // Data absensi
            $no = 1;
            foreach ($riwayatAbsensi as $absensi) {
                $jenisAbsensi = '';
                if ($absensi->getKonfigurasiJadwal()) {
                    $jenisAbsensi = $absensi->getKonfigurasiJadwal()->getNamaJadwal();
                } elseif ($absensi->getJadwalAbsensi()) {
                    $jenisAbsensi = $absensi->getJadwalAbsensi()->getNamaJenisAbsensi();
                }

                $jamAbsensi = '';
                if ($absensi->getWaktuAbsensi()) {
                    $jamAbsensi = $absensi->getWaktuAbsensi()->format('H:i:s');
                } elseif ($absensi->getWaktuMasuk()) {
                    $jamAbsensi = $absensi->getWaktuMasuk()->format('H:i');
                }

                $status = $absensi->getStatus() ?? $absensi->getStatusKehadiran() ?? 'hadir';

                fputcsv($output, [
                    $no++,
                    $absensi->getTanggal()->format('d/m/Y'),
                    $jamAbsensi,
                    $jenisAbsensi,
                    ucfirst($status)
                ]);
            }

            fclose($output);
        });

        $filename = sprintf('Laporan_Absensi_%s_%s.csv', $pengguna->getNip(), $now->format('Ymd'));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}