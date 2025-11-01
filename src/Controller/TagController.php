<?php

namespace App\Controller;

use App\Repository\QuoteRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tag')]
class TagController extends AbstractController
{
    public function __construct(
        private TagRepository $tagRepository,
        private QuoteRepository $quoteRepository
    ) {
    }

    /**
     * Display all tags
     */
    #[Route('/', name: 'app_tag_index')]
    public function index(): Response
    {
        $tags = $this->tagRepository->findActiveTagsWithQuotes();
        $stats = $this->tagRepository->getTagStats();

        return $this->render('tag/index.html.twig', [
            'tags' => $tags,
            'stats' => $stats,
        ]);
    }

    /**
     * Display quotes for a specific tag
     */
    #[Route('/{slug}', name: 'app_tag_show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        // Find tag by slug
        $tag = $this->tagRepository->findOneBySlug($slug);

        if (!$tag) {
            throw $this->createNotFoundException('Tag tidak ditemukan');
        }

        // Get quotes with this tag, ordered by newest first
        $quotes = $this->quoteRepository->createQueryBuilder('q')
            ->innerJoin('q.tags', 't')
            ->where('t.id = :tagId')
            ->andWhere('q.isActive = :active')
            ->setParameter('tagId', $tag->getId())
            ->setParameter('active', true)
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults(50) // Limit to 50 quotes for now
            ->getQuery()
            ->getResult();

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'quotes' => $quotes,
        ]);
    }

    /**
     * Get popular tags for sidebar/widget
     */
    #[Route('/api/popular', name: 'app_tag_popular', methods: ['GET'])]
    public function popular(Request $request): Response
    {
        $limit = $request->query->getInt('limit', 10);
        $tags = $this->tagRepository->findAllByPopularity($limit);

        return $this->json([
            'tags' => array_map(function ($tag) {
                return [
                    'name' => $tag->getName(),
                    'slug' => $tag->getSlug(),
                    'count' => $tag->getUsageCount(),
                    'url' => $this->generateUrl('app_tag_show', ['slug' => $tag->getSlug()]),
                ];
            }, $tags)
        ]);
    }
}
