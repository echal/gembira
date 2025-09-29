<?php

namespace App\Repository;

use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Pegawai>
 */
class PegawaiRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pegawai::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Pegawai) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Cari pegawai berdasarkan NIP untuk login
     */
    public function findByNip(string $nip): ?Pegawai
    {
        return $this->findOneBy(['nip' => $nip]);
    }

    /**
     * Cari pegawai aktif saja
     */
    public function findActivePegawai(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statusKepegawaian = :status')
            ->setParameter('status', 'aktif')
            ->orderBy('p.nama', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pencarian pegawai berdasarkan nama, NIP, jabatan, atau unit kerja
     * Mendukung pencarian parsial dan case-insensitive
     */
    public function searchPegawai(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->orderBy('p.nama', 'ASC');

        // Tambahkan filter pencarian jika ada input search
        if (!empty($search)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.nama) LIKE LOWER(:search)',
                    'LOWER(p.nip) LIKE LOWER(:search)',
                    'LOWER(p.jabatan) LIKE LOWER(:search)',
                    'LOWER(uk.namaUnit) LIKE LOWER(:search)',
                    'LOWER(uk.kodeUnit) LIKE LOWER(:search)'
                )
            )->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Pencarian pegawai dengan filter unit kerja
     */
    public function searchPegawaiByUnit(?string $search = null, ?int $unitKerjaId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.unitKerjaEntity', 'uk')
            ->orderBy('p.nama', 'ASC');

        // Filter berdasarkan unit kerja
        if ($unitKerjaId !== null) {
            $qb->andWhere('uk.id = :unitId')
               ->setParameter('unitId', $unitKerjaId);
        }

        // Tambahkan filter pencarian jika ada input search
        if (!empty($search)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(p.nama) LIKE LOWER(:search)',
                    'LOWER(p.nip) LIKE LOWER(:search)',
                    'LOWER(p.jabatan) LIKE LOWER(:search)'
                )
            )->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}