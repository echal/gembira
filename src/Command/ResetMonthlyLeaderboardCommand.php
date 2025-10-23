<?php

namespace App\Command;

use App\Repository\MonthlyLeaderboardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:leaderboard:reset-monthly',
    description: 'Reset monthly leaderboard XP at the start of each month (preserves total XP)',
)]
class ResetMonthlyLeaderboardCommand extends Command
{
    public function __construct(
        private MonthlyLeaderboardRepository $leaderboardRepository,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'This command resets the monthly leaderboard at the start of each month. ' .
                'It does NOT delete or reset total_xp - only creates a new monthly period. ' .
                'Old monthly data is preserved for historical tracking.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $currentDate = new \DateTime();
        $currentMonth = (int) $currentDate->format('n');
        $currentYear = (int) $currentDate->format('Y');

        $previousDate = (clone $currentDate)->modify('-1 month');
        $previousMonth = (int) $previousDate->format('n');
        $previousYear = (int) $previousDate->format('Y');

        $io->title('Monthly Leaderboard Reset');
        $io->section('Current Period Information');

        $io->table(
            ['Parameter', 'Value'],
            [
                ['Previous Month', $previousMonth . '/' . $previousYear],
                ['Current Month', $currentMonth . '/' . $currentYear],
                ['Reset Date', $currentDate->format('Y-m-d H:i:s')],
            ]
        );

        // Get previous month's leaderboard for reporting
        $previousLeaderboard = $this->leaderboardRepository->findTop10ByMonthYear($previousMonth, $previousYear);

        if (!empty($previousLeaderboard)) {
            $io->section('Previous Month Top 10 Winners 🏆');

            $winners = [];
            foreach ($previousLeaderboard as $entry) {
                $winners[] = [
                    $entry->getRankMonthly(),
                    $entry->getUser()->getNama(),
                    $entry->getXpMonthly() . ' XP',
                    $entry->getUser()->getCurrentLevel(),
                    $entry->getUser()->getCurrentBadge()
                ];
            }

            $io->table(
                ['Rank', 'Name', 'XP', 'Level', 'Badge'],
                $winners
            );
        } else {
            $io->note('No leaderboard data found for previous month.');
        }

        // Reset is automatic - new month entries will be created automatically
        // when users earn XP in the new month
        $this->leaderboardRepository->resetMonthlyLeaderboard();

        $io->success([
            'Monthly leaderboard has been reset!',
            'New period: ' . $currentMonth . '/' . $currentYear,
            'Previous month data has been preserved for historical tracking.',
            'User total_xp remains unchanged - only monthly rankings reset.',
        ]);

        // Count total active users
        $totalUsers = count($previousLeaderboard);
        $io->info("Total users in previous leaderboard: {$totalUsers}");

        $io->note([
            'Next Steps:',
            '1. Users will start earning XP for the new month automatically',
            '2. Rankings will be calculated as XP is earned',
            '3. Schedule this command to run on the 1st of each month via cron',
            '',
            'Suggested cron schedule:',
            '0 0 1 * * php bin/console app:leaderboard:reset-monthly'
        ]);

        return Command::SUCCESS;
    }
}
