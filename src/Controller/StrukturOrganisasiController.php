<?php

namespace App\Controller;

use App\Entity\UnitKerja;
use App\Entity\KepalaBidang;
use App\Entity\KepalaKantor;
use App\Entity\Pegawai;
use App\Repository\UnitKerjaRepository;
use App\Repository\KepalaBidangRepository;
use App\Repository\KepalaKantorRepository;
use App\Service\ValidationBadgeService;
use App\Service\AdminPermissionService;
use App\Service\OrganizationalStructureService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/struktur-organisasi')]
#[IsGranted('ROLE_ADMIN')]
final class StrukturOrganisasiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidationBadgeService $validationBadgeService;
    private AdminPermissionService $adminPermissionService;
    private OrganizationalStructureService $organizationalService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidationBadgeService $validationBadgeService,
        AdminPermissionService $adminPermissionService,
        OrganizationalStructureService $organizationalService
    ) {
        $this->entityManager = $entityManager;
        $this->validationBadgeService = $validationBadgeService;
        $this->adminPermissionService = $adminPermissionService;
        $this->organizationalService = $organizationalService;
    }
    #[Route('/', name: 'app_struktur_organisasi_index')]
    public function index(
        UnitKerjaRepository $unitKerjaRepo,
        KepalaBidangRepository $kepalaBidangRepo,
        KepalaKantorRepository $kepalaKantorRepo,
        EntityManagerInterface $em
    ): Response {
        $admin = $this->getUser();

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua data
            $unitKerjaList = $unitKerjaRepo->findAllWithKepalaBidang();
            $kepalaBidangList = $kepalaBidangRepo->findAllWithUnitKerja();
        } else {
            // Admin Unit hanya lihat data unit kerjanya
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }

            // Filter unit kerja dan kepala bidang berdasarkan unit admin
            $unitKerjaList = [$adminUnitKerja]; // Hanya unit kerja admin
            $kepalaBidangList = $kepalaBidangRepo->findByUnitKerjaEntity($adminUnitKerja);
        }

        $kepalaKantorList = $kepalaKantorRepo->findAllOrdered();
        $activeKepalaKantor = $kepalaKantorRepo->findActiveKepalaKantor();

        // Get pegawai berdasarkan permission admin
        $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
        if ($admin->isSuperAdmin()) {
            $allPegawai = $pegawaiRepo->findAll();
        } else {
            // Admin Unit hanya lihat pegawai di unit kerjanya
            $allPegawai = $pegawaiRepo->findBy(['unitKerjaEntity' => $adminUnitKerja]);
        }

        // Statistics
        $unitStats = $unitKerjaRepo->getStatistics();
        $kepalaBidangStats = $kepalaBidangRepo->getStatistics();
        $kepalaKantorStats = $kepalaKantorRepo->getStatistics();
        
        // Add pegawai statistics
        $totalPegawai = count($allPegawai);
        $pegawaiWithUnit = 0;
        foreach ($allPegawai as $pegawai) {
            if ($pegawai->getUnitKerjaEntity() !== null) {
                $pegawaiWithUnit++;
            }
        }

        // Ambil stats untuk badge sidebar menggunakan service yang konsisten
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/struktur_organisasi.html.twig', array_merge([
            'unit_kerja_list' => $unitKerjaList,
            'kepala_bidang_list' => $kepalaBidangList,
            'kepala_kantor_list' => $kepalaKantorList,
            'active_kepala_kantor' => $activeKepalaKantor,
            'all_pegawai' => $allPegawai,
            'statistics' => [
                'unit_kerja' => $unitStats,
                'kepala_bidang' => $kepalaBidangStats,
                'kepala_kantor' => $kepalaKantorStats,
                'pegawai' => [
                    'total_pegawai' => $totalPegawai,
                    'pegawai_with_unit' => $pegawaiWithUnit,
                    'pegawai_without_unit' => $totalPegawai - $pegawaiWithUnit
                ]
            ]
        ], $sidebarStats));
    }

    #[Route('/unit-kerja', name: 'app_admin_unit_kerja')]
    public function unitKerja(UnitKerjaRepository $unitKerjaRepo): Response
    {
        $admin = $this->getUser();

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua unit kerja
            $unitKerjaList = $unitKerjaRepo->findAllWithKepalaBidang();
        } else {
            // Admin Unit hanya lihat unit kerjanya sendiri
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }
            $unitKerjaList = [$adminUnitKerja];
        }

        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/unit_kerja.html.twig', array_merge([
            'unit_kerja_list' => $unitKerjaList
        ], $sidebarStats));
    }

    #[Route('/kepala-bidang', name: 'app_admin_kepala_bidang')]
    public function kepalaBidang(
        KepalaBidangRepository $kepalaBidangRepo,
        UnitKerjaRepository $unitKerjaRepo
    ): Response {
        $admin = $this->getUser();

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua kepala bidang dan unit kerja
            $kepalaBidangList = $kepalaBidangRepo->findAllWithUnitKerja();
            $unitKerjaList = $unitKerjaRepo->findAll();
        } else {
            // Admin Unit hanya lihat kepala bidang dan unit kerja miliknya
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }

            $kepalaBidangList = $kepalaBidangRepo->findByUnitKerjaEntity($adminUnitKerja);
            $unitKerjaList = [$adminUnitKerja]; // Hanya unit kerja admin
        }

        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/kepala_bidang.html.twig', array_merge([
            'kepala_bidang_list' => $kepalaBidangList,
            'unit_kerja_list' => $unitKerjaList
        ], $sidebarStats));
    }

    #[Route('/kepala-kantor', name: 'app_admin_kepala_kantor')]
    public function kepalaKantor(KepalaKantorRepository $kepalaKantorRepo): Response
    {
        $kepalaKantorList = $kepalaKantorRepo->findAllOrdered();
        $activeKepalaKantor = $kepalaKantorRepo->findActiveKepalaKantor();
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/kepala_kantor.html.twig', array_merge([
            'kepala_kantor_list' => $kepalaKantorList,
            'active_kepala_kantor' => $activeKepalaKantor
        ], $sidebarStats));
    }

    #[Route('/pegawai', name: 'app_admin_pegawai')]
    public function pegawai(
        Request $request,
        UnitKerjaRepository $unitKerjaRepo,
        EntityManagerInterface $em
    ): Response {
        $admin = $this->getUser();

        // FILTER BERDASARKAN UNIT KERJA ADMIN
        if ($admin->isSuperAdmin()) {
            // Super Admin bisa lihat semua unit kerja
            $unitKerjaList = $unitKerjaRepo->findBy([], ['namaUnit' => 'ASC']);
        } else {
            // Admin Unit hanya lihat unit kerjanya sendiri
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if (!$adminUnitKerja) {
                $this->addFlash('warning', 'Anda belum di-assign ke unit kerja. Hubungi Super Admin.');
                return $this->redirectToRoute('app_admin_dashboard');
            }
            $unitKerjaList = [$adminUnitKerja];
        }

        // Ambil parameter pencarian dan filter dari query string
        $search = $request->query->get('search');
        $unitKerjaId = $request->query->get('unit_kerja_id');

        // Konversi unitKerjaId ke integer jika tidak kosong
        $unitKerjaIdInt = !empty($unitKerjaId) ? (int)$unitKerjaId : null;

        // FILTER: Admin Unit tidak boleh akses pegawai unit lain
        if (!$admin->isSuperAdmin() && $unitKerjaIdInt && $unitKerjaIdInt !== $admin->getUnitKerjaEntity()->getId()) {
            $this->addFlash('error', 'Anda tidak memiliki akses ke unit kerja tersebut.');
            return $this->redirectToRoute('app_admin_pegawai');
        }

        // FIXED: Ambil pegawai dari kedua source - Pegawai entity dan Admin dengan role pegawai
        $allPegawai = $this->getCombinedPegawaiData($em, $search, $unitKerjaIdInt, $admin);

        // Group pegawai by unit kerja untuk display
        $groupedPegawai = $this->groupPegawaiByUnitKerja($allPegawai);
        $sidebarStats = $this->validationBadgeService->getStatsForSidebar();

        return $this->render('admin/pegawai.html.twig', array_merge([
            'unit_kerja_list' => $unitKerjaList,
            'all_pegawai' => $allPegawai, // Keep for statistics
            'grouped_pegawai' => $groupedPegawai, // New grouped data
            'search' => $search,
            'selected_unit_id' => $unitKerjaIdInt
        ], $sidebarStats));
    }

    /**
     * Mengambil data pegawai dari kedua source: Pegawai entity dan Admin dengan role pegawai
     */
    private function getCombinedPegawaiData(EntityManagerInterface $em, ?string $search = null, ?int $unitKerjaId = null, $admin = null): array
    {
        $combinedData = [];

        // 1. Ambil dari Pegawai entity
        $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
        $pegawaiQueryBuilder = $pegawaiRepo->createQueryBuilder('p')
            ->leftJoin('p.unitKerjaEntity', 'u')
            ->orderBy('p.nama', 'ASC');

        // FILTER: Admin Unit hanya bisa lihat pegawai di unit kerjanya
        if ($admin && !$admin->isSuperAdmin()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if ($adminUnitKerja) {
                $pegawaiQueryBuilder->andWhere('p.unitKerjaEntity = :adminUnit')
                                  ->setParameter('adminUnit', $adminUnitKerja);
            }
        }

        if (!empty($search)) {
            $pegawaiQueryBuilder->andWhere(
                $pegawaiQueryBuilder->expr()->orX(
                    $pegawaiQueryBuilder->expr()->like('p.nama', ':search'),
                    $pegawaiQueryBuilder->expr()->like('p.nip', ':search'),
                    $pegawaiQueryBuilder->expr()->like('p.jabatan', ':search'),
                    $pegawaiQueryBuilder->expr()->like('u.namaUnit', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        if ($unitKerjaId !== null) {
            $pegawaiQueryBuilder->andWhere('p.unitKerjaEntity = :unitKerjaId')
                              ->setParameter('unitKerjaId', $unitKerjaId);
        }

        $pegawaiList = $pegawaiQueryBuilder->getQuery()->getResult();

        // Add to combined data with source indicator
        foreach ($pegawaiList as $pegawai) {
            $combinedData[] = [
                'entity' => $pegawai,
                'source' => 'pegawai',
                'nama' => $pegawai->getNama(),
                'nip' => $pegawai->getNip(),
                'jabatan' => $pegawai->getJabatan(),
                'unitKerjaEntity' => $pegawai->getUnitKerjaEntity()
            ];
        }

        // 2. Ambil dari Admin dengan role pegawai
        $adminRepo = $em->getRepository('App\Entity\Admin');
        $adminQueryBuilder = $adminRepo->createQueryBuilder('a')
            ->leftJoin('a.unitKerjaEntity', 'u')
            ->where('a.role = :role')
            ->setParameter('role', 'pegawai')
            ->orderBy('a.namaLengkap', 'ASC');

        // FILTER: Admin Unit hanya bisa lihat admin pegawai di unit kerjanya
        if ($admin && !$admin->isSuperAdmin()) {
            $adminUnitKerja = $admin->getUnitKerjaEntity();
            if ($adminUnitKerja) {
                $adminQueryBuilder->andWhere('a.unitKerjaEntity = :adminUnit')
                                 ->setParameter('adminUnit', $adminUnitKerja);
            }
        }

        if (!empty($search)) {
            $adminQueryBuilder->andWhere(
                $adminQueryBuilder->expr()->orX(
                    $adminQueryBuilder->expr()->like('a.namaLengkap', ':search'),
                    $adminQueryBuilder->expr()->like('a.nip', ':search'),
                    $adminQueryBuilder->expr()->like('a.username', ':search'),
                    $adminQueryBuilder->expr()->like('u.namaUnit', ':search')
                )
            )->setParameter('search', '%' . $search . '%');
        }

        if ($unitKerjaId !== null) {
            $adminQueryBuilder->andWhere('a.unitKerjaEntity = :unitKerjaId')
                            ->setParameter('unitKerjaId', $unitKerjaId);
        }

        $adminPegawaiList = $adminQueryBuilder->getQuery()->getResult();

        // Add to combined data with source indicator
        foreach ($adminPegawaiList as $admin) {
            // Check if already exists in pegawai data (to avoid duplicates)
            $exists = false;
            foreach ($combinedData as $existing) {
                if ($existing['nip'] === $admin->getNip() && !empty($admin->getNip())) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $combinedData[] = [
                    'entity' => $admin,
                    'source' => 'admin',
                    'nama' => $admin->getNamaLengkap(),
                    'nip' => $admin->getNip() ?: $admin->getUsername(),
                    'jabatan' => 'Pegawai', // Default for admin with role pegawai
                    'unitKerjaEntity' => $admin->getUnitKerjaEntity()
                ];
            }
        }

        // Sort by name
        usort($combinedData, function($a, $b) {
            return strcmp($a['nama'], $b['nama']);
        });

        return $combinedData;
    }

    /**
     * Group pegawai data by unit kerja
     */
    private function groupPegawaiByUnitKerja(array $allPegawai): array
    {
        $grouped = [];

        foreach ($allPegawai as $pegawai) {
            $unitKerjaId = $pegawai['unitKerjaEntity'] ? $pegawai['unitKerjaEntity']->getId() : 'no_unit';
            $unitKerjaName = $pegawai['unitKerjaEntity'] ? $pegawai['unitKerjaEntity']->getNamaUnit() : 'Tanpa Unit Kerja';
            $unitKerjaKode = $pegawai['unitKerjaEntity'] ? $pegawai['unitKerjaEntity']->getKodeUnit() : '';

            if (!isset($grouped[$unitKerjaId])) {
                $grouped[$unitKerjaId] = [
                    'unit_kerja' => [
                        'id' => $unitKerjaId,
                        'nama' => $unitKerjaName,
                        'kode' => $unitKerjaKode
                    ],
                    'pegawai' => []
                ];
            }

            $grouped[$unitKerjaId]['pegawai'][] = $pegawai;
        }

        // Sort by unit kerja name, with "Tanpa Unit Kerja" at the end
        uksort($grouped, function($a, $b) use ($grouped) {
            if ($a === 'no_unit') return 1;
            if ($b === 'no_unit') return -1;
            return strcmp($grouped[$a]['unit_kerja']['nama'], $grouped[$b]['unit_kerja']['nama']);
        });

        return $grouped;
    }

    // =================== UNIT KERJA ===================

    #[Route('/unit-kerja/create', name: 'app_struktur_organisasi_create_unit', methods: ['POST'])]
    public function createUnitKerja(Request $request, EntityManagerInterface $em, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $namaUnit = trim($request->request->get('nama_unit', ''));
            $kodeUnit = trim($request->request->get('kode_unit', ''));
            $keterangan = trim($request->request->get('keterangan', ''));

            // Validation
            if (empty($namaUnit) || empty($kodeUnit)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama Unit dan Kode Unit wajib diisi'
                ]);
            }

            if (strlen($kodeUnit) > 20) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Kode Unit maksimal 20 karakter'
                ]);
            }

            // Check duplicate kode unit
            if ($unitKerjaRepo->isKodeUnitExists($kodeUnit)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Kode Unit sudah digunakan'
                ]);
            }

            $unitKerja = new UnitKerja();
            $unitKerja->setNamaUnit($namaUnit);
            $unitKerja->setKodeUnit($kodeUnit);
            $unitKerja->setKeterangan($keterangan ?: null);

            $em->persist($unitKerja);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Unit Kerja '{$namaUnit}' berhasil ditambahkan",
                'unit_kerja' => [
                    'id' => $unitKerja->getId(),
                    'nama_unit' => $unitKerja->getNamaUnit(),
                    'kode_unit' => $unitKerja->getKodeUnit(),
                    'keterangan' => $unitKerja->getKeterangan(),
                    'kepala_bidang' => null,
                    'jumlah_pegawai' => 0
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menambah unit kerja: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/unit-kerja/{id}/edit', name: 'app_struktur_organisasi_edit_unit', methods: ['GET'])]
    public function getUnitKerja(UnitKerja $unitKerja): JsonResponse
    {
        return new JsonResponse([
            'id' => $unitKerja->getId(),
            'nama_unit' => $unitKerja->getNamaUnit(),
            'kode_unit' => $unitKerja->getKodeUnit(),
            'keterangan' => $unitKerja->getKeterangan()
        ]);
    }

    #[Route('/unit-kerja/{id}/update', name: 'app_struktur_organisasi_update_unit', methods: ['POST'])]
    public function updateUnitKerja(UnitKerja $unitKerja, Request $request, EntityManagerInterface $em, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $namaUnit = trim($request->request->get('nama_unit', ''));
            $kodeUnit = trim($request->request->get('kode_unit', ''));
            $keterangan = trim($request->request->get('keterangan', ''));

            // Validation
            if (empty($namaUnit) || empty($kodeUnit)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama Unit dan Kode Unit wajib diisi'
                ]);
            }

            // Check duplicate kode unit (excluding current record)
            if ($unitKerjaRepo->isKodeUnitExists($kodeUnit, $unitKerja->getId())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Kode Unit sudah digunakan'
                ]);
            }

            $unitKerja->setNamaUnit($namaUnit);
            $unitKerja->setKodeUnit($kodeUnit);
            $unitKerja->setKeterangan($keterangan ?: null);
            $unitKerja->setUpdatedAt(new \DateTime());

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Unit Kerja '{$namaUnit}' berhasil diperbarui",
                'unit_kerja' => [
                    'id' => $unitKerja->getId(),
                    'nama_unit' => $unitKerja->getNamaUnit(),
                    'kode_unit' => $unitKerja->getKodeUnit(),
                    'keterangan' => $unitKerja->getKeterangan(),
                    'kepala_bidang' => $unitKerja->getNamaKepalaBidang(),
                    'jumlah_pegawai' => $unitKerja->getJumlahPegawai()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal memperbarui unit kerja: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/unit-kerja/{id}/delete', name: 'app_struktur_organisasi_delete_unit', methods: ['DELETE'])]
    public function deleteUnitKerja(UnitKerja $unitKerja, EntityManagerInterface $em): JsonResponse
    {
        try {
            // Check if unit has kepala bidang or pegawai
            if ($unitKerja->getKepalaBidang() !== null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Tidak dapat menghapus unit kerja yang memiliki Kepala Bidang. Hapus Kepala Bidang terlebih dahulu.'
                ]);
            }

            if ($unitKerja->getJumlahPegawai() > 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Tidak dapat menghapus unit kerja yang memiliki pegawai. Pindahkan pegawai terlebih dahulu.'
                ]);
            }

            $namaUnit = $unitKerja->getNamaUnit();
            $em->remove($unitKerja);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "🗑️ Unit Kerja '{$namaUnit}' berhasil dihapus",
                'unit_id' => $unitKerja->getId()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menghapus unit kerja: ' . $e->getMessage()
            ]);
        }
    }

    // =================== KEPALA BIDANG ===================

    #[Route('/kepala-bidang/create', name: 'app_struktur_organisasi_create_kepala_bidang', methods: ['POST'])]
    public function createKepalaBidang(Request $request, EntityManagerInterface $em, KepalaBidangRepository $kepalaBidangRepo, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $admin = $this->getUser();

            // Get JSON data
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No JSON data received'
                ]);
            }

            $nama = $data['nama'] ?? '';
            $nip = $data['nip'] ?? '';
            $jabatan = $data['jabatan'] ?? '';
            $pangkatGol = $data['pangkat_gol'] ?? '';
            $unitKerjaId = $data['unit_kerja_id'] ?? null;

            // Basic validation
            if (empty($nama) || empty($nip) || empty($jabatan) || empty($unitKerjaId)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Semua field wajib diisi'
                ]);
            }

            // Get unit kerja
            $unitKerja = $unitKerjaRepo->find($unitKerjaId);
            if (!$unitKerja) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Unit Kerja tidak ditemukan'
                ]);
            }

            // PERMISSION CHECK: Admin Unit hanya bisa create kepala bidang untuk unit kerjanya
            if (!$admin->isSuperAdmin()) {
                $adminUnitKerja = $admin->getUnitKerjaEntity();
                if (!$adminUnitKerja || $adminUnitKerja->getId() !== $unitKerja->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Anda hanya dapat mengelola kepala bidang untuk unit kerja Anda sendiri'
                    ]);
                }
            }

            // Create and save
            $kepalaBidang = new KepalaBidang();
            $kepalaBidang->setNama($nama);
            $kepalaBidang->setNip($nip);
            $kepalaBidang->setJabatan($jabatan);
            $kepalaBidang->setPangkatGol($pangkatGol);
            $kepalaBidang->setUnitKerja($unitKerja);

            $em->persist($kepalaBidang);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Kepala Bidang berhasil ditambahkan'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/kepala-bidang/{id}/edit', name: 'app_struktur_organisasi_edit_kepala_bidang', methods: ['GET'])]
    public function getKepalaBidang(KepalaBidang $kepalaBidang): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'kepala_bidang' => [
                'id' => $kepalaBidang->getId(),
                'nama' => $kepalaBidang->getNama(),
                'nip' => $kepalaBidang->getNip(),
                'jabatan' => $kepalaBidang->getJabatan(),
                'pangkatGol' => $kepalaBidang->getPangkatGol(),
                'unitKerjaId' => $kepalaBidang->getUnitKerja()->getId()
            ]
        ]);
    }

    #[Route('/kepala-bidang/{id}/update', name: 'app_struktur_organisasi_update_kepala_bidang', methods: ['POST'])]
    public function updateKepalaBidang(KepalaBidang $kepalaBidang, Request $request, EntityManagerInterface $em, KepalaBidangRepository $kepalaBidangRepo, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $admin = $this->getUser();

            // PERMISSION CHECK: Admin Unit hanya bisa update kepala bidang di unit kerjanya
            if (!$admin->isSuperAdmin()) {
                $adminUnitKerja = $admin->getUnitKerjaEntity();
                $kepalaBidangUnitKerja = $kepalaBidang->getUnitKerja();

                if (!$adminUnitKerja || !$kepalaBidangUnitKerja || $adminUnitKerja->getId() !== $kepalaBidangUnitKerja->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Anda hanya dapat mengelola kepala bidang untuk unit kerja Anda sendiri'
                    ]);
                }
            }
            // Support both JSON and form data
            $data = [];
            if ($request->getContentType() === 'json') {
                $data = json_decode($request->getContent(), true) ?: [];
            } else {
                $data = $request->request->all();
            }

            $nama = trim($data['nama'] ?? '');
            $nip = trim($data['nip'] ?? '');
            $jabatan = trim($data['jabatan'] ?? '');
            $pangkatGol = trim($data['pangkat_gol'] ?? '');
            $unitKerjaId = $data['unit_kerja_id'] ?? null;

            // Validation
            if (empty($nama) || empty($nip) || empty($jabatan) || empty($unitKerjaId)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama, NIP, Jabatan, dan Unit Kerja wajib diisi'
                ]);
            }

            // Validate NIP format
            if (!KepalaBidang::validateNip($nip)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP harus berupa 18 digit angka'
                ]);
            }

            // Check duplicate NIP (excluding current record)
            if ($kepalaBidangRepo->isNipExists($nip, $kepalaBidang->getId())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP sudah digunakan'
                ]);
            }

            // Get unit kerja
            $unitKerja = $unitKerjaRepo->find($unitKerjaId);
            if (!$unitKerja) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Unit Kerja tidak ditemukan'
                ]);
            }

            // PERMISSION CHECK: Admin Unit tidak boleh pindah kepala bidang ke unit lain
            if (!$admin->isSuperAdmin() && $unitKerja->getId() !== $kepalaBidang->getUnitKerja()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Anda tidak dapat memindahkan kepala bidang ke unit kerja lain'
                ]);
            }

            // Check if different unit already has kepala bidang
            if ($unitKerja->getId() !== $kepalaBidang->getUnitKerja()->getId() && $unitKerja->getKepalaBidang()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Unit Kerja yang dipilih sudah memiliki Kepala Bidang'
                ]);
            }

            $kepalaBidang->setNama($nama);
            $kepalaBidang->setNip($nip);
            $kepalaBidang->setJabatan($jabatan);
            $kepalaBidang->setPangkatGol($pangkatGol ?: null);
            $kepalaBidang->setUnitKerja($unitKerja);
            $kepalaBidang->setUpdatedAt(new \DateTime());

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Kepala Bidang '{$nama}' berhasil diperbarui",
                'kepala_bidang' => [
                    'id' => $kepalaBidang->getId(),
                    'nama' => $kepalaBidang->getNama(),
                    'nip' => $kepalaBidang->getNip(),
                    'jabatan' => $kepalaBidang->getJabatan(),
                    'pangkat_gol' => $kepalaBidang->getPangkatGol(),
                    'unit_kerja' => $unitKerja->getNamaUnit()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal memperbarui kepala bidang: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/kepala-bidang/{id}/delete', name: 'app_struktur_organisasi_delete_kepala_bidang', methods: ['DELETE'])]
    public function deleteKepalaBidang(KepalaBidang $kepalaBidang, EntityManagerInterface $em): JsonResponse
    {
        try {
            $admin = $this->getUser();

            // PERMISSION CHECK: Admin Unit hanya bisa delete kepala bidang di unit kerjanya
            if (!$admin->isSuperAdmin()) {
                $adminUnitKerja = $admin->getUnitKerjaEntity();
                $kepalaBidangUnitKerja = $kepalaBidang->getUnitKerja();

                if (!$adminUnitKerja || !$kepalaBidangUnitKerja || $adminUnitKerja->getId() !== $kepalaBidangUnitKerja->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Anda hanya dapat mengelola kepala bidang untuk unit kerja Anda sendiri'
                    ]);
                }
            }
            $nama = $kepalaBidang->getNama();
            $em->remove($kepalaBidang);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "🗑️ Kepala Bidang '{$nama}' berhasil dihapus",
                'kepala_bidang_id' => $kepalaBidang->getId()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menghapus kepala bidang: ' . $e->getMessage()
            ]);
        }
    }

    // =================== KEPALA KANTOR ===================

    #[Route('/kepala-kantor/create', name: 'app_struktur_organisasi_create_kepala_kantor', methods: ['POST'])]
    public function createKepalaKantor(Request $request, EntityManagerInterface $em, KepalaKantorRepository $kepalaKantorRepo): JsonResponse
    {
        try {
            $nama = trim($request->request->get('nama', ''));
            $nip = trim($request->request->get('nip', ''));
            $jabatan = trim($request->request->get('jabatan', ''));
            $pangkatGol = trim($request->request->get('pangkat_gol', ''));
            $periode = trim($request->request->get('periode', ''));
            $isAktif = $request->request->get('is_aktif') === '1';

            // Validation
            if (empty($nama) || empty($nip) || empty($jabatan) || empty($periode)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama, NIP, Jabatan, dan Periode wajib diisi'
                ]);
            }

            // Validate NIP format
            if (!KepalaKantor::validateNip($nip)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP harus berupa 18 digit angka'
                ]);
            }

            // Check duplicate NIP
            if ($kepalaKantorRepo->isNipExists($nip)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP sudah digunakan'
                ]);
            }

            // Check if there's already an active kepala kantor
            if ($isAktif && $kepalaKantorRepo->hasActiveKepalaKantor()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Sudah ada Kepala Kantor aktif. Nonaktifkan yang lama terlebih dahulu.'
                ]);
            }

            $kepalaKantor = new KepalaKantor();
            $kepalaKantor->setNama($nama);
            $kepalaKantor->setNip($nip);
            $kepalaKantor->setJabatan($jabatan);
            $kepalaKantor->setPangkatGol($pangkatGol ?: null);
            $kepalaKantor->setPeriode($periode);
            $kepalaKantor->setIsAktif($isAktif);

            $em->persist($kepalaKantor);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Kepala Kantor '{$nama}' berhasil ditambahkan",
                'kepala_kantor' => [
                    'id' => $kepalaKantor->getId(),
                    'nama' => $kepalaKantor->getNama(),
                    'nip' => $kepalaKantor->getNip(),
                    'jabatan' => $kepalaKantor->getJabatan(),
                    'pangkat_gol' => $kepalaKantor->getPangkatGol(),
                    'periode' => $kepalaKantor->getPeriode(),
                    'is_aktif' => $kepalaKantor->isAktif(),
                    'status_text' => $kepalaKantor->getStatusText()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menambah kepala kantor: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/kepala-kantor/{id}/edit', name: 'app_struktur_organisasi_edit_kepala_kantor', methods: ['GET'])]
    public function getKepalaKantor(KepalaKantor $kepalaKantor): JsonResponse
    {
        return new JsonResponse([
            'id' => $kepalaKantor->getId(),
            'nama' => $kepalaKantor->getNama(),
            'nip' => $kepalaKantor->getNip(),
            'jabatan' => $kepalaKantor->getJabatan(),
            'pangkat_gol' => $kepalaKantor->getPangkatGol(),
            'periode' => $kepalaKantor->getPeriode(),
            'is_aktif' => $kepalaKantor->isAktif()
        ]);
    }

    #[Route('/kepala-kantor/{id}/update', name: 'app_struktur_organisasi_update_kepala_kantor', methods: ['POST'])]
    public function updateKepalaKantor(KepalaKantor $kepalaKantor, Request $request, EntityManagerInterface $em, KepalaKantorRepository $kepalaKantorRepo): JsonResponse
    {
        try {
            $nama = trim($request->request->get('nama', ''));
            $nip = trim($request->request->get('nip', ''));
            $jabatan = trim($request->request->get('jabatan', ''));
            $pangkatGol = trim($request->request->get('pangkat_gol', ''));
            $periode = trim($request->request->get('periode', ''));
            $isAktif = $request->request->get('is_aktif') === '1';

            // Validation
            if (empty($nama) || empty($nip) || empty($jabatan) || empty($periode)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama, NIP, Jabatan, dan Periode wajib diisi'
                ]);
            }

            // Validate NIP format
            if (!KepalaKantor::validateNip($nip)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP harus berupa 18 digit angka'
                ]);
            }

            // Check duplicate NIP (excluding current record)
            if ($kepalaKantorRepo->isNipExists($nip, $kepalaKantor->getId())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP sudah digunakan'
                ]);
            }

            // Check if there's already an active kepala kantor (excluding current)
            if ($isAktif && $kepalaKantorRepo->hasActiveKepalaKantor($kepalaKantor->getId())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Sudah ada Kepala Kantor aktif lainnya. Nonaktifkan yang lama terlebih dahulu.'
                ]);
            }

            $kepalaKantor->setNama($nama);
            $kepalaKantor->setNip($nip);
            $kepalaKantor->setJabatan($jabatan);
            $kepalaKantor->setPangkatGol($pangkatGol ?: null);
            $kepalaKantor->setPeriode($periode);
            $kepalaKantor->setIsAktif($isAktif);
            $kepalaKantor->setUpdatedAt(new \DateTime());

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Kepala Kantor '{$nama}' berhasil diperbarui",
                'kepala_kantor' => [
                    'id' => $kepalaKantor->getId(),
                    'nama' => $kepalaKantor->getNama(),
                    'nip' => $kepalaKantor->getNip(),
                    'jabatan' => $kepalaKantor->getJabatan(),
                    'pangkat_gol' => $kepalaKantor->getPangkatGol(),
                    'periode' => $kepalaKantor->getPeriode(),
                    'is_aktif' => $kepalaKantor->isAktif(),
                    'status_text' => $kepalaKantor->getStatusText()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal memperbarui kepala kantor: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/kepala-kantor/{id}/toggle', name: 'app_struktur_organisasi_toggle_kepala_kantor', methods: ['POST'])]
    public function toggleKepalaKantor(KepalaKantor $kepalaKantor, EntityManagerInterface $em, KepalaKantorRepository $kepalaKantorRepo): JsonResponse
    {
        try {
            $newStatus = !$kepalaKantor->isAktif();

            // If activating, check if there's already an active one
            if ($newStatus && $kepalaKantorRepo->hasActiveKepalaKantor($kepalaKantor->getId())) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Sudah ada Kepala Kantor aktif lainnya. Nonaktifkan yang lama terlebih dahulu.'
                ]);
            }

            $kepalaKantor->setIsAktif($newStatus);
            $kepalaKantor->setUpdatedAt(new \DateTime());
            $em->flush();

            $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Kepala Kantor '{$kepalaKantor->getNama()}' berhasil {$statusText}",
                'is_aktif' => $kepalaKantor->isAktif(),
                'status_text' => $kepalaKantor->getStatusText()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengubah status kepala kantor: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/kepala-kantor/{id}/delete', name: 'app_struktur_organisasi_delete_kepala_kantor', methods: ['DELETE'])]
    public function deleteKepalaKantor(KepalaKantor $kepalaKantor, EntityManagerInterface $em): JsonResponse
    {
        try {
            $nama = $kepalaKantor->getNama();
            $em->remove($kepalaKantor);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "🗑️ Kepala Kantor '{$nama}' berhasil dihapus",
                'kepala_kantor_id' => $kepalaKantor->getId()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menghapus kepala kantor: ' . $e->getMessage()
            ]);
        }
    }

    // =================== PEGAWAI MANAGEMENT ===================

    #[Route('/get-all-pegawai', name: 'app_struktur_organisasi_get_all_pegawai', methods: ['GET'])]
    public function getAllPegawai(EntityManagerInterface $em): JsonResponse
    {
        $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
        $allPegawai = $pegawaiRepo->findAll();
        
        $result = array_map(function($pegawai) {
            return [
                'id' => $pegawai->getId(),
                'nama' => $pegawai->getNama(),
                'nip' => $pegawai->getNip(),
                'jabatan' => $pegawai->getJabatan(),
                'unit_kerja_id' => $pegawai->getUnitKerjaEntity() ? $pegawai->getUnitKerjaEntity()->getId() : null,
                'unit_kerja_nama' => $pegawai->getNamaUnitKerja()
            ];
        }, $allPegawai);

        return new JsonResponse($result);
    }

    #[Route('/pegawai/assign-unit', name: 'app_struktur_organisasi_assign_pegawai_unit', methods: ['POST'])]
    public function assignPegawaiToUnit(Request $request, EntityManagerInterface $em, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $pegawaiId = $request->request->get('pegawai_id');
            $unitKerjaId = $request->request->get('unit_kerja_id');

            if (!$pegawaiId || !$unitKerjaId) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pegawai dan Unit Kerja harus dipilih'
                ]);
            }

            // Get Pegawai entity
            $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
            $pegawai = $pegawaiRepo->find($pegawaiId);
            if (!$pegawai) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pegawai tidak ditemukan'
                ]);
            }

            // Get UnitKerja entity
            $unitKerja = $unitKerjaRepo->find($unitKerjaId);
            if (!$unitKerja) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Unit Kerja tidak ditemukan'
                ]);
            }

            // Assign pegawai to unit kerja
            $pegawai->setUnitKerjaEntity($unitKerja);
            $pegawai->setUpdatedAt(new \DateTime());
            
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Pegawai '{$pegawai->getNama()}' berhasil di-assign ke unit '{$unitKerja->getNamaUnit()}'",
                'pegawai' => [
                    'id' => $pegawai->getId(),
                    'nama' => $pegawai->getNama(),
                    'unit_kerja' => $unitKerja->getNamaUnit()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal assign pegawai ke unit: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/pegawai/{id}/remove-unit', name: 'app_struktur_organisasi_remove_pegawai_unit', methods: ['POST'])]
    public function removePegawaiFromUnit(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            // Get Pegawai entity
            $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
            $pegawai = $pegawaiRepo->find($id);
            if (!$pegawai) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pegawai tidak ditemukan'
                ]);
            }

            $namaPegawai = $pegawai->getNama();
            $namaUnitLama = $pegawai->getNamaUnitKerja();

            // Remove from unit kerja
            $pegawai->setUnitKerjaEntity(null);
            $pegawai->setUpdatedAt(new \DateTime());
            
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Pegawai '{$namaPegawai}' berhasil dilepas dari unit '{$namaUnitLama}'",
                'pegawai_id' => $pegawai->getId()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal melepas pegawai dari unit: ' . $e->getMessage()
            ]);
        }
    }

    // =================== PEGAWAI EDIT FUNCTIONALITY ===================

    #[Route('/pegawai/{id}/edit', name: 'app_struktur_organisasi_edit_pegawai', methods: ['GET'])]
    public function getPegawai(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
            $pegawai = $pegawaiRepo->find($id);

            if (!$pegawai) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pegawai tidak ditemukan'
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'pegawai' => [
                    'id' => $pegawai->getId(),
                    'nama' => $pegawai->getNama(),
                    'nip' => $pegawai->getNip(),
                    'jabatan' => $pegawai->getJabatan(),
                    'unit_kerja_id' => $pegawai->getUnitKerjaEntity() ? $pegawai->getUnitKerjaEntity()->getId() : null
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal memuat data pegawai: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/pegawai/{id}/update', name: 'app_struktur_organisasi_update_pegawai', methods: ['POST'])]
    public function updatePegawai(int $id, Request $request, EntityManagerInterface $em, UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        try {
            $pegawaiRepo = $em->getRepository('App\Entity\Pegawai');
            $pegawai = $pegawaiRepo->find($id);

            if (!$pegawai) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Pegawai tidak ditemukan'
                ]);
            }

            $nama = trim($request->request->get('nama', ''));
            $nip = trim($request->request->get('nip', ''));
            $jabatan = trim($request->request->get('jabatan', ''));
            $unitKerjaId = $request->request->get('unit_kerja_id');

            // Validation
            if (empty($nama) || empty($nip) || empty($jabatan)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Nama, NIP, dan Jabatan wajib diisi'
                ]);
            }

            // Validate NIP format (18 digits)
            if (!preg_match('/^\d{18}$/', $nip)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP harus berupa 18 digit angka'
                ]);
            }

            // Check duplicate NIP (excluding current pegawai)
            $existingPegawai = $pegawaiRepo->findOneBy(['nip' => $nip]);
            if ($existingPegawai && $existingPegawai->getId() !== $pegawai->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ NIP sudah digunakan oleh pegawai lain'
                ]);
            }

            // Handle unit kerja
            $unitKerja = null;
            if (!empty($unitKerjaId)) {
                $unitKerja = $unitKerjaRepo->find($unitKerjaId);
                if (!$unitKerja) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => '❌ Unit Kerja tidak ditemukan'
                    ]);
                }
            }

            // Update pegawai data
            $pegawai->setNama($nama);
            $pegawai->setNip($nip);
            $pegawai->setJabatan($jabatan);
            $pegawai->setUnitKerjaEntity($unitKerja);
            $pegawai->setUpdatedAt(new \DateTime());

            $em->flush();

            $unitKerjaName = $unitKerja ? $unitKerja->getNamaUnit() : 'Tidak ada unit';

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Data pegawai '{$nama}' berhasil diperbarui",
                'pegawai' => [
                    'id' => $pegawai->getId(),
                    'nama' => $pegawai->getNama(),
                    'nip' => $pegawai->getNip(),
                    'jabatan' => $pegawai->getJabatan(),
                    'unit_kerja' => $unitKerjaName
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal memperbarui data pegawai: ' . $e->getMessage()
            ]);
        }
    }

    // =================== PEGAWAI IMPORT FUNCTIONALITY ===================

    /**
     * Export Template Excel untuk Import Pegawai
     *
     * PERBAIKAN:
     * - Diperbaiki injection EntityManager melalui constructor
     * - Ditambahkan handling error yang lebih baik
     * - Diperbaiki format response agar file bisa di-download langsung
     * - Ditambahkan validasi PhpSpreadsheet yang lebih robust
     */
    #[Route('/pegawai/export-template', name: 'app_struktur_organisasi_export_template_pegawai', methods: ['GET'])]
    public function exportTemplatePegawai(): Response
    {
        $result = $this->organizationalService->generatePegawaiTemplate();

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal membuat template Excel: ' . $result['error']
            ], 500);
        }

        $spreadsheet = $result['spreadsheet'];

        try {
            $fileName = 'Template_Import_Pegawai_' . date('Y-m-d_H-i-s') . '.xlsx';
            $writer = new Xlsx($spreadsheet);

            $response = new StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$fileName}\"");
            $response->headers->set('Cache-Control', 'max-age=0');
            $response->headers->set('Pragma', 'public');

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal membuat template Excel: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Preview Import Pegawai dari Excel
     */
    #[Route('/pegawai/preview-import', name: 'app_struktur_organisasi_preview_import_pegawai', methods: ['POST'])]
    public function previewImportPegawai(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('excelFile');

            if (!$uploadedFile) {
                return new JsonResponse(['success' => false, 'message' => 'File Excel tidak ditemukan'], 400);
            }

            // Validasi file mime type
            $allowedMimeTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'
            ];

            if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
                return new JsonResponse(['success' => false, 'message' => 'Format file tidak didukung'], 400);
            }

            // Delegate ke service
            $result = $this->organizationalService->previewExcelImport($uploadedFile->getPathname());

            if (!$result['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            if (!empty($result['errors'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Terdapat kesalahan dalam data',
                    'errors' => $result['errors'],
                    'totalValid' => $result['totalValid'],
                    'totalErrors' => $result['totalErrors']
                ], 400);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $result['validData'],
                'totalValid' => $result['totalValid']
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memproses file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Pegawai dari Excel
     *
     * PERBAIKAN:
     * - Diperbaiki handling file upload dan validasi format
     * - Ditambahkan transaksi database untuk mencegah import partial
     * - Diperbaiki validasi data yang lebih ketat
     * - Ditambahkan error handling yang lebih detail per baris
     */
    #[Route('/pegawai/import', name: 'app_struktur_organisasi_import_pegawai', methods: ['POST'])]
    public function importPegawai(Request $request): JsonResponse
    {
        // Ultimate error catching to prevent any HTML error pages
        try {
            // Set response headers to ensure JSON
            header('Content-Type: application/json');

            error_log('=== Import Excel Function Called ===');
            error_log('=== Import Excel started ===');
            error_log('Request method: ' . $request->getMethod());
            error_log('Request files: ' . json_encode($request->files->keys()));

            // 1. BASIC REQUEST VALIDATION
            if (!$request->files->has('excelFile')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Field excelFile tidak ditemukan dalam request'
                ], 400);
            }

            $uploadedFile = $request->files->get('excelFile');
            if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'File upload gagal: ' . ($uploadedFile ? $uploadedFile->getErrorMessage() : 'File tidak ditemukan')
                ], 400);
            }

            // Mulai database transaction untuk memastikan konsistensi data
            $this->entityManager->getConnection()->beginTransaction();

            $updateExisting = $request->request->get('updateExisting') === '1';

            // Validasi ukuran file (max 5MB)
            if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
                throw new \Exception('Ukuran file terlalu besar. Maksimal 5MB');
            }

            // Validasi format file
            $allowedMimeTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv'
            ];

            if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
                throw new \Exception('Format file tidak didukung. Gunakan .xlsx, .xls, atau .csv');
            }

            // 2. VALIDASI PHPSPREADSHEET
            if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Library PhpSpreadsheet tidak tersedia. Silakan install melalui composer require phpoffice/phpspreadsheet'
                ], 500);
            }

            // 3. BACA FILE EXCEL dengan error handling
            try {
                error_log('Loading Excel file: ' . $uploadedFile->getClientOriginalName());
                $spreadsheet = IOFactory::load($uploadedFile->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();
                error_log('Excel loaded successfully, rows: ' . count($data));
            } catch (\Exception $excelError) {
                error_log('Excel loading error: ' . $excelError->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Gagal membaca file Excel: ' . $excelError->getMessage()
                ], 400);
            }

            // Validasi minimal ada header dan 1 baris data
            if (count($data) < 2) {
                throw new \Exception('File Excel kosong atau hanya berisi header. Minimal harus ada 1 baris data pegawai');
            }

            // Skip header row (baris pertama)
            $headerRow = array_shift($data);

            // 4. VALIDASI HEADER KOLOM
            $expectedHeaders = ['NIP', 'Nama Lengkap', 'Email', 'Jabatan', 'Nomor Telepon', 'Unit Kerja', 'Status Kepegawaian', 'Password'];
            $actualHeaders = array_slice($headerRow, 0, 8); // Ambil 8 kolom pertama

            // 5. INISIALISASI VARIABEL TRACKING
            $importedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errors = [];

            // Cache repository untuk performa
            $unitKerjaRepo = $this->entityManager->getRepository('App\Entity\UnitKerja');
            $pegawaiRepo = $this->entityManager->getRepository('App\Entity\Pegawai');

            // PERBAIKAN: Cache semua unit kerja untuk mapping yang lebih efisien
            $allUnits = $unitKerjaRepo->findAll();
            $unitKerjaMap = [];

            // Buat mapping berdasarkan nama unit (case-insensitive) dan kode unit
            foreach ($allUnits as $unit) {
                $unitName = strtolower(trim($unit->getNamaUnit()));
                $unitCode = strtolower(trim($unit->getKodeUnit()));

                // Mapping berdasarkan nama unit
                $unitKerjaMap[$unitName] = $unit;

                // Mapping berdasarkan kode unit (jika ada)
                if (!empty($unitCode)) {
                    $unitKerjaMap[$unitCode] = $unit;
                }
            }

            // Counter untuk statistik unit kerja
            $unitKerjaStats = [
                'found' => 0,
                'not_found' => 0,
                'empty' => 0
            ];

            // Logging untuk debugging (bisa dihapus di production)
            error_log("📋 Unit Kerja mapping prepared - Total units: " . count($allUnits) . ", Mapping entries: " . count($unitKerjaMap));

            // 6. PROSES SETIAP BARIS DATA
            foreach ($data as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 karena dimulai dari 0 dan skip header

                // Skip baris yang benar-benar kosong
                if (empty(array_filter($row, function($cell) { return !empty(trim($cell)); }))) {
                    continue;
                }

                // Extract data dari setiap kolom
                $pegawaiData = [
                    'nip' => trim((string)($row[0] ?? '')),
                    'namaLengkap' => trim((string)($row[1] ?? '')),
                    'email' => trim((string)($row[2] ?? '')),
                    'jabatan' => trim((string)($row[3] ?? '')) ?: 'Pegawai',
                    'nomorTelepon' => trim((string)($row[4] ?? '')),
                    'unitKerja' => trim((string)($row[5] ?? '')),
                    'statusKepegawaian' => trim((string)($row[6] ?? '')) ?: 'aktif',
                    'password' => trim((string)($row[7] ?? ''))
                ];

                // 7. VALIDASI DATA WAJIB
                $validationErrors = [];

                if (empty($pegawaiData['nip'])) {
                    $validationErrors[] = 'NIP tidak boleh kosong';
                }

                if (empty($pegawaiData['namaLengkap'])) {
                    $validationErrors[] = 'Nama Lengkap tidak boleh kosong';
                }

                // Validasi format NIP (18 digit)
                if (!empty($pegawaiData['nip']) && !preg_match('/^\d{18}$/', $pegawaiData['nip'])) {
                    $validationErrors[] = 'NIP harus berupa 18 digit angka';
                }

                // Validasi email jika diisi
                if (!empty($pegawaiData['email']) && !filter_var($pegawaiData['email'], FILTER_VALIDATE_EMAIL)) {
                    $validationErrors[] = 'Format email tidak valid';
                }

                // Validasi status kepegawaian
                $allowedStatus = ['aktif', 'nonaktif'];
                if (!in_array(strtolower($pegawaiData['statusKepegawaian']), $allowedStatus)) {
                    $validationErrors[] = 'Status kepegawaian harus "aktif" atau "nonaktif"';
                }

                // Jika ada error validasi, skip baris ini
                if (!empty($validationErrors)) {
                    $errors[] = "Baris {$rowNumber}: " . implode(', ', $validationErrors);
                    $skippedCount++;
                    continue;
                }

                // Set default password = NIP jika kosong
                if (empty($pegawaiData['password'])) {
                    $pegawaiData['password'] = $pegawaiData['nip'];
                }

                try {
                    // 8. CEK PEGAWAI EXISTING
                    $existingPegawai = $pegawaiRepo->findOneBy(['nip' => $pegawaiData['nip']]);

                    if ($existingPegawai && !$updateExisting) {
                        $errors[] = "Baris {$rowNumber}: Pegawai dengan NIP '{$pegawaiData['nip']}' sudah ada (set 'Update Existing' untuk menimpa)";
                        $skippedCount++;
                        continue;
                    }

                    // 9. CREATE OR UPDATE PEGAWAI
                    if ($existingPegawai && $updateExisting) {
                        $pegawai = $existingPegawai;
                        $updatedCount++;
                    } else {
                        $pegawai = new Pegawai();
                        $importedCount++;
                    }

                    // 10. SET DATA PEGAWAI
                    $pegawai->setNip($pegawaiData['nip']);
                    $pegawai->setNama($pegawaiData['namaLengkap']);
                    $pegawai->setEmail(!empty($pegawaiData['email']) ? $pegawaiData['email'] : null);
                    $pegawai->setJabatan($pegawaiData['jabatan']);
                    $pegawai->setNomorTelepon(!empty($pegawaiData['nomorTelepon']) ? $pegawaiData['nomorTelepon'] : null);
                    $pegawai->setStatusKepegawaian(strtolower($pegawaiData['statusKepegawaian']));

                    // Set tanggal
                    if (!$existingPegawai) {
                        $pegawai->setTanggalMulaiKerja(new \DateTime());
                        $pegawai->setCreatedAt(new \DateTime());
                    }
                    $pegawai->setUpdatedAt(new \DateTime());

                    // Hash password (hanya jika ada password baru atau pegawai baru)
                    if (!$existingPegawai || !empty($pegawaiData['password'])) {
                        $hashedPassword = password_hash($pegawaiData['password'], PASSWORD_DEFAULT);
                        $pegawai->setPassword($hashedPassword);
                    }

                    // 11. SET UNIT KERJA RELATIONSHIP - PERBAIKAN MAPPING
                    $unitKerja = null;
                    $unitKerjaInput = trim($pegawaiData['unitKerja']);

                    if (!empty($unitKerjaInput)) {
                        // LANGKAH 1: Coba mapping berdasarkan cache yang sudah dibuat
                        $unitKerjaKey = strtolower($unitKerjaInput);

                        if (isset($unitKerjaMap[$unitKerjaKey])) {
                            $unitKerja = $unitKerjaMap[$unitKerjaKey];
                            $unitKerjaStats['found']++;

                            // Logging sukses mapping unit kerja
                            error_log("✅ Unit kerja ditemukan: '{$unitKerjaInput}' -> '{$unitKerja->getNamaUnit()}'");
                        } else {
                            // LANGKAH 2: Coba fuzzy matching untuk typo atau variasi nama
                            $bestMatch = null;
                            $bestSimilarity = 0;

                            foreach ($unitKerjaMap as $mapKey => $mapUnit) {
                                // Hitung similarity untuk nama unit
                                $similarity = 0;
                                similar_text($unitKerjaKey, $mapKey, $similarity);

                                // Jika similarity > 80%, anggap sebagai match
                                if ($similarity > 80 && $similarity > $bestSimilarity) {
                                    $bestMatch = $mapUnit;
                                    $bestSimilarity = $similarity;
                                }
                            }

                            if ($bestMatch && $bestSimilarity > 80) {
                                $unitKerja = $bestMatch;
                                $unitKerjaStats['found']++;

                                // Beri warning tapi tetap gunakan unit yang mirip
                                $errors[] = "Baris {$rowNumber}: Unit kerja '{$unitKerjaInput}' tidak persis, tapi menggunakan '{$unitKerja->getNamaUnit()}' (similarity: {$bestSimilarity}%)";

                                error_log("⚠️ Unit kerja fuzzy match: '{$unitKerjaInput}' -> '{$unitKerja->getNamaUnit()}' (similarity: {$bestSimilarity}%)");
                            } else {
                                // Unit kerja benar-benar tidak ditemukan
                                $unitKerjaStats['not_found']++;

                                // Buat daftar unit kerja yang tersedia untuk membantu admin
                                $availableUnits = array_slice(array_keys($unitKerjaMap), 0, 5); // Ambil 5 unit pertama sebagai contoh
                                $availableUnitsStr = implode(', ', $availableUnits);

                                $errors[] = "Baris {$rowNumber}: Unit kerja '{$unitKerjaInput}' tidak ditemukan. Contoh unit yang tersedia: {$availableUnitsStr}";

                                error_log("❌ Unit kerja tidak ditemukan: '{$unitKerjaInput}'");
                            }
                        }

                        // Set unit kerja ke pegawai
                        if ($unitKerja) {
                            $pegawai->setUnitKerjaEntity($unitKerja);
                            error_log("🔗 Pegawai '{$pegawaiData['namaLengkap']}' di-assign ke unit '{$unitKerja->getNamaUnit()}'");
                        } else {
                            $pegawai->setUnitKerjaEntity(null);
                            error_log("⚪ Pegawai '{$pegawaiData['namaLengkap']}' dibuat tanpa unit kerja");
                        }
                    } else {
                        // Unit kerja kosong
                        $unitKerjaStats['empty']++;
                        $pegawai->setUnitKerjaEntity(null);
                        error_log("⚪ Pegawai '{$pegawaiData['namaLengkap']}' dibuat tanpa unit kerja (kolom kosong)");
                    }

                    // 12. SET DEFAULT ROLES
                    if (!$existingPegawai) {
                        $pegawai->setRoles(['ROLE_USER']);
                    }

                    $this->entityManager->persist($pegawai);

                } catch (\Throwable $e) {
                    $errors[] = "Baris {$rowNumber}: Error database - " . $e->getMessage();
                    $skippedCount++;
                }
            }

            // 13. COMMIT TRANSAKSI
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            // 14. BUAT RESPONSE SUKSES
            $totalProcessed = $importedCount + $updatedCount;
            $totalRows = count($data);

            $message = "Import berhasil diselesaikan!";
            if ($importedCount > 0) $message .= " {$importedCount} pegawai baru ditambahkan.";
            if ($updatedCount > 0) $message .= " {$updatedCount} pegawai diupdate.";
            if ($skippedCount > 0) $message .= " {$skippedCount} baris dilewati karena error.";

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'statistics' => [
                    'total_rows' => $totalRows,
                    'imported' => $importedCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                    'processed' => $totalProcessed,
                    'unit_kerja' => [
                        'found' => $unitKerjaStats['found'],
                        'not_found' => $unitKerjaStats['not_found'],
                        'empty' => $unitKerjaStats['empty'],
                        'total_units_available' => count($allUnits)
                    ]
                ],
                'errors' => $errors,
                'has_errors' => !empty($errors)
            ]);

        } catch (\Throwable $e) {
            // Rollback transaksi jika terjadi error
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->getConnection()->rollBack();
                }
            } catch (\Exception $rollbackException) {
                error_log('Rollback failed: ' . $rollbackException->getMessage());
            }

            // Log error untuk debugging
            error_log('Import Excel Error: ' . $e->getMessage());
            error_log('Import Excel Trace: ' . $e->getTraceAsString());

            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal mengimport data: ' . $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);

        } catch (\Throwable $fatalError) {
            // Ultimate fallback untuk semua error termasuk fatal errors
            error_log('=== FATAL ERROR IN IMPORT ===');
            error_log('Fatal Error: ' . $fatalError->getMessage());
            error_log('Fatal Trace: ' . $fatalError->getTraceAsString());

            // Force JSON response
            header('Content-Type: application/json');
            http_response_code(500);

            return new JsonResponse([
                'success' => false,
                'message' => 'Fatal error: ' . $fatalError->getMessage(),
                'error_type' => get_class($fatalError),
                'fatal' => true
            ], 500);
        }
    }

    // =================== TESTING & UTILITY ===================

    #[Route('/pegawai/test-import', name: 'app_struktur_organisasi_test_import', methods: ['GET'])]
    public function testImport(): JsonResponse
    {
        try {
            error_log('=== TEST IMPORT CALLED ===');

            // Test EntityManager
            $testEntity = $this->entityManager->getRepository('App\Entity\UnitKerja')->findAll();
            error_log('UnitKerja count: ' . count($testEntity));

            // Test PhpSpreadsheet
            $testSpreadsheet = class_exists('PhpOffice\PhpSpreadsheet\IOFactory');
            error_log('PhpSpreadsheet available: ' . ($testSpreadsheet ? 'YES' : 'NO'));

            return new JsonResponse([
                'success' => true,
                'message' => 'Test berhasil',
                'tests' => [
                    'entity_manager' => 'OK',
                    'unit_kerja_count' => count($testEntity),
                    'phpspreadsheet' => $testSpreadsheet ? 'available' : 'not_available'
                ]
            ]);

        } catch (\Throwable $e) {
            error_log('Test Import Error: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Test gagal: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ], 500);
        }
    }

    #[Route('/get-available-units', name: 'app_struktur_organisasi_get_available_units', methods: ['GET'])]
    public function getAvailableUnits(UnitKerjaRepository $unitKerjaRepo): JsonResponse
    {
        $availableUnits = $unitKerjaRepo->findUnitsWithoutKepalaBidang();
        
        $result = array_map(function($unit) {
            return [
                'id' => $unit->getId(),
                'nama_unit' => $unit->getNamaUnit(),
                'kode_unit' => $unit->getKodeUnit()
            ];
        }, $availableUnits);

        return new JsonResponse($result);
    }

}