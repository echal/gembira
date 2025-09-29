<?php

namespace App\Repository;

use App\Entity\UnitKerja;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnitKerja>
 */
class UnitKerjaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnitKerja::class);
    }

    /**
     * Find all unit kerja with their kepala bidang
     */
    public function findAllWithKepalaBidang(): array
    {
        $unitKerjaList = $this->findBy([], ['namaUnit' => 'ASC']);

        // Manually populate kepala bidang data for each unit
        $kepalaBidangRepo = $this->getEntityManager()->getRepository('App\\Entity\\KepalaBidang');

        foreach ($unitKerjaList as $unit) {
            $kepalaBidang = $kepalaBidangRepo->findOneBy(['unitKerja' => $unit]);
            // Store kepala bidang info in unit object temporarily
            $unit->_kepalaBidang = $kepalaBidang;
        }

        return $unitKerjaList;
    }

    /**
     * Find unit kerja by kode unit
     */
    public function findByKodeUnit(string $kodeUnit): ?UnitKerja
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.kodeUnit = :kodeUnit')
            ->setParameter('kodeUnit', strtoupper($kodeUnit))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if kode unit already exists (for validation)
     */
    public function isKodeUnitExists(string $kodeUnit, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.kodeUnit = :kodeUnit')
            ->setParameter('kodeUnit', strtoupper($kodeUnit));

        if ($excludeId !== null) {
            $qb->andWhere('u.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Get unit kerja statistics
     */
    public function getStatistics(): array
    {
        $totalUnits = $this->count([]);
        
        $unitsWithKepala = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('App\Entity\KepalaBidang', 'k', 'WITH', 'k.unitKerja = u.id')
            ->getQuery()
            ->getSingleScalarResult();

        $unitsWithoutKepala = $totalUnits - $unitsWithKepala;

        return [
            'total_units' => $totalUnits,
            'with_kepala' => (int) $unitsWithKepala,
            'without_kepala' => $unitsWithoutKepala
        ];
    }

    /**
     * Find units without kepala bidang
     */
    public function findUnitsWithoutKepalaBidang(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('App\Entity\KepalaBidang', 'k', 'WITH', 'k.unitKerja = u.id')
            ->where('k.id IS NULL')
            ->orderBy('u.namaUnit', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find kepala bidang for a specific unit kerja
     */
    public function findKepalaBidangByUnitKerja(UnitKerja $unitKerja): ?\App\Entity\KepalaBidang
    {
        $kepalaBidangRepo = $this->getEntityManager()->getRepository('App\\Entity\\KepalaBidang');
        return $kepalaBidangRepo->findOneBy(['unitKerja' => $unitKerja]);
    }

    //    /**
    //     * @return UnitKerja[] Returns an array of UnitKerja objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UnitKerja
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}