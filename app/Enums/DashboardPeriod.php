<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * The home page's period filter: today, this week, this month, this year so far, or a range
 * the owner picks (Maxsus).
 */
enum DashboardPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case Custom = 'custom';

    /**
     * Get the period's first and last day ("Y-m-d"); a custom period uses the given days.
     *
     * @return array{0: string, 1: string}
     */
    public function range(?string $from = null, ?string $to = null): array
    {
        $today = Carbon::today();

        return match ($this) {
            self::Day => [$today->toDateString(), $today->toDateString()],
            self::Week => [$today->copy()->startOfWeek()->toDateString(), $today->toDateString()],
            self::Month => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            self::Year => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
            self::Custom => [$from ?? $today->toDateString(), $to ?? $today->toDateString()],
        };
    }
}
