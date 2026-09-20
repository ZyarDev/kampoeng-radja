<?php

namespace App\Support;

use Carbon\Carbon;

final class KpiClock
{
    private const TIMEZONE = 'Asia/Jakarta';

    public static function now(): Carbon
    {
        $debugDate = trim((string) config('kpi.debug_date', ''));
        if (self::isDebugging()) {
            try {
                return Carbon::parse($debugDate, self::TIMEZONE);
            } catch (\Throwable) {
                // Invalid local debug values safely fall back to the real clock.
            }
        }

        return Carbon::now(self::TIMEZONE);
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function isDebugging(): bool
    {
        $environment = app()->environment();

        return in_array($environment, ['local', 'development'], true)
            && (bool) config('kpi.debug_clock', false)
            && trim((string) config('kpi.debug_date', '')) !== '';
    }
}
