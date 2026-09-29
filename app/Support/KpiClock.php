<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

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

    /**
     * Format an existing KPI signature timestamp as local business time.
     *
     * Signature timestamps in the existing KPI tables are persisted as the
     * Asia/Jakarta wall-clock value. Formatting an already-cast Carbon with
     * timezone('Asia/Jakarta') would interpret that value as UTC and add
     * seven hours. Keep the stored wall-clock value intact so UI and export
     * share one representation.
     */
    public static function formatSignatureDateTime(DateTimeInterface|string|null $value, string $format = 'd/m/Y H:i'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        return Carbon::parse($value, self::TIMEZONE)->format($format);
    }

    public static function isDebugging(): bool
    {
        $environment = app()->environment();

        return in_array($environment, ['local', 'development'], true)
            && (bool) config('kpi.debug_clock', false)
            && trim((string) config('kpi.debug_date', '')) !== '';
    }
}
