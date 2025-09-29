<?php

namespace App\Command;

use App\Service\NotifikasiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-notifikasi',
    description: 'Membersihkan notifikasi lama untuk menghemat ruang database'
)]
class CleanNotifikasiCommand extends Command
{
    private NotifikasiService $notifikasiService;

    public function __construct(NotifikasiService $notifikasiService)
    {
        parent::__construct();
        $this->notifikasiService = $notifikasiService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('days', InputArgument::OPTIONAL, 'Hapus notifikasi lebih lama dari X hari', 30)
            ->setHelp('Command ini akan menghapus notifikasi yang lebih lama dari jumlah hari yang ditentukan untuk menghemat ruang database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getArgument('days');

        if ($days < 1) {
            $io->error('Jumlah hari harus minimal 1');
            return Command::FAILURE;
        }

        $io->title('Membersihkan Notifikasi Lama');
        $io->note("Menghapus notifikasi lebih dari {$days} hari yang lalu...");

        try {
            $this->notifikasiService->bersihkanNotifikasiLama($days);
            $io->success("Berhasil membersihkan notifikasi lama (lebih dari {$days} hari)");
            
            $io->info([
                'Untuk menjalankan secara otomatis, tambahkan ke crontab:',
                '# Bersihkan notifikasi setiap hari pukul 02:00',
                '0 2 * * * /usr/bin/php /path/to/project/bin/console app:clean-notifikasi'
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Gagal membersihkan notifikasi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}