<?php

namespace App\Service;

/**
 * UiHelper - Service untuk utility UI dan formatting
 *
 * Service ini menangani:
 * - Badge status formatting
 * - UI helper functions yang digunakan berulang
 * - Konsolidasi duplikasi getStatusBadge() dari multiple locations
 *
 * REFACTOR: Dipindahkan dari duplikasi di RankingService, Entity\Event, Entity\EventAbsensi
 *
 * @author Refactor Assistant
 */
class UiHelper
{
    /**
     * Generate status badge berdasarkan persentase kehadiran
     *
     * ATURAN BADGE (sesuai requirement terbaru):
     * - ≥ 90% → 🟢 "Luar Biasa"
     * - 75–89% → 🟡 "Bagus"
     * - < 75% → 🔴 "Perlu Perhatian"
     *
     * @param float $persentase Persentase kehadiran (0-100)
     * @return string Emoji badge status
     */
    public static function getStatusBadge(float $persentase): string
    {
        if ($persentase >= 90) {
            return '🟢'; // Hijau untuk ≥ 90% = "Luar Biasa"
        } elseif ($persentase >= 75) {
            return '🟡'; // Kuning untuk 75-89% = "Bagus"
        } else {
            return '🔴'; // Merah untuk < 75% = "Perlu Perhatian"
        }
    }

    /**
     * Generate status text berdasarkan persentase kehadiran
     *
     * @param float $persentase Persentase kehadiran (0-100)
     * @return string Status text
     */
    public static function getStatusText(float $persentase): string
    {
        if ($persentase >= 90) {
            return 'Luar Biasa';
        } elseif ($persentase >= 75) {
            return 'Bagus';
        } else {
            return 'Perlu Perhatian';
        }
    }

    /**
     * Generate combined badge + text
     *
     * @param float $persentase Persentase kehadiran (0-100)
     * @return string Badge emoji + text
     */
    public static function getStatusBadgeWithText(float $persentase): string
    {
        return self::getStatusBadge($persentase) . ' ' . self::getStatusText($persentase);
    }

    /**
     * Format persentase untuk display
     *
     * @param float $persentase
     * @param int $decimals Jumlah desimal (default: 1)
     * @return string Formatted percentage
     */
    public static function formatPercentage(float $persentase, int $decimals = 1): string
    {
        return number_format($persentase, $decimals) . '%';
    }
}