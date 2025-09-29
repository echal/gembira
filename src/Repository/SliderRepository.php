<?php

namespace App\Repository;

use App\Entity\Slider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Slider>
 */
class SliderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Slider::class);
    }

    /**
     * Get all active sliders ordered by order_no
     */
    public function findActiveSliders(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', 'aktif')
            ->orderBy('s.orderNo', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all sliders for admin (ordered by order_no)
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.orderNo', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next order number for new slider
     */
    public function getNextOrderNo(): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.orderNo)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Count active sliders
     */
    public function countActiveSliders(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.status = :status')
            ->setParameter('status', 'aktif')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Update order numbers after delete/reorder
     */
    public function reorderSliders(): void
    {
        $sliders = $this->findBy([], ['orderNo' => 'ASC']);
        $order = 1;
        
        foreach ($sliders as $slider) {
            $slider->setOrderNo($order++);
        }
        
        $this->getEntityManager()->flush();
    }

    public function save(Slider $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Slider $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}