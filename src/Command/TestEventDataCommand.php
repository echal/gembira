<?php

namespace App\Command;

use App\Entity\Event;
use App\Entity\Pegawai;
use App\Entity\UnitKerja;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-event-data',
    description: 'Test dan verifikasi data event untuk debugging API kalender'
)]
class TestEventDataCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('date', InputArgument::OPTIONAL, 'Tanggal untuk test (YYYY-MM-DD)', date('Y-m-d'))
            ->addArgument('nip', InputArgument::OPTIONAL, 'NIP pegawai untuk test', null)
            ->setHelp('Command ini akan menguji query event untuk tanggal dan user tertentu, untuk memverifikasi apakah data event benar-benar dikirim dari backend.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = $input->getArgument('date');
        $nipInput = $input->getArgument('nip');

        $io->title('Test Event Data - Debugging API Kalender');

        try {
            // perbaikan query event: validasi format tanggal
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $io->error("Format tanggal harus YYYY-MM-DD. Diberikan: {$date}");
                return Command::FAILURE;
            }

            $dateObj = new \DateTime($date);
            $io->info("Testing untuk tanggal: " . $dateObj->format('l, d F Y'));

            // perbaikan query event: test semua event aktif terlebih dahulu
            $eventRepo = $this->entityManager->getRepository(Event::class);
            $allEvents = $eventRepo->findBy(['status' => 'aktif']);
            
            $io->section('📋 Semua Event Aktif di Database');
            $io->table(
                ['ID', 'Judul', 'Tanggal', 'Target Audience', 'Target Units'],
                array_map(function($event) {
                    return [
                        $event->getId(),
                        substr($event->getJudulEvent(), 0, 30) . '...',
                        $event->getTanggalMulai()->format('Y-m-d H:i'),
                        $event->getTargetAudience(),
                        json_encode($event->getTargetUnits())
                    ];
                }, $allEvents)
            );

            // perbaikan query event: filter event berdasarkan tanggal
            $eventsOnDate = $eventRepo->findByDate($dateObj);
            
            $io->section("📅 Event pada tanggal {$date}");
            if (count($eventsOnDate) > 0) {
                $io->table(
                    ['ID', 'Judul', 'Waktu', 'Target', 'Status'],
                    array_map(function($event) {
                        return [
                            $event->getId(),
                            $event->getJudulEvent(),
                            $event->getTanggalMulai()->format('H:i'),
                            $event->getTargetAudience(),
                            $event->getStatus()
                        ];
                    }, $eventsOnDate)
                );
            } else {
                $io->warning("Tidak ada event pada tanggal {$date}");
            }

            // perbaikan query event: test dengan user tertentu
            if ($nipInput) {
                $pegawaiRepo = $this->entityManager->getRepository(Pegawai::class);
                $user = $pegawaiRepo->findOneBy(['nip' => $nipInput]);
                
                if (!$user) {
                    $io->error("Pegawai dengan NIP {$nipInput} tidak ditemukan");
                    return Command::FAILURE;
                }

                $io->section("👤 Test dengan User: {$user->getNama()} ({$user->getNip()})");
                $io->text("Unit Kerja: " . ($user->getUnitKerjaEntity() ? $user->getUnitKerjaEntity()->getNamaUnit() . " (ID: " . $user->getUnitKerjaEntity()->getId() . ")" : "TIDAK ADA"));

                // perbaikan query event: test query dengan user filter
                $userEvents = $eventRepo->findByDateForUser($dateObj, $user);
                
                $io->section("🎯 Event yang dapat dilihat user pada {$date}");
                if (count($userEvents) > 0) {
                    $io->table(
                        ['ID', 'Judul', 'Waktu', 'Target', 'Units', 'Kategori'],
                        array_map(function($event) {
                            return [
                                $event->getId(),
                                $event->getJudulEvent(),
                                $event->getTanggalMulai()->format('H:i'),
                                $event->getTargetAudience(),
                                json_encode($event->getTargetUnits()),
                                $event->getKategoriNama()
                            ];
                        }, $userEvents)
                    );

                    // perbaikan query event: test format data seperti API
                    $io->section('🔍 Format Data seperti API Response');
                    foreach ($userEvents as $event) {
                        $eventData = [
                            'id' => $event->getId(),
                            'nama' => $event->getJudulEvent(),
                            'deskripsi' => $event->getDeskripsi() ?? '',
                            'kategori' => $event->getKategoriNama(),
                            'icon' => $event->getKategoriIcon(),
                            'emoji_badge' => $event->getKategoriBadgeEmoji(),
                            'badge_class' => $event->getKategoriBadgeClass(),
                            'warna' => $event->getWarna() ?? '#87CEEB',
                            'waktu' => $event->getTanggalMulai()->format('H:i'),
                            'lokasi' => $event->getLokasi() ?? '',
                        ];
                        
                        $io->text("Event ID {$event->getId()}: " . json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }
                } else {
                    $io->warning("User ini tidak dapat melihat event apapun pada tanggal {$date}");
                    
                    // perbaikan query event: analisis mengapa user tidak dapat melihat event
                    if (count($eventsOnDate) > 0) {
                        $io->section('🔍 Analisis: Mengapa user tidak dapat melihat event?');
                        foreach ($eventsOnDate as $event) {
                            $reason = [];
                            
                            if ($event->getTargetAudience() === 'all') {
                                $reason[] = "✅ Target audience = 'all' (seharusnya bisa dilihat)";
                            } elseif ($event->getTargetAudience() === 'custom') {
                                if ($user->getUnitKerjaEntity()) {
                                    $userUnitId = $user->getUnitKerjaEntity()->getId();
                                    if ($event->getTargetUnits() && in_array($userUnitId, $event->getTargetUnits())) {
                                        $reason[] = "✅ User unit ({$userUnitId}) ada di target units";
                                    } else {
                                        $reason[] = "❌ User unit ({$userUnitId}) TIDAK ada di target units: " . json_encode($event->getTargetUnits());
                                    }
                                } else {
                                    $reason[] = "❌ User tidak punya unit kerja";
                                }
                            }
                            
                            $io->text("Event '{$event->getJudulEvent()}': " . implode(', ', $reason));
                        }
                    }
                }
            } else {
                // perbaikan query event: tampilkan semua user untuk referensi
                $io->section('👥 Daftar User untuk Testing');
                $allUsers = $this->entityManager->getRepository(Pegawai::class)->findBy(['statusKepegawaian' => 'aktif'], null, 10);
                $io->table(
                    ['NIP', 'Nama', 'Unit Kerja'],
                    array_map(function($user) {
                        return [
                            $user->getNip(),
                            $user->getNama(),
                            $user->getUnitKerjaEntity() ? $user->getUnitKerjaEntity()->getNamaUnit() : 'TIDAK ADA'
                        ];
                    }, $allUsers)
                );
                
                $io->note('Gunakan: php bin/console app:test-event-data ' . $date . ' [NIP] untuk test dengan user tertentu');
            }

            // perbaikan query event: tampilkan unit kerja yang ada
            $io->section('🏢 Daftar Unit Kerja');
            $allUnits = $this->entityManager->getRepository(UnitKerja::class)->findAll();
            $io->table(
                ['ID', 'Nama Unit', 'Jumlah Pegawai'],
                array_map(function($unit) {
                    return [
                        $unit->getId(),
                        $unit->getNamaUnit(),
                        $unit->getJumlahPegawai()
                    ];
                }, $allUnits)
            );

            $io->success('Test selesai! Periksa log aplikasi untuk detail debug dari Repository dan Controller.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error saat testing: ' . $e->getMessage());
            $io->text('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}