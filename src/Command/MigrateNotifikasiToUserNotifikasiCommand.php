<?php

namespace App\Command;

use App\Entity\Notifikasi;
use App\Entity\UserNotifikasi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-notifikasi-to-user-notifikasi',
    description: 'Migrate existing Notifikasi to UserNotifikasi pivot table'
)]
class MigrateNotifikasiToUserNotifikasiCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔄 Migrate Existing Notifikasi to UserNotifikasi Pivot Table');

        // Ambil semua notifikasi yang ada
        $notifikasiRepo = $this->entityManager->getRepository(Notifikasi::class);
        $existingNotifikasi = $notifikasiRepo->findAll();

        $io->info(sprintf('Found %d existing notifications to migrate', count($existingNotifikasi)));

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($existingNotifikasi as $notifikasi) {
            // Skip jika notifikasi tidak punya pegawai (data corrupt)
            if (!$notifikasi->getPegawai()) {
                $io->warning(sprintf('Skipping notification ID %d: no pegawai assigned', $notifikasi->getId()));
                $skippedCount++;
                continue;
            }

            // Cek apakah sudah ada UserNotifikasi untuk kombinasi ini
            $existingUserNotifikasi = $this->entityManager->getRepository(UserNotifikasi::class)
                ->findOneBy([
                    'pegawai' => $notifikasi->getPegawai(),
                    'notifikasi' => $notifikasi
                ]);

            if ($existingUserNotifikasi) {
                $skippedCount++;
                continue;
            }

            // Buat UserNotifikasi baru
            $userNotifikasi = new UserNotifikasi();
            $userNotifikasi->setPegawai($notifikasi->getPegawai());
            $userNotifikasi->setNotifikasi($notifikasi);
            $userNotifikasi->setIsRead($notifikasi->isSudahDibaca());
            $userNotifikasi->setReceivedAt($notifikasi->getWaktuDibuat());
            
            // Set read_at jika sudah dibaca
            if ($notifikasi->isSudahDibaca() && $notifikasi->getWaktuDibaca()) {
                $userNotifikasi->setReadAt($notifikasi->getWaktuDibaca());
            }

            // Tentukan prioritas berdasarkan tipe
            $priority = match($notifikasi->getTipe()) {
                'reminder' => 'high',
                'event_baru' => 'normal',
                'pengumuman' => 'normal',
                'absensi' => 'high',
                default => 'normal'
            };
            $userNotifikasi->setPriority($priority);

            $this->entityManager->persist($userNotifikasi);
            $migratedCount++;

            // Flush setiap 100 record untuk memory efficiency
            if ($migratedCount % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->progressStart(count($existingNotifikasi));
                $io->progressAdvance($migratedCount);
            }
        }

        // Final flush
        $this->entityManager->flush();

        $io->success([
            '✅ Migration completed successfully!',
            sprintf('📊 Migrated: %d notifications', $migratedCount),
            sprintf('⏭️ Skipped: %d notifications', $skippedCount),
            sprintf('📋 Total: %d notifications processed', count($existingNotifikasi))
        ]);

        $io->note([
            '🔧 Next steps:',
            '1. Test the notification system with the new UserNotifikasi pivot table',
            '2. Consider running app:test-event-data command to verify',
            '3. The old Notifikasi.pegawai field can be deprecated in future versions'
        ]);

        return Command::SUCCESS;
    }
}