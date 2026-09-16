<?php

use App\Actions\Employee\ResolveEmployeeAccountRole;
use App\Actions\Employee\SyncEmployeeAccountRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('employees:sync-account-roles {--apply : Terapkan perubahan role ke database}', function () {
    $resolver = app(ResolveEmployeeAccountRole::class);
    $synchronizer = app(SyncEmployeeAccountRole::class);
    $apply = (bool) $this->option('apply');
    $mismatches = 0;
    $updated = 0;
    $skipped = 0;

    User::query()
        ->with(['role:id,nama_role', 'karyawan.jabatan:id,nama_jabatan'])
        ->orderBy('id')
        ->each(function (User $account) use ($resolver, $synchronizer, $apply, &$mismatches, &$updated, &$skipped): void {
            $employee = $account->karyawan;
            $roleName = $resolver->handle($employee?->jabatan?->nama_jabatan);

            if (! $employee || ! $roleName) {
                $this->warn("Lewati akun {$account->username}: Karyawan/Jabatan tidak memiliki mapping role.");
                $skipped++;

                return;
            }

            if (mb_strtolower($account->role?->nama_role ?? '') === $roleName) {
                return;
            }

            $mismatches++;
            $this->line("{$account->username}: {$account->role?->nama_role} -> {$roleName}");

            if ($apply && $synchronizer->handle($employee)) {
                $updated++;
            }
        });

    if (! $apply) {
        $this->info("Dry run selesai: {$mismatches} akun perlu disinkronkan; {$skipped} akun dilewati.");
        if ($mismatches > 0) {
            $this->comment('Jalankan kembali dengan --apply untuk menerapkan perubahan.');
        }

        return;
    }

    $this->info("Sinkronisasi selesai: {$updated} akun diperbarui; {$skipped} akun dilewati.");
})->purpose('Audit atau sinkronkan role akun dengan Jabatan Karyawan secara idempotent');

Artisan::command('kpi:process-deadlines', function () {
    $now = now('Asia/Jakarta');
    $kiNow = \App\Support\KpiClock::now();
    \App\Models\KpiPeriod::query()->with('participants')->each(function ($period) use ($now, $kiNow): void {
        $next = $now->copy()->startOfMonth();
        foreach ($period->participants as $participant) {
            // 1. MPA Evaluator Assignment Deadline: End of performance month (blocked if no evaluator by day 1 of next month)
            if ($now->month === $period->bulan + 1 && ! $period->mpa_evaluator_id) {
                // Period marked as blocked for normal assignment, HRD takeover required
                $period->update(['status' => 'blocked']);
            }

            // 2a. KI uses its local-only debug clock when configured.
            if ($kiNow->day >= 3 && $kiNow->month === $period->bulan + 1) {
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

            // 2b. KOPS always uses the real application clock.
            if ($now->day >= 3 && $now->month === $period->bulan + 1) {
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
            // 4. Auto-sign deadline: Tanggal 9 23:59 WIB for KI, KOPS, and Published Monthly
            $autoSignDeadline = \Carbon\Carbon::create($period->tahun, $period->bulan, 9, 23, 59, 59, 'Asia/Jakarta')->addMonth();
            if ($kiNow->gte($autoSignDeadline)) {
                $ki = \App\Models\KpiIndividualScore::where('kpi_participant_id', $participant->id)->first();
                if ($ki && in_array($ki->status, ['submitted', 'approved', 'auto_submitted', 'not_filled'], true)) {
                    \App\Models\KpiSignature::firstOrCreate(
                        ['signable_type' => \App\Models\KpiIndividualScore::class, 'signable_id' => $ki->id, 'role' => 'atasan_langsung'],
                        ['source' => 'automatic', 'signed_for_user_id' => $participant->atasanLangsung?->user?->id, 'signed_by_user_id' => null, 'signature_path' => null, 'signed_at' => $kiNow, 'reason' => 'deadline']
                    );
                    if ($ki->status === 'submitted') {
                        $ki->update(['status' => 'auto_signed', 'submit_type' => $ki->submit_type ?: 'automatic']);
                    }
                }
            }
            if ($now->gte($autoSignDeadline)) {
                // KOPS Auto-Sign (pending Employee and Direct Supervisor)
                $opsItems = \App\Models\KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
                if ($opsItems->isNotEmpty()) {
                    foreach (['employee' => $participant->karyawan?->user?->id, 'atasan_langsung' => $participant->atasanLangsung?->user?->id] as $role => $uId) {
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
        }
    });
    $this->info('KPI deadline processing completed.');
})->purpose('Process KPI deadlines and catch-up automation idempotently');

Schedule::command('kpi:process-deadlines')->dailyAt('23:59')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('kpi:process-deadlines')->hourly()->timezone('Asia/Jakarta')->withoutOverlapping();

Artisan::command('kpi:ensure-current-period', function () {
    $now = now('Asia/Jakarta');
    $performanceMonth = $now->copy()->subMonthNoOverflow();
    $period = app(\App\Services\KpiPeriodService::class)->ensureForPerformanceMonth($performanceMonth->month, $performanceMonth->year);
    $this->info("Periode KPI {$period->bulan}/{$period->tahun} tersedia dengan ".\App\Models\KpiParticipant::where('kpi_period_id', $period->id)->count().' peserta.');
})->purpose('Ensure KPI period and participant snapshots for the previous performance month');

Schedule::command('kpi:ensure-current-period')->monthlyOn(1, '00:05')->timezone('Asia/Jakarta')->withoutOverlapping();
