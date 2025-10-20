<?php

namespace App\Repository;

use App\Entity\RankingBulanan;
use App\Entity\Pegawai;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository untuk mengelola data RankingBulanan
 *
 * @extends ServiceEntityRepository<RankingBulanan>
 */
class RankingBulananRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankingBulanan::class);
    }

    /**
     * Simpan entity RankingBulanan
     */
    public function save(RankingBulanan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Hapus entity RankingBulanan
     */
    public function remove(RankingBulanan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Cari ranking bulanan berdasarkan pegawai dan periode
     *
     * @param Pegawai $pegawai
     * @param string $periode Format: YYYY-MM
     * @return RankingBulanan|null
     */
    public function findByPegawaiAndPeriode(Pegawai $pegawai, string $periode): ?RankingBulanan
    {
        return $this->createQueryBuilder('rb')
            ->andWhere('rb.pegawai = :pegawai')
            ->andWhere('rb.periode = :periode')
            ->setParameter('pegawai', $pegawai)
            ->setParameter('periode', $periode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dapatkan semua ranking untuk periode tertentu, diurutkan berdasarkan peringkat
     *
     * @param string $periode Format: YYYY-MM
     * @return RankingBulanan[]
     */
    public function findByPeriode(string $periode): array
    {
        return $this->createQueryBuilder('rb')
            ->andWhere('rb.periode = :periode')
            ->setParameter('periode', $periode)
            ->orderBy('rb.peringkat', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan top N ranking untuk periode tertentu
     *
     * @param string $periode Format: YYYY-MM
     * @param int $limit
     * @return RankingBulanan[]
     */
    public function findTopByPeriode(string $periode, int $limit = 10): array
    {
        return $this->createQueryBuilder('rb')
            ->andWhere('rb.periode = :periode')
            ->setParameter('periode', $periode)
            ->orderBy('rb.peringkat', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan ranking bulanan pegawai untuk beberapa periode
     *
     * @param Pegawai $pegawai
     * @param int $jumlahBulan Jumlah bulan ke belakang (default: 6)
     * @return RankingBulanan[]
     */
    public function findByPegawaiLastMonths(Pegawai $pegawai, int $jumlahBulan = 6): array
    {
        return $this->createQueryBuilder('rb')
            ->andWhere('rb.pegawai = :pegawai')
            ->setParameter('pegawai', $pegawai)
            ->orderBy('rb.periode', 'DESC')
            ->setMaxResults($jumlahBulan)
            ->getQuery()
            ->getResult();
    }

    /**
     * Dapatkan ranking bulanan pegawai untuk bulan ini
     *
     * @param Pegawai $pegawai
     * @return RankingBulanan|null
     */
    public function findByPegawaiThisMonth(Pegawai $pegawai): ?RankingBulanan
    {
        $periode = (new \DateTime())->format('Y-m');
        return $this->findByPegawaiAndPeriode($pegawai, $periode);
    }

    /**
     * Hapus semua ranking bulanan untuk periode tertentu
     *
     * @param string $periode Format: YYYY-MM
     * @return int Jumlah record yang dihapus
     */
    public function deleteByPeriode(string $periode): int
    {
        return $this->createQueryBuilder('rb')
            ->delete()
            ->andWhere('rb.periode = :periode')
            ->setParameter('periode', $periode)
            ->getQuery()
            ->execute();
    }

    /**
     * Dapatkan peringkat pegawai untuk periode tertentu dengan unit kerja yang sama
     *
     * @param Pegawai $pegawai
     * @param string $periode Format: YYYY-MM
     * @return array ['posisi' => int, 'total_pegawai' => int, 'nama_unit' => string]
     */
    public function getRankingByUnitKerja(Pegawai $pegawai, string $periode): array
    {
        $unitKerja = $pegawai->getUnitKerjaEntity();

        if (!$unitKerja) {
            return [
                'posisi' => 0,
                'total_pegawai' => 0,
                'nama_unit' => 'Unit Tidak Diketahui'
            ];
        }

        // Dapatkan semua ranking untuk periode tersebut dari unit kerja yang sama
        $rankings = $this->createQueryBuilder('rb')
            ->join('rb.pegawai', 'p')
            ->andWhere('rb.periode = :periode')
            ->andWhere('p.unitKerjaEntity = :unitKerja')
            ->setParameter('periode', $periode)
            ->setParameter('unitKerja', $unitKerja)
            ->orderBy('rb.totalDurasi', 'ASC')
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
            'total_pegawai' => count($rankings),
            'nama_unit' => $unitKerja->getNamaUnit()
        ];
    }

    /**
     * Dapatkan statistik agregat untuk periode tertentu
     *
     * @param string $periode Format: YYYY-MM
     * @return array ['total_pegawai' => int, 'rata_rata_durasi' => float, 'min_durasi' => int, 'max_durasi' => int]
     */
    public function getStatistikByPeriode(string $periode): array
    {
        $result = $this->createQueryBuilder('rb')
            ->select(
                'COUNT(rb.id) as total_pegawai',
                'AVG(rb.rataRataDurasi) as rata_rata_durasi',
                'MIN(rb.totalDurasi) as min_durasi',
                'MAX(rb.totalDurasi) as max_durasi'
            )
            ->andWhere('rb.periode = :periode')
            ->setParameter('periode', $periode)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_pegawai' => (int) ($result['total_pegawai'] ?? 0),
            'rata_rata_durasi' => (float) ($result['rata_rata_durasi'] ?? 0),
            'min_durasi' => (int) ($result['min_durasi'] ?? 0),
            'max_durasi' => (int) ($result['max_durasi'] ?? 0),
        ];
    }

    /**
     * Dapatkan tren peringkat pegawai selama beberapa bulan terakhir
     *
     * @param Pegawai $pegawai
     * @param int $jumlahBulan
     * @return array Array of ['periode' => string, 'peringkat' => int, 'total_durasi' => int]
     */
    public function getTrenPeringkat(Pegawai $pegawai, int $jumlahBulan = 6): array
    {
        $rankings = $this->findByPegawaiLastMonths($pegawai, $jumlahBulan);

        $tren = [];
        foreach ($rankings as $ranking) {
            $tren[] = [
                'periode' => $ranking->getPeriode(),
                'nama_bulan' => $ranking->getNamaBulan(),
                'peringkat' => $ranking->getPeringkat(),
                'total_durasi' => $ranking->getTotalDurasi(),
                'rata_rata' => $ranking->getRataRataDurasi(),
            ];
        }

        return array_reverse($tren); // Urutkan dari yang terlama ke terbaru
    }
}
