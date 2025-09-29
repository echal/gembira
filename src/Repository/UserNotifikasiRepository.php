<?php

namespace App\Repository;

use App\Entity\UserNotifikasi;
use App\Entity\Pegawai;
use App\Entity\Notifikasi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserNotifikasi>
 */
class UserNotifikasiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserNotifikasi::class);
    }

    /**
     * # QUERY UNREAD COUNT: hitung notifikasi yang belum dibaca untuk user tertentu
     */
    public function countUnreadForUser(Pegawai $user): int
    {
        return $this->createQueryBuilder('un')
            ->select('COUNT(un.id)')
            // # ENTITY RELATION: join dengan pegawai untuk filter user
            ->where('un.pegawai = :user')
            ->andWhere('un.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Ambil semua notifikasi untuk user tertentu dengan status read/unread
     */
    public function findNotificationsForUser(Pegawai $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('un')
            // # ENTITY RELATION: join dengan notifikasi untuk mendapatkan detail
            ->leftJoin('un.notifikasi', 'n')
            ->addSelect('n')
            // # ENTITY RELATION: join dengan pegawai untuk filter user
            ->where('un.pegawai = :user')
            ->setParameter('user', $user)
            ->orderBy('un.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Ambil notifikasi yang belum dibaca untuk user tertentu
     */
    public function findUnreadForUser(Pegawai $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('un')
            // # ENTITY RELATION: join dengan notifikasi untuk detail lengkap
            ->leftJoin('un.notifikasi', 'n')
            ->addSelect('n')
            // # ENTITY RELATION: filter berdasarkan pegawai dan status unread
            ->where('un.pegawai = :user')
            ->andWhere('un.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('un.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cari UserNotifikasi berdasarkan user dan notifikasi
     */
    public function findUserNotification(Pegawai $user, Notifikasi $notifikasi): ?UserNotifikasi
    {
        return $this->createQueryBuilder('un')
            // # ENTITY RELATION: filter berdasarkan pegawai dan notifikasi
            ->where('un.pegawai = :user')
            ->andWhere('un.notifikasi = :notifikasi')
            ->setParameter('user', $user)
            ->setParameter('notifikasi', $notifikasi)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mark semua notifikasi sebagai sudah dibaca untuk user tertentu
     */
    public function markAllAsReadForUser(Pegawai $user): void
    {
        $this->createQueryBuilder('un')
            ->update()
            ->set('un.isRead', 'true')
            ->set('un.readAt', ':now')
            // # ENTITY RELATION: update berdasarkan pegawai
            ->where('un.pegawai = :user')
            ->andWhere('un.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Hapus user notifikasi lama untuk cleanup
     */
    public function deleteOldUserNotifications(int $days = 30): void
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$days} days");

        $this->createQueryBuilder('un')
            ->delete()
            ->where('un.receivedAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Ambil statistik notifikasi untuk user (total, read, unread)
     */
    public function getNotificationStatsForUser(Pegawai $user): array
    {
        $qb = $this->createQueryBuilder('un')
            // # ENTITY RELATION: filter berdasarkan pegawai
            ->where('un.pegawai = :user')
            ->setParameter('user', $user);

        $total = (clone $qb)->select('COUNT(un.id)')->getQuery()->getSingleScalarResult();
        $unread = (clone $qb)->select('COUNT(un.id)')->andWhere('un.isRead = false')->getQuery()->getSingleScalarResult();
        $read = $total - $unread;

        return [
            'total' => $total,
            'read' => $read,
            'unread' => $unread,
            'read_percentage' => $total > 0 ? round(($read / $total) * 100, 1) : 0
        ];
    }
}