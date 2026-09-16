<?php

namespace App\Support;

use Carbon\Carbon;

final class KpiClock
{
    public static function now(): Carbon
    {
        $debugDate = config('kpi.debug_date');
        if (app()->environment('local') && filled($debugDate)) {
            try {
                return Carbon::parse($debugDate, 'Asia/Jakarta');
            } catch (\Throwable) {
                // Invalid local debug values safely fall back to the real clock.
            }
        }

        return now('Asia/Jakarta');
    }
}
