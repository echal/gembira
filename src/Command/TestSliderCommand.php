<?php

namespace App\Command;

use App\Repository\SliderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:slider',
    description: 'Test slider repository functionality'
)]
class TestSliderCommand extends Command
{
    public function __construct(
        private SliderRepository $sliderRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing Slider Repository...');
        
        // Test findActiveSliders
        $activeSliders = $this->sliderRepository->findActiveSliders();
        $output->writeln('Active sliders count: ' . count($activeSliders));
        
        foreach ($activeSliders as $slider) {
            $output->writeln(sprintf(
                'ID: %d | Title: %s | Image: %s | Status: %s | Order: %d',
                $slider->getId(),
                $slider->getTitle() ?? 'No Title',
                $slider->getImagePath(),
                $slider->getStatus(),
                $slider->getOrderNo()
            ));
        }
        
        return Command::SUCCESS;
    }
}