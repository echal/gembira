<?php

namespace App\Twig;

use App\Service\UserXpService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * XpFormatterExtension
 *
 * Twig extension untuk format XP di template
 */
class XpFormatterExtension extends AbstractExtension
{
    private UserXpService $xpService;

    public function __construct(UserXpService $xpService)
    {
        $this->xpService = $xpService;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_xp', [$this, 'formatXp']),
        ];
    }

    /**
     * Format XP dengan K notation
     * Usage: {{ user.totalXp|format_xp }}
     * Output: "3.7K", "30K", "150"
     */
    public function formatXp(int $xp): string
    {
        return $this->xpService->formatXp($xp);
    }
}
