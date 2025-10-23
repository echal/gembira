<?php

namespace App\Command;

use App\Entity\Pegawai;
use App\Service\UserXpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-user-levels',
    description: 'Recalculate all user levels based on new XP requirements'
)]
class RecalculateUserLevelsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserXpService $xpService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making actual changes')
            ->setHelp(<<<'HELP'
This command recalculates all user levels based on the new XP requirement system.

Usage:
  # Preview changes without saving
  php bin/console app:recalculate-user-levels --dry-run

  # Execute the recalculation
  php bin/console app:recalculate-user-levels
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be saved');
        } else {
            $io->note('Starting user level recalculation...');
        }

        // Get all users with XP
        $users = $this->em->getRepository(Pegawai::class)
            ->createQueryBuilder('p')
            ->where('p.total_xp IS NOT NULL')
            ->orderBy('p.total_xp', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            $io->warning('No users found with XP data.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d users with XP data', count($users)));
        $io->newLine();

        // Statistics
        $stats = [
            'total' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'level_changes' => [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
            ]
        ];

        // Create progress bar
        $io->progressStart(count($users));

        foreach ($users as $user) {
            $stats['total']++;

            try {
                $oldLevel = $user->getCurrentLevel();
                $oldBadge = $user->getCurrentBadge();
                $totalXp = $user->getTotalXp();

                // Calculate new level based on new system
                $newLevel = $this->xpService->calculateLevel($totalXp);
                $newBadge = $this->xpService->getBadgeForLevel($newLevel);
                $newTitle = $this->xpService->getTitleForLevel($newLevel);

                if ($oldLevel !== $newLevel || $oldBadge !== $newBadge) {
                    $stats['updated']++;
                    $stats['level_changes'][$newLevel]++;

                    if (!$dryRun) {
                        $user->setCurrentLevel($newLevel);
                        $user->setCurrentBadge($newBadge);
                    }

                    if ($output->isVerbose()) {
                        $io->text(sprintf(
                            '  User #%d (%s): %d XP | Level %d → %d | %s → %s (%s)',
                            $user->getId(),
                            $user->getNamaLengkap(),
                            $totalXp,
                            $oldLevel,
                            $newLevel,
                            $oldBadge,
                            $newBadge,
                            $newTitle
                        ));
                    }
                } else {
                    $stats['unchanged']++;
                }

                $io->progressAdvance();

            } catch (\Exception $e) {
                $stats['errors']++;
                $io->error(sprintf(
                    'Error processing user #%d: %s',
                    $user->getId(),
                    $e->getMessage()
                ));
            }
        }

        $io->progressFinish();
        $io->newLine();

        // Save changes
        if (!$dryRun && $stats['updated'] > 0) {
            $io->text('Saving changes to database...');
            $this->em->flush();
            $io->success('Changes saved successfully!');
        }

        // Display statistics
        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Users Processed', $stats['total']],
                ['Users Updated', $stats['updated']],
                ['Users Unchanged', $stats['unchanged']],
                ['Errors', $stats['errors']],
            ]
        );

        $io->section('Level Distribution After Recalculation');
        $io->table(
            ['Level', 'Badge', 'Title', 'Count'],
            [
                ['1', '🌱', 'Pemula Ikhlas', $stats['level_changes'][1]],
                ['2', '🌿', 'Aktor Kebaikan', $stats['level_changes'][2]],
                ['3', '🌺', 'Penggerak Semangat', $stats['level_changes'][3]],
                ['4', '🌞', 'Inspirator Ikhlas', $stats['level_changes'][4]],
                ['5', '🏆', 'Teladan Kinerja', $stats['level_changes'][5]],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN completed - No changes were saved');
            $io->note('Run without --dry-run to save changes');
        } else {
            $io->success(sprintf(
                'Successfully recalculated levels for %d users!',
                $stats['updated']
            ));
        }

        return Command::SUCCESS;
    }
}
