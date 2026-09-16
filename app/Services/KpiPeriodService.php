<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use Illuminate\Support\Facades\DB;

class KpiPeriodService
{
    public function ensureForPerformanceMonth(int $month, int $year): KpiPeriod
    {
        return DB::transaction(function () use ($month, $year) {
            $period = KpiPeriod::firstOrCreate(['bulan' => $month, 'tahun' => $year]);

            Karyawan::with(['jabatan', 'departemen', 'penempatan', 'atasanLangsung.atasanLangsung'])
                ->where('status_keaktifan', 'aktif')
                ->get()
                ->filter(fn ($employee) => ! $this->excludedPosition($employee->jabatan?->nama_jabatan))
                ->each(function (Karyawan $employee) use ($period): void {
                    KpiParticipant::firstOrCreate(
                        ['kpi_period_id' => $period->id, 'karyawan_id' => $employee->id],
                        [
                            'jabatan_id' => $employee->jabatan_id,
                            'jabatan_snapshot' => $employee->jabatan?->nama_jabatan ?? '-',
                            'departemen_id' => $employee->departemen_id,
                            'departemen_snapshot' => $employee->departemen?->nama_departemen ?? '-',
                            'penempatan_id' => $employee->penempatan_id,
                            'penempatan_snapshot' => $employee->penempatan?->nama_penempatan ?? '-',
                            'atasan_langsung_id' => $employee->atasan_langsung_id,
                            'atasan_langsung_snapshot' => $employee->atasanLangsung?->nama,
                            'atasan_kedua_id' => $employee->atasanLangsung?->atasan_langsung_id,
                            'atasan_kedua_snapshot' => $employee->atasanLangsung?->atasanLangsung?->nama,
                        ]
                    );
                });

            return $period->fresh();
        });
    }

    private function excludedPosition(?string $position): bool
    {
        return in_array(mb_strtolower(trim((string) $position)), ['dirut', 'direktur', 'direktur utama'], true);
    }
}
