<?php

namespace App\Command;

use App\Entity\Pegawai;
use App\Repository\EventRepository;
use App\Repository\UserNotifikasiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-user-calendar-notification',
    description: 'Test User Calendar and Notification System'
)]
class TestUserCalendarNotificationCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private EventRepository $eventRepository;
    private UserNotifikasiRepository $userNotifikasiRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->eventRepository = $entityManager->getRepository(\App\Entity\Event::class);
        $this->userNotifikasiRepository = $entityManager->getRepository(\App\Entity\UserNotifikasi::class);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('nip', InputArgument::OPTIONAL, 'User NIP to test (default: 123456789)', '123456789');
        $this->addArgument('date', InputArgument::OPTIONAL, 'Date to test (default: 2025-08-29)', '2025-08-29');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nip = $input->getArgument('nip');
        $dateString = $input->getArgument('date');

        $io->title('🧪 Test User Calendar and Notification System');

        // Find user
        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        $user = $pegawaiRepo->findOneBy(['nip' => $nip]);

        if (!$user) {
            $io->error("User dengan NIP {$nip} tidak ditemukan!");
            return Command::FAILURE;
        }

        $io->section("👤 Testing User: {$user->getNama()} ({$nip})");

        $userUnit = $user->getUnitKerjaEntity();
        $io->text("🏢 Unit Kerja: " . ($userUnit ? $userUnit->getNamaUnit() . " (ID: {$userUnit->getId()})" : "NULL"));

        // Test Calendar Events
        $io->section('📅 Test Calendar Events');

        $testDate = new \DateTime($dateString);
        $io->text("🔍 Searching events for date: " . $testDate->format('d F Y'));

        // Simulate kalender query
        $calendarEvents = $this->eventRepository->findByDateForUser($testDate, $user);

        $io->text("📊 Found " . count($calendarEvents) . " events for this user on this date");

        if (!empty($calendarEvents)) {
            $eventTable = new Table($output);
            $eventTable->setHeaders(['ID', 'Judul Event', 'Waktu', 'Target Audience', 'Target Units']);

            foreach ($calendarEvents as $event) {
                $eventTable->addRow([
                    $event->getId(),
                    substr($event->getJudulEvent(), 0, 30) . (strlen($event->getJudulEvent()) > 30 ? '...' : ''),
                    $event->getTanggalMulai()->format('H:i'),
                    $event->getTargetAudience(),
                    json_encode($event->getTargetUnits())
                ]);
            }

            $eventTable->render();
        } else {
            $io->warning('❌ No events found for this user on this date');
        }

        // Test Notifications
        $io->section('🔔 Test User Notifications');

        $notifications = $this->userNotifikasiRepository->findNotificationsForUser($user, 10);
        $unreadCount = $this->userNotifikasiRepository->countUnreadForUser($user);

        $io->text("📊 Total notifications: " . count($notifications));
        $io->text("📊 Unread notifications: {$unreadCount}");

        if (!empty($notifications)) {
            $notifTable = new Table($output);
            $notifTable->setHeaders(['Judul', 'Tipe', 'Event', 'Status', 'Diterima', 'Dibaca']);

            foreach ($notifications as $un) {
                $notifikasi = $un->getNotifikasi();
                $notifTable->addRow([
                    substr($notifikasi->getJudul(), 0, 25) . (strlen($notifikasi->getJudul()) > 25 ? '...' : ''),
                    $notifikasi->getTipe(),
                    $notifikasi->getEvent() ? 'ID:' . $notifikasi->getEvent()->getId() : 'NULL',
                    $un->isRead() ? '✅ Read' : '⭕ Unread',
                    $un->getReceivedAt()->format('d/m H:i'),
                    $un->getReadAt() ? $un->getReadAt()->format('d/m H:i') : '-'
                ]);
            }

            $notifTable->render();
        } else {
            $io->warning('❌ No notifications found for this user');
        }

        // Success Summary
        $io->section('📊 Test Summary');

        $calendarOk = !empty($calendarEvents) ? '✅' : '❌';
        $notificationOk = !empty($notifications) ? '✅' : '❌';

        $io->listing([
            "{$calendarOk} Calendar Events: " . count($calendarEvents) . " events found for {$testDate->format('d F Y')}",
            "{$notificationOk} Notifications: " . count($notifications) . " total, {$unreadCount} unread",
            "🏢 User Unit: " . ($userUnit ? $userUnit->getNamaUnit() : "No unit assigned"),
            "📅 Test Date: " . $testDate->format('d F Y'),
        ]);

        if (!empty($calendarEvents) && !empty($notifications)) {
            $io->success('🎉 Both Calendar and Notification systems are working correctly!');
            return Command::SUCCESS;
        } else {
            $io->error('❌ One or both systems have issues');
            return Command::FAILURE;
        }
    }
}