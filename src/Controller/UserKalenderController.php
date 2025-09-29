<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventAbsensi;
use App\Entity\Pegawai;
use App\Repository\EventAbsensiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kalender')]
final class UserKalenderController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_user_kalender')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Pastikan user adalah pegawai, bukan admin
        if (!$user instanceof Pegawai) {
            throw $this->createAccessDeniedException('Hanya pegawai yang dapat mengakses kalender.');
        }

        // Ambil bulan dan tahun dari parameter atau gunakan bulan sekarang
        $month = $request->query->getInt('month', (int)date('m'));
        $year = $request->query->getInt('year', (int)date('Y'));

        // Validasi bulan dan tahun
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }
        if ($year < 2020 || $year > 2030) {
            $year = (int)date('Y');
        }

        // Ambil event untuk bulan yang dipilih dengan filter unit kerja
        $eventRepo = $this->entityManager->getRepository(Event::class);
        $events = $eventRepo->findByMonthForUser($month, $year, $user);
        $eventDates = $eventRepo->getEventDatesInMonthForUser($month, $year, $user);

        // Generate kalender data
        $calendarData = $this->generateCalendarData($month, $year, $eventDates);

        return $this->render('user/kalender/index.html.twig', [
            'pegawai' => $user,
            'events' => $events,
            'calendar_data' => $calendarData,
            'current_month' => $month,
            'current_year' => $year,
            'month_name' => $this->getMonthName($month),
            'today' => new \DateTime(),
            'prev_month' => $this->getPreviousMonth($month, $year),
            'next_month' => $this->getNextMonth($month, $year),
        ]);
    }

    #[Route('/api/events/{date}', name: 'app_user_kalender_api_events')]
    #[IsGranted('ROLE_USER')]
    public function getEventsByDate(string $date): JsonResponse
    {
        $user = $this->getUser();
        
        // perbaikan query event: validasi user adalah pegawai
        if (!$user instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Akses ditolak. User harus pegawai.',
                'events' => [],
                'count' => 0
            ], 403);
        }

        try {
            // perbaikan query event: validasi format tanggal YYYY-MM-DD
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Format tanggal harus YYYY-MM-DD',
                    'events' => [],
                    'count' => 0
                ], 400);
            }

            $dateObj = new \DateTime($date);
            
            // perbaikan query event: log untuk debug internal dengan detail lengkap
            error_log("=== API Controller Debug ===");
            error_log("API Call: Loading events for date {$date} for user {$user->getNip()}");
            error_log("Controller Debug - User details: NIP=" . $user->getNip() . ", Name=" . $user->getNama());
            error_log("Controller Debug - User unit: " . ($user->getUnitKerjaEntity() ? $user->getUnitKerjaEntity()->getNamaUnit() . " (ID=" . $user->getUnitKerjaEntity()->getId() . ")" : "NULL"));
            error_log("Controller Debug - Parsed date: " . $dateObj->format('Y-m-d H:i:s'));
            
            $eventRepo = $this->entityManager->getRepository(Event::class);
            
            // perbaikan query event: log sebelum dan sesudah query
            error_log("Controller Debug - Calling EventRepository->findByDateForUser()...");
            $events = $eventRepo->findByDateForUser($dateObj, $user);
            error_log("Controller Debug - Repository returned " . count($events) . " events");

            $eventData = [];
            foreach ($events as $event) {
                // perbaikan query event: log setiap event yang diproses
                error_log("Controller Debug - Processing event: ID=" . $event->getId() . ", Title='" . $event->getJudulEvent() . "'");
                
                // perbaikan query event: pastikan semua data event lengkap dan valid
                $eventItem = [
                    'id' => $event->getId(),
                    'nama' => $event->getJudulEvent(),
                    'deskripsi' => $event->getDeskripsi() ?? '',
                    'kategori' => $event->getKategoriNama(),
                    'icon' => $event->getKategoriIcon(),
                    'emoji_badge' => $event->getKategoriBadgeEmoji(),
                    'badge_class' => $event->getKategoriBadgeClass(),
                    'warna' => $event->getWarna() ?? '#87CEEB',
                    'waktu' => $event->getTanggalMulai()->format('H:i'),
                    'lokasi' => $event->getLokasi() ?? '',
                    'link_meeting' => $event->getLinkMeeting(),
                    'can_join_meeting' => $event->canJoinMeeting(),
                    'meeting_expired' => $event->isMeetingExpired(),
                ];
                
                // perbaikan query event: validasi data sebelum menambahkan ke array
                if (empty($eventItem['nama'])) {
                    error_log("Controller Debug - WARNING: Event ID " . $event->getId() . " has empty title!");
                }
                
                $eventData[] = $eventItem;
                error_log("Controller Debug - Event data: " . json_encode($eventItem, JSON_UNESCAPED_UNICODE));
            }

            // perbaikan query event: format tanggal Indonesia untuk display
            $tanggalIndonesia = $dateObj->format('l, d F Y');
            $tanggalIndonesia = str_replace([
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ], [
                'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu',
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ], $tanggalIndonesia);

            // perbaikan query event: response sukses dengan informasi lengkap
            $response = [
                'success' => true,
                'events' => $eventData,
                'date' => $tanggalIndonesia,
                'date_raw' => $date,
                'count' => count($eventData),
                'user_unit' => $user->getNamaUnitKerja()
            ];
            
            // perbaikan query event: log response final yang dikirim ke frontend
            error_log("Controller Debug - Final response count: " . count($eventData));
            error_log("Controller Debug - Response size: " . strlen(json_encode($response)));
            error_log("Controller Debug - Full response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
            error_log("=== API Controller Debug END ===");
            
            return new JsonResponse($response);

        } catch (\Exception $e) {
            // perbaikan query event: log error internal tapi tidak tampilkan ke user
            error_log("Error loading events for date {$date}: " . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat data event. Silakan coba lagi.',
                'events' => [],
                'count' => 0
            ], 500);
        }
    }

    private function generateCalendarData(int $month, int $year, array $eventDates): array
    {
        $firstDay = new \DateTime("{$year}-{$month}-01");
        $lastDay = new \DateTime($firstDay->format('Y-m-t'));
        
        // Hari pertama dalam minggu (1 = Senin, 7 = Minggu)
        $firstDayOfWeek = (int)$firstDay->format('N');
        $daysInMonth = (int)$lastDay->format('d');
        
        // Convert event dates to day numbers for easy lookup
        $eventDays = [];
        foreach ($eventDates as $eventDate) {
            $day = (int)(new \DateTime($eventDate))->format('d');
            $eventDays[$day] = true;
        }

        return [
            'first_day_of_week' => $firstDayOfWeek,
            'days_in_month' => $daysInMonth,
            'event_days' => $eventDays,
            'month' => $month,
            'year' => $year
        ];
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? 'Unknown';
    }

    private function getPreviousMonth(int $month, int $year): array
    {
        if ($month == 1) {
            return ['month' => 12, 'year' => $year - 1];
        }
        return ['month' => $month - 1, 'year' => $year];
    }

    private function getNextMonth(int $month, int $year): array
    {
        if ($month == 12) {
            return ['month' => 1, 'year' => $year + 1];
        }
        return ['month' => $month + 1, 'year' => $year];
    }

    #[Route('/api/absen-event/{id}', name: 'app_user_kalender_absen_event', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function absenEvent(Event $event): JsonResponse
    {
        $user = $this->getUser();
        
        // Debug logging
        error_log("🎯 ABSEN EVENT DEBUG: Event ID = " . $event->getId() . ", Title = " . $event->getJudulEvent());
        error_log("🎯 ABSEN EVENT DEBUG: User = " . ($user ? $user->getUserIdentifier() : 'NULL'));
        
        // Pastikan user adalah pegawai
        if (!$user instanceof Pegawai) {
            error_log("🎯 ABSEN EVENT ERROR: User is not instance of Pegawai");
            return new JsonResponse([
                'success' => false,
                'message' => 'User tidak valid'
            ], 403);
        }

        // Cek apakah event membutuhkan absensi
        if (!$event->isButuhAbsensi()) {
            error_log("🎯 ABSEN EVENT ERROR: Event does not require absensi");
            return new JsonResponse([
                'success' => false,
                'message' => 'Event ini tidak membutuhkan absensi'
            ], 400);
        }

        // SIGNATURE REQUIREMENT - Pastikan pegawai sudah upload tanda tangan
        if (!$user->hasTandaTangan()) {
            error_log("🎯 ABSEN EVENT ERROR: User has no digital signature");
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda belum mengupload tanda tangan digital. Silakan upload tanda tangan di menu Profil > Tanda Tangan terlebih dahulu.',
                'action_required' => 'upload_signature',
                'redirect_url' => '/profile/tanda-tangan'
            ], 400);
        }

        // Cek apakah user adalah target dari event ini
        if (!$event->isUserTargeted($user)) {
            error_log("🎯 ABSEN EVENT ERROR: Event not targeted for user's unit");
            return new JsonResponse([
                'success' => false,
                'message' => 'Event ini tidak ditargetkan untuk unit kerja Anda'
            ], 403);
        }

        // Cek apakah user sudah absen
        $absensiRepo = $this->entityManager->getRepository(EventAbsensi::class);
        if ($absensiRepo->isUserAlreadyAttended($event, $user)) {
            error_log("🎯 ABSEN EVENT ERROR: User already attended this event");
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda sudah absen untuk event ini'
            ], 400);
        }

        // Validasi waktu absensi
        $timeValidation = $event->validateAbsensiTime();
        if (!$timeValidation['valid']) {
            error_log("🎯 ABSEN EVENT ERROR: Time validation failed - " . $timeValidation['message']);
            return new JsonResponse([
                'success' => false,
                'message' => $timeValidation['message']
            ], 400);
        }

        try {
            // STATIC SIGNATURE ABSENSI - Buat record absensi dengan timestamp waktu hadir
            // Tanda tangan diambil dari field statis di entity Pegawai, tidak perlu disimpan ulang
            $absensi = new EventAbsensi();
            $absensi->setEvent($event);
            $absensi->setUser($user);
            $absensi->setStatus('hadir');
            $absensi->setWaktuAbsen(new \DateTime()); // Waktu hadir diisi otomatis saat absensi

            $this->entityManager->persist($absensi);
            $this->entityManager->flush();

            // Response sukses dengan informasi lengkap
            return new JsonResponse([
                'success' => true,
                'message' => 'Absensi berhasil dicatat untuk event "' . $event->getJudulEvent() . '"',
                'waktu_absen' => $absensi->getWaktuAbsen()->format('d/m/Y H:i:s'),
                'has_signature' => true, // Konfirmasi bahwa tanda tangan tersedia
                'signature_info' => 'Tanda tangan diambil dari data profil Anda'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan absensi'
            ], 500);
        }
    }

    #[Route('/api/riwayat-absensi', name: 'app_user_kalender_riwayat_absensi')]
    #[IsGranted('ROLE_USER')]
    public function riwayatAbsensi(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof Pegawai) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User tidak valid'
            ], 403);
        }

        $absensiRepo = $this->entityManager->getRepository(EventAbsensi::class);
        $riwayat = $absensiRepo->findUserAttendanceHistory($user, 10);

        $data = [];
        foreach ($riwayat as $absensi) {
            $data[] = [
                'event_id' => $absensi->getEvent()->getId(),
                'event_judul' => $absensi->getEvent()->getJudulEvent(),
                'waktu_absen' => $absensi->getWaktuAbsen()->format('d/m/Y H:i:s'),
                'status' => $absensi->getStatus(),
                'status_badge' => $absensi->getStatusBadge()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ]);
    }
}