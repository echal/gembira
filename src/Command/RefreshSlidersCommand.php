<?php

namespace App\Command;

use App\Repository\SliderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:refresh-sliders',
    description: 'Refresh and check slider configuration for troubleshooting',
)]
class RefreshSlidersCommand extends Command
{
    public function __construct(
        private SliderRepository $sliderRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔄 Refresh Sliders - Troubleshooting Tool');

        // Get all sliders
        $allSliders = $this->sliderRepository->findAll();
        $activeSliders = $this->sliderRepository->findActiveSliders();

        $io->section('📊 Slider Statistics');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Sliders', count($allSliders)],
                ['Active Sliders', count($activeSliders)],
                ['Inactive Sliders', count($allSliders) - count($activeSliders)]
            ]
        );

        if (count($activeSliders) > 0) {
            $io->section('✅ Active Sliders');
            $rows = [];
            foreach ($activeSliders as $slider) {
                $imagePath = 'public/uploads/sliders/' . $slider->getImagePath();
                $imageExists = file_exists($imagePath) ? '✅ Exists' : '❌ Missing';
                
                $rows[] = [
                    $slider->getId(),
                    $slider->getTitle(),
                    $slider->getStatus(),
                    $slider->getOrderNo(),
                    $slider->getImagePath(),
                    $imageExists
                ];
            }
            
            $io->table(
                ['ID', 'Title', 'Status', 'Order', 'Image File', 'File Status'],
                $rows
            );

            $io->success('✅ Found ' . count($activeSliders) . ' active slider(s)');
            $io->note('If users still don\'t see sliders, ask them to:');
            $io->listing([
                'Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)',
                'Clear browser cache',
                'Check if maintenance mode is active',
                'Ensure they are logged in as regular user (not admin)'
            ]);
        } else {
            $io->warning('⚠️  No active sliders found!');
            $io->note('To fix this:');
            $io->listing([
                'Go to /admin/banner/ as admin',
                'Check if sliders exist and are set to "aktif" status',
                'Add new sliders if none exist'
            ]);
        }

        return Command::SUCCESS;
    }
}