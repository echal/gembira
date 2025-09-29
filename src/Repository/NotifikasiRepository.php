<?php

namespace App\Repository;

use App\Entity\Notifikasi;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notifikasi>
 */
class NotifikasiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notifikasi::class);
    }

    /**
     * # DEPRECATED: Gunakan UserNotifikasiRepository->findNotificationsForUser()
     * Ambil notifikasi untuk pegawai tertentu, diurutkan berdasarkan waktu terbaru
     */
    public function findByPegawai(Pegawai $pegawai, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.pegawai = :pegawai')
            ->setParameter('pegawai', $pegawai)
            ->orderBy('n.waktuDibuat', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * # DEPRECATED: Gunakan UserNotifikasiRepository->countUnreadForUser()
     * Hitung jumlah notifikasi yang belum dibaca untuk pegawai
     */
    public function countUnreadByPegawai(Pegawai $pegawai): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.pegawai = :pegawai')
            ->andWhere('n.sudahDibaca = false')
            ->setParameter('pegawai', $pegawai)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * # DEPRECATED: Gunakan UserNotifikasiRepository->markAllAsReadForUser()
     * Tandai semua notifikasi sebagai dibaca untuk pegawai tertentu
     */
    public function markAllAsReadForPegawai(Pegawai $pegawai): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.sudahDibaca', 'true')
            ->set('n.waktuDibaca', ':now')
            ->where('n.pegawai = :pegawai')
            ->andWhere('n.sudahDibaca = false')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Hapus notifikasi lama (lebih dari X hari)
     */
    public function deleteOldNotifications(int $days = 30): void
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$days} days");

        $this->createQueryBuilder('n')
            ->delete()
            ->where('n.waktuDibuat < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}