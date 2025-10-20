<?php

namespace App\Repository;

use App\Entity\RankingHarian;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository untuk mengelola data RankingHarian
 *
 * @extends ServiceEntityRepository<RankingHarian>
 */
class RankingHarianRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankingHarian::class);
    }

    /**
     * Simpan entity RankingHarian
     */
    public function save(RankingHarian $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Hapus entity RankingHarian
     */
    public function remove(RankingHarian $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Cari ranking harian berdasarkan pegawai dan tanggal
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $tanggal
     * @return RankingHarian|null
     */
    public function findByPegawaiAndTanggal(Pegawai $pegawai, \DateTimeInterface $tanggal): ?RankingHarian
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.pegawai = :pegawai')
            ->andWhere('rh.tanggal = :tanggal')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dapatkan semua ranking untuk tanggal tertentu, diurutkan berdasarkan peringkat
     *
     * @param \DateTimeInterface $tanggal
     * @return RankingHarian[]
     */
    public function findByTanggal(\DateTimeInterface $tanggal): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.tanggal = :tanggal')
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->orderBy('rh.peringkat', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan top N ranking untuk tanggal tertentu
     *
     * @param \DateTimeInterface $tanggal
     * @param int $limit
     * @return RankingHarian[]
     */
    public function findTopByTanggal(\DateTimeInterface $tanggal, int $limit = 10): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.tanggal = :tanggal')
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->orderBy('rh.peringkat', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan ranking harian pegawai untuk periode tertentu
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $mulai
     * @param \DateTimeInterface $selesai
     * @return RankingHarian[]
     */
    public function findByPegawaiAndPeriode(Pegawai $pegawai, \DateTimeInterface $mulai, \DateTimeInterface $selesai): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.pegawai = :pegawai')
            ->andWhere('rh.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->orderBy('rh.tanggal', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan total durasi pegawai untuk periode tertentu (agregasi dari ranking harian)
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $mulai
     * @param \DateTimeInterface $selesai
     * @return array ['total_durasi' => int, 'rata_rata' => float, 'jumlah_hari' => int]
     */
    public function getAgregasiByPeriode(Pegawai $pegawai, \DateTimeInterface $mulai, \DateTimeInterface $selesai): array
    {
        $result = $this->createQueryBuilder('rh')
            ->select(
                'SUM(rh.totalDurasi) as total_durasi',
                'AVG(rh.totalDurasi) as rata_rata',
                'COUNT(rh.id) as jumlah_hari'
            )
            ->andWhere('rh.pegawai = :pegawai')
            ->andWhere('rh.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_durasi' => (int) ($result['total_durasi'] ?? 0),
            'rata_rata' => (float) ($result['rata_rata'] ?? 0),
            'jumlah_hari' => (int) ($result['jumlah_hari'] ?? 0),
        ];
    }

    /**
     * Hapus semua ranking harian untuk periode tertentu
     *
     * @param \DateTimeInterface $mulai
     * @param \DateTimeInterface $selesai
     * @return int Jumlah record yang dihapus
     */
    public function deleteByPeriode(\DateTimeInterface $mulai, \DateTimeInterface $selesai): int
    {
        return $this->createQueryBuilder('rh')
            ->delete()
            ->andWhere('rh.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }

    /**
     * Dapatkan ranking harian pegawai untuk bulan ini
     *
     * @param Pegawai $pegawai
     * @return RankingHarian[]
     */
    public function findByPegawaiThisMonth(Pegawai $pegawai): array
    {
        $now = new \DateTime();
        $mulai = new \DateTime($now->format('Y-m-01'));
        $selesai = new \DateTime($now->format('Y-m-t'));

        return $this->findByPegawaiAndPeriode($pegawai, $mulai, $selesai);
    }

    /**
     * Dapatkan peringkat pegawai untuk tanggal tertentu dengan unit kerja yang sama
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $tanggal
     * @return array ['posisi' => int, 'total_pegawai' => int]
     */
    public function getRankingByUnitKerja(Pegawai $pegawai, \DateTimeInterface $tanggal): array
    {
        $unitKerja = $pegawai->getUnitKerjaEntity();

        if (!$unitKerja) {
            return ['posisi' => 0, 'total_pegawai' => 0];
        }

        // Dapatkan semua ranking untuk tanggal tersebut dari unit kerja yang sama
        $rankings = $this->createQueryBuilder('rh')
            ->join('rh.pegawai', 'p')
            ->andWhere('rh.tanggal = :tanggal')
            ->andWhere('p.unitKerjaEntity = :unitKerja')
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->setParameter('unitKerja', $unitKerja)
            ->orderBy('rh.totalDurasi', 'ASC')
            ->getQuery()
            ->getResult();

        $posisi = 0;
        foreach ($rankings as $index => $ranking) {
            if ($ranking->getPegawai()->getId() === $pegawai->getId()) {
                $posisi = $index + 1;
                break;
            }
        }

        return [
            'posisi' => $posisi,
            'total_pegawai' => count($rankings)
        ];
    }
}
