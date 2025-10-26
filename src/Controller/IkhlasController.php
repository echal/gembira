<?php

namespace App\Controller;

use App\Entity\Pegawai;
use App\Entity\Quote;
use App\Entity\QuoteComment;
use App\Repository\QuoteRepository;
use App\Repository\QuoteCommentRepository;
use App\Repository\UserQuoteInteractionRepository;
use App\Repository\PegawaiRepository;
use App\Service\IkhlasLeaderboardService;
use App\Service\GamificationService;
use App\Service\UserXpService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ikhlas')]
#[IsGranted('ROLE_USER')]
class IkhlasController extends AbstractController
{
    public function __construct(
        private QuoteRepository $quoteRepository,
        private QuoteCommentRepository $commentRepository,
        private UserQuoteInteractionRepository $interactionRepository,
        private PegawaiRepository $pegawaiRepository,
        private EntityManagerInterface $em,
        private IkhlasLeaderboardService $leaderboardService,
        private GamificationService $gamificationService,
        private UserXpService $userXpService,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'app_ikhlas')]
    public function index(): Response
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        // Award daily login bonus (only once per day)
        $dailyBonus = $this->gamificationService->awardDailyLogin($user);
        if ($dailyBonus && $dailyBonus['level_up']) {
            $this->addFlash('level_up', [
                'level' => $dailyBonus['new_level'],
                'badge' => $dailyBonus['badge_info']
            ]);
        }

        // Get ALL quotes ordered by newest first
        $allQuotes = $this->quoteRepository->findBy([], ['id' => 'DESC']);

        if (empty($allQuotes)) {
            // No quotes available - show empty state
            return $this->render('ikhlas/index.html.twig', [
                'quotes' => []
            ]);
        }

        // Prepare quotes with user interaction data
        $quotesWithData = [];
        foreach ($allQuotes as $quote) {
            $hasLiked = $this->interactionRepository->hasUserLiked($user, $quote);
            $hasSaved = $this->interactionRepository->hasUserSaved($user, $quote);

            // Get users who liked this quote (for Facebook-style display)
            $likedByUsers = $this->interactionRepository->getUsersWhoLiked($quote, 1); // Get last 1 user

            // Get author photo and level
            $authorPhoto = null;
            $authorLevel = 1; // Default level
            if ($quote->getAuthor()) {
                $authorUser = $this->pegawaiRepository->findOneBy(['nama' => $quote->getAuthor()]);
                if ($authorUser) {
                    if ($authorUser->getPhoto()) {
                        $authorPhoto = $authorUser->getPhoto();
                    }
                    $authorLevel = $authorUser->getCurrentLevel() ?? 1;
                }
            }

            $quotesWithData[] = [
                'quote' => $quote,
                'hasLiked' => $hasLiked,
                'hasSaved' => $hasSaved,
                'likedByUsers' => $likedByUsers,
                'authorPhoto' => $authorPhoto,
                'authorLevel' => $authorLevel
            ];
        }

        // Award view points (once for visiting the page)
        $this->gamificationService->addPoints($user, GamificationService::POINTS_VIEW_QUOTE, 'View quotes feed');

        return $this->render('ikhlas/index.html.twig', [
            'quotes' => $quotesWithData
        ]);
    }

    #[Route('/api/next/{currentId}', name: 'app_ikhlas_next', methods: ['GET'])]
    public function getNextQuote(int $currentId): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->findNextQuote($currentId);

        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tidak ada quotes tersedia'
            ], 404);
        }

        $hasLiked = $this->interactionRepository->hasUserLiked($user, $quote);
        $hasSaved = $this->interactionRepository->hasUserSaved($user, $quote);

        return new JsonResponse([
            'success' => true,
            'quote' => [
                'id' => $quote->getId(),
                'content' => $quote->getContent(),
                'author' => $quote->getAuthor() ?? 'Anonim',
                'category' => $quote->getCategory(),
                'hasLiked' => $hasLiked,
                'hasSaved' => $hasSaved
            ]
        ]);
    }

    #[Route('/api/previous/{currentId}', name: 'app_ikhlas_previous', methods: ['GET'])]
    public function getPreviousQuote(int $currentId): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->findPreviousQuote($currentId);

        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tidak ada quotes tersedia'
            ], 404);
        }

        $hasLiked = $this->interactionRepository->hasUserLiked($user, $quote);
        $hasSaved = $this->interactionRepository->hasUserSaved($user, $quote);

        return new JsonResponse([
            'success' => true,
            'quote' => [
                'id' => $quote->getId(),
                'content' => $quote->getContent(),
                'author' => $quote->getAuthor() ?? 'Anonim',
                'category' => $quote->getCategory(),
                'hasLiked' => $hasLiked,
                'hasSaved' => $hasSaved
            ]
        ]);
    }

    #[Route('/api/create-quote', name: 'app_ikhlas_create_quote', methods: ['POST'])]
    public function createQuote(Request $request): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak boleh kosong'
            ], 400);
        }

        try {
            $this->logger->info('=== CREATE QUOTE START ===');
            $this->logger->info('User: ' . $user->getNama() . ' (ID: ' . $user->getId() . ')');
            $this->logger->info('Content: ' . trim($data['content']));
            $this->logger->info('Category: ' . ($data['category'] ?? 'Motivasi'));

            // Create new quote
            $quote = new Quote();
            $quote->setContent(trim($data['content']));
            $quote->setCategory($data['category'] ?? 'Motivasi');
            $quote->setAuthor($user->getNama()); // Set author as current user's name
            $quote->setTotalViews(0);
            $quote->setTotalLikes(0);
            $quote->setTotalComments(0);

            $this->logger->info('Quote object created, persisting...');
            $this->em->persist($quote);

            $this->logger->info('Flushing to database...');
            $this->em->flush();

            $this->logger->info('Quote saved! ID: ' . $quote->getId());

            // Award XP for creating a quote
            try {
                $xpResult = $this->userXpService->awardXpForActivity(
                    $user,
                    'create_quote',
                    $quote->getId()
                );
            } catch (\Exception $xpError) {
                // Log the XP error but don't fail the quote creation
                $this->logger->error('XP Service Error: ' . $xpError->getMessage());
                $this->logger->error('XP Service Trace: ' . $xpError->getTraceAsString());

                // Set default xpResult if XP service fails
                $xpResult = [
                    'xp_earned' => UserXpService::XP_CREATE_QUOTE,
                    'total_xp' => $user->getTotalXp(),
                    'level_up' => false,
                    'current_level' => $user->getCurrentLevel(),
                    'current_badge' => $user->getCurrentBadge(),
                    'level_title' => 'Pemula'
                ];
            }

            // Note: Old gamification service removed to improve performance
            // Now using XP system exclusively

            return new JsonResponse([
                'success' => true,
                'message' => '🎉 Kata semangatmu telah dibagikan! +' . UserXpService::XP_CREATE_QUOTE . ' XP',
                'quote' => [
                    'id' => $quote->getId(),
                    'content' => $quote->getContent(),
                    'author' => $quote->getAuthor(),
                    'category' => $quote->getCategory(),
                    'totalViews' => 0,
                    'totalLikes' => 0,
                    'totalComments' => 0,
                    'hasLiked' => false,
                    'hasSaved' => false
                ],
                'xp_earned' => $xpResult['xp_earned'] ?? 0,
                'level_up' => $xpResult['level_up'] ?? false,
                'level_info' => $xpResult['level_up'] ? [
                    'new_level' => $xpResult['new_level'],
                    'badge' => $xpResult['current_badge'],
                    'title' => $xpResult['level_title']
                ] : null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/interact', name: 'app_ikhlas_interact', methods: ['POST'])]
    public function interactWithQuote(Request $request): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['quoteId']) || !isset($data['action'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Data tidak lengkap'
            ], 400);
        }

        $quoteId = (int) $data['quoteId'];
        $action = $data['action']; // 'like' or 'save'

        $quote = $this->quoteRepository->find($quoteId);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        // Find or create interaction
        $interaction = $this->interactionRepository->findOrCreateInteraction($user, $quote);

        try {
            $levelUpInfo = null;

            if ($action === 'like') {
                // Toggle like
                $oldStatus = $interaction->isLiked();
                $newStatus = !$oldStatus;
                $interaction->setLiked($newStatus);

                // Update quote's like counter
                if ($newStatus && !$oldStatus) {
                    $quote->incrementLikes();
                } elseif (!$newStatus && $oldStatus) {
                    $quote->decrementLikes();
                }

                $this->em->persist($interaction);
                $this->em->persist($quote);
                $this->em->flush();

                // Award/subtract XP for like
                if ($newStatus && !$oldStatus) {
                    // Just liked - award XP to the user who liked
                    $xpResult = $this->userXpService->awardXpForActivity(
                        $user,
                        'like_quote',
                        $quoteId
                    );
                    if ($xpResult['level_up']) {
                        $levelUpInfo = [
                            'level_up' => true,
                            'new_level' => $xpResult['new_level'],
                            'badge' => $xpResult['current_badge'],
                            'title' => $xpResult['level_title']
                        ];
                    }

                    // Award XP to quote author for receiving a like (+5 XP)
                    $quoteAuthorName = $quote->getAuthor();
                    if ($quoteAuthorName) {
                        // Find the author by name (assuming nama matches author field)
                        $quoteAuthor = $this->em->getRepository(Pegawai::class)
                            ->findOneBy(['nama' => $quoteAuthorName]);

                        if ($quoteAuthor && $quoteAuthor->getId() !== $user->getId()) {
                            // Don't reward if user likes their own quote
                            $this->userXpService->addXp(
                                $quoteAuthor,
                                5, // +5 XP for receiving like
                                'receive_like',
                                'Quote Anda mendapat like',
                                $quoteId
                            );
                        }
                    }
                }

                return new JsonResponse([
                    'success' => true,
                    'action' => 'like',
                    'status' => $newStatus,
                    'totalLikes' => $quote->getTotalLikes(),
                    'message' => $newStatus ? '❤️ Anda menyukai quote ini! +' . UserXpService::XP_LIKE_QUOTE . ' XP' : 'Like dibatalkan',
                    'xp_earned' => $newStatus ? UserXpService::XP_LIKE_QUOTE : 0,
                    'level_up' => $levelUpInfo
                ]);
            } elseif ($action === 'save') {
                // Toggle save
                $oldStatus = $interaction->isSaved();
                $newStatus = !$oldStatus;
                $interaction->setSaved($newStatus);

                $this->em->persist($interaction);
                $this->em->flush();

                // Note: Save action doesn't award XP to keep it simple
                // Only create, like, comment, share award XP

                return new JsonResponse([
                    'success' => true,
                    'action' => 'save',
                    'status' => $newStatus,
                    'message' => $newStatus ? '📌 Quote disimpan ke favorit!' : 'Favorit dibatalkan',
                    'level_up' => $levelUpInfo
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Aksi tidak valid'
            ], 400);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/view', name: 'app_ikhlas_track_view', methods: ['POST'])]
    public function trackView(int $id): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        try {
            $quote = $this->quoteRepository->find($id);
            if (!$quote) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quote tidak ditemukan'
                ], 404);
            }

            // Check if user has already viewed this quote
            $interaction = $this->interactionRepository->findOneBy([
                'user' => $user,
                'quote' => $quote
            ]);

            // Only increment if user hasn't viewed before
            if (!$interaction || !$interaction->isViewed()) {
                // Create or update interaction
                if (!$interaction) {
                    $interaction = new UserQuoteInteraction();
                    $interaction->setUser($user);
                    $interaction->setQuote($quote);
                }

                // Mark as viewed
                $interaction->setViewed(true);
                $this->em->persist($interaction);

                // Increment view counter
                $quote->incrementViews();
                $this->em->persist($quote);

                $this->em->flush();
            }

            return new JsonResponse([
                'success' => true,
                'totalViews' => $quote->getTotalViews(),
                'alreadyViewed' => $interaction && $interaction->isViewed()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/my-favorites', name: 'app_ikhlas_favorites')]
    public function myFavorites(): Response
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $savedInteractions = $this->interactionRepository->findSavedByUser($user);
        $likedInteractions = $this->interactionRepository->findLikedByUser($user);

        return $this->render('ikhlas/favorites.html.twig', [
            'savedQuotes' => $savedInteractions,
            'likedQuotes' => $likedInteractions
        ]);
    }

    #[Route('/leaderboard', name: 'app_ikhlas_leaderboard')]
    public function leaderboard(): Response
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        // Get current month/year
        $currentDate = new \DateTime();
        $currentMonth = (int) $currentDate->format('n');
        $currentYear = (int) $currentDate->format('Y');

        // Get monthly XP leaderboard (new system)
        $monthlyLeaderboard = $this->userXpService->getFullMonthlyLeaderboard($currentMonth, $currentYear, 50);

        // Get old leaderboard data for backward compatibility
        $leaderboard = $this->leaderboardService->getLeaderboard(10);

        // Get current user's XP ranking
        $userXpRank = $this->userXpService->getUserRanking($user, $currentMonth, $currentYear);

        // Get current user's old rank
        $userRank = $this->leaderboardService->getUserRank($user->getId());

        // Get global stats
        $globalStats = $this->leaderboardService->getGlobalStats();

        // Get top quotes
        $topQuotes = $this->leaderboardService->getTopQuotes(3);

        // Get user's XP progress
        $xpProgress = $user->getXpProgress();

        return $this->render('ikhlas/leaderboard.html.twig', [
            'monthlyLeaderboard' => $monthlyLeaderboard,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
            'userXpRank' => $userXpRank,
            'xpProgress' => $xpProgress,
            'leaderboard' => $leaderboard,
            'userRank' => $userRank,
            'globalStats' => $globalStats,
            'topQuotes' => $topQuotes
        ]);
    }

    #[Route('/api/stats', name: 'app_ikhlas_stats_api', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        try {
            $globalStats = $this->leaderboardService->getGlobalStats();
            $topQuotes = $this->leaderboardService->getTopQuotes(3);
            $dailyActivity = $this->leaderboardService->getDailyActivity(7);

            return new JsonResponse([
                'success' => true,
                'stats' => $globalStats,
                'topQuotes' => $topQuotes,
                'dailyActivity' => $dailyActivity
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching stats: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/my-stats', name: 'app_ikhlas_my_stats', methods: ['GET'])]
    public function getMyStats(): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        try {
            $userStats = $this->leaderboardService->getUserStats($user->getId());

            return new JsonResponse([
                'success' => true,
                'stats' => $userStats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching user stats: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/comment', name: 'app_quote_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['comment']) || empty(trim($data['comment']))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Komentar tidak boleh kosong'
            ], 400);
        }

        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        try {
            // Create new comment with QuoteComment entity
            $comment = new QuoteComment();
            $comment->setQuote($quote);
            $comment->setUser($user);
            $comment->setComment(trim($data['comment']));

            // Handle parent comment (reply)
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parentComment = $this->commentRepository->find($data['parent_id']);
                if ($parentComment) {
                    $comment->setParent($parentComment);
                }
            }

            // Update quote's comment counter
            $quote->incrementComments();

            $this->em->persist($comment);
            $this->em->persist($quote);
            $this->em->flush();

            // Award XP for commenting
            $xpResult = $this->userXpService->awardXpForActivity(
                $user,
                'comment_quote',
                $quote->getId()
            );

            return new JsonResponse([
                'success' => true,
                'message' => '💬 Komentar berhasil ditambahkan! +' . UserXpService::XP_COMMENT_QUOTE . ' XP',
                'comment' => $comment->toArray(false),
                'totalComments' => $quote->getTotalComments(),
                'xp_earned' => $xpResult['xp_earned'] ?? 0,
                'level_up' => $xpResult['level_up'] ?? false,
                'level_info' => $xpResult['level_up'] ? [
                    'new_level' => $xpResult['new_level'],
                    'badge' => $xpResult['current_badge'],
                    'title' => $xpResult['level_title']
                ] : null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/comments', name: 'app_quote_comments', methods: ['GET'])]
    public function getComments(int $id): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        try {
            // Get top-level comments (with replies nested)
            $topLevelComments = $this->commentRepository->findByQuoteWithUser($quote);

            $comments = array_map(function($comment) {
                return $comment->toArray(true); // Include replies
            }, $topLevelComments);

            return new JsonResponse([
                'success' => true,
                'comments' => $comments,
                'total' => count($comments),
                'current_user_id' => $user->getId() // For delete button visibility
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/comments/{id}', name: 'app_delete_comment', methods: ['DELETE'])]
    public function deleteComment(int $id): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        try {
            $comment = $this->commentRepository->find($id);

            if (!$comment) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Komentar tidak ditemukan'
                ], 404);
            }

            // Check if user is the owner of the comment
            if ($comment->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'
                ], 403);
            }

            $quote = $comment->getQuote();

            // Count total comments to delete (parent + all nested replies)
            $totalToDelete = $this->countCommentWithReplies($comment);

            // Delete the comment (replies will be cascade deleted by database)
            $this->em->remove($comment);

            // Decrement comment counter by total deleted (parent + replies)
            $quote->setTotalComments(max(0, $quote->getTotalComments() - $totalToDelete));
            $this->em->persist($quote);

            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => '🗑️ Komentar berhasil dihapus' . ($totalToDelete > 1 ? " ({$totalToDelete} komentar)" : ''),
                'totalComments' => $quote->getTotalComments(),
                'deletedCount' => $totalToDelete
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/share', name: 'app_quote_share', methods: ['POST'])]
    public function shareQuote(int $id): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        try {
            // Award XP for sharing
            $xpResult = $this->userXpService->awardXpForActivity(
                $user,
                'share_quote',
                $quote->getId()
            );

            return new JsonResponse([
                'success' => true,
                'message' => '📤 Quote dibagikan! +' . UserXpService::XP_SHARE_QUOTE . ' XP',
                'xp_earned' => $xpResult['xp_earned'] ?? 0,
                'level_up' => $xpResult['level_up'] ?? false,
                'level_info' => $xpResult['level_up'] ? [
                    'new_level' => $xpResult['new_level'],
                    'badge' => $xpResult['current_badge'],
                    'title' => $xpResult['level_title']
                ] : null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/update', name: 'app_quote_update', methods: ['PUT'])]
    public function updateQuote(int $id, Request $request): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        // Check if user is the author
        if ($quote->getAuthor() !== $user->getNama()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengedit quote ini'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak boleh kosong'
            ], 400);
        }

        try {
            $quote->setContent(trim($data['content']));
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Quote berhasil diupdate',
                'quote' => [
                    'id' => $quote->getId(),
                    'content' => $quote->getContent()
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/quotes/{id}/delete', name: 'app_quote_delete', methods: ['DELETE'])]
    public function deleteQuote(int $id): JsonResponse
    {
        /** @var Pegawai $user */
        $user = $this->getUser();

        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quote tidak ditemukan'
            ], 404);
        }

        // Check if user is the author
        if ($quote->getAuthor() !== $user->getNama()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk menghapus quote ini'
            ], 403);
        }

        try {
            // Delete all related interactions
            $interactions = $this->interactionRepository->findBy(['quote' => $quote]);
            foreach ($interactions as $interaction) {
                $this->em->remove($interaction);
            }

            // Delete all related comments
            $comments = $this->commentRepository->findBy(['quote' => $quote]);
            foreach ($comments as $comment) {
                $this->em->remove($comment);
            }

            // Delete the quote
            $this->em->remove($quote);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Quote berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Count total comments to delete (parent + all nested replies recursively)
     */
    private function countCommentWithReplies(QuoteComment $comment): int
    {
        $count = 1; // Count the comment itself

        // Get all replies to this comment
        $replies = $this->commentRepository->findBy(['parent' => $comment]);

        // Recursively count each reply and its sub-replies
        foreach ($replies as $reply) {
            $count += $this->countCommentWithReplies($reply);
        }

        return $count;
    }
}
