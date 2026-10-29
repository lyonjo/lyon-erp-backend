<?php

namespace App\Support\Rent;

use Carbon\CarbonInterface;
use App\Models\Rent\Setting;

final class BillableRentalDays
{
    public static function allowance(): int
    {
        return max(0, min(24, (int) (Setting::where('key', 'additional_hours')->value('value') ?? 0)));
    }

    public static function count(CarbonInterface $start, CarbonInterface $end): int
    {
        // Timestamps are compared in absolute seconds, and the remainder
        // must EXCEED the allowance to incur another day.
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($seconds <= 0) return 0;
        $completeDays = intdiv($seconds, 86400);
        $remainderSeconds = $seconds % 86400;
        return max(1, $completeDays + ($remainderSeconds > self::allowance() * 3600 ? 1 : 0));
    }
}
