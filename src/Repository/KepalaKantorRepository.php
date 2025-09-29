<?php

namespace App\Repository;

use App\Entity\KepalaKantor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KepalaKantor>
 */
class KepalaKantorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KepalaKantor::class);
    }

    /**
     * Find the currently active kepala kantor
     */
    public function findActiveKepalaKantor(): ?KepalaKantor
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.isAktif = :isAktif')
            ->setParameter('isAktif', true)
            ->orderBy('k.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find kepala kantor by NIP
     */
    public function findByNip(string $nip): ?KepalaKantor
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
     * Check if there's already an active kepala kantor
     */
    public function hasActiveKepalaKantor(?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.isAktif = :isAktif')
            ->setParameter('isAktif', true);

        if ($excludeId !== null) {
            $qb->andWhere('k.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Deactivate all kepala kantor (used when setting new active one)
     */
    public function deactivateAll(): void
    {
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.isAktif', ':isAktif')
            ->set('k.updatedAt', ':updatedAt')
            ->setParameter('isAktif', false)
            ->setParameter('updatedAt', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Find all kepala kantor ordered by creation date
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.isAktif', 'DESC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find kepala kantor by periode
     */
    public function findByPeriode(string $periode): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.periode = :periode')
            ->setParameter('periode', $periode)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get kepala kantor statistics
     */
    public function getStatistics(): array
    {
        $totalKepala = $this->count([]);
        $activeKepala = $this->count(['isAktif' => true]);
        
        return [
            'total_kepala_kantor' => $totalKepala,
            'active_kepala_kantor' => $activeKepala,
            'inactive_kepala_kantor' => $totalKepala - $activeKepala
        ];
    }

    //    /**
    //     * @return KepalaKantor[] Returns an array of KepalaKantor objects
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

    //    public function findOneBySomeField($value): ?KepalaKantor
    //    {
    //        return $this->createQueryBuilder('k')
    //            ->andWhere('k.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}