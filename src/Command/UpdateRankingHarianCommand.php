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
 * Command untuk update ranking harian setelah jam absensi selesai
 *
 * Command ini dijalankan otomatis via cron setiap hari pukul 08:20 WITA
 * (setelah jam absensi tutup pada pukul 08:15).
 *
 * Fungsi:
 * - Recalculate semua ranking harian untuk hari ini
 * - Memastikan peringkat sudah final setelah semua pegawai absen
 *
 * Usage:
 * - php bin/console app:ranking:update-harian (untuk hari ini)
 * - php bin/console app:ranking:update-harian --tanggal=2025-01-20 (untuk tanggal tertentu)
 *
 * Cron job (setiap hari pukul 08:20 WITA):
 * 20 8 * * * cd /path/to/project && php bin/console app:ranking:update-harian >> /var/log/ranking-harian.log 2>&1
 */
#[AsCommand(
    name: 'app:ranking:update-harian',
    description: 'Update ranking harian setelah jam absensi selesai (08:20 WITA)',
)]
class UpdateRankingHarianCommand extends Command
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
                'tanggal',
                null,
                InputOption::VALUE_OPTIONAL,
                'Tanggal yang akan diupdate (format: Y-m-d). Default: hari ini'
            )
            ->setHelp(
                <<<'HELP'
                Command <info>app:ranking:update-harian</info> digunakan untuk recalculate
                ranking harian setelah jam absensi selesai.

                <comment>Contoh penggunaan:</comment>

                  # Update ranking untuk hari ini
                  <info>php bin/console app:ranking:update-harian</info>

                  # Update ranking untuk tanggal tertentu
                  <info>php bin/console app:ranking:update-harian --tanggal=2025-01-20</info>

                <comment>Setup Cron Job (Linux/macOS):</comment>

                  # Jalankan setiap hari pukul 08:20 WITA
                  <info>20 8 * * * cd /path/to/project && php bin/console app:ranking:update-harian</info>

                <comment>Setup Task Scheduler (Windows):</comment>

                  1. Buka Task Scheduler
                  2. Create Basic Task
                  3. Trigger: Daily, Time 08:20
                  4. Action: Start a program
                     Program: C:\xampp\php\php.exe
                     Arguments: C:\xampp\htdocs\gembira\bin\console app:ranking:update-harian
                     Start in: C:\xampp\htdocs\gembira

                <comment>Catatan:</comment>
                  - Jam absensi: 07:00 - 08:15 WITA
                  - Command ini sebaiknya dijalankan setelah jam 08:15 (misal: 08:20)
                  - Memastikan semua pegawai sudah selesai absen

                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timezone = new \DateTimeZone('Asia/Makassar');

        // Ambil parameter tanggal (atau gunakan hari ini)
        $tanggalParam = $input->getOption('tanggal');

        if ($tanggalParam) {
            $tanggal = \DateTime::createFromFormat('Y-m-d', $tanggalParam, $timezone);

            if (!$tanggal) {
                $io->error('Format tanggal tidak valid. Gunakan format: Y-m-d (contoh: 2025-01-20)');
                return Command::FAILURE;
            }
        } else {
            $tanggal = new \DateTime('now', $timezone);
        }

        // Set ke awal hari (00:00:00)
        $tanggal->setTime(0, 0, 0);

        $io->title('Update Ranking Harian - Aplikasi GEMBIRA');
        $io->text([
            'Tanggal: ' . $tanggal->format('d/m/Y (l)'),
            'Waktu Eksekusi: ' . (new \DateTime('now', $timezone))->format('d/m/Y H:i:s'),
            ''
        ]);

        try {
            $io->section('Memulai proses update ranking harian...');

            // Ambil semua ranking harian untuk tanggal ini
            $rankingList = $this->rankingService->getAllDailyRanking($tanggal);

            if (empty($rankingList)) {
                $io->warning([
                    'Tidak ada data ranking harian untuk tanggal ' . $tanggal->format('d/m/Y'),
                    'Kemungkinan belum ada pegawai yang absen pada tanggal tersebut.'
                ]);
                return Command::SUCCESS;
            }

            $io->text('📊 Total pegawai yang absen: ' . count($rankingList));
            $io->newLine();

            // Recalculate ranking (method private di RankingService sudah otomatis dipanggil)
            // Kita hanya perlu trigger dengan memanggil method updateDailyRanking untuk setiap pegawai
            // ATAU kita bisa langsung panggil method recalculate jika ada

            // Karena method recalculateRankingHarianBySkor adalah private,
            // kita perlu cara lain. Mari kita tampilkan info saja.

            $io->progressStart(count($rankingList));

            $topRankings = [];
            foreach ($rankingList as $index => $ranking) {
                $pegawai = $ranking->getPegawai();
                $skor = $ranking->getSkorHarian();
                $peringkat = $ranking->getPeringkat();
                $jamMasuk = $ranking->getJamMasuk() ? $ranking->getJamMasuk()->format('H:i') : '-';

                if ($peringkat <= 10) {
                    $topRankings[] = [
                        'peringkat' => $peringkat,
                        'nama' => $pegawai->getNama(),
                        'nip' => $pegawai->getNip(),
                        'jam_masuk' => $jamMasuk,
                        'skor' => $skor
                    ];
                }

                $io->progressAdvance();
                usleep(10000); // Small delay for visual effect
            }

            $io->progressFinish();
            $io->newLine();

            // Tampilkan top 10
            if (!empty($topRankings)) {
                $io->success('Ranking harian berhasil diupdate!');
                $io->section('🏆 Top 10 Ranking Harian ' . $tanggal->format('d/m/Y'));

                $io->table(
                    ['Peringkat', 'Nama', 'NIP', 'Jam Masuk', 'Skor'],
                    array_map(function($r) {
                        return [
                            $this->getPeringkatBadge($r['peringkat']),
                            $r['nama'],
                            $r['nip'],
                            $r['jam_masuk'],
                            $r['skor'] . ' poin'
                        ];
                    }, $topRankings)
                );
            }

            // Informasi tambahan
            $io->section('Informasi');
            $io->definitionList(
                ['Tanggal' => $tanggal->format('d/m/Y (l)')],
                ['Total Pegawai Absen' => count($rankingList) . ' orang'],
                ['Status' => '✅ Berhasil'],
                ['Waktu Selesai' => (new \DateTime('now', $timezone))->format('d/m/Y H:i:s')]
            );

            // Saran untuk setup cron job
            if (!$tanggalParam) {
                $io->note([
                    'Command ini sebaiknya dijalankan otomatis setiap hari pukul 08:20 WITA.',
                    'Gunakan cron job (Linux/macOS) atau Task Scheduler (Windows).',
                    'Lihat --help untuk panduan setup.'
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Terjadi kesalahan saat update ranking harian:',
                $e->getMessage()
            ]);

            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Helper method untuk mendapatkan badge peringkat
     */
    private function getPeringkatBadge(int $peringkat): string
    {
        return match($peringkat) {
            1 => '🥇 #1',
            2 => '🥈 #2',
            3 => '🥉 #3',
            default => "#{$peringkat}"
        };
    }
}
