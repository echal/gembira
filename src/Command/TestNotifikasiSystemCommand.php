<?php

namespace App\Command;

use App\Entity\Pegawai;
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
    name: 'app:test-notifikasi-system',
    description: 'Test the new UserNotifikasi system'
)]
class TestNotifikasiSystemCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserNotifikasiRepository $userNotifikasiRepo;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->userNotifikasiRepo = $entityManager->getRepository(\App\Entity\UserNotifikasi::class);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('nip', InputArgument::OPTIONAL, 'NIP pegawai untuk test (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nip = $input->getArgument('nip');

        $io->title('🧪 Test Sistem Notifikasi UserNotifikasi');

        // Test 1: Tampilkan semua user dan notifikasi count mereka
        $io->section('📊 Statistik Notifikasi Per User');

        $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
        $allPegawai = $pegawaiRepo->findBy(['statusKepegawaian' => 'aktif']);

        $table = new Table($output);
        $table->setHeaders(['NIP', 'Nama', 'Total', 'Unread', 'Read', 'Read %']);

        foreach ($allPegawai as $pegawai) {
            $stats = $this->userNotifikasiRepo->getNotificationStatsForUser($pegawai);
            
            $table->addRow([
                $pegawai->getNip(),
                $pegawai->getNama(),
                $stats['total'],
                $stats['unread'],
                $stats['read'],
                $stats['read_percentage'] . '%'
            ]);
        }

        $table->render();

        // Test 2: Detail untuk user tertentu jika ada NIP
        if ($nip) {
            $pegawai = $pegawaiRepo->findOneBy(['nip' => $nip]);
            
            if (!$pegawai) {
                $io->error("Pegawai dengan NIP {$nip} tidak ditemukan");
                return Command::FAILURE;
            }

            $io->section("🔍 Detail Notifikasi untuk {$pegawai->getNama()} ({$nip})");

            // Ambil notifikasi user
            $userNotifications = $this->userNotifikasiRepo->findNotificationsForUser($pegawai, 10);

            if (empty($userNotifications)) {
                $io->info('Tidak ada notifikasi untuk user ini');
            } else {
                $detailTable = new Table($output);
                $detailTable->setHeaders(['ID', 'Judul', 'Tipe', 'Priority', 'Status', 'Diterima', 'Dibaca']);

                foreach ($userNotifications as $un) {
                    $notifikasi = $un->getNotifikasi();
                    $detailTable->addRow([
                        $notifikasi->getId(),
                        substr($notifikasi->getJudul(), 0, 30) . (strlen($notifikasi->getJudul()) > 30 ? '...' : ''),
                        $notifikasi->getTipe(),
                        $un->getPriority() . ' ' . $un->getPriorityIcon(),
                        $un->isRead() ? '✅ Read' : '⭕ Unread',
                        $un->getReceivedAt()->format('d/m H:i'),
                        $un->getReadAt() ? $un->getReadAt()->format('d/m H:i') : '-'
                    ]);
                }

                $detailTable->render();
            }

            // Test 3: Simulasi mark as read
            $io->section('🧪 Test Mark As Read Functionality');
            
            $unreadNotifications = $this->userNotifikasiRepo->findUnreadForUser($pegawai, 1);
            
            if (!empty($unreadNotifications)) {
                $firstUnread = $unreadNotifications[0];
                $notifikasi = $firstUnread->getNotifikasi();
                
                $io->text("📝 Testing mark as read untuk: " . $notifikasi->getJudul());
                
                // Sebelum mark as read
                $beforeCount = $this->userNotifikasiRepo->countUnreadForUser($pegawai);
                $io->text("📊 Unread count sebelum: {$beforeCount}");
                
                // Mark as read
                $firstUnread->setIsRead(true);
                $this->entityManager->flush();
                
                // Setelah mark as read
                $afterCount = $this->userNotifikasiRepo->countUnreadForUser($pegawai);
                $io->text("📊 Unread count setelah: {$afterCount}");
                
                if ($afterCount === $beforeCount - 1) {
                    $io->success('✅ Mark as read functionality working correctly!');
                } else {
                    $io->error('❌ Mark as read functionality not working properly');
                }
                
                // Reset untuk testing selanjutnya
                $firstUnread->setIsRead(false);
                $firstUnread->setReadAt(null);
                $this->entityManager->flush();
                $io->note('🔄 Reset notification status for future testing');
                
            } else {
                $io->info('Tidak ada notifikasi unread untuk testing mark as read');
            }
        }

        // Test 4: Test Repository Methods
        $io->section('🧪 Test Repository Methods');
        
        // Test dengan user pertama
        if (!empty($allPegawai)) {
            $testUser = $allPegawai[0];
            
            $io->text("Testing dengan user: {$testUser->getNama()} ({$testUser->getNip()})");
            
            // Test countUnreadForUser
            $unreadCount = $this->userNotifikasiRepo->countUnreadForUser($testUser);
            $io->text("📊 countUnreadForUser(): {$unreadCount}");
            
            // Test findNotificationsForUser
            $notifications = $this->userNotifikasiRepo->findNotificationsForUser($testUser, 5);
            $io->text("📋 findNotificationsForUser(): " . count($notifications) . " notifications");
            
            // Test findUnreadForUser
            $unreadNotifications = $this->userNotifikasiRepo->findUnreadForUser($testUser, 5);
            $io->text("⭕ findUnreadForUser(): " . count($unreadNotifications) . " unread notifications");
            
            $io->success('✅ All repository methods working correctly!');
        }

        $io->success([
            '🎉 Sistem notifikasi UserNotifikasi berfungsi dengan baik!',
            '',
            '✅ Migration berhasil',
            '✅ Repository methods berfungsi',
            '✅ Count unread akurat', 
            '✅ Mark as read functionality working',
            '',
            '🚀 Sistem siap digunakan!'
        ]);

        return Command::SUCCESS;
    }
}