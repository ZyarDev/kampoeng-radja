<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\KpiDailyActivity;
use App\Models\KpiDailyReport;
use App\Models\KpiFinalScore;
use App\Models\KpiIndividualScore;
use App\Models\KpiMonthly;
use App\Models\KpiOpsItem;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\User;
use App\Support\KpiClock;
use App\Services\KpiPeriodService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KpiController extends Controller
{
    /**
     * Dashboard KPI (Semua Periode)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $employee = $user->karyawan;

        $periods = KpiPeriod::withCount('participants')->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get()
            ->map(function (KpiPeriod $period) {
                $period->configuration_errors = $period->participants()->whereNull('atasan_langsung_id')->count();
                return $period;
            });

        $activePeriod = $periods->first();

        $myDailyMissingCount = 0;
        if ($employee) {
            $myDailyMissingCount = $this->calculateMissingDailyCount($employee->id, now('Asia/Jakarta'));
        }

        return inertia('Internal/Kpi/Index', [
            'user' => $this->userPayload($request),
            'periods' => $periods,
            'activePeriod' => $activePeriod,
            'missingDailyCount' => $myDailyMissingCount,
        ]);
    }

    /**
     * Create Period & Snapshot Participants (Super Admin)
     */
    public function createPeriod(Request $request)
    {
        abort_unless($request->user()->role()->value('nama_role') === 'super_admin', 403);

        $d = Carbon::createFromDate($request->integer('tahun'), $request->integer('bulan'), 1);
        $period = app(KpiPeriodService::class)->ensureForPerformanceMonth($d->month, $d->year);

        return back()->with('success', 'Periode KPI dan snapshot peserta berhasil dibuat.');
    }

    /**
     * List KPI Karyawan for Supervisor/Admin (Monitoring Hierarchy)
     */
    public function employees(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuper = $user->role()->value('nama_role') === 'super_admin';

        $query = $period->participants()
            ->with(['karyawan.jabatan', 'karyawan.departemen', 'karyawan.penempatan'])
            ->whereHas('karyawan', fn ($employeeQuery) => $employeeQuery->where('status_keaktifan', 'aktif'));

        // Hierarchy filter for non-superadmin: filter by subordinates using period participant snapshot
        if (! $isSuper && $user->karyawan_id) {
            $subordinateIds = $this->getCurrentSubordinateKaryawanIds($user->karyawan_id);
            $query->whereIn('karyawan_id', $subordinateIds);
        }

        $participants = $query->get()
            ->reject(fn ($p) => $this->isExcludedKpiPosition($p->karyawan?->jabatan?->nama_jabatan))
            ->map(function ($p) use ($user, $isSuper, $period) {
            $ki = KpiIndividualScore::where('kpi_participant_id', $p->id)->first();
            $opsCount = KpiOpsItem::where('kpi_participant_id', $p->id)->count();

            $perfDate = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->endOfMonth();
            $missingDailyCount = $this->calculateMissingDailyCount($p->karyawan_id, $perfDate);
            $needsSp1Followup = $missingDailyCount >= 3;

            $isSelf = $p->karyawan_id === $user->karyawan_id;
            $isDirectSupervisor = $user->karyawan_id && $p->atasan_langsung_id === $user->karyawan_id;

            $dailyStatuses = KpiDailyReport::where('karyawan_id', $p->karyawan_id)
                ->whereBetween('tanggal', [
                    Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->toDateString(),
                    Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->endOfMonth()->toDateString(),
                ])->pluck('status', 'id');
            $dailyStatusValues = $dailyStatuses->values();
            $dailyStatus = $dailyStatusValues->contains('waiting_approval') ? 'waiting_approval'
                : ($dailyStatusValues->contains('not_filled') ? 'not_filled'
                : ($dailyStatusValues->isNotEmpty() && $dailyStatusValues->every(fn ($status) => $status === 'approved') ? 'approved' : 'none'));


            $canEditKi = $isSelf || $isDirectSupervisor || $isSuper;
            $canEditOps = $isSelf || $isSuper;

            return [
                'id' => $p->id,
                'karyawan_id' => $p->karyawan_id,
                'nama' => $p->karyawan?->nama ?? 'Unknown',
                'nip' => $p->karyawan?->nip ?? '-',
                'nik' => $p->karyawan?->nik ?? $p->karyawan?->nip ?? '-',
                'jabatan' => $p->jabatan_snapshot,
                'departemen' => $p->departemen_snapshot,
                'penempatan' => $p->penempatan_snapshot ?? $p->karyawan?->penempatan?->nama_penempatan ?? '-',
                'atasan_langsung' => $p->atasan_langsung_snapshot,
                'atasan_langsung_id' => $p->karyawan?->atasan_langsung_id ?? $p->atasan_langsung_id,
                'ki_status' => $ki?->status ?? 'draft',
                'ki_score' => $ki?->score ?? 0,
                'ops_count' => $opsCount,
                'missing_daily_count' => $missingDailyCount,
                'needs_sp1_followup' => $needsSp1Followup,
                'can_edit_ki' => $canEditKi,
                'can_edit_ops' => $canEditOps,
                'daily_status' => $dailyStatus,
                'daily_count' => $dailyStatuses->count(),
                'pending_daily_ids' => $user->karyawan_id ? KpiDailyReport::whereIn('id', $dailyStatuses->keys())
                    ->where('status', 'waiting_approval')
                    ->where('atasan_snapshot_id', $user->karyawan_id)->pluck('id')->all() : [],
            ];
            });

        $levels = [];
        $children = Karyawan::where('status_keaktifan', 'aktif')
            ->get(['id', 'atasan_langsung_id'])->groupBy('atasan_langsung_id');
        $frontier = $user->karyawan_id ? [(int) $user->karyawan_id] : [];
        $visited = array_fill_keys($frontier, true);
        $level = 1;
        while ($frontier) {
            $next = [];
            foreach ($frontier as $parentId) {
                foreach ($children->get($parentId, collect()) as $child) {
                    if (isset($visited[$child->id])) continue;
                    $visited[$child->id] = true;
                    $next[] = (int) $child->id;
                }
            }
            $members = $participants->filter(fn ($p) => in_array((int) $p['karyawan_id'], $next, true))->values();
            if ($members->isNotEmpty()) {
                $levels[] = ['level' => $level, 'label' => $level === 1 ? 'Bawahan Langsung' : 'Bawahan '.([2 => 'Kedua', 3 => 'Ketiga', 4 => 'Keempat'][$level] ?? 'Level '.$level), 'participants' => $members];
            }
            $frontier = $next;
            $level++;
        }
        if ($isSuper) {
            $outside = $participants->reject(fn ($p) => isset($visited[$p['karyawan_id']]) || (int) $p['karyawan_id'] === (int) $user->karyawan_id)->values();
            if ($outside->isNotEmpty()) {
                $levels[] = ['level' => 0, 'label' => 'Bawahan Tidak Langsung', 'participants' => $outside];
            }
        }

        return inertia('Internal/Kpi/Employees', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'participants' => $participants,
            'hierarchy' => $levels,
        ]);
    }

    /**
     * Daily Report Page & Submission
     */
    public function daily(Request $request)
    {
        $viewer = $request->user();
        $employee = $viewer->karyawan;
        abort_unless($employee, 403, 'User tidak terhubung ke data karyawan.');
        $targetKaryawanId = $request->integer('karyawan_id') ?: $employee->id;
        if ($targetKaryawanId !== $employee->id) {
            $isSuper = $viewer->role()->value('nama_role') === 'super_admin';
            $periodId = $request->integer('period_id');
            $allowed = $isSuper || ($periodId && in_array($targetKaryawanId, $this->getAllSubordinateKaryawanIds($employee->id, $periodId), true));
            abort_unless($allowed, 403, 'Anda tidak memiliki akses monitoring Daily Report karyawan ini.');
            $employee = Karyawan::findOrFail($targetKaryawanId);
        }
        $isOwner = $targetKaryawanId === $viewer->karyawan_id;
        $employee->loadMissing(['jabatan', 'departemen', 'penempatan', 'atasanLangsung']);
        abort_if($this->isExcludedKpiPosition($employee->jabatan?->nama_jabatan), 403, 'Jabatan ini tidak wajib mengisi Daily Report.');

        $dateStr = $request->input('tanggal', now('Asia/Jakarta')->toDateString());
        $targetDate = Carbon::parse($dateStr, 'Asia/Jakarta');
        abort_unless($targetDate->format('Y-m-d') === $dateStr, 422, 'Format tanggal tidak valid.');
        $today = now('Asia/Jakarta')->startOfDay();
        $yesterday = now('Asia/Jakarta')->subDay()->startOfDay();

        $isEditable = $targetDate->equalTo($today) || $targetDate->equalTo($yesterday);

        $report = KpiDailyReport::firstOrNew(
            [
                'karyawan_id' => $employee->id,
                'tanggal' => $targetDate->toDateString(),
            ],
            [
                'status' => 'draft',
                'atasan_snapshot_id' => $employee->atasan_langsung_id,
                'approver_id' => $employee->atasan_langsung_id,
            ]
        );

        $absensi = Absensi::where('karyawan_id', $employee->id)
            ->whereDate('tanggal_absensi', $targetDate->toDateString())
            ->first();
        $statusKehadiran = $absensi?->status_kehadiran;

        if ($request->isMethod('post')) {
            abort_unless($isOwner, 403, 'Daily Report bawahan hanya dapat dimonitor, bukan diedit.');
            abort_unless($isEditable, 422, 'Hanya dapat mengisi/edit daily report untuk hari ini atau kemarin.');
            abort_if($report->status === 'approved', 422, 'Daily report yang sudah disetujui tidak dapat diubah.');
            abort_unless($statusKehadiran === 'H', 422, 'Daily Report hanya wajib pada hari dengan status Absensi H.');

            $data = $request->validate([
                'activities' => 'required|array|min:1',
                'activities.*.rincian_kegiatan' => 'required|string|max:1000',
                'activities.*.keterangan' => 'nullable|string|max:500',
                'activities.*.foto_evidence' => 'nullable|file|image|max:5120',
                'activities.*.existing_foto' => 'nullable|string',
            ]);

            DB::transaction(function () use ($report, $data, $request) {
                // firstOrNew returns an unsaved model for a new date. Persist
                // the header before touching activities so the FK can never
                // receive a null daily_report_id.
                if (! $report->exists) {
                    $report->save();
                }

                $oldPaths = $report->exists ? $report->activities()->pluck('bukti_path')->filter()->all() : [];
                $newPaths = [];
                $report->activities()->delete();

                foreach ($data['activities'] as $index => $actData) {
                    $fotoPath = $actData['existing_foto'] ?? null;

                    if ($request->hasFile("activities.{$index}.foto_evidence")) {
                        $file = $request->file("activities.{$index}.foto_evidence");
                        $storedPath = $file->store('kpi/daily-evidence', 'public');
                        $fotoPath = $storedPath;
                    }
                    if ($fotoPath) $newPaths[] = $fotoPath;

                    $report->activities()->create([
                        'urutan' => $index + 1,
                        'rincian_kegiatan' => $actData['rincian_kegiatan'],
                        'keterangan' => $actData['keterangan'] ?? null,
                        'bukti_path' => $fotoPath,
                    ]);
                }

                $report->update([
                    'status' => 'waiting_approval',
                    'submitted_at' => now('Asia/Jakarta'),
                    'atasan_snapshot_id' => $report->atasan_snapshot_id ?? $request->user()->karyawan?->atasan_langsung_id,
                ]);
                foreach (array_diff($oldPaths, $newPaths) as $oldPath) {
                    if (! str_starts_with($oldPath, 'http')) Storage::disk('public')->delete($oldPath);
                }
            });

            return back()->with('success', 'Daily Report berhasil disimpan dan diajukan.');
        }

        $missingCount = $this->calculateMissingDailyCount($employee->id, $targetDate);

        // A brand-new report for today may start from yesterday's activity
        // descriptions. This is deliberately limited to yesterday and never
        // persisted until the employee explicitly saves today's report.
        if (! $report->exists && $targetDate->isToday()) {
            $yesterdayReport = KpiDailyReport::with('activities')
                ->where('karyawan_id', $employee->id)
                ->whereDate('tanggal', $targetDate->copy()->subDay()->toDateString())
                ->where('status', '!=', 'not_filled')
                ->first();

            if ($yesterdayReport?->activities?->isNotEmpty()) {
                $report->setRelation('activities', $yesterdayReport->activities->map(
                    fn (KpiDailyActivity $activity) => new KpiDailyActivity([
                        'urutan' => $activity->urutan,
                        'rincian_kegiatan' => $activity->rincian_kegiatan,
                        'keterangan' => null,
                        'bukti_path' => null,
                    ])
                ));
            }
        }

        $pendingApprovals = KpiDailyReport::with(['activities', 'karyawan'])
            ->where('atasan_snapshot_id', $employee->id)
            ->where('status', 'waiting_approval')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'karyawan_nama' => $r->karyawan?->nama ?? 'Karyawan',
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'submitted_at' => $r->submitted_at?->diffForHumans(),
                'activities_count' => $r->activities->count(),
                'activities' => $r->activities,
            ]);

        $historyPeriod = $request->integer('period_id') ? KpiPeriod::find($request->integer('period_id')) : null;
        $historyStart = $historyPeriod && $historyPeriod->tahun === $targetDate->year && $historyPeriod->bulan === $targetDate->month
            ? Carbon::create($historyPeriod->tahun, $historyPeriod->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
            : $targetDate->copy()->startOfMonth();
        $dailyHistory = KpiDailyReport::where('karyawan_id', $employee->id)
            ->whereBetween('tanggal', [$historyStart->toDateString(), $historyStart->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('tanggal')
            ->get(['id', 'tanggal', 'status', 'approved_at']);
        $reportsByDate = $dailyHistory->keyBy(fn ($item) => $item->tanggal->toDateString());
        $attendanceByDate = Absensi::where('karyawan_id', $employee->id)
            ->whereBetween('tanggal_absensi', [$historyStart->toDateString(), $historyStart->copy()->endOfMonth()->toDateString()])
            ->pluck('status_kehadiran', 'tanggal_absensi');
        $today = now('Asia/Jakarta')->startOfDay();
        $dailyCalendar = collect(range(1, $historyStart->daysInMonth))->map(function ($day) use ($historyStart, $reportsByDate, $attendanceByDate, $today) {
            $date = $historyStart->copy()->day($day);
            $key = $date->toDateString();
            $report = $reportsByDate->get($key);
            $status = $date->dayOfWeekIso === 5 ? 'neutral' : ($report?->status === 'approved' ? 'approved' : ($report?->status === 'waiting_approval' ? 'waiting_approval' : ($report?->status === 'not_filled' ? 'not_filled' : ($attendanceByDate->get($key) === 'H' ? ($date->greaterThanOrEqualTo($today->copy()->subDay()) ? 'in_progress' : 'not_filled') : 'neutral'))));
            return ['date' => $key, 'day' => $day, 'status' => $status, 'report_id' => $report?->id];
        })->values()->all();

        return inertia('Internal/Kpi/DailyReport', [
            'user' => $this->userPayload($request),
            'report' => $report->exists ? $report->load('activities') : $report,
            'targetDate' => $targetDate->toDateString(),
            'isEditable' => $isEditable,
            'statusKehadiran' => $statusKehadiran,
            'isEligible' => $statusKehadiran === 'H',
            'employeeHeader' => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'nik' => $employee->nik,
                'perusahaan' => 'Kampoeng Radja',
                'jabatan' => $employee->jabatan?->nama_jabatan ?? '-',
                'departemen' => $employee->departemen?->nama_departemen ?? '-',
                'penempatan' => $employee->penempatan?->nama_penempatan ?? '-',
                // Approval ownership is historical. Do not silently replace a
                // missing snapshot with the employee's current hierarchy.
                'atasan_langsung' => $report->atasanSnapshot?->nama ?? '-',
            ],
            'approvalInfo' => [
                'name' => $report->atasanSnapshot?->nama ?? '-',
                'position' => $report->atasanSnapshot?->jabatan?->nama_jabatan ?? 'Atasan Langsung',
                'status' => $report->status,
                'approved_at' => $report->approved_at?->format('d/m/Y H:i'),
                'can_approve' => $report->status === 'waiting_approval' && $viewer->karyawan_id && $report->atasan_snapshot_id === $viewer->karyawan_id,
                'signature_url' => $report->approval_signature_path ? Storage::disk('public')->url($report->approval_signature_path) : ($report->atasanSnapshot?->foto_tanda_tangan ? Storage::disk('public')->url($report->atasanSnapshot->foto_tanda_tangan) : null),
            ],
            'activePeriodId' => KpiParticipant::where('karyawan_id', $employee->id)
                ->whereHas('period', fn ($query) => $query->orderByDesc('tahun')->orderByDesc('bulan'))
                ->latest('kpi_period_id')
                ->value('kpi_period_id'),
            'isOwner' => $isOwner,
            'missingCount' => $missingCount,
            'pendingApprovals' => $pendingApprovals,
            'dailyCalendar' => $dailyCalendar,
            'calendarMonth' => $historyStart->month,
            'calendarYear' => $historyStart->year,
        ]);
    }

    /**
     * Single Approve Daily Report
     */
    public function approveDaily(Request $request, KpiDailyReport $report)
    {
        $user = $request->user();
        $employee = $user->karyawan;

        $isDirectSupervisor = $employee && $report->atasan_snapshot_id === $employee->id;
        abort_unless($isDirectSupervisor, 403, 'Hanya Atasan Langsung (snapshot) yang dapat menyetujui Daily Report ini.');
        abort_unless($report->status === 'waiting_approval', 422, 'Report tidak dalam status menunggu persetujuan.');
        $signaturePath = $this->snapshotDailyApprovalSignature($report, $employee);

        $report->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now('Asia/Jakarta'),
            'approval_source' => 'manual',
            'approval_signature_path' => $signaturePath,
        ]);

        return back()->with('success', 'Daily Report berhasil disetujui.');
    }

    /**
     * Bulk Approve Daily Reports
     */
    public function bulkApproveDaily(Request $request)
    {
        $user = $request->user();
        $employee = $user->karyawan;

        $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:kpi_daily_reports,id',
        ]);

        $query = KpiDailyReport::whereIn('id', $request->input('report_ids'))
            ->where('status', 'waiting_approval');
        abort_unless($employee, 403, 'User tidak Memiliki data karyawan.');
        $query->where('atasan_snapshot_id', $employee->id);

        $reports = $query->with('karyawan')->get();
        $count = DB::transaction(function () use ($reports, $user, $employee): int {
            foreach ($reports as $report) {
                $signaturePath = $this->snapshotDailyApprovalSignature($report, $employee);
                $report->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now('Asia/Jakarta'),
                    'approval_source' => 'manual',
                    'approval_signature_path' => $signaturePath,
                ]);
            }
            return $reports->count();
        });

        return back()->with('success', "{$count} Daily Report berhasil disetujui secara masal.");
    }

    private function snapshotDailyApprovalSignature(KpiDailyReport $report, Karyawan $approver): string
    {
        $source = trim((string) $approver->foto_tanda_tangan);
        $source = preg_replace('#^/?storage/#', '', $source);
        abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Tanda tangan Atasan Langsung belum tersedia.');

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
        $destination = 'kpi/daily-signatures/'.$report->id.'_'.now('Asia/Jakarta')->format('YmdHisv').'.'.$extension;
        abort_unless(Storage::disk('public')->copy($source, $destination), 422, 'Snapshot tanda tangan Daily Report gagal disimpan.');

        return $destination;
    }

    /**
     * Kinerja Individu (KI) Page & Submit
     */
    public function individual(Request $request, KpiPeriod $period)
    {
        $participant = $this->getParticipantOrTarget($request, $period);
        $score = KpiIndividualScore::firstOrCreate(
            ['kpi_participant_id' => $participant->id],
            [
                'capaian_departemen' => null,
                'perawatan_aset' => null,
                'kebersihan_kerapihan' => null,
                'score' => 0,
                'status' => 'draft',
                'parameter_snapshot' => $this->individualParameterSnapshot(),
            ]
        );

        if (! $score->parameter_snapshot) {
            $score->update(['parameter_snapshot' => $this->individualParameterSnapshot()]);
        }

        // K-OPS uses the real application clock; the local debug clock is reserved for KI.
        $opsNow = now('Asia/Jakarta');
        $opsStart = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->addMonth()->startOfDay();
        $opsEnd = $opsStart->copy()->addDay()->endOfDay();
        $isWindowAllowed = $opsNow->between($opsStart, $opsEnd);
        $windowState = $this->individualWindowState($period);
        $user = $request->user();
        $isSelf = $participant->karyawan_id === $user->karyawan_id;
        $signature = KpiSignature::where('signable_type', KpiIndividualScore::class)
            ->where('signable_id', $score->id)
            ->where('role', 'atasan_langsung')
            ->first();
        $approver = $participant->atasanLangsung()->with(['jabatan', 'user'])->first();

        if ($request->isMethod('post')) {
            abort_unless($isSelf, 403, 'Hanya pemilik penilaian yang dapat mengisi Kinerja Individu.');
            abort_unless($isWindowAllowed, 422, 'Pengisian Kinerja Individu hanya diizinkan pada tanggal 1–2 bulan berikutnya.');
            abort_if(in_array($score->status, ['approved', 'auto_signed', 'locked'], true), 422, 'Nilai Kinerja Individu sudah dikunci.');

            $v = $request->validate([
                'capaian_departemen' => 'required|numeric|min:1|max:100',
                'perawatan_aset' => 'required|numeric|min:1|max:100',
                'kebersihan_kerapihan' => 'required|numeric|min:1|max:100',
                'keterangan_capaian' => 'nullable|string|max:500',
                'keterangan_aset' => 'nullable|string|max:500',
                'keterangan_kebersihan' => 'nullable|string|max:500',
            ]);

            $capaian = (float) $v['capaian_departemen'];
            $aset = (float) $v['perawatan_aset'];
            $kebersihan = (float) $v['kebersihan_kerapihan'];

            $finalScore = round(($capaian * 0.70) + ($aset * 0.05) + ($kebersihan * 0.05), 2);

            $score->update([
                'capaian_departemen' => $capaian,
                'perawatan_aset' => $aset,
                'kebersihan_kerapihan' => $kebersihan,
                'keterangan_capaian' => $v['keterangan_capaian'] ?? null,
                'keterangan_aset' => $v['keterangan_aset'] ?? null,
                'keterangan_kebersihan' => $v['keterangan_kebersihan'] ?? null,
                'score' => $finalScore,
                'status' => 'submitted',
                'submitted_at' => KpiClock::now(),
                'submit_type' => 'manual',
            ]);

            return back()->with('success', 'Kinerja Individu berhasil disimpan.');
        }

        return inertia('Internal/Kpi/Individual', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'participant' => $participant->load(['karyawan.jabatan', 'karyawan.departemen', 'karyawan.penempatan', 'atasanLangsung.jabatan']),
            'score' => $score,
            'isWindowAllowed' => $isWindowAllowed,
            'windowState' => $windowState,
            'isOwner' => $isSelf,
            'canApprove' => (bool) ($user->karyawan_id && $participant->atasan_langsung_id === $user->karyawan_id),
            'approval' => $signature ? [
                'status' => $signature->source === 'automatic' ? 'auto_signed' : 'approved',
                'source' => $signature->source,
                'signed_at' => $signature->signed_at,
                'signature_url' => $signature->signature_path ? Storage::disk('public')->url($signature->signature_path) : null,
                'signer_name' => $approver?->nama,
                'signer_position' => $approver?->jabatan?->nama_jabatan,
            ] : null,
        ]);
    }

    private function individualParameterSnapshot(): array
    {
        return [
            ['key' => 'capaian_departemen', 'label' => 'Capaian Departemen', 'weight' => 0.70],
            ['key' => 'perawatan_aset', 'label' => 'Perawatan Aset Kerja Sesuai Bidang', 'weight' => 0.05],
            ['key' => 'kebersihan_kerapihan', 'label' => 'Kebersihan & Kerapihan Lingkungan Kerja', 'weight' => 0.05],
        ];
    }

    private function individualWindowState(KpiPeriod $period): string
    {
        $now = $this->kpiDebugNow();
        $start = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->addMonth()->startOfDay();
        $end = $start->copy()->addDay()->endOfDay();
        return $now->lt($start) ? 'upcoming' : ($now->lte($end) ? 'open' : 'closed');
    }

    private function kpiDebugNow(): Carbon
    {
        return KpiClock::now();
    }

    /**
     * Kinerja OPS Page & Management
     */
    public function ops(Request $request, KpiPeriod $period)
    {
        $participant = $this->getParticipantOrTarget($request, $period)
            ->load(['karyawan.user', 'karyawan.jabatan', 'atasanLangsung.user', 'atasanLangsung.jabatan']);
        $items = KpiOpsItem::where('kpi_participant_id', $participant->id)
            ->orderBy('urutan', 'asc')
            ->get();

        $isWindowAllowed = $this->isHardWindowActive($period);
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isSelf = $participant->karyawan_id === $user->karyawan_id;

        if ($request->isMethod('post')) {
            abort_unless($isSelf || $isSuperAdmin, 403, 'Anda tidak memiliki hak akses untuk mengisi/mengubah Kinerja OPS peserta ini.');
            abort_unless($isWindowAllowed, 422, 'Pengisian Kinerja OPS hanya diizinkan pada tanggal 1–2 bulan berikutnya.');
            abort_if($items->contains(fn ($item) => in_array($item->status, ['approved', 'locked', 'auto_signed', 'not_filled'], true)), 422, 'Kinerja OPS ini sudah dikirim atau dikunci.');

            $v = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|integer',
                'items.*.kpi_item' => 'nullable|string|max:255',
                'items.*.maintenance' => 'nullable|string|max:255',
                'items.*.target_unit' => 'nullable|numeric|min:0.0001',
                'items.*.tanda' => 'nullable|string|max:10',
                'items.*.frekuensi' => 'nullable|string|max:100',
                'items.*.hasil' => 'nullable|numeric|min:0',
                'items.*.aktivitas' => 'nullable|string|max:1000',
                'items.*.bukti_evidence' => 'nullable|file|image|max:5120',
                'items.*.existing_bukti' => 'nullable|string',
            ]);

            $totalTargetUnit = collect($v['items'])->sum(fn ($i) => (float) $i['target_unit']);
            abort_if($totalTargetUnit <= 0, 422, 'Total Target Unit harus lebih dari 0.');

            DB::transaction(function () use ($participant, $v, $totalTargetUnit, $request, $isSuperAdmin) {
                $existingIds = collect($v['items'])->pluck('id')->filter();
                KpiOpsItem::where('kpi_participant_id', $participant->id)
                    ->whereNotIn('id', $existingIds)
                    ->delete();

                foreach ($v['items'] as $index => $itemData) {
                    $existing = !empty($itemData['id'])
                        ? KpiOpsItem::where('kpi_participant_id', $participant->id)->find($itemData['id'])
                        : null;
                    if (!$isSuperAdmin && $existing) {
                        $itemData['kpi_item'] = $existing->kpi_item;
                        $itemData['maintenance'] = $existing->maintenance;
                        $itemData['target_unit'] = $existing->target_unit;
                        $itemData['tanda'] = $existing->tanda;
                        $itemData['frekuensi'] = $existing->frekuensi;
                    }
                    $target = (float) $itemData['target_unit'];
                    $hasil = isset($itemData['hasil']) && $itemData['hasil'] !== '' && $itemData['hasil'] !== null ? (float) $itemData['hasil'] : null;

                    $bebanTarget = round(($target / $totalTargetUnit) * 10, 4);

                    $nilaiItem = 0;
                    if ($hasil !== null && $target > 0) {
                        $nilaiItem = round(($hasil / $target) * $bebanTarget, 4);
                    }

                    $buktiPath = $itemData['existing_bukti'] ?? null;
                    if ($request->hasFile("items.{$index}.bukti_evidence")) {
                        $file = $request->file("items.{$index}.bukti_evidence");
                        $storedPath = $file->store('kpi/ops-evidence', 'public');
                        $buktiPath = $storedPath;
                    }

                    KpiOpsItem::updateOrCreate(
                        [
                            'id' => $itemData['id'] ?? null,
                            'kpi_participant_id' => $participant->id,
                        ],
                        [
                            'urutan' => $index + 1,
                            'kpi_item' => $itemData['kpi_item'],
                            'maintenance' => $itemData['maintenance'] ?? null,
                            'target_unit' => $target,
                            'tanda' => $itemData['tanda'] ?? '+',
                            'beban_target' => $bebanTarget,
                            'sumber_data_snapshot' => $participant->jabatan_snapshot,
                            'frekuensi' => $itemData['frekuensi'] ?? null,
                            'hasil' => $hasil,
                            'aktivitas' => $itemData['aktivitas'] ?? null,
                            'bukti_path' => $buktiPath,
                            'nilai_item' => $nilaiItem,
                            'target_bulanan' => $target,
                            'bulan' => $participant->period?->bulan,
                            'status' => ($hasil === null && empty($itemData['aktivitas']) && !$buktiPath) ? 'draft' : 'submitted',
                            'submit_type' => ($hasil === null && empty($itemData['aktivitas']) && !$buktiPath) ? null : 'manual',
                            'submitted_at' => ($hasil === null && empty($itemData['aktivitas']) && !$buktiPath) ? null : now('Asia/Jakarta'),
                        ]
                    );
                }
            });

            return back()->with('success', 'Kinerja OPS berhasil disimpan.');
        }

        $totalKops = round($items->sum('nilai_item'), 2);
        $totalBebanTarget = round($items->sum('beban_target'), 2);
        $signatures = KpiSignature::query()
            ->where('signable_type', KpiParticipant::class)
            ->where('signable_id', $participant->id)
            ->whereIn('role', ['employee', 'atasan_langsung'])
            ->get()
            ->keyBy('role')
            ->map(fn (KpiSignature $signature) => [
                'source' => $signature->source,
                'signed_at' => $signature->signed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'signature_url' => $signature->source === 'manual' && $signature->signature_path
                    ? Storage::disk('public')->url($signature->signature_path)
                    : null,
                'reason' => $signature->reason,
            ]);
        $hasSubmittedItems = $items->isNotEmpty() && $items->every(fn (KpiOpsItem $item) => in_array($item->status, ['submitted', 'approved', 'locked', 'auto_signed', 'not_filled'], true));

        return inertia('Internal/Kpi/Ops', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'participant' => $participant,
            'items' => $items,
            'totalKops' => $totalKops,
            'totalBebanTarget' => $totalBebanTarget,
            'isWindowAllowed' => $isWindowAllowed,
            'isOwner' => (int) $participant->karyawan_id === (int) $user->karyawan_id,
            'signatures' => $signatures,
            'canSignEmployee' => $hasSubmittedItems && (int) $participant->karyawan_id === (int) $user->karyawan_id && ! $signatures->has('employee'),
            'canSignSupervisor' => $hasSubmittedItems && (int) $participant->atasan_langsung_id === (int) $user->karyawan_id && ! $signatures->has('atasan_langsung'),
        ]);
    }

    // =========================================================================
    // BATCH 2 IMPLEMENTATION: MPA EVALUATOR ASSIGNMENT, ASSESSMENT, TAKEOVER, MONTHLY
    // =========================================================================

    /**
     * Assign MPA Primary Evaluator for a Period (Super Admin only, Deadline: End of perf month 23:59 Asia/Jakarta)
     */
    public function assignEvaluator(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        abort_unless($user->role()->value('nama_role') === 'super_admin', 403, 'Hanya Super Admin yang berwenang menetapkan Evaluator MPA.');

        // Deadline check: End of performance month 23:59:59 Asia/Jakarta
        $now = now('Asia/Jakarta');
        $assignmentDeadline = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->endOfMonth()->endOfDay();

        abort_unless($now->lte($assignmentDeadline), 422, 'Batas waktu penetapan evaluator MPA periode ini sudah lewat.');

        $v = $request->validate([
            'evaluator_id' => 'required|exists:users,id',
        ]);

        $evaluatorUser = User::with('karyawan.jabatan')->findOrFail($v['evaluator_id']);

        // Eligible check: Active user, active employee, participant of this period, NOT Dirut, NOT Direktur
        abort_unless($evaluatorUser->is_active, 422, 'User evaluator tidak aktif.');
        abort_unless($evaluatorUser->karyawan_id, 422, 'Evaluator harus terikat data karyawan.');

        $jabatanNama = mb_strtolower($evaluatorUser->karyawan?->jabatan?->nama_jabatan ?? '');
        abort_if($this->isExcludedKpiPosition($jabatanNama), 422, 'Dirut dan Direktur tidak dapat ditunjuk sebagai evaluator reguler.');

        $isParticipant = KpiParticipant::where('kpi_period_id', $period->id)
            ->where('karyawan_id', $evaluatorUser->karyawan_id)
            ->exists();

        abort_unless($isParticipant, 422, 'Evaluator harus terdaftar sebagai peserta KPI pada periode tersebut.');

        DB::transaction(function () use ($period, $evaluatorUser) {
            $period->update([
                'mpa_evaluator_id' => $evaluatorUser->id,
                'mpa_assigned_at' => now('Asia/Jakarta'),
            ]);

            // Synchronize mpa_evaluator_id across all participant monthly records for this period
            $participants = $period->participants()->pluck('id');
            foreach ($participants as $pId) {
                KpiMonthly::updateOrCreate(
                    ['kpi_participant_id' => $pId],
                    ['evaluator_id' => $evaluatorUser->id]
                );
            }
        });

        return back()->with('success', "Evaluator MPA untuk periode {$period->bulan}/{$period->tahun} berhasil ditetapkan.");
    }

    /**
     * MPA Assessment Page & Submission (Normal Evaluator window: Days 1-5)
     */
    public function mpa(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        // Check if assigned primary evaluator or HRD takeover
        $isAssignedEvaluator = $period->mpa_evaluator_id === $user->id;

        // Normal Evaluator Window Check: Days 1-5 of the month following performance month
        $now = now('Asia/Jakarta');
        $perfMonth = $period->bulan;
        $perfYear = $period->tahun;
        $windowStart = Carbon::create($perfYear, $perfMonth, 1, 0, 0, 0, 'Asia/Jakarta')->addMonth()->startOfDay(); // Day 1 00:00
        $windowEnd = Carbon::create($perfYear, $perfMonth, 5, 23, 59, 59, 'Asia/Jakarta')->addMonth(); // Day 5 23:59

        $isWindowOpen = $now->betweenIncluded($windowStart, $windowEnd);
        $isBlocked = ! $period->mpa_evaluator_id && $now->gt($windowStart);

        // Fetch target participant if provided in request, else default first eligible
        $targetKaryawanId = $request->input('karyawan_id');
        $participants = $period->participants()->with(['karyawan', 'karyawan.bawahan'])->get();

        // Eligible candidates for evaluator assignment dropdown (Super Admin view)
        $eligibleEvaluators = User::with(['karyawan.jabatan'])
            ->where('is_active', true)
            ->whereHas('karyawan', function ($q) {
                $q->where('status_keaktifan', 'aktif');
            })
            ->get()
            ->filter(function ($u) use ($period) {
                $jab = mb_strtolower($u->karyawan?->jabatan?->nama_jabatan ?? '');
                if ($this->isExcludedKpiPosition($jab)) return false;
                return KpiParticipant::where('kpi_period_id', $period->id)->where('karyawan_id', $u->karyawan_id)->exists();
            })
            ->values();

        // Selected participant for assessment
        $selectedParticipant = null;
        if ($targetKaryawanId) {
            $selectedParticipant = $participants->firstWhere('karyawan_id', (int) $targetKaryawanId);
        }
        if (! $selectedParticipant) {
            // Default to first participant that is not self
            $selectedParticipant = $participants->first(fn ($p) => $p->karyawan_id !== $user->karyawan_id) ?? $participants->first();
        }

        // Fetch existing monthly record for selected participant
        $monthly = null;
        if ($selectedParticipant) {
            $monthly = KpiMonthly::firstOrCreate(
                ['kpi_participant_id' => $selectedParticipant->id],
                ['evaluator_id' => $period->mpa_evaluator_id]
            );
        }

        // Leadership eligibility check: based on snapshot or subordinate count in snapshot
        $hasSubordinatesSnapshot = KpiParticipant::where('kpi_period_id', $period->id)
            ->where('atasan_langsung_id', $selectedParticipant?->karyawan_id)
            ->exists();

        // Self-evaluation exclusion check: Evaluator cannot rate self!
        $isRatingSelf = $selectedParticipant && $user->karyawan_id && $selectedParticipant->karyawan_id === $user->karyawan_id;

        // Post action: Save MPA Assessment
        if ($request->isMethod('post')) {
            // Authorization checks
            abort_if($isRatingSelf && ! $isHrdOrDirektur, 403, 'Evaluator tidak dapat menilai dirinya sendiri. Record ini diisi oleh HRD/Direktur.');
            abort_unless($isAssignedEvaluator || $isHrdOrDirektur, 403, 'Anda tidak berwenang memberikan penilaian MPA pada periode ini.');
            abort_unless($isWindowOpen || $isHrdOrDirektur, 422, 'Pengisian MPA oleh evaluator reguler hanya dibuka pada tanggal 1–5 bulan berikutnya.');
            abort_if($monthly && $monthly->status === 'completed' && (! $isHrdOrDirektur || ($monthly->takeover_by && $monthly->takeover_by !== $user->id)), 422, 'Penilaian MPA yang sudah completed tidak dapat diubah secara normal.');

            $v = $request->validate([
                'kinerja_operasional' => 'required|numeric|min:1|max:45',
                'sikap_kerja' => 'required|numeric|min:1|max:45',
                'team_work' => 'required|numeric|min:1|max:45',
                'inisiatif' => 'required|numeric|min:1|max:45',
                'kepemimpinan' => $hasSubordinatesSnapshot ? 'required|numeric|min:1|max:45' : 'nullable|numeric|min:1|max:45',
                'performance' => 'required|string|max:2000',
                'coaching' => 'required|string|max:2000',
                'takeover_reason' => (! $isAssignedEvaluator && $isHrdOrDirektur && ! $monthly->takeover_by) ? 'required|string|max:500' : 'nullable|string|max:500',
            ]);

            $ko = (float) $v['kinerja_operasional'];
            $sk = (float) $v['sikap_kerja'];
            $tw = (float) $v['team_work'];
            $in = (float) $v['inisiatif'];
            $kp = $hasSubordinatesSnapshot ? (float) ($v['kepemimpinan'] ?? 0) : 0;

            // Intermediate calculation (min 4 decimal places)
            // If no leadership: score = ((KO + Sikap + Team + Inisiatif) / 4) / 45 * 5
            // If has leadership: score = ((KO + Sikap + Team + Inisiatif + Kepemimpinan) / 5) / 45 * 5
            $sum = $ko + $sk + $tw + $in + ($hasSubordinatesSnapshot ? $kp : 0);
            $countComponents = $hasSubordinatesSnapshot ? 5 : 4;
            $avgRating = $sum / $countComponents; // out of 45
            $mpaScoreRaw = ($avgRating / 45) * 5;

            // Final component 2 decimal
            $mpaScoreFinal = round($mpaScoreRaw, 2);

            $updateData = [
                'kinerja_operasional' => $ko,
                'sikap_kerja' => $sk,
                'team_work' => $tw,
                'inisiatif' => $in,
                'kepemimpinan' => $hasSubordinatesSnapshot ? $kp : null,
                'mpa_score' => $mpaScoreFinal,
                'performance' => $v['performance'] ?? null,
                'coaching' => $v['coaching'] ?? null,
                'status' => 'completed',
            ];

            // If takeover by HRD
            if (! $isAssignedEvaluator && $isHrdOrDirektur && ! $monthly->takeover_by) {
                $updateData['takeover_by'] = $user->id;
                $updateData['takeover_at'] = now('Asia/Jakarta');
                $updateData['takeover_reason'] = $v['takeover_reason'] ?? 'HRD Takeover completing unfulfilled MPA assessment';
            }

            $monthly->update($updateData);

            return back()->with('success', 'Penilaian MPA berhasil disimpan.');
        }

        // Map participants summary for MPA list view
        $participantList = $participants->map(function ($p) use ($user) {
            $m = KpiMonthly::where('kpi_participant_id', $p->id)->first();
            return [
                'id' => $p->id,
                'karyawan_id' => $p->karyawan_id,
                'nama' => $p->karyawan?->nama ?? 'Karyawan',
                'jabatan' => $p->jabatan_snapshot,
                'departemen' => $p->departemen_snapshot,
                'status' => $m?->status ?? 'scheduled',
                'mpa_score' => $m?->mpa_score ?? 0,
                'is_self' => $p->karyawan_id === $user->karyawan_id,
                'is_takeover' => (bool) $m?->takeover_by,
            ];
        });

        return inertia('Internal/Kpi/MPA', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'eligibleEvaluators' => $eligibleEvaluators,
            'assignedEvaluatorId' => $period->mpa_evaluator_id,
            'assignedEvaluatorName' => User::find($period->mpa_evaluator_id)?->name ?? 'Belum Ditentukan',
            'isAssignedEvaluator' => $isAssignedEvaluator,
            'isWindowOpen' => $isWindowOpen,
            'isBlocked' => $isBlocked,
            'participants' => $participantList,
            'selectedParticipant' => $selectedParticipant,
            'hasSubordinatesSnapshot' => $hasSubordinatesSnapshot,
            'isRatingSelf' => $isRatingSelf,
            'monthly' => $monthly,
        ]);
    }

    /**
     * HRD Takeover MPA Assessment
     */
    public function takeoverMpa(Request $request, KpiPeriod $period, KpiMonthly $monthly)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        abort_unless($isHrdOrDirektur, 403, 'Hanya HRD/Direktur/Super Admin yang berwenang melakukan Takeover MPA.');

        $v = $request->validate([
            'takeover_reason' => 'required|string|max:500',
        ]);

        $monthly->update([
            'takeover_by' => $user->id,
            'takeover_at' => now('Asia/Jakarta'),
            'takeover_reason' => $v['takeover_reason'],
        ]);

        return back()->with('success', 'Takeover MPA berhasil diaktifkan. Anda dapat melengkapi penilaian.');
    }

    /**
     * Monthly HRD Page (Attendance Deduction & Reward/Punishment)
     */
    public function monthly(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        abort_unless($isHrdOrDirektur, 403, 'Hanya Direktur / HRD yang berwenang mengelola komponen Monthly Attendance & Reward/Punishment.');

        $monthlies = KpiMonthly::with(['participant.karyawan', 'attendanceAdjustments', 'rewardPunishments'])
            ->whereHas('participant', fn ($q) => $q->where('kpi_period_id', $period->id))
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'participant_id' => $m->kpi_participant_id,
                    'nama' => $m->participant?->karyawan?->nama ?? 'Karyawan',
                    'jabatan' => $m->participant?->jabatan_snapshot,
                    'departemen' => $m->participant?->departemen_snapshot,
                    'status' => $m->status,
                    'mpa_score' => $m->mpa_score,
                    'attendance_score' => $m->attendance_score,
                    'reward_punishment_score' => $m->reward_punishment_score,
                    'adjustments' => $m->attendanceAdjustments,
                    'rewards' => $m->rewardPunishments,
                    'is_complete' => in_array($m->status, ['completed', 'published']),
                ];
            });

        $now = now('Asia/Jakarta');
        $isPublishable = $monthlies->every(fn ($m) => $m['is_complete']);

        return inertia('Internal/Kpi/Monthly', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'monthlies' => $monthlies,
            'isPublishable' => $isPublishable,
        ]);
    }

    /**
     * Save Monthly Attendance Adjustments & Reward/Punishment (HRD)
     * Attendance score formula = (10 - total_deduction) * 0.5 (NO clamping!)
     */
    public function saveMonthlyHrd(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        abort_unless($isHrdOrDirektur, 403, 'Hanya HRD yang berwenang menyimpan komponen Monthly.');

        $v = $request->validate([
            'monthly_id' => 'required|exists:kpi_monthlies,id',
            'adjustments' => 'present|array',
            'adjustments.*.kode' => 'required|string|in:P1,DL,PC,LC,M',
            'adjustments.*.jumlah' => 'required|integer|min:0',
            'rewards' => 'present|array',
            'rewards.*.jenis' => 'required|string|in:major_award,minor_award,minor_demerit,major_demerit',
            'rewards.*.jumlah' => 'required|integer|min:0',
        ]);

        $monthly = KpiMonthly::findOrFail($v['monthly_id']);

        // Rates map per PRD: P1 = 0.5, DL = 0.3, PC = 0.3, LC = 0.3, M = 3
        $rateMap = [
            'P1' => 0.5,
            'DL' => 0.3,
            'PC' => 0.3,
            'LC' => 0.3,
            'M' => 3.0,
        ];

        // Reward rates: Major Award = +7, Minor Award = +3, Minor Demerit = -4, Major Demerit = -8
        $rewardMap = [
            'major_award' => 7.0,
            'minor_award' => 3.0,
            'minor_demerit' => -4.0,
            'major_demerit' => -8.0,
        ];

        DB::transaction(function () use ($monthly, $v, $rateMap, $rewardMap) {
            // Sync attendance adjustments
            $monthly->attendanceAdjustments()->delete();
            $totalDeduction = 0;

            foreach ($v['adjustments'] as $adj) {
                if ($adj['jumlah'] > 0) {
                    $rate = $rateMap[$adj['kode']] ?? 0;
                    $deduction = $rate * $adj['jumlah'];
                    $totalDeduction += $deduction;

                    $monthly->attendanceAdjustments()->create([
                        'kode' => $adj['kode'],
                        'jumlah' => $adj['jumlah'],
                        'nilai' => $deduction,
                    ]);
                }
            }

            // Attendance score formula: (10 - total_deduction) * 0.5 (NO clamping!)
            $attendanceScore = round((10 - $totalDeduction) * 0.5, 2);

            // Sync reward & punishment
            $monthly->rewardPunishments()->delete();
            $totalReward = 0;

            foreach ($v['rewards'] as $rew) {
                if ($rew['jumlah'] > 0) {
                    $val = ($rewardMap[$rew['jenis']] ?? 0) * $rew['jumlah'];
                    $totalReward += $val;

                    $monthly->rewardPunishments()->create([
                        'jenis' => $rew['jenis'],
                        'jumlah' => $rew['jumlah'],
                        'nilai' => $val,
                    ]);
                }
            }

            $now = now('Asia/Jakarta');
            $isLate = $now->day > 8; // Normal monthly deadline is day 8

            $monthlyUpdate = [
                'attendance_score' => $attendanceScore,
                'reward_punishment_score' => round($totalReward, 2),
                'completed_at' => $now,
                'completed_by' => auth()->id(),
            ];

            if ($isLate || $monthly->status === 'HRD_INCOMPLETE') {
                $monthlyUpdate['late_completed'] = true;
            }

            $monthly->update($monthlyUpdate);
        });

        return back()->with('success', 'Komponen Monthly HRD berhasil disimpan.');
    }

    /**
     * Publish Monthly for Whole Period (Enforce period-wide completeness, auto-sign HRD)
     */
    public function publishMonthly(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        abort_unless($isHrdOrDirektur, 403, 'Hanya Direktur / HRD yang berwenang mem-publish Monthly periode.');

        $participants = $period->participants()->get();
        if ($participants->isEmpty()) {
            abort(422, 'Tidak ada peserta KPI dalam periode ini.');
        }

        $monthlies = KpiMonthly::whereIn('kpi_participant_id', $participants->pluck('id'))->get();

        // Enforce period completeness check: ALL participants must have completed MPA (scores, performance, coaching) AND Attendance score defined
        $incompleteCount = 0;
        foreach ($participants as $p) {
            $m = $monthlies->firstWhere('kpi_participant_id', $p->id);
            if (! $m) {
                $incompleteCount++;
                continue;
            }

            $hasMpa = ! is_null($m->mpa_score) && ! empty($m->performance) && ! empty($m->coaching);
            $hasAttendance = ! is_null($m->attendance_score);

            if (! $hasMpa || ! $hasAttendance) {
                $incompleteCount++;
            }
        }

        abort_if($incompleteCount > 0, 422, "Gagal mem-publish! Terdapat {$incompleteCount} peserta yang belum lengkap penilaian MPA/Attendance-nya.");

        $now = now('Asia/Jakarta');
        $isLatePublish = $now->day > 8; // Publish deadline is day 8

        DB::transaction(function () use ($monthlies, $period, $user, $now, $isLatePublish) {
            $monthlies->each(function ($m) use ($user, $now, $isLatePublish) {
                $m->update([
                    'status' => 'published',
                    'published_at' => $now,
                    'published_by' => $user->id,
                    'late_published' => $isLatePublish,
                ]);

                // Automatically record HRD signature on publish
                KpiSignature::firstOrCreate(
                    [
                        'signable_type' => KpiMonthly::class,
                        'signable_id' => $m->id,
                        'role' => 'hrd_publish',
                    ],
                    [
                        'source' => 'manual',
                        'signed_for_user_id' => $m->participant?->karyawan_id,
                        'signed_by_user_id' => $user->id,
                        'signed_at' => $now,
                        'reason' => $isLatePublish ? 'Monthly Period Late Publish' : 'Monthly Period Publish',
                    ]
                );
            });

            $period->update([
                'status' => 'published',
                'published_at' => $now,
                'published_by' => $user->id,
            ]);

            // Late Publish Catch-Up Auto-Sign: if published on or after Day 9 23:59 Asia/Jakarta, perform immediate auto-sign
            $deadline = Carbon::create($period->tahun, $period->bulan, 9, 23, 59, 59, 'Asia/Jakarta')->addMonth();
            if ($now->gte($deadline)) {
                foreach ($period->participants as $participant) {
                    $m = $monthlies->firstWhere('kpi_participant_id', $participant->id);
                    if ($m) {
                        $expectedSigners = [
                            'employee' => $participant->karyawan?->user?->id,
                            'atasan_langsung' => $participant->atasanLangsung?->user?->id,
                            'atasan_kedua' => $participant->atasanKedua?->user?->id,
                        ];

                        foreach ($expectedSigners as $role => $userId) {
                            if ($userId || $role === 'employee' || $role === 'atasan_langsung') {
                                KpiSignature::firstOrCreate(
                                    [
                                        'signable_type' => KpiMonthly::class,
                                        'signable_id' => $m->id,
                                        'role' => $role,
                                    ],
                                    [
                                        'source' => 'automatic',
                                        'signed_for_user_id' => $userId,
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
            }
        });

        return back()->with('success', "Seluruh Monthly periode {$period->bulan}/{$period->tahun} berhasil dipublish.");
    }

    /**
     * Nilai Akhir / Final Score Page & Auto-Calculation
     */
    public function finalScore(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';

        $participants = $period->participants()->with(['karyawan', 'atasanLangsung'])->get();
        $now = now('Asia/Jakarta');
        $isLateFinalization = $now->day > 10;

        $finalScores = [];

        foreach ($participants as $p) {
            $ki = KpiIndividualScore::where('kpi_participant_id', $p->id)->first();
            $opsItems = KpiOpsItem::where('kpi_participant_id', $p->id)->get();
            $monthly = KpiMonthly::where('kpi_participant_id', $p->id)->first();

            $kiScore = (float) ($ki?->score ?? 0);
            $opsScore = (float) round($opsItems->sum('nilai_item'), 2);
            $mpaScore = (float) ($monthly?->mpa_score ?? 0);
            $attScore = (float) ($monthly?->attendance_score ?? 0);
            $rpScore = (float) ($monthly?->reward_punishment_score ?? 0);

            // Intermediate calculation (precise desimal)
            $totalScoreRaw = $kiScore + $opsScore + $mpaScore + $attScore + $rpScore;
            $totalScoreFinal = round($totalScoreRaw, 2); // NO clamping! (>100 stays >100)

            // Category evaluation based on PRD boundary:
            // >= 90: Sangat Baik
            // >= 80 & < 90: Baik
            // >= 70 & < 80: Cukup
            // >= 60 & < 70: Kurang
            // < 60: Sangat Kurang
            $kategori = match (true) {
                $totalScoreFinal >= 90.00 => 'Sangat Baik',
                $totalScoreFinal >= 80.00 => 'Baik',
                $totalScoreFinal >= 70.00 => 'Cukup',
                $totalScoreFinal >= 60.00 => 'Kurang',
                default => 'Sangat Kurang',
            };

            // Prerequisites check: KI submitted/approved, OPS submitted, Monthly published with MPA & Attendance
            $isKiReady = $ki && in_array($ki->status, ['submitted', 'approved', 'auto_submitted', 'not_filled']);
            $isOpsReady = $opsItems->isNotEmpty() && $opsItems->every(fn ($item) => in_array($item->status, ['submitted', 'auto_submitted', 'not_filled']));
            $isMonthlyReady = $monthly && $monthly->status === 'published' && ! is_null($monthly->mpa_score) && ! is_null($monthly->attendance_score);

            $isReady = $isKiReady && $isOpsReady && $isMonthlyReady;

            $record = KpiFinalScore::where('kpi_participant_id', $p->id)->first();

            if ($isReady) {
                if (! $record) {
                    $record = KpiFinalScore::create([
                        'kpi_participant_id' => $p->id,
                        'ki_score' => $kiScore,
                        'ops_score' => $opsScore,
                        'mpa_score' => $mpaScore,
                        'attendance_score' => $attScore,
                        'reward_punishment_score' => $rpScore,
                        'score' => $totalScoreFinal,
                        'kategori' => $kategori,
                        'status' => 'completed',
                        'late_finalization' => $isLateFinalization,
                        'calculated_at' => $now,
                    ]);
                } else {
                    $record->update([
                        'ki_score' => $kiScore,
                        'ops_score' => $opsScore,
                        'mpa_score' => $mpaScore,
                        'attendance_score' => $attScore,
                        'reward_punishment_score' => $rpScore,
                        'score' => $totalScoreFinal,
                        'kategori' => $kategori,
                        'status' => 'completed',
                        'calculated_at' => $now,
                    ]);
                }
            }

            // Signature status
            $supervisorSig = $record ? KpiSignature::where('signable_type', KpiFinalScore::class)
                ->where('signable_id', $record->id)
                ->where('role', 'atasan_langsung')
                ->first() : null;

            $finalScores[] = [
                'id' => $record?->id,
                'participant_id' => $p->id,
                'karyawan_id' => $p->karyawan_id,
                'nama' => $p->karyawan?->nama ?? 'Karyawan',
                'jabatan' => $p->jabatan_snapshot,
                'departemen' => $p->departemen_snapshot,
                'ki_score' => $kiScore,
                'ops_score' => $opsScore,
                'mpa_score' => $mpaScore,
                'attendance_score' => $attScore,
                'reward_punishment_score' => $rpScore,
                'score' => $record?->score ?? $totalScoreFinal,
                'kategori' => $record?->kategori ?? $kategori,
                'is_ready' => $isReady,
                'status' => $record?->status ?? 'pending',
                'late_finalization' => (bool) ($record?->late_finalization ?? $isLateFinalization),
                'signature' => $supervisorSig ? [
                    'source' => $supervisorSig->source,
                    'signed_at' => $supervisorSig->signed_at,
                    'signer_name' => User::find($supervisorSig->signed_by_user_id)?->name ?? 'System',
                    'signature_path' => $supervisorSig->signature_path,
                ] : null,
            ];
        }

        return inertia('Internal/Kpi/FinalScore', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'scores' => $finalScores,
        ]);
    }

    /**
     * Generic Component Manual Signature Endpoint
     */
    public function signComponent(Request $request)
    {
        $user = $request->user()->load('karyawan');
        $v = $request->validate([
            'signable_type' => 'required|string|in:kinerja_individu,kinerja_ops,monthly,final_score',
            'signable_id' => 'required|integer',
            'role' => 'required|string|in:employee,atasan_langsung,atasan_kedua,hrd',
        ]);

        $employee = $user->karyawan;
        abort_unless($employee && $employee->foto_tanda_tangan, 422, $request->input('signable_type') === 'kinerja_individu'
            ? 'Tanda tangan Atasan Langsung belum tersedia. Lengkapi foto tanda tangan di profil/master karyawan terlebih dahulu.'
            : 'Foto tanda tangan Anda belum diunggah. Lengkapi foto tanda tangan di profil/master karyawan terlebih dahulu.');

        $map = [
            'kinerja_individu' => KpiIndividualScore::class,
            'kinerja_ops' => KpiParticipant::class, // signed per participant ops set
            'monthly' => KpiMonthly::class,
            'final_score' => KpiFinalScore::class,
        ];

        $modelClass = $map[$v['signable_type']];
        $record = $modelClass::findOrFail($v['signable_id']);

        // Authorization check based on expected signer role in period snapshot
        if ($v['signable_type'] === 'kinerja_individu') {
            $participant = $record->participant;
            abort_unless($user->karyawan_id === $participant->atasan_langsung_id, 403, 'Hanya Atasan Langsung snapshot periode ini yang berwenang menandatangani Kinerja Individu.');
            abort_if(in_array($record->status, ['approved', 'auto_signed', 'locked'], true), 422, 'Kinerja Individu sudah dikunci.');
        } elseif ($v['signable_type'] === 'kinerja_ops') {
            $participant = $record;
            if ($v['role'] === 'employee') {
                abort_unless($user->karyawan_id === $participant->karyawan_id, 403, 'Anda bukan karyawan bersangkutan.');
            } elseif ($v['role'] === 'atasan_langsung') {
                abort_unless($user->karyawan_id === $participant->atasan_langsung_id, 403, 'Anda bukan Atasan Langsung karyawan.');
            }
        } elseif ($v['signable_type'] === 'monthly') {
            $participant = $record->participant;
            if ($v['role'] === 'employee') {
                abort_unless($user->karyawan_id === $participant->karyawan_id, 403, 'Anda bukan karyawan bersangkutan.');
            } elseif ($v['role'] === 'atasan_langsung') {
                abort_unless($user->karyawan_id === $participant->atasan_langsung_id, 403, 'Anda bukan Atasan Langsung karyawan.');
            } elseif ($v['role'] === 'atasan_kedua') {
                abort_unless($participant->atasan_kedua_id && $user->karyawan_id === $participant->atasan_kedua_id, 403, 'Anda bukan Atasan Kedua karyawan.');
            }
        } elseif ($v['signable_type'] === 'final_score') {
            $participant = $record->participant;
            abort_unless($user->karyawan_id === $participant->atasan_langsung_id, 403, 'Hanya Atasan Langsung snapshot yang berwenang menandatangani Nilai Akhir.');
        }

        $signaturePath = $employee->foto_tanda_tangan;
        if ($v['signable_type'] === 'kinerja_individu') {
            $source = preg_replace('#^/?storage/#', '', trim((string) $employee->foto_tanda_tangan));
            abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Tanda tangan Atasan Langsung belum tersedia.');
            $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = 'kpi/individual-signatures/'.$record->id.'_'.KpiClock::now()->format('YmdHisv').'.'.$ext;
            abort_unless(Storage::disk('public')->copy($source, $signaturePath), 422, 'Snapshot tanda tangan gagal disimpan.');
        }

        // Idempotent signature creation/update
        KpiSignature::updateOrCreate(
            [
                'signable_type' => $modelClass,
                'signable_id' => $record->id,
                'role' => $v['role'],
            ],
            [
                'source' => 'manual',
                'signed_for_user_id' => $v['signable_type'] === 'kinerja_individu' ? $participant->karyawan?->user?->id : $user->id,
                'signed_by_user_id' => $user->id,
                'signature_path' => $signaturePath,
                'signed_at' => $v['signable_type'] === 'kinerja_individu' ? KpiClock::now() : now('Asia/Jakarta'),
                'reason' => 'Manual Signature',
            ]
        );

        if ($v['signable_type'] === 'kinerja_individu') {
            $record->update(['status' => 'approved']);
        }

        return back()->with('success', 'Tanda tangan berhasil disimpan.');
    }

    /**
     * Administrative Correction Endpoint (Super Admin SOP)
     */
    public function correctComponent(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role()->value('nama_role') === 'super_admin', 403, 'Hanya Super Admin yang berwenang melakukan Koreksi Administratif.');

        $v = $request->validate([
            'period_id' => 'required|exists:kpi_periods,id',
            'participant_id' => 'required|exists:kpi_participants,id',
            'component' => 'required|string|in:kinerja_individu,kinerja_ops,mpa,monthly_hrd',
            'reason' => 'required|string|max:1000',
            'payload' => 'required|array',
        ]);

        $period = KpiPeriod::findOrFail($v['period_id']);
        $participant = KpiParticipant::findOrFail($v['participant_id']);

        DB::transaction(function () use ($period, $participant, $v, $user) {
            $beforeData = [];
            $afterData = [];

            if ($v['component'] === 'kinerja_individu') {
                $ki = KpiIndividualScore::firstOrCreate(['kpi_participant_id' => $participant->id]);
                $beforeData = $ki->toArray();

                $capaian = (float) ($v['payload']['capaian_departemen'] ?? $ki->capaian_departemen ?? 0);
                $aset = (float) ($v['payload']['perawatan_aset'] ?? $ki->perawatan_aset ?? 0);
                $kebersihan = (float) ($v['payload']['kebersihan_kerapihan'] ?? $ki->kebersihan_kerapihan ?? 0);

                $score = round(($capaian * 0.70) + ($aset * 0.05) + ($kebersihan * 0.05), 2);

                $ki->update([
                    'capaian_departemen' => $capaian,
                    'perawatan_aset' => $aset,
                    'kebersihan_kerapihan' => $kebersihan,
                    'score' => $score,
                    'status' => 'submitted',
                ]);
                $afterData = $ki->fresh()->toArray();
            } elseif ($v['component'] === 'kinerja_ops') {
                $opsItems = KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
                $beforeData = $opsItems->toArray();

                if (isset($v['payload']['items']) && is_array($v['payload']['items'])) {
                    foreach ($v['payload']['items'] as $itemData) {
                        if (isset($itemData['id'])) {
                            $item = KpiOpsItem::where('kpi_participant_id', $participant->id)->find($itemData['id']);
                            if ($item) {
                                $item->update([
                                    'hasil' => $itemData['hasil'] ?? $item->hasil,
                                    'aktivitas' => $itemData['aktivitas'] ?? $item->aktivitas,
                                    'nilai_item' => isset($itemData['nilai_item']) ? (float)$itemData['nilai_item'] : $item->nilai_item,
                                    'status' => 'submitted',
                                ]);
                            }
                        }
                    }
                }
                $afterData = KpiOpsItem::where('kpi_participant_id', $participant->id)->get()->toArray();
            } elseif ($v['component'] === 'mpa' || $v['component'] === 'monthly_hrd') {
                $monthly = KpiMonthly::firstOrCreate(['kpi_participant_id' => $participant->id]);
                $beforeData = $monthly->toArray();

                if ($v['component'] === 'mpa') {
                    $ko = (float) ($v['payload']['kinerja_operasional'] ?? $monthly->kinerja_operasional ?? 0);
                    $sk = (float) ($v['payload']['sikap_kerja'] ?? $monthly->sikap_kerja ?? 0);
                    $tw = (float) ($v['payload']['team_work'] ?? $monthly->team_work ?? 0);
                    $in = (float) ($v['payload']['inisiatif'] ?? $monthly->inisiatif ?? 0);

                    $hasSubordinates = KpiParticipant::where('kpi_period_id', $period->id)
                        ->where('atasan_langsung_id', $participant->karyawan_id)
                        ->exists();
                    $kp = $hasSubordinates ? (float) ($v['payload']['kepemimpinan'] ?? $monthly->kepemimpinan ?? 0) : 0;

                    $sum = $ko + $sk + $tw + $in + ($hasSubordinates ? $kp : 0);
                    $cnt = $hasSubordinates ? 5 : 4;
                    $mpaScore = round((($sum / $cnt) / 45) * 5, 2);

                    $monthly->update([
                        'kinerja_operasional' => $ko,
                        'sikap_kerja' => $sk,
                        'team_work' => $tw,
                        'inisiatif' => $in,
                        'kepemimpinan' => $hasSubordinates ? $kp : null,
                        'mpa_score' => $mpaScore,
                        'performance' => $v['payload']['performance'] ?? $monthly->performance,
                        'coaching' => $v['payload']['coaching'] ?? $monthly->coaching,
                    ]);
                }
                $afterData = $monthly->fresh()->toArray();
            }

            // Get last revision number
            $lastRev = KpiAudit::where('kpi_period_id', $period->id)->max('revision') ?? 0;

            KpiAudit::create([
                'kpi_period_id' => $period->id,
                'actor_id' => $user->id,
                'action' => "Koreksi Administratif {$v['component']} Peserta #{$participant->id}",
                'reason' => $v['reason'],
                'before' => $beforeData,
                'after' => $afterData,
                'revision' => $lastRev + 1,
            ]);

            // Recalculate Final Score dependent value
            $kiScore = (float) (KpiIndividualScore::where('kpi_participant_id', $participant->id)->value('score') ?? 0);
            $opsItems = KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
            $opsScore = (float) round($opsItems->sum('nilai_item'), 2);
            $m = KpiMonthly::where('kpi_participant_id', $participant->id)->first();
            $mpaScore = (float) ($m?->mpa_score ?? 0);
            $attScore = (float) ($m?->attendance_score ?? 0);
            $rpScore = (float) ($m?->reward_punishment_score ?? 0);

            $totalScoreFinal = round($kiScore + $opsScore + $mpaScore + $attScore + $rpScore, 2);
            $kategori = match (true) {
                $totalScoreFinal >= 90.00 => 'Sangat Baik',
                $totalScoreFinal >= 80.00 => 'Baik',
                $totalScoreFinal >= 70.00 => 'Cukup',
                $totalScoreFinal >= 60.00 => 'Kurang',
                default => 'Sangat Kurang',
            };

            KpiFinalScore::updateOrCreate(
                ['kpi_participant_id' => $participant->id],
                [
                    'ki_score' => $kiScore,
                    'ops_score' => $opsScore,
                    'mpa_score' => $mpaScore,
                    'attendance_score' => $attScore,
                    'reward_punishment_score' => $rpScore,
                    'score' => $totalScoreFinal,
                    'kategori' => $kategori,
                    'status' => 'completed',
                    'late_finalization' => true,
                ]
            );
        });

        return back()->with('success', 'Koreksi Administratif berhasil diterapkan dan Nilai Akhir telah dihitung ulang.');
    }

    /**
     * In-App Notification / Action Required Indicator API Endpoint
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $karyawanId = $user->karyawan_id;
        $roleName = $user->role()->value('nama_role');
        $isHrdOrDirektur = in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']) || $roleName === 'super_admin';

        $activePeriod = KpiPeriod::latest('id')->first();
        if (! $activePeriod) {
            return response()->json(['total' => 0, 'items' => []]);
        }

        $items = [];

        // Daily approval needed (for supervisors)
        if ($karyawanId) {
            $pendingDaily = KpiDailyReport::where('approver_id', $karyawanId)
                ->where('status', 'waiting_approval')
                ->count();
            if ($pendingDaily > 0) {
                $items[] = [
                    'type' => 'daily_approval',
                    'message' => "Terdapat {$pendingDaily} Daily Report menunggu persetujuan Anda.",
                    'url' => route('dashboard.kpi.daily'),
                ];
            }
        }

        // KI Signature pending for Direct Supervisor snapshot
        if ($karyawanId) {
            $pendingKiSig = KpiIndividualScore::whereHas('participant', fn ($q) => $q->where('kpi_period_id', $activePeriod->id)->where('atasan_langsung_id', $karyawanId))
                ->whereIn('status', ['submitted', 'approved', 'auto_submitted'])
                ->whereDoesntHave('signatures', fn ($q) => $q->where('role', 'atasan_langsung'))
                ->count();
            if ($pendingKiSig > 0) {
                $items[] = [
                    'type' => 'ki_signature',
                    'message' => "Terdapat {$pendingKiSig} Kinerja Individu menunggu tanda tangan Anda.",
                    'url' => route('dashboard.kpi.individual', $activePeriod->id),
                ];
            }
        }

        // K-OPS Employee Signature pending
        if ($karyawanId) {
            $pendingOpsEmp = KpiParticipant::where('kpi_period_id', $activePeriod->id)
                ->where('karyawan_id', $karyawanId)
                ->whereHas('opsItems', fn ($q) => $q->whereIn('status', ['submitted', 'auto_submitted']))
                ->whereDoesntHave('signatures', fn ($q) => $q->where('signable_type', KpiParticipant::class)->where('role', 'employee'))
                ->count();
            if ($pendingOpsEmp > 0) {
                $items[] = [
                    'type' => 'ops_employee_signature',
                    'message' => "Kinerja OPS Anda belum ditandatangani.",
                    'url' => route('dashboard.kpi.ops', $activePeriod->id),
                ];
            }
        }

        // K-OPS Supervisor Signature pending
        if ($karyawanId) {
            $pendingOpsSup = KpiParticipant::where('kpi_period_id', $activePeriod->id)
                ->where('atasan_langsung_id', $karyawanId)
                ->whereHas('opsItems', fn ($q) => $q->whereIn('status', ['submitted', 'auto_submitted']))
                ->whereDoesntHave('signatures', fn ($q) => $q->where('signable_type', KpiParticipant::class)->where('role', 'atasan_langsung'))
                ->count();
            if ($pendingOpsSup > 0) {
                $items[] = [
                    'type' => 'ops_supervisor_signature',
                    'message' => "Terdapat {$pendingOpsSup} Kinerja OPS bawahan menunggu tanda tangan Anda.",
                    'url' => route('dashboard.kpi.ops', $activePeriod->id),
                ];
            }
        }

        // MPA Assignment missing (for Super Admin / HRD)
        if ($isHrdOrDirektur && ! $activePeriod->mpa_evaluator_id) {
            $items[] = [
                'type' => 'mpa_assignment_missing',
                'message' => "Evaluator MPA untuk periode {$activePeriod->bulan}/{$activePeriod->tahun} belum ditetapkan.",
                'url' => route('dashboard.kpi.mpa', $activePeriod->id),
            ];
        }

        // MPA action required (for assigned evaluator or HRD takeover)
        if ($activePeriod->mpa_evaluator_id === $user->id) {
            $pendingMpa = KpiMonthly::whereHas('participant', fn ($q) => $q->where('kpi_period_id', $activePeriod->id))
                ->where('status', 'scheduled')
                ->count();
            if ($pendingMpa > 0) {
                $items[] = [
                    'type' => 'mpa_assessment',
                    'message' => "Anda memiliki {$pendingMpa} penilaian MPA yang perlu diselesaikan.",
                    'url' => route('dashboard.kpi.mpa', $activePeriod->id),
                ];
            }
        }

        // MPA Blocked / Takeover required (for HRD/Direktur)
        if ($isHrdOrDirektur && $activePeriod->status === 'blocked') {
            $items[] = [
                'type' => 'mpa_blocked',
                'message' => "Penilaian MPA periode {$activePeriod->bulan}/{$activePeriod->tahun} terblokir (HRD Takeover diperlukan).",
                'url' => route('dashboard.kpi.mpa', $activePeriod->id),
            ];
        }

        // Monthly HRD incomplete (for HRD/Direktur)
        if ($isHrdOrDirektur) {
            $hrdIncomplete = KpiMonthly::whereHas('participant', fn ($q) => $q->where('kpi_period_id', $activePeriod->id))
                ->where('status', 'HRD_INCOMPLETE')
                ->count();
            if ($hrdIncomplete > 0) {
                $items[] = [
                    'type' => 'hrd_incomplete',
                    'message' => "Terdapat {$hrdIncomplete} Monthly HRD berstatus HRD_INCOMPLETE yang belum selesai.",
                    'url' => route('dashboard.kpi.monthly', $activePeriod->id),
                ];
            }
        }

        // Monthly Signature pending (Employee, Atasan Langsung, Atasan Kedua)
        if ($karyawanId) {
            $pendingMonthlySig = KpiMonthly::whereHas('participant', fn ($q) => $q->where('kpi_period_id', $activePeriod->id)->where(function ($sub) use ($karyawanId) {
                $sub->where('karyawan_id', $karyawanId)
                    ->orWhere('atasan_langsung_id', $karyawanId)
                    ->orWhere('atasan_kedua_id', $karyawanId);
            }))
                ->where('status', 'published')
                ->count();
            if ($pendingMonthlySig > 0) {
                $items[] = [
                    'type' => 'monthly_signature',
                    'message' => "Terdapat {$pendingMonthlySig} dokumen Monthly yang memerlukan persetujuan/tanda tangan.",
                    'url' => route('dashboard.kpi.monthly', $activePeriod->id),
                ];
            }
        }

        // SP1 Action Needed Indicator for Supervisors
        if ($karyawanId) {
            $subordinateIds = KpiParticipant::where('kpi_period_id', $activePeriod->id)
                ->where('atasan_langsung_id', $karyawanId)
                ->pluck('karyawan_id');
            $sp1Count = 0;
            $nowDate = now('Asia/Jakarta');
            foreach ($subordinateIds as $subId) {
                if ($this->calculateMissingDailyCount($subId, $nowDate) >= 3) {
                    $sp1Count++;
                }
            }
            if ($sp1Count > 0) {
                $items[] = [
                    'type' => 'sp1_indicator',
                    'message' => "Terdapat {$sp1Count} bawahan yang memenuhi kriteria Perlu Tindak Lanjut SP1 (>=3 Missing Daily).",
                    'url' => route('dashboard.kpi.employees', $activePeriod->id),
                ];
            }
        }

        return response()->json([
            'total' => count($items),
            'items' => $items,
        ]);
    }

    /**
     * Helper to calculate missing daily report count for an employee in current month
     */
    private function calculateMissingDailyCount(int $karyawanId, Carbon $currentDate): int
    {
        $startOfMonth = $currentDate->copy()->startOfMonth();
        $yesterday = $currentDate->copy()->subDay()->endOfDay();

        if ($startOfMonth->gt($yesterday)) {
            return 0;
        }

        $attendances = Absensi::where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal_absensi', [$startOfMonth->toDateString(), $yesterday->toDateString()])
            ->where('status_kehadiran', 'H')
            ->pluck('tanggal_absensi')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        if ($attendances->isEmpty()) {
            return 0;
        }

        $submittedReports = KpiDailyReport::where('karyawan_id', $karyawanId)
            ->whereIn('tanggal', $attendances)
            ->whereIn('status', ['waiting_approval', 'approved'])
            ->pluck('tanggal')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        return $attendances->diff($submittedReports)->count();
    }

    /**
     * Helper to get target participant from route or logged in user
     */
    private function getParticipantOrTarget(Request $request, KpiPeriod $period): KpiParticipant
    {
        $user = $request->user();
        $targetKaryawanId = $request->input('karyawan_id');
        $isSuper = $user->role()->value('nama_role') === 'super_admin';

        if ($targetKaryawanId) {
            if ($isSuper || ($user->karyawan_id && in_array((int)$targetKaryawanId, $this->getAllSubordinateKaryawanIds($user->karyawan_id, $period->id)))) {
                return KpiParticipant::where('kpi_period_id', $period->id)
                    ->where('karyawan_id', $targetKaryawanId)
                    ->firstOrFail();
            }
        }

        return KpiParticipant::where('kpi_period_id', $period->id)
            ->where('karyawan_id', $user->karyawan_id)
            ->firstOrFail();
    }

    /**
     * Helper for Hard Window check: Only days 1-2 of the month following performance month
     * Timezone: Asia/Jakarta
     */
    private function isHardWindowActive(KpiPeriod $period): bool
    {
        $now = $this->kpiDebugNow();

        $perfMonth = $period->bulan;
        $perfYear = $period->tahun;

        $windowStart = Carbon::create($perfYear, $perfMonth, 1, 0, 0, 0, 'Asia/Jakarta')->addMonth()->startOfDay();
        $windowEnd = Carbon::create($perfYear, $perfMonth, 1, 0, 0, 0, 'Asia/Jakarta')->addMonth()->addDay()->endOfDay();

        return $now->betweenIncluded($windowStart, $windowEnd);
    }

    /**
     * Recursive helper to fetch all subordinate karyawan IDs in hierarchy using period participant snapshot
     */
    private function getAllSubordinateKaryawanIds(int $karyawanId, int $periodId): array
    {
        $subordinates = KpiParticipant::where('kpi_period_id', $periodId)
            ->where('atasan_langsung_id', $karyawanId)
            ->pluck('karyawan_id')
            ->toArray();
        $all = $subordinates;

        foreach ($subordinates as $subKaryawanId) {
            $all = array_merge($all, $this->getAllSubordinateKaryawanIds($subKaryawanId, $periodId));
        }

        return array_unique($all);
    }

    /**
     * Resolve the live employee hierarchy for KPI-Karyawan monitoring.
     * A visited set prevents malformed circular reporting lines from looping forever.
     */
    private function getCurrentSubordinateKaryawanIds(int $managerId): array
    {
        $employees = Karyawan::query()
            ->where('status_keaktifan', 'aktif')
            ->get(['id', 'atasan_langsung_id']);
        $children = $employees->groupBy(fn (Karyawan $employee) => (string) $employee->atasan_langsung_id);
        $frontier = [$managerId];
        $visited = [$managerId => true];
        $result = [];

        while ($frontier) {
            $next = [];
            foreach ($frontier as $parentId) {
                foreach ($children->get((string) $parentId, collect()) as $employee) {
                    if (isset($visited[$employee->id])) {
                        continue;
                    }
                    $visited[$employee->id] = true;
                    $result[] = $employee->id;
                    $next[] = $employee->id;
                }
            }
            $frontier = $next;
        }

        return $result;
    }

    /**
     * Dirut may be stored as "Dirut" or "Direktur Utama" in master Jabatan.
     * Both represent the executive exclusion in the KPI PRD.
     */
    private function isExcludedKpiPosition(?string $position): bool
    {
        $normalized = mb_strtolower(trim((string) $position));

        return in_array($normalized, ['dirut', 'direktur', 'direktur utama'], true);
    }

    /**
     * Helper for standard user payload
     */
    private function userPayload(Request $request): array
    {
        $u = $request->user()->load(['karyawan.jabatan', 'karyawan.departemen', 'role']);

        return [
            'id' => $u->id,
            'name' => $u->karyawan?->nama ?? $u->name,
            'roleName' => $u->role?->nama_role ?? 'user',
            'position' => $u->karyawan?->jabatan?->nama_jabatan ?? 'Karyawan',
            'departemen' => $u->karyawan?->departemen?->nama_departemen ?? '-',
            'karyawan_id' => $u->karyawan_id,
        ];
    }
}
