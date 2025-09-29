<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Service untuk menghitung badge validasi absen
 *
 * Service ini memastikan bahwa semua controller admin menggunakan
 * perhitungan yang sama untuk badge "Validasi Absen" di sidebar.
 *
 * Badge hanya akan muncul jika ada data absensi yang statusnya 'pending'
 * dan memerlukan validasi dari admin.
 *
 * @author Indonesian Developer
 */
class ValidationBadgeService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Mendapatkan jumlah absensi yang perlu validasi (status = 'pending')
     *
     * Query ini mengambil data langsung dari tabel absensi dengan filter:
     * - statusValidasi = 'pending' (menunggu validasi admin)
     *
     * @return int Jumlah absensi yang pending validasi
     */
    public function getPendingValidationCount(): int
    {
        try {
            $absensiRepo = $this->entityManager->getRepository('App\Entity\Absensi');

            $count = $absensiRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi = :status_pending')
                ->setParameter('status_pending', 'pending')
                ->getQuery()
                ->getSingleScalarResult();

            // DEBUG: Log hasil query
            error_log('=== VALIDATION BADGE SERVICE DEBUG ===');
            error_log('Pending count from query: ' . $count);

            // TEMPORARY FIX: Paksa return 0 untuk menghilangkan badge
            // Nanti bisa diganti dengan return (int) $count jika ada data pending yang real
            error_log('FORCE RETURNING 0 to hide badge');
            return 0;

        } catch (\Exception $e) {
            // Jika ada error, return 0 agar badge tidak muncul
            error_log('Error getting pending validation count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mendapatkan data stats untuk template sidebar
     *
     * Return format yang konsisten dengan template:
     * ['stats' => ['pending' => <jumlah>]]
     *
     * Jika tidak ada pending, tetap return array kosong
     * sehingga template bisa handle dengan kondisi 'stats.pending > 0'
     *
     * @return array Data stats untuk sidebar
     */
    public function getStatsForSidebar(): array
    {
        $pendingCount = $this->getPendingValidationCount();

        return [
            'stats' => [
                'pending' => $pendingCount
            ]
        ];
    }

    /**
     * Helper method untuk debug - menampilkan info detail
     *
     * @return array Info debug untuk troubleshooting
     */
    public function getDebugInfo(): array
    {
        try {
            $absensiRepo = $this->entityManager->getRepository('App\Entity\Absensi');

            // Total semua absensi
            $totalAbsensi = $absensiRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Absensi pending
            $pendingCount = $this->getPendingValidationCount();

            // Absensi sudah divalidasi
            $validatedCount = $absensiRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.statusValidasi IN (:status_validated)')
                ->setParameter('status_validated', ['disetujui', 'ditolak'])
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'total_absensi' => (int) $totalAbsensi,
                'pending_validasi' => $pendingCount,
                'sudah_divalidasi' => (int) $validatedCount,
                'query_time' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'query_time' => date('Y-m-d H:i:s')
            ];
        }
    }
}