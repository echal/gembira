<?php

namespace App\Command;

use App\Service\RankingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command untuk reset dan recalculate ranking bulanan
 *
 * Command ini biasanya dijalankan otomatis via cron setiap awal bulan (tanggal 1)
 * untuk memulai perhitungan ranking bulan baru.
 *
 * Usage:
 * - php bin/console app:reset-ranking (untuk bulan ini)
 * - php bin/console app:reset-ranking --tahun=2025 --bulan=2 (untuk bulan tertentu)
 *
 * Cron job (setiap tanggal 1 pukul 00:00):
 * 0 0 1 * * cd /path/to/project && php bin/console app:reset-ranking >> /var/log/ranking-reset.log 2>&1
 */
#[AsCommand(
    name: 'app:reset-ranking',
    description: 'Reset dan recalculate ranking bulanan untuk periode baru',
)]
class ResetRankingCommand extends Command
{
    public function __construct(
        private RankingService $rankingService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'tahun',
                null,
                InputOption::VALUE_OPTIONAL,
                'Tahun yang akan direset (default: tahun sekarang)'
            )
            ->addOption(
                'bulan',
                null,
                InputOption::VALUE_OPTIONAL,
                'Bulan yang akan direset (default: bulan sekarang)'
            )
            ->setHelp(
                <<<'HELP'
                Command <info>app:reset-ranking</info> digunakan untuk reset dan recalculate
                ranking bulanan setiap awal bulan.

                <comment>Contoh penggunaan:</comment>

                  # Reset ranking untuk bulan ini
                  <info>php bin/console app:reset-ranking</info>

                  # Reset ranking untuk bulan tertentu
                  <info>php bin/console app:reset-ranking --tahun=2025 --bulan=2</info>

                <comment>Setup Cron Job (Linux/macOS):</comment>

                  # Jalankan setiap tanggal 1 pukul 00:00
                  <info>0 0 1 * * cd /path/to/project && php bin/console app:reset-ranking</info>

                <comment>Setup Task Scheduler (Windows):</comment>

                  1. Buka Task Scheduler
                  2. Create Basic Task
                  3. Trigger: Monthly, Day 1, Time 00:00
                  4. Action: Start a program
                     Program: C:\xampp\php\php.exe
                     Arguments: C:\xampp\htdocs\gembira\bin\console app:reset-ranking
                     Start in: C:\xampp\htdocs\gembira

                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Ambil parameter tahun dan bulan (atau gunakan default)
        $tahun = $input->getOption('tahun');
        $bulan = $input->getOption('bulan');

        // Validasi input
        if ($tahun !== null && (!is_numeric($tahun) || $tahun < 2020 || $tahun > 2100)) {
            $io->error('Tahun harus berupa angka antara 2020-2100');
            return Command::FAILURE;
        }

        if ($bulan !== null && (!is_numeric($bulan) || $bulan < 1 || $bulan > 12)) {
            $io->error('Bulan harus berupa angka antara 1-12');
            return Command::FAILURE;
        }

        // Konversi ke integer jika ada
        $tahun = $tahun ? (int)$tahun : null;
        $bulan = $bulan ? (int)$bulan : null;

        // Dapatkan info periode
        $now = new \DateTime();
        $tahunAktif = $tahun ?? (int)$now->format('Y');
        $bulanAktif = $bulan ?? (int)$now->format('n');
        $periode = sprintf('%04d-%02d', $tahunAktif, $bulanAktif);

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $io->title('Reset Ranking Bulanan - Aplikasi GEMBIRA');
        $io->text([
            'Periode: ' . $namaBulan[$bulanAktif] . ' ' . $tahunAktif,
            'Tanggal: ' . $now->format('d/m/Y H:i:s'),
            ''
        ]);

        try {
            $io->section('Memulai proses reset ranking...');

            // Reset ranking bulanan
            $io->text('⏳ Menghitung ulang ranking bulanan...');
            $result = $this->rankingService->resetMonthlyRanking($tahun, $bulan);

            $io->success([
                'Ranking bulanan berhasil direset!',
                'Periode: ' . $result['periode'],
                'Total pegawai: ' . $result['total_pegawai'],
            ]);

            // Tampilkan informasi tambahan
            $io->section('Informasi');
            $io->definitionList(
                ['Periode' => $result['periode']],
                ['Total Pegawai' => $result['total_pegawai'] . ' pegawai'],
                ['Status' => '✅ Berhasil'],
                ['Waktu Selesai' => (new \DateTime())->format('d/m/Y H:i:s')]
            );

            // Saran untuk setup cron job
            if ($tahun === null && $bulan === null) {
                $io->note([
                    'Command ini sebaiknya dijalankan otomatis setiap awal bulan.',
                    'Gunakan cron job (Linux/macOS) atau Task Scheduler (Windows).',
                    'Lihat --help untuk panduan setup.'
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Terjadi kesalahan saat reset ranking:',
                $e->getMessage()
            ]);

            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
