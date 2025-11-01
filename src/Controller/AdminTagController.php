<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tag')]
#[IsGranted('ROLE_ADMIN')]
final class AdminTagController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private TagService $tagService
    ) {
    }

    /**
     * List all tags with statistics
     */
    #[Route('/', name: 'app_admin_tag_index')]
    public function index(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $this->getUser();

        // Get sorting parameters
        $sortBy = $request->query->get('sort', 'usage');
        $order = $request->query->get('order', 'desc');

        // Get all tags
        if ($sortBy === 'usage') {
            $tags = $this->tagRepository->findAllByPopularity();
            if ($order === 'asc') {
                $tags = array_reverse($tags);
            }
        } else {
            $tags = $this->tagRepository->findAllByName();
            if ($order === 'desc') {
                $tags = array_reverse($tags);
            }
        }

        // Get statistics
        $stats = $this->tagRepository->getTagStats();

        return $this->render('admin/tag/index.html.twig', [
            'tags' => $tags,
            'stats' => $stats,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    /**
     * Delete a tag (AJAX)
     */
    #[Route('/{id}/delete', name: 'app_admin_tag_delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);

        if (!$tag) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tag tidak ditemukan'
            ], 404);
        }

        try {
            $tagName = $tag->getName();
            $this->tagService->deleteTag($tag);

            return new JsonResponse([
                'success' => true,
                'message' => "Tag #{$tagName} berhasil dihapus"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up orphaned tags (tags with no quotes)
     */
    #[Route('/cleanup', name: 'app_admin_tag_cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
        try {
            $count = $this->tagService->cleanupOrphanedTags();

            return new JsonResponse([
                'success' => true,
                'message' => "{$count} tag tidak terpakai berhasil dihapus"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal membersihkan tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate all tag usage counts
     */
    #[Route('/recalculate', name: 'app_admin_tag_recalculate', methods: ['POST'])]
    public function recalculate(): JsonResponse
    {
        try {
            $this->tagService->recalculateUsageCounts();

            return new JsonResponse([
                'success' => true,
                'message' => 'Jumlah penggunaan tag berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View tag details with quotes
     */
    #[Route('/{id}', name: 'app_admin_tag_show')]
    public function show(int $id): Response
    {
        $tag = $this->tagRepository->find($id);

        if (!$tag) {
            throw $this->createNotFoundException('Tag tidak ditemukan');
        }

        $quotes = $tag->getQuotes()->toArray();

        // Sort quotes by creation date (newest first)
        usort($quotes, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return $this->render('admin/tag/show.html.twig', [
            'tag' => $tag,
            'quotes' => $quotes,
        ]);
    }
}
