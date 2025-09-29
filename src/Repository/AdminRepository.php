<?php

namespace App\Repository;

use App\Entity\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Admin) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Cari admin berdasarkan username untuk login
     */
    public function findByUsername(string $username): ?Admin
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Cari admin aktif saja
     */
    public function findActiveAdmins(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'aktif')
            ->orderBy('a.namaLengkap', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cari admin dengan permission tertentu
     */
    public function findAdminsWithPermission(string $permission): array
    {
        // Gunakan native SQL query karena JSON_CONTAINS tidak didukung DQL
        $sql = "SELECT id FROM admin 
                WHERE JSON_CONTAINS(permissions, :permission) = 1
                AND status = 'aktif'";
        
        $conn = $this->getEntityManager()->getConnection();
        $results = $conn->executeQuery($sql, [
            'permission' => json_encode([$permission])
        ])->fetchAllAssociative();
        
        if (empty($results)) {
            return [];
        }
        
        // Convert hasil ke entities
        $ids = array_column($results, 'id');
        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Hitung jumlah admin berdasarkan role
     */
    public function countByRole(string $role): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.role = :role')
            ->andWhere('a.status = :status')
            ->setParameter('role', $role)
            ->setParameter('status', 'aktif')
            ->getQuery()
            ->getSingleScalarResult();
    }
}