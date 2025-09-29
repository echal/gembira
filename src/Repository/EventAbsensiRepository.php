<?php

namespace App\Repository;

use App\Entity\EventAbsensi;
use App\Entity\Event;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventAbsensi>
 */
class EventAbsensiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventAbsensi::class);
    }

    /**
     * Cek apakah user sudah absen untuk event tertentu
     */
    public function isUserAlreadyAttended(Event $event, Pegawai $user): bool
    {
        $result = $this->createQueryBuilder('ea')
            ->select('COUNT(ea.id)')
            ->where('ea.event = :event')
            ->andWhere('ea.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Ambil data absensi user untuk event tertentu
     */
    public function findUserEventAbsensi(Event $event, Pegawai $user): ?EventAbsensi
    {
        return $this->createQueryBuilder('ea')
            ->where('ea.event = :event')
            ->andWhere('ea.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Ambil semua absensi untuk event tertentu
     */
    public function findByEvent(Event $event): array
    {
        return $this->createQueryBuilder('ea')
            ->leftJoin('ea.user', 'u')
            ->where('ea.event = :event')
            ->setParameter('event', $event)
            ->orderBy('ea.waktuAbsen', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ambil statistik absensi untuk event
     */
    public function getEventAbsensiStats(Event $event): array
    {
        $qb = $this->createQueryBuilder('ea');
        
        $stats = $qb
            ->select('ea.status, COUNT(ea.id) as count')
            ->where('ea.event = :event')
            ->setParameter('event', $event)
            ->groupBy('ea.status')
            ->getQuery()
            ->getResult();

        $result = [
            'hadir' => 0,
            'tidak_hadir' => 0,
            'izin' => 0,
            'total' => 0
        ];

        foreach ($stats as $stat) {
            $result[$stat['status']] = (int)$stat['count'];
            $result['total'] += (int)$stat['count'];
        }

        return $result;
    }

    /**
     * Ambil riwayat absensi user
     */
    public function findUserAttendanceHistory(Pegawai $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('ea')
            ->leftJoin('ea.event', 'e')
            ->where('ea.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ea.waktuAbsen', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save absensi baru
     */
    public function save(EventAbsensi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove absensi
     */
    public function remove(EventAbsensi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}