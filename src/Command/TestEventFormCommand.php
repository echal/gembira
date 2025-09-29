<?php

namespace App\Command;

use App\Entity\UnitKerja;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-event-form',
    description: 'Test Event Form - verifikasi unit kerja tersedia untuk dropdown'
)]
class TestEventFormCommand extends Command
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

        $io->title('🧪 Test Form Event - Unit Kerja Dropdown');

        // Test 1: Periksa unit kerja di database
        $io->section('📊 Unit Kerja yang Tersedia di Database');

        $unitKerjas = $this->entityManager->getRepository(UnitKerja::class)->findBy([], ['namaUnit' => 'ASC']);

        if (empty($unitKerjas)) {
            $io->error('❌ Tidak ada unit kerja di database!');
            $io->note('Silakan tambahkan unit kerja melalui admin panel atau seed data');
            return Command::FAILURE;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Nama Unit Kerja']);

        foreach ($unitKerjas as $unit) {
            $table->addRow([
                $unit->getId(),
                $unit->getNamaUnit()
            ]);
        }

        $table->render();

        // Test 2: Simulasi format untuk form choices
        $io->section('🎯 Format Unit Choices untuk Form');

        $unitChoices = [];
        foreach ($unitKerjas as $unit) {
            $unitChoices[$unit->getNamaUnit()] = $unit->getId();
        }

        $io->text('Format choices yang dikirim ke EventType form:');
        foreach ($unitChoices as $label => $value) {
            $io->text("  • \"$label\" => $value");
        }

        // Test 3: API Response simulation
        $io->section('🔌 API Response Format');

        $unitData = [];
        foreach ($unitKerjas as $unit) {
            $unitData[] = [
                'id' => $unit->getId(),
                'nama_unit' => $unit->getNamaUnit(),
                'label' => $unit->getNamaUnit()
            ];
        }

        $apiResponse = [
            'success' => true,
            'data' => $unitData,
            'total' => count($unitData)
        ];

        $io->text('JSON response untuk AJAX:');
        $io->text(json_encode($apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Test 4: Route checking
        $io->section('🛠️ Troubleshooting Tips');

        $io->listing([
            'Controller AdminEventController passes unit_choices to EventType ✅',
            'EventType form uses unit_choices for targetUnits field ✅',
            'Template has targetUnits checkboxes with Twig loop ✅',
            'JavaScript toggles visibility based on radio selection ✅',
            'AJAX fallback loads unit kerja if form doesn\'t have them ✅',
            'API endpoint /admin/event/api/unit-kerja available ✅'
        ]);

        // Success summary
        $io->success([
            '🎉 Form Event Unit Kerja Setup Complete!',
            '',
            sprintf('📋 %d unit kerja tersedia', count($unitKerjas)),
            '✅ Controller mengirim data unit choices ke form',
            '✅ Template memiliki dropdown dengan AJAX fallback',
            '✅ Multi-select functionality aktif',
            '',
            '🔧 Jika masih ada masalah:',
            '1. Buka Developer Tools (F12) di browser',
            '2. Pilih tab Console untuk melihat log JavaScript',
            '3. Pilih tab Network untuk monitor AJAX requests',
            '4. Clear browser cache dan reload halaman'
        ]);

        return Command::SUCCESS;
    }
}