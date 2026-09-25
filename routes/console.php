<?php

use App\Support\KpiClock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kpi:process-deadlines', function () {
    $now = KpiClock::now();
    \App\Models\KpiPeriod::query()->with('participants')->each(function ($period) use ($now): void {
        $next = $now->copy()->startOfMonth();
        foreach ($period->participants as $participant) {
            // Evaluator assignment is configuration, not a date gate. A
            // participant becomes ready when its HRD initial data is saved.

            // 2a. KI uses the canonical KPI clock.
            if ($now->day >= 2 && $now->month === $period->bulan + 1) {
                $score = \App\Models\KpiIndividualScore::firstOrCreate(['kpi_participant_id' => $participant->id]);
                if ($score->status === 'draft') {
                    $score->update([
                        'status' => 'not_filled',
                        'submit_type' => 'automatic',
                        'capaian_departemen' => null,
                        'perawatan_aset' => null,
                        'kebersihan_kerapihan' => null,
                        'score' => 0,
                    ]);
                }
            }

            // 2b. KOPS uses the canonical KPI clock and the performance-period
            // deadline, including the December rollover.
            $opsDeadline = \Carbon\Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
                ->addMonthNoOverflow()
                ->startOfMonth()
                ->endOfDay();
            if ($now->gte($opsDeadline)) {
                $items = \App\Models\KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
                if ($items->isNotEmpty()) {
                    foreach ($items as $item) {
                        if ($item->status === 'draft') {
                            $item->update([
                                'hasil' => null,
                                'aktivitas' => null,
                                'nilai_item' => 0,
                                'status' => 'not_filled',
                                'submit_type' => 'automatic',
                                'submitted_at' => $now,
                            ]);
                        }
                    }
                }
            }

            // 3. Lewat tanggal 8: Monthly belum complete menjadi HRD_INCOMPLETE
            if ($now->day >= 9 && $now->month === $period->bulan + 1) {
                $monthly = \App\Models\KpiMonthly::firstOrCreate(['kpi_participant_id' => $participant->id]);
                if (in_array($monthly->status, ['scheduled', 'draft', 'waiting_approval'], true)) {
                    $monthly->update([
                        'status' => 'HRD_INCOMPLETE',
                    ]);
                }
            }
            // 4. Normal signer deadlines are enforced by the approval
            // endpoint. Do not create automatic signatures here: after a
            // deadline the explicit Super Admin takeover must record the
            // actual signer and source.
            /*
            $autoSignDeadline = \Carbon\Carbon::create($period->tahun, $period->bulan, 9, 23, 59, 59, 'Asia/Jakarta')->addMonth();
            if ($now->gte($autoSignDeadline)) {
                $ki = \App\Models\KpiIndividualScore::where('kpi_participant_id', $participant->id)->first();
                if ($ki && in_array($ki->status, ['submitted', 'approved', 'auto_submitted', 'not_filled'], true)) {
                    \App\Models\KpiSignature::firstOrCreate(
                        ['signable_type' => \App\Models\KpiIndividualScore::class, 'signable_id' => $ki->id, 'role' => 'atasan_langsung'],
                        ['source' => 'automatic', 'signed_for_user_id' => $participant->atasanLangsung?->user?->id, 'signed_by_user_id' => null, 'signature_path' => null, 'signed_at' => $now, 'reason' => 'deadline']
                    );
                    if ($ki->status === 'submitted') {
                        $ki->update(['status' => 'auto_signed', 'submit_type' => $ki->submit_type ?: 'automatic']);
                    }
                }
            }
            if ($now->gte($autoSignDeadline)) {
                // KOPS Auto-Sign (pending Employee and Direct Supervisor)
                $opsItems = \App\Models\KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
                if ($opsItems->isNotEmpty() && $opsItems->every(fn ($item) => in_array($item->status, ['submitted', 'approved', 'locked', 'auto_signed', 'not_filled'], true))) {
                    foreach (['employee' => $participant->karyawan?->user?->id, 'atasan_langsung' => $participant->atasanLangsung?->user?->id] as $role => $uId) {
                        if (! $uId) {
                            continue;
                        }
                        \App\Models\KpiSignature::firstOrCreate(
                            [
                                'signable_type' => \App\Models\KpiParticipant::class,
                                'signable_id' => $participant->id,
                                'role' => $role,
                            ],
                            [
                                'source' => 'automatic',
                                'signed_for_user_id' => $uId,
                                'signed_by_user_id' => null,
                                'signature_path' => null,
                                'signed_at' => $now,
                                'reason' => 'deadline',
                            ]
                        );
                    }

                    $hasBothSignatures = \App\Models\KpiSignature::where('signable_type', \App\Models\KpiParticipant::class)
                        ->where('signable_id', $participant->id)
                        ->whereIn('role', ['employee', 'atasan_langsung'])
                        ->count() >= 2;
                    if ($hasBothSignatures) {
                        $opsItems->where('status', 'submitted')->each(fn ($item) => $item->update(['status' => 'locked']));
                    }
                }

                // Monthly Published Auto-Sign (pending Employee, Direct Supervisor, and Second Supervisor if available)
                $monthly = \App\Models\KpiMonthly::where('kpi_participant_id', $participant->id)->first();
                if ($monthly && $monthly->status === 'published') {
                    $monthlyRoles = [
                        'employee' => $participant->karyawan?->user?->id,
                        'atasan_langsung' => $participant->atasanLangsung?->user?->id,
                    ];
                    if ($participant->atasan_kedua_id) {
                        $monthlyRoles['atasan_kedua'] = $participant->atasanKedua?->user?->id;
                    }

                    foreach ($monthlyRoles as $role => $uId) {
                        \App\Models\KpiSignature::firstOrCreate(
                            [
                                'signable_type' => \App\Models\KpiMonthly::class,
                                'signable_id' => $monthly->id,
                                'role' => $role,
                            ],
                            [
                                'source' => 'automatic',
                                'signed_for_user_id' => $uId,
                                'signed_by_user_id' => null,
                                'signature_path' => null,
                                'signed_at' => $now,
                                'reason' => 'deadline',
                            ]
                        );
                    }
                }
            }
            */
        }
    });
    $this->info('KPI deadline processing completed.');
})->purpose('Process KPI deadlines and catch-up automation idempotently');

Schedule::command('kpi:process-deadlines')->dailyAt('23:59')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('kpi:process-deadlines')->hourly()->timezone('Asia/Jakarta')->withoutOverlapping();

Artisan::command('kpi:ensure-current-period', function () {
    $now = KpiClock::now();
    $performanceMonth = $now->copy()->subMonthNoOverflow();
    $period = app(\App\Services\KpiPeriodService::class)->ensureForPerformanceMonth($performanceMonth->month, $performanceMonth->year);
    $this->info("Periode KPI {$period->bulan}/{$period->tahun} tersedia dengan ".\App\Models\KpiParticipant::where('kpi_period_id', $period->id)->count().' peserta.');
})->purpose('Ensure KPI period and participant snapshots for the previous performance month');

Artisan::command('kpi:clock', function () {
    $this->line('KPI Clock Mode : '.(KpiClock::isDebugging() ? 'DEBUG' : 'REAL'));
    $this->line('KPI Date       : '.KpiClock::now()->format('Y-m-d H:i:s'));
    $this->line('Timezone       : Asia/Jakarta');
})->purpose('Display the effective clock used by KPI business rules');

Schedule::command('kpi:ensure-current-period')->monthlyOn(1, '00:05')->timezone('Asia/Jakarta')->withoutOverlapping();
