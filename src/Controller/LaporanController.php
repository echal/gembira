<?php

namespace App\Controller;

use App\Entity\Absensi;
use App\Entity\Pegawai;
use App\Entity\HariLibur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin/laporan')]
final class LaporanController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_laporan')]
    public function index(): Response
    {
        return $this->render('laporan/index.html.twig', [
            'tanggal_hari_ini' => new \DateTime(),
        ]);
    }

    #[Route('/rekap', name: 'app_laporan_rekap', methods: ['GET', 'POST'])]
    public function rekap(Request $request): Response
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        
        // Ambil data rekap kehadiran
        $dataRekap = $this->hitungRekapKehadiran($bulan, $tahun);
        
        return $this->render('laporan/rekap.html.twig', [
            'data_rekap' => $dataRekap,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nama_bulan' => $this->getNamaBulan($bulan),
        ]);
    }

    #[Route('/export/csv', name: 'app_laporan_export_csv', methods: ['POST'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $bulan = $request->request->get('bulan');
        $tahun = $request->request->get('tahun');
        
        $dataRekap = $this->hitungRekapKehadiran($bulan, $tahun);
        
        $response = new StreamedResponse();
        $response->setCallback(function() use ($dataRekap, $bulan, $tahun) {
            $handle = fopen('php://output', 'w+');
            
            // Header CSV
            fputcsv($handle, [
                'NIP',
                'Nama Pegawai', 
                'Jabatan',
                'Unit Kerja',
                'Total Hari Kerja',
                'Hadir',
                'Tidak Hadir',
                'Persentase Kehadiran (%)'
            ]);
            
            // Data pegawai
            foreach ($dataRekap['detail_pegawai'] as $pegawai) {
                fputcsv($handle, [
                    $pegawai['nip'],
                    $pegawai['nama'],
                    $pegawai['jabatan'],
                    $pegawai['unit_kerja'],
                    $pegawai['total_hari_kerja'],
                    $pegawai['total_hadir'],
                    $pegawai['total_tidak_hadir'],
                    round($pegawai['persentase_kehadiran'], 2)
                ]);
            }
            
            fclose($handle);
        });
        
        $namaBulan = $this->getNamaBulan($bulan);
        $filename = "laporan_kehadiran_{$namaBulan}_{$tahun}.csv";
        
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        
        return $response;
    }

    #[Route('/export/pdf', name: 'app_laporan_export_pdf', methods: ['POST'])]
    public function exportPdf(Request $request): Response
    {
        $bulan = $request->request->get('bulan');
        $tahun = $request->request->get('tahun');
        
        $dataRekap = $this->hitungRekapKehadiran($bulan, $tahun);
        
        // Generate HTML untuk PDF
        $html = $this->renderView('laporan/pdf_template.html.twig', [
            'data_rekap' => $dataRekap,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nama_bulan' => $this->getNamaBulan($bulan),
            'tanggal_cetak' => new \DateTime(),
        ]);
        
        // Konfigurasi Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $namaBulan = $this->getNamaBulan($bulan);
        $filename = "laporan_kehadiran_{$namaBulan}_{$tahun}.pdf";
        
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\""
            ]
        );
    }

    private function hitungRekapKehadiran(int $bulan, int $tahun): array
    {
        // Hitung total hari kerja dalam bulan (exclude weekend dan libur)
        $totalHariKerja = $this->hitungHariKerja($bulan, $tahun);
        
        // Ambil semua pegawai
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        $semuaPegawai = $pegawaiRepo->findBy(['statusKepegawaian' => 'aktif']);
        
        $detailPegawai = [];
        $totalHadir = 0;
        $totalTidakHadir = 0;
        
        // Buat date range sekali untuk semua pegawai
        $startDate = new \DateTime("{$tahun}-{$bulan}-01");
        $endDate = new \DateTime($startDate->format('Y-m-t'));
        
        foreach ($semuaPegawai as $pegawai) {
            // Hitung absensi pegawai dalam bulan ini
            
            $absensiRepo = $this->entityManager->getRepository(Absensi::class);
            $queryBuilder = $absensiRepo->createQueryBuilder('a')
                ->where('a.pegawai = :pegawai')
                ->andWhere('a.tanggal >= :startDate')
                ->andWhere('a.tanggal <= :endDate')
                ->andWhere("a.statusKehadiran IN ('hadir')")
                ->setParameter('pegawai', $pegawai)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
                
            $jumlahHadir = $queryBuilder->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
            $tidakHadir = $totalHariKerja - $jumlahHadir;
            $persentaseKehadiran = $totalHariKerja > 0 ? ($jumlahHadir / $totalHariKerja) * 100 : 0;
            
            $detailPegawai[] = [
                'nip' => $pegawai->getNip(),
                'nama' => $pegawai->getNama(),
                'jabatan' => $pegawai->getJabatan(),
                'unit_kerja' => $pegawai->getUnitKerja(),
                'total_hari_kerja' => $totalHariKerja,
                'total_hadir' => $jumlahHadir,
                'total_tidak_hadir' => $tidakHadir,
                'persentase_kehadiran' => $persentaseKehadiran
            ];
            
            $totalHadir += $jumlahHadir;
            $totalTidakHadir += $tidakHadir;
        }
        
        $totalPegawai = count($semuaPegawai);
        $rataRataKehadiran = $totalPegawai > 0 && $totalHariKerja > 0 ? 
            ($totalHadir / ($totalPegawai * $totalHariKerja)) * 100 : 0;
        
        return [
            'total_pegawai' => $totalPegawai,
            'total_hari_kerja' => $totalHariKerja,
            'total_hadir' => $totalHadir,
            'total_tidak_hadir' => $totalTidakHadir,
            'rata_rata_kehadiran' => $rataRataKehadiran,
            'detail_pegawai' => $detailPegawai
        ];
    }

    private function hitungHariKerja(int $bulan, int $tahun): int
    {
        $tanggalMulai = new \DateTime("{$tahun}-{$bulan}-01");
        $tanggalAkhir = new \DateTime($tanggalMulai->format('Y-m-t'));
        
        // Ambil hari libur dalam periode ini
        $hariLiburRepo = $this->entityManager->getRepository(HariLibur::class);
        $hariLibur = $hariLiburRepo->createQueryBuilder('h')
            ->where('h.tanggalLibur >= :mulai')
            ->andWhere('h.tanggalLibur <= :akhir')
            ->andWhere('h.status = :status')
            ->setParameter('mulai', $tanggalMulai)
            ->setParameter('akhir', $tanggalAkhir)
            ->setParameter('status', 'aktif')
            ->getQuery()
            ->getResult();
        
        $tanggalLibur = array_map(function($libur) {
            return $libur->getTanggalLibur()->format('Y-m-d');
        }, $hariLibur);
        
        $hariKerja = 0;
        $current = clone $tanggalMulai;
        
        while ($current <= $tanggalAkhir) {
            // Skip weekend (Sabtu=6, Minggu=0)
            $dayOfWeek = $current->format('w');
            if ($dayOfWeek != 0 && $dayOfWeek != 6) {
                // Check apakah bukan hari libur
                if (!in_array($current->format('Y-m-d'), $tanggalLibur)) {
                    $hariKerja++;
                }
            }
            $current->add(new \DateInterval('P1D'));
        }
        
        return $hariKerja;
    }

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
