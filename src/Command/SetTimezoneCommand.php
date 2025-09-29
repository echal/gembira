<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:set-timezone',
    description: 'Set and verify application timezone to Asia/Makassar (WITA)',
)]
class SetTimezoneCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Set timezone
        date_default_timezone_set('Asia/Makassar');
        
        // Verify timezone setting
        $currentTimezone = date_default_timezone_get();
        $currentTime = date('Y-m-d H:i:s T');
        $utcTime = gmdate('Y-m-d H:i:s T');
        
        $io->title('🌏 Gembira Application Timezone Configuration');
        
        $io->section('Current Timezone Information');
        $io->definitionList(
            ['Current Timezone' => $currentTimezone],
            ['Current Time' => $currentTime],
            ['UTC Time' => $utcTime],
            ['Offset' => 'UTC+8 (WITA - Waktu Indonesia Tengah)']
        );
        
        if ($currentTimezone === 'Asia/Makassar') {
            $io->success('✅ Timezone successfully set to Asia/Makassar (WITA)');
            $io->note('This timezone is correct for Kanwil Kemenag Sulawesi Barat');
        } else {
            $io->error('❌ Failed to set timezone to Asia/Makassar');
            return Command::FAILURE;
        }
        
        $io->section('Timezone Details');
        $io->text([
            '🏢 Location: Sulawesi Barat, Indonesia',
            '⏰ WITA = Waktu Indonesia Tengah',
            '🌐 UTC Offset: +8 hours',
            '📍 Same as: Singapore, Malaysia, Philippines',
        ]);
        
        return Command::SUCCESS;
    }
}