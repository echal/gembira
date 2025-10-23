<?php

namespace App\Twig;

use App\Service\TimeFormatterService;
use DateTimeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * TimeFormatterExtension
 *
 * Twig extension untuk format waktu relatif di template
 */
class TimeFormatterExtension extends AbstractExtension
{
    private TimeFormatterService $timeFormatter;

    public function __construct(TimeFormatterService $timeFormatter)
    {
        $this->timeFormatter = $timeFormatter;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('time_ago', [$this, 'timeAgo']),
            new TwigFilter('time_full', [$this, 'timeFull']),
            new TwigFilter('time_tooltip', [$this, 'timeTooltip']),
            new TwigFilter('time_with_fallback', [$this, 'timeWithFallback']),
        ];
    }

    /**
     * Format waktu relatif (Facebook-style)
     * Usage: {{ post.createdAt|time_ago }}
     * Output: "5 mnt", "1 j", "1 hr", "1 sep"
     */
    public function timeAgo(DateTimeInterface $dateTime): string
    {
        return $this->timeFormatter->formatRelativeTime($dateTime);
    }

    /**
     * Format tanggal lengkap
     * Usage: {{ post.createdAt|time_full }}
     * Output: "1 Sep 2024", "15 Okt"
     */
    public function timeFull(DateTimeInterface $dateTime): string
    {
        return $this->timeFormatter->formatFullDate($dateTime);
    }

    /**
     * Format untuk tooltip (hover)
     * Usage: {{ post.createdAt|time_tooltip }}
     * Output: "Senin, 22 Oktober 2025 pukul 14:30"
     */
    public function timeTooltip(DateTimeInterface $dateTime): string
    {
        return $this->timeFormatter->formatTooltip($dateTime);
    }

    /**
     * Format dengan fallback ke tanggal lengkap
     * Usage: {{ post.createdAt|time_with_fallback(7) }}
     * Output: "5 mnt" (jika < 7 hari), "1 Sep" (jika > 7 hari)
     */
    public function timeWithFallback(DateTimeInterface $dateTime, int $daysThreshold = 7): string
    {
        return $this->timeFormatter->formatWithFallback($dateTime, $daysThreshold);
    }
}
