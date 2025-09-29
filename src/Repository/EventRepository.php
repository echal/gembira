<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Pegawai;
use App\Entity\UnitKerja;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Ambil event berdasarkan bulan dan tahun
     */
    public function findByMonth(int $month, int $year): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = new \DateTime($startDate->format('Y-m-t 23:59:59'));

        return $this->createQueryBuilder('e')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif')
            ->orderBy('e.tanggalMulai', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ambil event berdasarkan tanggal spesifik
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        $startDate = new \DateTime($date->format('Y-m-d') . ' 00:00:00');
        $endDate = new \DateTime($date->format('Y-m-d') . ' 23:59:59');
        
        return $this->createQueryBuilder('e')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif')
            ->orderBy('e.tanggalMulai', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ambil semua tanggal yang memiliki event dalam bulan tertentu
     * untuk memberikan indikator pada kalender
     */
    public function getEventDatesInMonth(int $month, int $year): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = new \DateTime($startDate->format('Y-m-t 23:59:59'));

        $events = $this->createQueryBuilder('e')
            ->select('e.tanggalMulai')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif')
            ->getQuery()
            ->getResult();

        $dates = [];
        foreach ($events as $event) {
            $dateString = $event['tanggalMulai']->format('Y-m-d');
            if (!in_array($dateString, $dates)) {
                $dates[] = $dateString;
            }
        }

        return $dates;
    }

    /**
     * Ambil event berdasarkan bulan, tahun, dan filter unit kerja user
     */
    public function findByMonthForUser(int $month, int $year, Pegawai $user): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = new \DateTime($startDate->format('Y-m-t 23:59:59'));
        $userUnitId = $user->getUnitKerjaEntity()?->getId();

        $qb = $this->createQueryBuilder('e')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif');

        // Filter berdasarkan target audience
        if ($userUnitId) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'e.targetAudience = :all',
                    $qb->expr()->andX(
                        'e.targetAudience = :custom',
                        $qb->expr()->like('e.targetUnits', ':unitPattern')
                    )
                )
            )
            ->setParameter('all', 'all')
            ->setParameter('custom', 'custom')
            ->setParameter('unitPattern', '%' . $userUnitId . '%');
        } else {
            // Jika user tidak punya unit kerja, hanya tampilkan event untuk semua
            $qb->andWhere('e.targetAudience = :all')
               ->setParameter('all', 'all');
        }

        return $qb->orderBy('e.tanggalMulai', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Ambil event berdasarkan tanggal spesifik dan filter unit kerja user
     */
    public function findByDateForUser(\DateTimeInterface $date, Pegawai $user): array
    {
        $startDate = new \DateTime($date->format('Y-m-d') . ' 00:00:00');
        $endDate = new \DateTime($date->format('Y-m-d') . ' 23:59:59');
        $userUnitId = $user->getUnitKerjaEntity()?->getId();
        
        // perbaikan query event: log detail untuk debug
        error_log("EventRepository Debug - Searching events for date: " . $date->format('Y-m-d'));
        error_log("EventRepository Debug - Date range: " . $startDate->format('Y-m-d H:i:s') . " to " . $endDate->format('Y-m-d H:i:s'));
        error_log("EventRepository Debug - User: " . $user->getNip() . " (Unit ID: " . ($userUnitId ?: 'NULL') . ")");
        
        $qb = $this->createQueryBuilder('e')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif');

        // perbaikan query event: Filter berdasarkan target audience dengan logging
        if ($userUnitId) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'e.targetAudience = :all',
                    $qb->expr()->andX(
                        'e.targetAudience = :custom',
                        $qb->expr()->like('e.targetUnits', ':unitPattern')
                    )
                )
            )
            ->setParameter('all', 'all')
            ->setParameter('custom', 'custom')
            ->setParameter('unitPattern', '%' . $userUnitId . '%');
            
            error_log("EventRepository Debug - Filter: target_audience = 'all' OR (target_audience = 'custom' AND target_units LIKE '%" . $userUnitId . "%')");
        } else {
            $qb->andWhere('e.targetAudience = :all')
               ->setParameter('all', 'all');
            
            error_log("EventRepository Debug - Filter: user has no unit, only showing 'all' audience events");
        }

        // perbaikan query event: log SQL query untuk debugging
        $query = $qb->orderBy('e.tanggalMulai', 'DESC')
                    ->getQuery();
        error_log("EventRepository Debug - SQL: " . $query->getSQL());
        error_log("EventRepository Debug - Parameters: " . json_encode($query->getParameters()->toArray()));
        
        $results = $query->getResult();
        error_log("EventRepository Debug - Found " . count($results) . " events");
        
        // perbaikan query event: log setiap event yang ditemukan
        foreach ($results as $event) {
            error_log("EventRepository Debug - Event: ID=" . $event->getId() . 
                     ", Title='" . $event->getJudulEvent() . "'" .
                     ", Date=" . $event->getTanggalMulai()->format('Y-m-d H:i') .
                     ", Target=" . $event->getTargetAudience() .
                     ", Units=" . json_encode($event->getTargetUnits()));
        }
        
        return $results;
    }

    /**
     * Ambil tanggal yang memiliki event dalam bulan tertentu, dengan filter unit kerja user
     */
    public function getEventDatesInMonthForUser(int $month, int $year, Pegawai $user): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = new \DateTime($startDate->format('Y-m-t 23:59:59'));
        $userUnitId = $user->getUnitKerjaEntity()?->getId();

        $qb = $this->createQueryBuilder('e')
            ->select('e.tanggalMulai')
            ->where('e.tanggalMulai >= :startDate')
            ->andWhere('e.tanggalMulai <= :endDate')
            ->andWhere('e.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'aktif');

        // Filter berdasarkan target audience
        if ($userUnitId) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'e.targetAudience = :all',
                    $qb->expr()->andX(
                        'e.targetAudience = :custom',
                        $qb->expr()->like('e.targetUnits', ':unitPattern')
                    )
                )
            )
            ->setParameter('all', 'all')
            ->setParameter('custom', 'custom')
            ->setParameter('unitPattern', '%' . $userUnitId . '%');
        } else {
            $qb->andWhere('e.targetAudience = :all')
               ->setParameter('all', 'all');
        }

        $events = $qb->getQuery()->getResult();

        $dates = [];
        foreach ($events as $event) {
            $dateString = $event['tanggalMulai']->format('Y-m-d');
            if (!in_array($dateString, $dates)) {
                $dates[] = $dateString;
            }
        }

        return $dates;
    }

    /**
     * Pencarian event berdasarkan nama kegiatan (judulEvent)
     * Mendukung pencarian parsial dan case-insensitive
     */
    public function searchByJudul(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'aktif')
            ->orderBy('e.tanggalMulai', 'DESC');

        // Tambahkan filter pencarian jika ada input search
        if (!empty($search)) {
            $qb->andWhere('LOWER(e.judulEvent) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Ambil semua event yang dikelompokkan berdasarkan bulan dan tahun
     * Mengembalikan array associative dengan key = "bulan tahun" dan value = array event
     */
    public function findAllGroupedByMonth(string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.tanggalMulai', 'DESC');

        // Tambahkan filter pencarian jika ada input search
        if (!empty($search)) {
            $qb->andWhere('LOWER(e.judulEvent) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $events = $qb->getQuery()->getResult();

        // Kelompokkan event berdasarkan bulan dan tahun
        $groupedEvents = [];
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        foreach ($events as $event) {
            $tanggal = $event->getTanggalMulai();
            $month = (int)$tanggal->format('n'); // 1-12
            $year = (int)$tanggal->format('Y');
            
            $monthKey = $monthNames[$month] . ' ' . $year;
            
            if (!isset($groupedEvents[$monthKey])) {
                $groupedEvents[$monthKey] = [];
            }
            
            $groupedEvents[$monthKey][] = $event;
        }

        return $groupedEvents;
    }

    /**
     * Ambil semua event dikelompokkan berdasarkan bulan/tahun untuk Admin Unit Kerja
     * Hanya menampilkan event yang targetnya mencakup unit kerja admin tersebut
     *
     * @param string|null $search
     * @param UnitKerja $unitKerja
     * @return array
     */
    public function findAllGroupedByMonthAndUnit(string $search = null, UnitKerja $unitKerja): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.createdBy', 'admin')
            ->orderBy('e.tanggalMulai', 'DESC');

        // Filter berdasarkan unit kerja admin - LOGIC YANG LEBIH KETAT
        $unitId = $unitKerja->getId();
        $qb->andWhere(
            $qb->expr()->orX(
                // 1. Event untuk semua unit kerja
                'e.targetAudience = :all',
                // 2. Event custom yang secara spesifik menargetkan unit ini
                $qb->expr()->andX(
                    'e.targetAudience = :custom',
                    $qb->expr()->like('e.targetUnits', ':unitPattern')
                ),
                // 3. Event yang dibuat oleh admin dari unit kerja yang sama
                'admin.unitKerjaEntity = :unitKerjaEntity'
            )
        )
        ->setParameter('all', 'all')
        ->setParameter('custom', 'custom')
        ->setParameter('unitPattern', '%' . $unitId . '%')
        ->setParameter('unitKerjaEntity', $unitKerja);

        // Tambahkan filter pencarian jika ada input search
        if (!empty($search)) {
            $qb->andWhere('LOWER(e.judulEvent) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $events = $qb->getQuery()->getResult();

        // Kelompokkan event berdasarkan bulan dan tahun
        $groupedEvents = [];
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        foreach ($events as $event) {
            $tanggal = $event->getTanggalMulai();
            $month = (int)$tanggal->format('n');
            $year = (int)$tanggal->format('Y');
            $monthKey = $monthNames[$month] . ' ' . $year;

            if (!isset($groupedEvents[$monthKey])) {
                $groupedEvents[$monthKey] = [];
            }

            $groupedEvents[$monthKey][] = $event;
        }

        return $groupedEvents;
    }
}