<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\UnitKerja;
use App\Entity\Pegawai;
use App\Form\EventType;
use App\Service\NotifikasiService;
use App\Service\AdminPermissionService;
use App\Service\ValidationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/event')]
#[IsGranted('ROLE_ADMIN')]
final class AdminEventController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private NotifikasiService $notifikasiService;
    private AdminPermissionService $permissionService;
    private ValidationBadgeService $validationBadgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        NotifikasiService $notifikasiService,
        AdminPermissionService $permissionService,
        ValidationBadgeService $validationBadgeService
    ) {
        $this->entityManager = $entityManager;
        $this->notifikasiService = $notifikasiService;
        $this->permissionService = $permissionService;
        $this->validationBadgeService = $validationBadgeService;
    }

    #[Route('/', name: 'app_admin_event_index')]
    public function index(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Pastikan admin bisa akses event
        if (!$this->permissionService->canAccessFeature($admin, 'kelola_event_unit')) {
            $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'mengakses event'));
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $eventRepo = $this->entityManager->getRepository(Event::class);

        // Ambil parameter pencarian dari query string
        $search = $request->query->get('search');

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua event
            $groupedEvents = $eventRepo->findAllGroupedByMonth($search);
        } else {
            // Admin Unit hanya lihat event unit kerjanya
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }
            $groupedEvents = $eventRepo->findAllGroupedByMonthAndUnit($search, $adminUnitKerja);
        }

        // Hitung total event untuk informasi
        $totalEvents = 0;
        foreach ($groupedEvents as $monthEvents) {
            $totalEvents += count($monthEvents);
        }

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/event/index.html.twig', array_merge([
            'groupedEvents' => $groupedEvents,
            'totalEvents' => $totalEvents,
            'search' => $search,
            'admin' => $admin
        ], $sidebarStats));
    }

    #[Route('/new', name: 'app_admin_event_new')]
    public function new(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // PERMISSION CHECK: Pastikan admin bisa membuat event
        if (!$this->permissionService->canAccessFeature($admin, 'kelola_event_unit')) {
            $this->addFlash('error', $this->permissionService->getAccessDeniedMessage($admin, 'membuat event'));
            return $this->redirectToRoute('app_admin_event_index');
        }

        $event = new Event();

        // Filter unit kerja berdasarkan role admin
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa pilih semua unit kerja
            $unitKerjas = $this->entityManager->getRepository(UnitKerja::class)->findBy([], ['namaUnit' => 'ASC']);
        } else {
            // Admin Unit hanya bisa pilih unit kerjanya sendiri
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('error', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_event_index');
            }
            $unitKerjas = [$adminUnitKerja]; // Hanya unit kerja admin
        }

        $unitChoices = [];
        foreach ($unitKerjas as $unit) {
            // Format: "Nama Unit" => id (untuk form choices)
            $unitChoices[$unit->getNamaUnit()] = $unit->getId();
        }
        
        $form = $this->createForm(EventType::class, $event, [
            'unit_choices' => $unitChoices
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set admin yang membuat event
            $admin = $this->getUser();
            if ($admin instanceof Admin) {
                $event->setCreatedBy($admin);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // # CONTROLLER FORM FIX: Kirim notifikasi dengan sistem UserNotifikasi yang baru
            try {
                error_log("DEBUG: Attempting to send notification for event ID {$event->getId()}");
                $this->notifikasiService->kirimNotifikasiEventBaru($event);
                error_log("DEBUG: Notification sent successfully for event ID {$event->getId()}");
            } catch (\Exception $e) {
                // Log error tapi jangan gagalkan proses
                error_log('ERROR: Gagal mengirim notifikasi event: ' . $e->getMessage());
                error_log('ERROR: Stack trace: ' . $e->getTraceAsString());
                $this->addFlash('warning', 'Event berhasil dibuat tetapi notifikasi gagal dikirim.');
            }

            $this->addFlash('success', 'Event berhasil dibuat dan notifikasi telah dikirim ke pegawai!');
            return $this->redirectToRoute('app_admin_event_index');
        }

        // Ambil stats untuk badge sidebar
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/event/new.html.twig', array_merge([
            'event' => $event,
            'form' => $form->createView(),
            'unitKerjas' => $unitKerjas,
            'unitChoicesCount' => count($unitChoices),
            'admin' => $admin
        ], $sidebarStats));
    }

    #[Route('/{id}', name: 'app_admin_event_show', requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        $unitKerjas = $this->entityManager->getRepository(UnitKerja::class)->findAll();
        
        return $this->render('admin/event/show.html.twig', [
            'event' => $event,
            'unitKerjas' => $unitKerjas,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_event_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Event $event): Response
    {
        // # CONTROLLER FORM FIX: Ambil unit kerja dengan urutan nama untuk consistency
        $unitKerjas = $this->entityManager->getRepository(UnitKerja::class)->findBy([], ['namaUnit' => 'ASC']);
        $unitChoices = [];
        foreach ($unitKerjas as $unit) {
            $unitChoices[$unit->getNamaUnit()] = $unit->getId();
        }
        
        $form = $this->createForm(EventType::class, $event, [
            'unit_choices' => $unitChoices
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Event berhasil diperbarui!');
            return $this->redirectToRoute('app_admin_event_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            // # CONTROLLER FORM FIX: Data untuk debugging dan template
            'unitKerjas' => $unitKerjas,
            'unitChoicesCount' => count($unitChoices)
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_event_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Event $event): Response
    {
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->request->get('_token'))) {
            try {
                // Hapus semua notifikasi yang terkait dengan event ini terlebih dahulu
                $notifikasiRepo = $this->entityManager->getRepository(\App\Entity\Notifikasi::class);
                $notifikasiTerkait = $notifikasiRepo->findBy(['event' => $event]);

                foreach ($notifikasiTerkait as $notifikasi) {
                    $this->entityManager->remove($notifikasi);
                }

                // Hapus semua event absensi yang terkait
                $eventAbsensiRepo = $this->entityManager->getRepository(\App\Entity\EventAbsensi::class);
                $eventAbsensiTerkait = $eventAbsensiRepo->findBy(['event' => $event]);

                foreach ($eventAbsensiTerkait as $eventAbsensi) {
                    $this->entityManager->remove($eventAbsensi);
                }

                // Sekarang hapus event
                $this->entityManager->remove($event);
                $this->entityManager->flush();

                $this->addFlash('success', 'Event berhasil dihapus bersama dengan semua notifikasi dan data absensi terkait!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Gagal menghapus event: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF tidak valid!');
        }

        return $this->redirectToRoute('app_admin_event_index');
    }

    #[Route('/api/toggle-status/{id}', name: 'app_admin_event_toggle_status', methods: ['POST'])]
    public function toggleStatus(Event $event): JsonResponse
    {
        try {
            // Toggle status antara aktif, selesai, dibatalkan
            $currentStatus = $event->getStatus();
            $newStatus = match($currentStatus) {
                'aktif' => 'selesai',
                'selesai' => 'aktif',
                'dibatalkan' => 'aktif',
                default => 'aktif'
            };
            
            $event->setStatus($newStatus);
            $event->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "Status event berhasil diubah menjadi {$newStatus}",
                'new_status' => $newStatus,
                'status_badge' => $event->getStatusBadge(),
                'status_icon' => $event->getStatusIcon()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status event'
            ], 500);
        }
    }

    #[Route('/api/calendar-data', name: 'app_admin_event_calendar_api')]
    public function calendarApi(Request $request): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        $eventRepo = $this->entityManager->getRepository(Event::class);
        
        $qb = $eventRepo->createQueryBuilder('e');
        
        if ($start) {
            $startDate = new \DateTime($start);
            $qb->andWhere('e.tanggalMulai >= :start')
               ->setParameter('start', $startDate);
        }
        
        if ($end) {
            $endDate = new \DateTime($end);
            $qb->andWhere('e.tanggalMulai <= :end')
               ->setParameter('end', $endDate);
        }

        $events = $qb->getQuery()->getResult();

        $calendarEvents = [];
        foreach ($events as $event) {
            $calendarEvents[] = [
                'id' => $event->getId(),
                'title' => $event->getJudulEvent(),
                'start' => $event->getTanggalMulai()->format('Y-m-d\TH:i:s'),
                'end' => $event->getTanggalSelesai() ? $event->getTanggalSelesai()->format('Y-m-d\TH:i:s') : null,
                'color' => $event->getWarna(),
                'description' => $event->getDeskripsi(),
                'location' => $event->getLokasi(),
                'status' => $event->getStatus(),
                'category' => $event->getKategoriNama()
            ];
        }

        return new JsonResponse($calendarEvents);
    }

    // # CONTROLLER FORM FIX: API endpoint untuk mendapatkan daftar unit kerja
    #[Route('/api/unit-kerja', name: 'app_admin_event_api_unit_kerja')]
    public function getUnitKerjaApi(): JsonResponse
    {
        try {
            $unitKerjas = $this->entityManager->getRepository(UnitKerja::class)->findBy([], ['namaUnit' => 'ASC']);
            
            $unitData = [];
            foreach ($unitKerjas as $unit) {
                $unitData[] = [
                    'id' => $unit->getId(),
                    'nama_unit' => $unit->getNamaUnit(),
                    'label' => $unit->getNamaUnit() // untuk display di frontend
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $unitData,
                'total' => count($unitData)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengambil data unit kerja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/download-kehadiran', name: 'app_admin_event_download_kehadiran', requirements: ['id' => '\\d+'])]
    public function downloadKehadiran(Event $event): StreamedResponse
    {
        if (!$event->getButuhAbsensi()) {
            throw $this->createNotFoundException('Event ini tidak memerlukan absensi');
        }

        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        
        // Tentukan peserta berdasarkan target audience
        if ($event->isTargetAll()) {
            // Semua pegawai
            $peserta = $pegawaiRepo->findBy(['statusKepegawaian' => 'aktif'], ['nama' => 'ASC']);
        } else {
            // Pegawai dari unit kerja yang ditargetkan
            $targetUnits = $event->getTargetUnits();
            $peserta = [];
            
            if ($targetUnits) {
                $peserta = $pegawaiRepo->createQueryBuilder('p')
                    ->leftJoin('p.unitKerjaEntity', 'u')
                    ->where('p.statusKepegawaian = :status')
                    ->andWhere('u.id IN (:units)')
                    ->setParameter('status', 'aktif')
                    ->setParameter('units', $targetUnits)
                    ->orderBy('p.nama', 'ASC')
                    ->getQuery()
                    ->getResult();
            }
        }

        // Buat nama file
        $fileName = sprintf('Kehadiran_%s_%s.xlsx', 
            preg_replace('/[^A-Za-z0-9]/', '_', $event->getJudulEvent()),
            $event->getTanggalMulai()->format('Y_m_d')
        );

        $response = new StreamedResponse(function() use ($event, $peserta) {
            // Buat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header event
            $sheet->setCellValue('A1', 'DAFTAR KEHADIRAN EVENT');
            $sheet->setCellValue('A2', 'Judul Event: ' . $event->getJudulEvent());
            $sheet->setCellValue('A3', 'Tanggal: ' . $event->getTanggalMulai()->format('d/m/Y H:i'));
            $sheet->setCellValue('A4', 'Lokasi: ' . ($event->getLokasi() ?: '-'));
            
            // Kosongkan baris untuk pemisah
            $currentRow = 6;
            
            // Header tabel - diperbarui untuk system signature + timestamp
            $sheet->setCellValue('A' . $currentRow, 'No');
            $sheet->setCellValue('B' . $currentRow, 'NIP');
            $sheet->setCellValue('C' . $currentRow, 'Nama Pegawai');
            $sheet->setCellValue('D' . $currentRow, 'Unit Kerja');
            $sheet->setCellValue('E' . $currentRow, 'Status Kehadiran');
            $sheet->setCellValue('F' . $currentRow, 'Waktu Absen');
            $sheet->setCellValue('G' . $currentRow, 'Tanda Tangan Digital');
            
            // Style header
            $headerStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => 'E5E7EB']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            
            $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray($headerStyle);
            
            // UPDATED LOGIC - Ambil data peserta yang benar-benar absen saja
            $eventAbsensiRepo = $this->entityManager->getRepository(\App\Entity\EventAbsensi::class);
            $daftarAbsensi = $eventAbsensiRepo->findBy(['event' => $event], ['waktuAbsen' => 'ASC']);
            
            $currentRow++;
            $no = 1;
            
            // STATIC SIGNATURE EXPORT - Loop hanya pegawai yang benar-benar absen
            foreach ($daftarAbsensi as $absensi) {
                $pegawai = $absensi->getUser();
                
                $sheet->setCellValue('A' . $currentRow, $no);
                $sheet->setCellValue('B' . $currentRow, $pegawai->getNip());
                $sheet->setCellValue('C' . $currentRow, $pegawai->getNama());
                $sheet->setCellValue('D' . $currentRow, $pegawai->getUnitKerjaEntity()?->getNamaUnit() ?: '-');
                $sheet->setCellValue('E' . $currentRow, ucfirst($absensi->getStatus()));
                $sheet->setCellValue('F' . $currentRow, $absensi->getWaktuAbsen()->format('d/m/Y H:i:s'));
                
                // TANDA TANGAN DIGITAL - Ambil dari field statis pegawai
                if ($pegawai->hasTandaTangan()) {
                    try {
                        // Tambahkan gambar tanda tangan ke spreadsheet
                        $signatureFilePath = $pegawai->getPublicTandaTanganPath();
                        
                        if ($signatureFilePath && file_exists($signatureFilePath)) {
                            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                            $drawing->setName('Signature_' . $pegawai->getNip());
                            $drawing->setDescription('Tanda Tangan Digital ' . $pegawai->getNama());
                            $drawing->setPath($signatureFilePath);
                            $drawing->setHeight(40); // Tinggi 40px sesuai dengan row height
                            $drawing->setCoordinates('G' . $currentRow);
                            $drawing->setWorksheet($sheet);
                            
                            $sheet->setCellValue('G' . $currentRow, 'Digital Signature');
                        } else {
                            $sheet->setCellValue('G' . $currentRow, 'File tidak ditemukan');
                        }
                    } catch (\Exception $e) {
                        error_log('Error adding signature to Excel: ' . $e->getMessage());
                        $sheet->setCellValue('G' . $currentRow, 'Error loading signature');
                    }
                } else {
                    $sheet->setCellValue('G' . $currentRow, 'Belum upload tanda tangan');
                }
                
                // Style data rows
                $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
                
                $currentRow++;
                $no++;
            }
            
            // Jika tidak ada yang absen, tambahkan row informasi
            if (empty($daftarAbsensi)) {
                $sheet->setCellValue('A' . $currentRow, '-');
                $sheet->setCellValue('B' . $currentRow, '-');
                $sheet->setCellValue('C' . $currentRow, 'Belum ada yang absen');
                $sheet->setCellValue('D' . $currentRow, '-');
                $sheet->setCellValue('E' . $currentRow, '-');
                $sheet->setCellValue('F' . $currentRow, '-');
                $sheet->setCellValue('G' . $currentRow, '-');
                
                $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
            }
            
            // Auto-size kolom - updated untuk kolom G
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set minimum width untuk kolom tanda tangan
            $sheet->getColumnDimension('G')->setWidth(20);
            
            // Set tinggi baris untuk tanda tangan - mulai dari data pertama
            $dataStartRow = 7;
            for ($row = $dataStartRow; $row < $currentRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(50); // Tinggi diperbesar untuk tanda tangan
            }

            // Output file
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}