<?php

namespace App\Twig\Extension;

use App\Entity\Pegawai;
use App\Repository\UserQuoteInteractionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QuoteExtension extends AbstractExtension
{
    public function __construct(
        private UserQuoteInteractionRepository $interactionRepository,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unviewed_quotes_count', [$this, 'getUnviewedQuotesCount']),
        ];
    }

    /**
     * Get count of unviewed quotes for current logged-in user
     */
    public function getUnviewedQuotesCount(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof Pegawai) {
            return 0;
        }

        return $this->interactionRepository->countUnviewedQuotes($user);
    }
}
