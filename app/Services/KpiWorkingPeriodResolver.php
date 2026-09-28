<?php

namespace App\Services;

use App\Models\KpiPeriod;
use App\Support\KpiClock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the period shown by the periodic KPI workspace.
 *
 * The calendar-active period is deliberately kept separate from the period
 * that is most relevant for KPI closing work. At the start of a new month,
 * the previous period remains the default until its normal finalisation
 * window has passed. This service is read-only; it never changes lifecycle
 * state or period data.
 */
class KpiWorkingPeriodResolver
{
    public function activeCalendarPeriod(?Collection $periods = null, ?Carbon $clock = null): ?KpiPeriod
    {
        $periods ??= KpiPeriod::query()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();
        $clock ??= KpiClock::now();

        return $periods->first(fn (KpiPeriod $period) =>
            (int) $period->tahun === $clock->year
            && (int) $period->bulan === $clock->month
            && $period->status === 'active'
        )
            ?? $periods->first(fn (KpiPeriod $period) => $period->status === 'active')
            ?? $periods->first();
    }

    public function resolveDefault(?Collection $periods = null, ?Carbon $clock = null): ?KpiPeriod
    {
        $periods ??= KpiPeriod::query()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();
        $clock ??= KpiClock::now();
        $active = $this->activeCalendarPeriod($periods, $clock);

        if (! $active) {
            return null;
        }

        $previousDate = $clock->copy()->startOfMonth()->subMonthNoOverflow();
        $previous = $periods->first(fn (KpiPeriod $period) =>
            (int) $period->tahun === $previousDate->year
            && (int) $period->bulan === $previousDate->month
        );

        // A period is still the natural KPI workspace while the calendar is
        // in the first month after it and the existing final-review deadline
        // (day 8 of the following month) has not passed. This is the same
        // deadline used by the KPI final-score workflow, centralized here so
        // each page does not invent its own day-of-month rule.
        if ($previous && $this->isClosingWindow($previous, $clock)) {
            return $previous;
        }

        return $active;
    }

    public function isClosingWindow(KpiPeriod $period, Carbon $clock): bool
    {
        $nextMonth = Carbon::create(
            (int) $period->tahun,
            (int) $period->bulan,
            1,
            0,
            0,
            0,
            'Asia/Jakarta'
        )->addMonthNoOverflow();

        if ($clock->year !== $nextMonth->year || $clock->month !== $nextMonth->month) {
            return false;
        }

        $deadline = $nextMonth->copy()->addDays(7)->endOfDay();
        return $clock->lessThanOrEqualTo($deadline);
    }
}
