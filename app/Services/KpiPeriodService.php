<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\MpaEvaluatorAssignment;
use App\Support\KpiClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiPeriodService
{
    public function ensureForPerformanceMonth(int $month, int $year): KpiPeriod
    {
        $period = $this->ensurePreparationForMonth($month, $year);
        return $this->activatePeriod($period);
    }

    public function ensurePreparationForNextMonth(?Carbon $clock = null): KpiPeriod
    {
        $clock ??= KpiClock::now();
        $next = $clock->copy()->startOfMonth()->addMonthNoOverflow();
        return $this->ensurePreparationForMonth($next->month, $next->year);
    }

    public function ensurePreparationForMonth(int $month, int $year): KpiPeriod
    {
        return DB::transaction(function () use ($month, $year) {
            $period = KpiPeriod::query()->firstOrCreate(['bulan' => $month, 'tahun' => $year], ['status' => 'draft']);
            $this->syncEvaluatorAssignment($period);
            // Provisional participants make K-OPS preparation possible. Their
            // final organisation snapshot is reconciled on activation.
            $this->syncParticipants($period, false);
            return $period->fresh();
        });
    }

    public function activateCurrentPeriod(?Carbon $clock = null): ?KpiPeriod
    {
        $clock ??= KpiClock::now();
        return $this->activatePeriod($this->ensurePreparationForMonth($clock->month, $clock->year));
    }

    public function runLifecycle(?Carbon $clock = null): array
    {
        $clock ??= KpiClock::now();
        $active = $this->activateCurrentPeriod($clock);
        $next = null;
        if ($clock->day >= 25) {
            $next = $this->ensurePreparationForNextMonth($clock);
        } else {
            $nextDate = $clock->copy()->startOfMonth()->addMonthNoOverflow();
            $next = KpiPeriod::query()->where('bulan', $nextDate->month)->where('tahun', $nextDate->year)->first();
        }
        return ['active' => $active, 'next' => $next];
    }

    public function activatePeriod(KpiPeriod $period): KpiPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = KpiPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (in_array($period->status, ['draft', 'preparation'], true)) {
                $this->syncParticipants($period, true);
                $period->update(['status' => 'active']);
            }
            return $period->fresh();
        });
    }

    private function syncEvaluatorAssignment(KpiPeriod $period): void
    {
        $assignment = MpaEvaluatorAssignment::query()->where('year', $period->tahun)->where('month', $period->bulan)->first();
        if ($assignment && (int) $period->mpa_evaluator_id !== (int) $assignment->evaluator_id) {
            $period->update(['mpa_evaluator_id' => $assignment->evaluator_id, 'mpa_assigned_at' => $assignment->updated_at]);
        }
    }

    private function syncParticipants(KpiPeriod $period, bool $finalizeSnapshot): void
    {
        $employees = Karyawan::with(['jabatan', 'departemen', 'penempatan', 'atasanLangsung.atasanLangsung'])
            ->where('status_keaktifan', 'aktif')->get()
            ->filter(fn (Karyawan $employee) => ! $this->excludedPosition($employee->jabatan?->nama_jabatan));
        $employeeIds = $employees->pluck('id');
        foreach ($employees as $employee) {
            $participant = KpiParticipant::query()->firstOrCreate(
                ['kpi_period_id' => $period->id, 'karyawan_id' => $employee->id],
                $this->snapshotAttributes($employee)
            );
            if ($finalizeSnapshot) {
                $participant->update($this->snapshotAttributes($employee) + ['status' => 'active']);
            }
        }
        if ($finalizeSnapshot) {
            KpiParticipant::query()->where('kpi_period_id', $period->id)->whereNotIn('karyawan_id', $employeeIds)->update(['status' => 'inactive']);
        }
    }

    private function snapshotAttributes(Karyawan $employee): array
    {
        return [
            'jabatan_id' => $employee->jabatan_id, 'jabatan_snapshot' => $employee->jabatan?->nama_jabatan ?? '-',
            'departemen_id' => $employee->departemen_id, 'departemen_snapshot' => $employee->departemen?->nama_departemen ?? '-',
            'penempatan_id' => $employee->penempatan_id, 'penempatan_snapshot' => $employee->penempatan?->nama_penempatan ?? '-',
            'atasan_langsung_id' => $employee->atasan_langsung_id, 'atasan_langsung_snapshot' => $employee->atasanLangsung?->nama,
            'atasan_kedua_id' => $employee->atasanLangsung?->atasan_langsung_id, 'atasan_kedua_snapshot' => $employee->atasanLangsung?->atasanLangsung?->nama,
        ];
    }

    private function excludedPosition(?string $position): bool
    {
        return in_array(mb_strtolower(trim((string) $position)), ['dirut', 'direktur', 'direktur utama'], true);
    }
}
