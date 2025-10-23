<?php

namespace App\Service;

use DateTimeInterface;
use DateTime;

/**
 * TimeFormatterService
 *
 * Service untuk format waktu relatif ala Facebook dalam Bahasa Indonesia
 * Format: "5 mnt lalu", "1 j lalu", "1 hr lalu", "1 sep lalu"
 */
class TimeFormatterService
{
    /**
     * Format waktu menjadi format relatif Indonesia (Facebook-style)
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    public function formatRelativeTime(DateTimeInterface $dateTime): string
    {
        $now = new DateTime();
        $diff = $now->getTimestamp() - $dateTime->getTimestamp();

        // Kurang dari 1 menit
        if ($diff < 60) {
            return 'Baru saja';
        }

        // Kurang dari 1 jam (dalam menit)
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' mnt';
        }

        // Kurang dari 24 jam (dalam jam)
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' j';
        }

        // Kurang dari 7 hari (dalam hari)
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' hr';
        }

        // Kurang dari 30 hari (dalam minggu)
        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' mgg';
        }

        // Kurang dari 365 hari (dalam bulan)
        if ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $this->getMonthName($months);
        }

        // Lebih dari 1 tahun
        $years = floor($diff / 31536000);
        return $years . ' thn';
    }

    /**
     * Get month abbreviation in Indonesian
     *
     * @param int $months
     * @return string
     */
    private function getMonthName(int $months): string
    {
        if ($months == 1) {
            return '1 bln';
        }

        return $months . ' bln';
    }

    /**
     * Format dengan tanggal lengkap jika lebih dari X hari
     *
     * @param DateTimeInterface $dateTime
     * @param int $daysThreshold Default 7 hari
     * @return string
     */
    public function formatWithFallback(DateTimeInterface $dateTime, int $daysThreshold = 7): string
    {
        $now = new DateTime();
        $diff = $now->getTimestamp() - $dateTime->getTimestamp();
        $days = floor($diff / 86400);

        // Jika lebih dari threshold, tampilkan tanggal lengkap
        if ($days > $daysThreshold) {
            return $this->formatFullDate($dateTime);
        }

        // Jika tidak, tampilkan format relatif
        return $this->formatRelativeTime($dateTime);
    }

    /**
     * Format tanggal lengkap Indonesia
     * Format: "1 Sep 2024" atau "15 Okt 2024"
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    public function formatFullDate(DateTimeInterface $dateTime): string
    {
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];

        $day = $dateTime->format('j');
        $month = (int) $dateTime->format('n');
        $year = $dateTime->format('Y');

        // Jika tahun ini, tampilkan tanpa tahun
        $currentYear = (new DateTime())->format('Y');
        if ($year === $currentYear) {
            return $day . ' ' . $monthNames[$month];
        }

        return $day . ' ' . $monthNames[$month] . ' ' . $year;
    }

    /**
     * Format dengan jam untuk hari yang sama
     * Format: "Hari ini 14:30" atau "Kemarin 09:15"
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    public function formatWithTime(DateTimeInterface $dateTime): string
    {
        $now = new DateTime();
        $dateDay = $dateTime->format('Y-m-d');
        $nowDay = $now->format('Y-m-d');
        $time = $dateTime->format('H:i');

        // Hari ini
        if ($dateDay === $nowDay) {
            return 'Hari ini ' . $time;
        }

        // Kemarin
        $yesterday = (new DateTime())->modify('-1 day')->format('Y-m-d');
        if ($dateDay === $yesterday) {
            return 'Kemarin ' . $time;
        }

        // Lebih dari kemarin, tampilkan format relatif
        return $this->formatRelativeTime($dateTime);
    }

    /**
     * Format untuk tooltip (full datetime)
     * Format: "Senin, 22 Oktober 2025 pukul 14:30"
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    public function formatTooltip(DateTimeInterface $dateTime): string
    {
        $dayNames = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu'
        ];

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $dayName = $dayNames[$dateTime->format('l')];
        $day = $dateTime->format('j');
        $month = $monthNames[(int) $dateTime->format('n')];
        $year = $dateTime->format('Y');
        $time = $dateTime->format('H:i');

        return "{$dayName}, {$day} {$month} {$year} pukul {$time}";
    }
}
