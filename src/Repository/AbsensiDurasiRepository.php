<?php

namespace App\Repository;

use App\Entity\AbsensiDurasi;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository untuk mengelola data AbsensiDurasi
 *
 * @extends ServiceEntityRepository<AbsensiDurasi>
 */
class AbsensiDurasiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsensiDurasi::class);
    }

    /**
     * Simpan entity AbsensiDurasi
     */
    public function save(AbsensiDurasi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Hapus entity AbsensiDurasi
     */
    public function remove(AbsensiDurasi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Cari data absensi durasi berdasarkan pegawai dan tanggal
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $tanggal
     * @return AbsensiDurasi|null
     */
    public function findByPegawaiAndTanggal(Pegawai $pegawai, \DateTimeInterface $tanggal): ?AbsensiDurasi
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.pegawai = :pegawai')
            ->andWhere('ad.tanggal = :tanggal')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dapatkan semua data absensi durasi untuk tanggal tertentu
     *
     * @param \DateTimeInterface $tanggal
     * @return AbsensiDurasi[]
     */
    public function findByTanggal(\DateTimeInterface $tanggal): array
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.tanggal = :tanggal')
            ->setParameter('tanggal', $tanggal->format('Y-m-d'))
            ->orderBy('ad.durasiMenit', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan total durasi pegawai untuk periode tertentu
     *
     * @param Pegawai $pegawai
     * @param \DateTimeInterface $mulai
     * @param \DateTimeInterface $selesai
     * @return int Total durasi dalam menit
     */
    public function getTotalDurasiByPeriode(Pegawai $pegawai, \DateTimeInterface $mulai, \DateTimeInterface $selesai): int
    {
        $result = $this->createQueryBuilder('ad')
            ->select('SUM(ad.durasiMenit) as total')
            ->andWhere('ad.pegawai = :pegawai')
            ->andWhere('ad.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Dapatkan data absensi durasi pegawai untuk bulan tertentu
     *
     * @param Pegawai $pegawai
     * @param int $tahun
     * @param int $bulan
     * @return AbsensiDurasi[]
     */
    public function findByPegawaiAndBulan(Pegawai $pegawai, int $tahun, int $bulan): array
    {
        $mulai = new \DateTime("{$tahun}-{$bulan}-01");
        $selesai = (clone $mulai)->modify('last day of this month');

        return $this->createQueryBuilder('ad')
            ->andWhere('ad.pegawai = :pegawai')
            ->andWhere('ad.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->orderBy('ad.tanggal', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Hapus semua data absensi durasi untuk periode tertentu
     *
     * @param \DateTimeInterface $mulai
     * @param \DateTimeInterface $selesai
     * @return int Jumlah record yang dihapus
     */
    public function deleteByPeriode(\DateTimeInterface $mulai, \DateTimeInterface $selesai): int
    {
        return $this->createQueryBuilder('ad')
            ->delete()
            ->andWhere('ad.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }

    /**
     * Dapatkan statistik absensi durasi pegawai untuk bulan tertentu
     *
     * @param Pegawai $pegawai
     * @param int $tahun
     * @param int $bulan
     * @return array ['total' => int, 'rata_rata' => float, 'min' => int, 'max' => int, 'jumlah_hari' => int]
     */
    public function getStatistikByBulan(Pegawai $pegawai, int $tahun, int $bulan): array
    {
        $mulai = new \DateTime("{$tahun}-{$bulan}-01");
        $selesai = (clone $mulai)->modify('last day of this month');

        $result = $this->createQueryBuilder('ad')
            ->select(
                'SUM(ad.durasiMenit) as total',
                'AVG(ad.durasiMenit) as rata_rata',
                'MIN(ad.durasiMenit) as min',
                'MAX(ad.durasiMenit) as max',
                'COUNT(ad.id) as jumlah_hari'
            )
            ->andWhere('ad.pegawai = :pegawai')
            ->andWhere('ad.tanggal BETWEEN :mulai AND :selesai')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('mulai', $mulai->format('Y-m-d'))
            ->setParameter('selesai', $selesai->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total' => (int) ($result['total'] ?? 0),
            'rata_rata' => (float) ($result['rata_rata'] ?? 0),
            'min' => (int) ($result['min'] ?? 0),
            'max' => (int) ($result['max'] ?? 0),
            'jumlah_hari' => (int) ($result['jumlah_hari'] ?? 0),
        ];
    }
}
