<?php

namespace App\Repository;

use App\Entity\KepalaBidang;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KepalaBidang>
 */
class KepalaBidangRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KepalaBidang::class);
    }

    /**
     * Find all kepala bidang with their unit kerja
     */
    public function findAllWithUnitKerja(): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.unitKerja', 'u')
            ->addSelect('u')
            ->orderBy('k.nama', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find kepala bidang by NIP
     */
    public function findByNip(string $nip): ?KepalaBidang
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.nip = :nip')
            ->setParameter('nip', $nip)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if NIP already exists (for validation)
     */
    public function isNipExists(string $nip, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.nip = :nip')
            ->setParameter('nip', $nip);

        if ($excludeId !== null) {
            $qb->andWhere('k.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Find kepala bidang by unit kerja
     */
    public function findByUnitKerja(int $unitKerjaId): ?KepalaBidang
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.unitKerja = :unitKerjaId')
            ->setParameter('unitKerjaId', $unitKerjaId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find kepala bidang by unit kerja entity (returns array for consistency)
     */
    public function findByUnitKerjaEntity(\App\Entity\UnitKerja $unitKerja): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.unitKerja', 'u')
            ->addSelect('u')
            ->andWhere('k.unitKerja = :unitKerja')
            ->setParameter('unitKerja', $unitKerja)
            ->orderBy('k.nama', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get kepala bidang statistics
     */
    public function getStatistics(): array
    {
        $totalKepala = $this->count([]);
        
        return [
            'total_kepala_bidang' => $totalKepala
        ];
    }

    //    /**
    //     * @return KepalaBidang[] Returns an array of KepalaBidang objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('k')
    //            ->andWhere('k.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('k.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?KepalaBidang
    //    {
    //        return $this->createQueryBuilder('k')
    //            ->andWhere('k.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}