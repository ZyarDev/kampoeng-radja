<?php

namespace App\Services;

use App\Models\WorkCalendarOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkCalendarService
{
    public function getDayStatus(CarbonInterface|string $date): array
    {
        $day = $this->date($date);
        $override = WorkCalendarOverride::query()->whereDate('tanggal', $day->toDateString())->first();

        if ($override) {
            return [
                'date' => $day->toDateString(),
                'is_working_day' => (bool) $override->is_working_day,
                'type' => $override->is_working_day ? 'working_override' : 'holiday_override',
                'label' => $override->is_working_day ? 'Hari Kerja' : 'Libur',
                'reason' => $override->keterangan ?: ($override->is_working_day ? 'Hari Kerja Khusus' : 'Libur Khusus'),
            ];
        }

        $isFriday = $day->dayOfWeekIso === 5;

        return [
            'date' => $day->toDateString(),
            'is_working_day' => ! $isFriday,
            'type' => $isFriday ? 'weekly_holiday' : 'normal_working_day',
            'label' => $isFriday ? 'Libur' : 'Hari Kerja',
            'reason' => $isFriday ? 'Libur Mingguan' : 'Hari Kerja Normal',
        ];
    }

    public function isWorkingDay(CarbonInterface|string $date): bool
    {
        return $this->getDayStatus($date)['is_working_day'];
    }

    /** @return Collection<string, array> */
    public function statusesForRange(CarbonInterface|string $start, CarbonInterface|string $end): Collection
    {
        $from = $this->date($start)->startOfDay();
        $to = $this->date($end)->startOfDay();
        if ($from->gt($to)) {
            return collect();
        }
        $overrides = WorkCalendarOverride::query()
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (WorkCalendarOverride $override) => $override->tanggal->toDateString());

        return collect(range(0, $from->diffInDays($to)))->mapWithKeys(function (int $offset) use ($from, $overrides): array {
            $date = $from->addDays($offset);
            $key = $date->toDateString();
            $override = $overrides->get($key);

            if ($override) {
                return [$key => [
                    'date' => $key,
                    'is_working_day' => (bool) $override->is_working_day,
                    'type' => $override->is_working_day ? 'working_override' : 'holiday_override',
                    'label' => $override->is_working_day ? 'Hari Kerja' : 'Libur',
                    'reason' => $override->keterangan ?: ($override->is_working_day ? 'Hari Kerja Khusus' : 'Libur Khusus'),
                ]];
            }

            $isFriday = $date->dayOfWeekIso === 5;
            return [$key => [
                'date' => $key,
                'is_working_day' => ! $isFriday,
                'type' => $isFriday ? 'weekly_holiday' : 'normal_working_day',
                'label' => $isFriday ? 'Libur' : 'Hari Kerja',
                'reason' => $isFriday ? 'Libur Mingguan' : 'Hari Kerja Normal',
            ]];
        });
    }

    private function date(CarbonInterface|string $date): CarbonImmutable
    {
        return $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->setTimezone('Asia/Jakarta')
            : CarbonImmutable::parse($date, 'Asia/Jakarta');
    }
}
