<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Exports\Kpi\EmployeeKpiExport;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\KpiDailyActivity;
use App\Models\KpiDailyReport;
use App\Models\KpiFinalScore;
use App\Models\KpiIndividualScore;
use App\Models\KpiMonthly;
use App\Models\MpaEvaluatorAssignment;
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
use Maatwebsite\Excel\Facades\Excel;

class KpiController extends Controller
{
    /**
     * Dashboard KPI (Semua Periode)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $roleName = $user->role()->value('nama_role');
        $position = mb_strtolower(trim((string) ($user->karyawan?->jabatan?->nama_jabatan ?? '')));
        $canManageActions = $roleName === 'super_admin';
        $canViewMonitoring = $canManageActions
            || in_array($roleName, ['admin'], true)
            || $roleName === 'user'
            || in_array($position, ['hrd', 'direktur', 'dirut', 'direktur utama'], true);
        abort_unless($canViewMonitoring, 403, 'Monitoring Periode KPI hanya dapat diakses oleh HRD/Admin atau Super Admin.');

        $periods = KpiPeriod::query()
            ->with([
                'evaluator.karyawan',
                'participants.karyawan.user',
                'participants.individualScore',
                'participants.opsItems',
                'participants.monthly.signatures',
            ])
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get();

        $activePeriod = $periods->first();
        $selectedPeriodId = $request->integer('period_id');
        $selectedPeriod = $selectedPeriodId
            ? $periods->firstWhere('id', $selectedPeriodId)
            : $activePeriod;

        abort_if($selectedPeriodId && ! $selectedPeriod, 404, 'Periode KPI tidak ditemukan.');

        $periodSummaries = $periods
            ->map(fn (KpiPeriod $period) => $this->periodMonitoringPayload($period, false))
            ->values();

        return inertia('Internal/Kpi/Index', [
            'user' => $this->userPayload($request),
            'periods' => $periodSummaries,
            'activePeriodId' => $activePeriod?->id,
            'selectedPeriod' => $selectedPeriod
                ? $this->periodMonitoringPayload($selectedPeriod, true, $canManageActions)
                : null,
            'canManageActions' => $canManageActions,
        ]);
    }

    /**
     * Export one participant and one explicitly selected KPI period.
     * This is intentionally stricter than the read-only monitoring page.
     */
    public function exportEmployeeKpi(Request $request, KpiPeriod $period, Karyawan $employee)
    {
        abort_unless($request->user()->role()->value('nama_role') === 'super_admin', 403, 'Export KPI hanya dapat dilakukan oleh Super Admin.');
        abort_unless($period->participants()->where('karyawan_id', $employee->id)->exists(), 404, 'Karyawan bukan participant pada periode ini.');

        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($employee->nama)) ?: 'Karyawan';
        $filename = sprintf('KPI_%s_%s_%s.xlsx', $safeName, $months[(int) $period->bulan - 1] ?? $period->bulan, $period->tahun);

        return Excel::download(new EmployeeKpiExport($period, $employee->id), $filename);
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

        $participants = $query->with([
            'individualScore',
            'opsItems',
            'monthly.signatures',
        ])->get()
            ->reject(fn ($p) => $this->isExcludedKpiPosition($p->karyawan?->jabatan?->nama_jabatan))
            ->values();

        $employeeIds = $participants->pluck('karyawan_id')->filter()->unique()->values();
        $performanceMonth = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $performanceStart = $performanceMonth->toDateString();
        $performanceEnd = $performanceMonth->copy()->endOfMonth()->toDateString();
        $dailyReportsByEmployee = KpiDailyReport::query()
            ->with('approver.karyawan')
            ->whereIn('karyawan_id', $employeeIds)
            ->whereBetween('tanggal', [$performanceStart, $performanceEnd])
            ->get(['id', 'karyawan_id', 'tanggal', 'status', 'atasan_snapshot_id', 'approval_source'])
            ->groupBy('karyawan_id');
        $attendanceByEmployee = Absensi::query()
            ->whereIn('karyawan_id', $employeeIds)
            ->whereBetween('tanggal_absensi', [$performanceStart, $performanceEnd])
            ->where('status_kehadiran', 'H')
            ->get(['karyawan_id', 'tanggal_absensi'])
            ->groupBy('karyawan_id');

        $participants = $participants->map(function (KpiParticipant $p) use ($user, $isSuper, $dailyReportsByEmployee, $attendanceByEmployee, $performanceMonth) {
            $ki = $p->individualScore;
            $opsItems = $p->opsItems;
            $monthly = $p->monthly;
            $dailyReports = $dailyReportsByEmployee->get($p->karyawan_id, collect());
            $dailyStatuses = $dailyReports->pluck('status');
            $pendingDailyCount = $dailyStatuses->filter(fn ($status) => $status === 'waiting_approval')->count();
            $approvedDailyCount = $dailyStatuses->filter(fn ($status) => $status === 'approved')->count();
            $takeoverDailyCount = $dailyReports->filter(fn ($report) => $report->status === 'approved' && $this->effectiveDailyApprovalSource($report) === 'super_admin_takeover')->count();
            $approvedNormalDailyCount = max(0, $approvedDailyCount - $takeoverDailyCount);
            $notFilledDailyCount = $dailyStatuses->filter(fn ($status) => in_array($status, ['not_filled', 'auto_submitted'], true))->count();
            $todayDate = KpiClock::today()->toDateString();
            $yesterdayDate = KpiClock::today()->subDay()->toDateString();
            $draftDailyReports = $dailyReports->filter(fn ($report) => $report->status === 'draft');
            $draftDailyCount = $draftDailyReports->filter(fn ($report) => in_array($report->tanggal->toDateString(), [$todayDate, $yesterdayDate], true))->count();
            $expiredDraftDailyCount = $draftDailyReports->filter(fn ($report) => $report->tanggal->toDateString() < $yesterdayDate)->count();
            $dailyCount = $dailyStatuses->count();
            $pendingDailyDates = $dailyReports
                ->filter(fn ($report) => $report->status === 'waiting_approval')
                ->sortBy('tanggal')
                ->map(fn ($report) => $report->tanggal->format('d/m'))
                ->values();

            $attendances = $attendanceByEmployee->get($p->karyawan_id, collect());
            $attendances = $attendances->filter(fn ($attendance) => $attendance->tanggal_absensi->toDateString() <= $todayDate);
            $reportedDates = $dailyReports->map(fn ($report) => $report->tanggal->toDateString());
            $missingAttendanceDates = $attendances->pluck('tanggal_absensi')
                ->map(fn ($date) => $date->toDateString())
                ->diff($reportedDates);
            $needsInputAttendanceCount = $missingAttendanceDates->filter(fn ($date) => in_array($date, [$todayDate, $yesterdayDate], true))->count();
            $expiredMissingDailyCount = $missingAttendanceDates->filter(fn ($date) => $date < $yesterdayDate)->count();
            $missingDailyCount = $needsInputAttendanceCount;
            $needsInputDailyCount = $needsInputAttendanceCount + $draftDailyCount;
            $notFilledDailyCount += $expiredMissingDailyCount + $expiredDraftDailyCount;

            $dailyProgress = match (true) {
                $pendingDailyCount > 0 => [
                    'status' => 'waiting_approval',
                    'label' => 'Approval '.$pendingDailyCount,
                    'detail' => 'Menunggu persetujuan: '.($pendingDailyDates->implode(', ') ?: "{$pendingDailyCount} laporan"),
                    'requires_action' => true,
                ],
                $needsInputDailyCount > 0 => [
                    'status' => 'needs_input',
                    'label' => 'Perlu Isi '.$needsInputDailyCount,
                    'detail' => 'Masih ada Daily Report yang belum diisi atau belum diajukan.',
                    'requires_action' => true,
                ],
                $notFilledDailyCount > 0 => [
                    'status' => 'not_filled',
                    'label' => 'Tidak Isi '.$notFilledDailyCount,
                    'detail' => 'Daily Report dikirim sebagai Tidak Mengisi.',
                    'requires_action' => false,
                ],
                $dailyCount > 0 && $approvedDailyCount === $dailyCount => [
                    'status' => 'approved',
                    'label' => 'Selesai',
                    'detail' => 'Semua laporan Daily sudah diselesaikan.',
                    'requires_action' => false,
                ],
                default => [
                    'status' => 'none',
                    'label' => 'Belum Diisi',
                    'detail' => 'Belum ada laporan Daily yang terselesaikan.',
                    'requires_action' => false,
                ],
            };
            $dailyProgress['count'] = $dailyCount;
            $dailyProgress['pending_count'] = $pendingDailyCount;
            $dailyProgress['needs_input_count'] = $needsInputDailyCount;
            $dailyProgress['not_filled_count'] = $notFilledDailyCount;
            $dailyProgress['takeover_count'] = $takeoverDailyCount;
            $dailyProgress['approved_count'] = $approvedNormalDailyCount;

            $individualStatus = $ki?->status;
            $individualProgress = match ($individualStatus) {
                'approved', 'auto_signed', 'locked' => ['status' => 'approved', 'label' => 'Selesai', 'requires_action' => false],
                'submitted' => ['status' => 'waiting_approval', 'label' => 'Menunggu Persetujuan', 'requires_action' => true],
                'not_filled', 'auto_submitted' => ['status' => 'not_filled', 'label' => 'Tidak Mengisi', 'requires_action' => false],
                'draft' => ['status' => 'draft', 'label' => 'Draft', 'requires_action' => false],
                default => ['status' => 'none', 'label' => 'Belum Diisi', 'requires_action' => false],
            };

            $opsStatuses = $opsItems->pluck('status');
            $opsHasContent = $opsItems->contains(fn (KpiOpsItem $item) => ! is_null($item->hasil) || filled($item->aktivitas) || filled($item->bukti_path));
            $opsProgress = match (true) {
                $opsItems->isEmpty() => ['status' => 'configuration_error', 'label' => 'Parameter Belum Ditetapkan', 'requires_action' => true],
                $opsStatuses->contains('not_filled') => ['status' => 'not_filled', 'label' => 'Tidak Mengisi', 'requires_action' => false],
                $opsStatuses->contains('submitted') => ['status' => 'waiting_approval', 'label' => 'Menunggu Persetujuan', 'requires_action' => true],
                $opsStatuses->isNotEmpty() && $opsStatuses->every(fn ($status) => in_array($status, ['approved', 'auto_signed', 'locked'], true)) => ['status' => 'approved', 'label' => 'Selesai', 'requires_action' => false],
                $opsHasContent => ['status' => 'draft', 'label' => 'Draft', 'requires_action' => false],
                default => ['status' => 'draft', 'label' => 'Draft', 'requires_action' => false],
            };
            $opsProgress['count'] = $opsItems->count();

            $monthlyStatus = $monthly?->status;
            $requiredSignatureRoles = ['hrd_publish', 'employee', 'atasan_langsung'];
            if ($p->atasan_kedua_id) $requiredSignatureRoles[] = 'atasan_kedua';
            $signedRoles = $monthly?->signatures?->pluck('role')->unique() ?? collect();
            $signaturesComplete = $monthly && in_array($monthlyStatus, ['completed', 'published'], true)
                && collect($requiredSignatureRoles)->every(fn ($role) => $signedRoles->contains($role));
            $monthlyProgress = match (true) {
                ! $monthly || $monthlyStatus === 'scheduled' => ['status' => 'none', 'label' => 'Belum Dinilai', 'requires_action' => false],
                $monthlyStatus === 'draft' && $monthly->takeover_by => ['status' => 'waiting_hrd', 'label' => 'Menunggu HRD', 'requires_action' => true],
                $monthlyStatus === 'draft' => ['status' => 'draft', 'label' => 'Draft Penilai', 'requires_action' => false],
                $monthlyStatus === 'HRD_INCOMPLETE' => ['status' => 'waiting_hrd', 'label' => 'Menunggu HRD', 'requires_action' => true],
                $monthlyStatus === 'completed' => ['status' => 'ready', 'label' => 'Siap Dipublish', 'requires_action' => true],
                $signaturesComplete => ['status' => 'approved', 'label' => 'Selesai', 'requires_action' => false],
                $monthlyStatus === 'published' => ['status' => 'waiting_signature', 'label' => 'Menunggu Tanda Tangan', 'requires_action' => true],
                default => ['status' => 'none', 'label' => 'Belum Dinilai', 'requires_action' => false],
            };

            $isSelf = (int) $p->karyawan_id === (int) $user->karyawan_id;
            $isDirectSupervisor = $user->karyawan_id && (int) $p->atasan_langsung_id === (int) $user->karyawan_id;

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
                'atasan_langsung_id' => $p->atasan_langsung_id,
                'ki_status' => $individualStatus ?? 'draft',
                'ki_score' => $ki?->score ?? 0,
                'ops_count' => $opsItems->count(),
                'missing_daily_count' => $missingDailyCount,
                'needs_sp1_followup' => $missingDailyCount >= 3,
                'can_edit_ki' => $isSelf || $isDirectSupervisor || $isSuper,
                'can_edit_ops' => $isSelf || $isSuper,
                'daily_status' => $dailyProgress['status'],
                'daily_count' => $dailyCount,
                'pending_daily_count' => $pendingDailyCount,
                'not_filled_daily_count' => $notFilledDailyCount,
                'takeover_daily_count' => $takeoverDailyCount,
                'pending_daily_dates' => $pendingDailyDates->all(),
                'pending_daily_ids' => $user->karyawan_id
                    ? $dailyReports->filter(fn ($report) => $report->status === 'waiting_approval' && (int) $report->atasan_snapshot_id === (int) $user->karyawan_id)->pluck('id')->values()->all()
                    : [],
                'progress' => [
                    'daily' => $dailyProgress,
                    'individu' => $individualProgress,
                    'ops' => $opsProgress,
                    'mpa_monthly' => $monthlyProgress,
                ],
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

        $clockToday = KpiClock::today();
        $currentPeriodId = KpiPeriod::query()
            ->where('tahun', $clockToday->year)
            ->where('bulan', $clockToday->month)
            ->value('id');

        return inertia('Internal/Kpi/Employees', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'currentPeriodId' => $currentPeriodId,
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

        $employeePeriods = $this->employeePeriodOptions($employee->id);
        $selectedPeriodId = $request->integer('period_id')
            ?: (int) (collect($employeePeriods)->firstWhere('is_active', true)['id'] ?? collect($employeePeriods)->last()['id']);
        $selectedPeriod = KpiPeriod::query()->findOrFail($selectedPeriodId);
        abort_unless(collect($employeePeriods)->contains(fn ($item) => (int) $item['id'] === $selectedPeriodId), 404, 'Karyawan tidak tercatat pada periode KPI ini.');
        $periodStart = Carbon::create($selectedPeriod->tahun, $selectedPeriod->bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $periodEnd = $periodStart->copy()->endOfMonth();
        $clockToday = KpiClock::today();
        $defaultDate = $clockToday->betweenIncluded($periodStart, $periodEnd)
            ? $clockToday->toDateString()
            : ($clockToday->greaterThan($periodEnd) ? $periodEnd->toDateString() : $periodStart->toDateString());
        $dateStr = $request->input('tanggal', $defaultDate);
        $targetDate = Carbon::parse($dateStr, 'Asia/Jakarta');
        abort_unless($targetDate->format('Y-m-d') === $dateStr, 422, 'Format tanggal tidak valid.');
        abort_unless($targetDate->year === (int) $selectedPeriod->tahun && $targetDate->month === (int) $selectedPeriod->bulan, 422, 'Tanggal Daily Report harus berada dalam periode KPI yang dipilih.');
        $today = KpiClock::today();
        $yesterday = KpiClock::now()->subDay()->startOfDay();

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
                    'submitted_at' => KpiClock::now(),
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

        $viewerIsSuperAdmin = $viewer->role()->value('nama_role') === 'super_admin';
        $pendingApprovalsQuery = KpiDailyReport::with(['activities', 'karyawan'])
            ->where('status', 'waiting_approval');
        if (! $viewerIsSuperAdmin) {
            $pendingApprovalsQuery->where('atasan_snapshot_id', $employee->id);
        }
        $pendingApprovals = $pendingApprovalsQuery
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'karyawan_nama' => $r->karyawan?->nama ?? 'Karyawan',
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'submitted_at' => $r->submitted_at?->diffForHumans(),
                'activities_count' => $r->activities->count(),
                'activities' => $r->activities,
            ]);

        $historyPeriod = $selectedPeriod;
        $historyStart = $historyPeriod && $historyPeriod->tahun === $targetDate->year && $historyPeriod->bulan === $targetDate->month
            ? Carbon::create($historyPeriod->tahun, $historyPeriod->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
            : $targetDate->copy()->startOfMonth();
        $dailyHistory = KpiDailyReport::with('approver.karyawan')->where('karyawan_id', $employee->id)
            ->whereBetween('tanggal', [$historyStart->toDateString(), $historyStart->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('tanggal')
            ->get(['id', 'tanggal', 'status', 'approval_source', 'approved_at']);
        $reportsByDate = $dailyHistory->keyBy(fn ($item) => $item->tanggal->toDateString());
        $attendanceByDate = Absensi::where('karyawan_id', $employee->id)
            ->whereBetween('tanggal_absensi', [$historyStart->toDateString(), $historyStart->copy()->endOfMonth()->toDateString()])
            ->pluck('status_kehadiran', 'tanggal_absensi');
        $today = KpiClock::today();
        $dailyCalendar = collect(range(1, $historyStart->daysInMonth))->map(function ($day) use ($historyStart, $reportsByDate, $attendanceByDate, $today) {
            $date = $historyStart->copy()->day($day);
            $key = $date->toDateString();
            $report = $reportsByDate->get($key);
            $status = $date->dayOfWeekIso === 5 ? 'neutral' : ($report?->status === 'approved'
                ? ($this->effectiveDailyApprovalSource($report) === 'super_admin_takeover' ? 'approved_takeover' : 'approved')
                : ($report?->status === 'waiting_approval' ? 'waiting_approval' : ($report?->status === 'not_filled' ? 'not_filled' : ($attendanceByDate->get($key) === 'H' ? ($date->greaterThanOrEqualTo($today->copy()->subDay()) ? 'in_progress' : 'not_filled') : 'neutral'))));
            return ['date' => $key, 'day' => $day, 'status' => $status, 'report_id' => $report?->id];
        })->values()->all();

        return inertia('Internal/Kpi/DailyReport', [
            'user' => $this->userPayload($request),
            'report' => $report->exists ? $report->load(['activities', 'approver.karyawan', 'atasanSnapshot.jabatan']) : $report,
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
                'name' => $report->approver?->karyawan?->nama ?? $report->atasanSnapshot?->nama ?? '-',
                'position' => $report->approver?->karyawan?->jabatan?->nama_jabatan ?? $report->atasanSnapshot?->jabatan?->nama_jabatan ?? 'Atasan Langsung',
                'status' => $report->status,
                'approved_at' => $report->approved_at?->format('d/m/Y H:i'),
                'source' => $this->effectiveDailyApprovalSource($report),
                'source_label' => $this->effectiveDailyApprovalSource($report) === 'super_admin_takeover' ? 'Takeover Super Admin' : ($this->effectiveDailyApprovalSource($report) === 'direct_supervisor' ? 'Atasan Langsung' : null),
                'can_approve' => $report->status === 'waiting_approval' && (($viewer->role()->value('nama_role') === 'super_admin') || ($viewer->karyawan_id && $report->atasan_snapshot_id === $viewer->karyawan_id && $report->tanggal->betweenIncluded(KpiClock::today()->subDay(), KpiClock::today()))),
                'approval_window_expired' => $report->status === 'waiting_approval' && $viewer->karyawan_id && $report->atasan_snapshot_id === $viewer->karyawan_id && ! $report->tanggal->betweenIncluded(KpiClock::today()->subDay(), KpiClock::today()),
                'signature_url' => $report->approval_signature_path
                    ? Storage::disk('public')->url($report->approval_signature_path)
                    : (($report->approver?->karyawan?->foto_tanda_tangan ?? $report->atasanSnapshot?->foto_tanda_tangan)
                        ? Storage::disk('public')->url($report->approver?->karyawan?->foto_tanda_tangan ?? $report->atasanSnapshot?->foto_tanda_tangan)
                        : null),
            ],
            'activePeriodId' => $selectedPeriod->id,
            'employeePeriods' => $employeePeriods,
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
        abort_unless($report->status === 'waiting_approval', 422, 'Report tidak dalam status menunggu persetujuan.');
        $context = $this->dailyApprovalContext($user, $report);
        $signaturePath = $this->snapshotDailyApprovalSignature($report, $context['approver']);

        $updated = KpiDailyReport::query()
            ->whereKey($report->id)
            ->where('status', 'waiting_approval')
            ->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => KpiClock::now(),
            'approval_source' => $context['source'],
            'approval_signature_path' => $signaturePath,
        ]);
        abort_unless($updated === 1, 422, 'Daily Report sudah disetujui oleh pengguna lain.');

        return back()->with('success', 'Daily Report berhasil disetujui.');
    }

    /**
     * Bulk Approve Daily Reports
     */
    public function bulkApproveDaily(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:kpi_daily_reports,id',
        ]);

        $query = KpiDailyReport::whereIn('id', $request->input('report_ids'))
            ->where('status', 'waiting_approval');
        $reports = $query->with('karyawan')->get();
        $count = DB::transaction(function () use ($reports, $user): int {
            $count = 0;
            foreach ($reports as $report) {
                $context = $this->dailyApprovalContext($user, $report);
                $signaturePath = $this->snapshotDailyApprovalSignature($report, $context['approver']);
                $updated = KpiDailyReport::query()->whereKey($report->id)->where('status', 'waiting_approval')->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => KpiClock::now(),
                    'approval_source' => $context['source'],
                    'approval_signature_path' => $signaturePath,
                ]);
                $count += $updated;
            }
            return $count;
        });

        return back()->with('success', "{$count} Daily Report berhasil disetujui secara masal.");
    }

    private function dailyApprovalContext($user, KpiDailyReport $report): array
    {
        $employee = $user->karyawan;
        $isDirectSupervisor = $employee && (int) $report->atasan_snapshot_id === (int) $employee->id;
        $withinNormalWindow = $report->tanggal->betweenIncluded(
            KpiClock::today()->subDay(),
            KpiClock::today(),
        );

        // Identity from the period snapshot always has priority over the
        // administrative role. A Super Admin who is also the direct
        // supervisor must remain a normal direct-supervisor approval while
        // the normal approval window is open.
        if ($isDirectSupervisor && $withinNormalWindow) {
            return ['source' => 'direct_supervisor', 'approver' => $employee];
        }

        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        abort_unless($isSuperAdmin, 403, $isDirectSupervisor
            ? 'Batas persetujuan Atasan Langsung telah berakhir. Persetujuan ini hanya dapat dilakukan oleh Super Admin.'
            : 'Hanya Atasan Langsung (snapshot) atau Super Admin yang dapat menyetujui Daily Report ini.');

        abort_unless($employee, 403, 'User tidak memiliki data karyawan untuk tanda tangan approval.');

        return ['source' => 'super_admin_takeover', 'approver' => $employee];
    }

    /**
     * Resolve the displayed source from the actual approver for legacy rows
     * created before the direct-supervisor priority rule was corrected.
     */
    private function effectiveDailyApprovalSource(KpiDailyReport $report): ?string
    {
        $source = $report->approval_source;
        if ($source !== 'super_admin_takeover' || ! $report->approved_at || ! $report->atasan_snapshot_id) {
            return $source;
        }

        $approver = $report->relationLoaded('approver') ? $report->approver : $report->approver()->with('karyawan')->first();
        if (! $approver || (int) $approver->karyawan_id !== (int) $report->atasan_snapshot_id) {
            return $source;
        }

        $approvedDate = $report->approved_at->copy()->timezone('Asia/Jakarta')->startOfDay();
        $reportDate = $report->tanggal->copy()->startOfDay();

        return $approvedDate->betweenIncluded($reportDate, $reportDate->copy()->addDay())
            ? 'direct_supervisor'
            : $source;
    }

    private function snapshotDailyApprovalSignature(KpiDailyReport $report, Karyawan $approver): string
    {
        $source = trim((string) $approver->foto_tanda_tangan);
        $source = preg_replace('#^/?storage/#', '', $source);
        abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Tanda tangan Atasan Langsung belum tersedia.');

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
        $destination = 'kpi/daily-signatures/'.$report->id.'_'.KpiClock::now()->format('YmdHisv').'.'.$extension;
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

        // The period end is the target/deadline, not an opening gate.  A
        // participant may complete KI during the period and until the normal
        // deadline on day one of the following month.
        $isWindowAllowed = ! KpiClock::now()->gt($this->normalEntryDeadline($period));
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
            abort_unless($isWindowAllowed, 422, 'Batas normal pengisian Kinerja Individu telah berakhir.');
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
            'canApprove' => (bool) ($score->status === 'submitted' && ! $signature && (($user->karyawan_id && (int) $participant->atasan_langsung_id === (int) $user->karyawan_id) || $user->role()->value('nama_role') === 'super_admin')),
            'approval' => $signature ? [
                'status' => $signature->source === 'automatic' ? 'auto_signed' : 'approved',
                'source' => $signature->source,
                'signed_at' => $signature->signed_at,
                'signature_url' => $signature->signature_path ? Storage::disk('public')->url($signature->signature_path) : null,
                'signer_name' => User::with('karyawan.jabatan')->find($signature->signed_by_user_id)?->karyawan?->nama ?? $approver?->nama,
                'signer_position' => User::with('karyawan.jabatan')->find($signature->signed_by_user_id)?->karyawan?->jabatan?->nama_jabatan ?? $approver?->jabatan?->nama_jabatan,
            ] : null,
            'employeePeriods' => $this->employeePeriodOptions($participant->karyawan_id),
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
        $periodEnd = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->endOfMonth()->endOfDay();
        return $now->gt($this->normalEntryDeadline($period)) ? 'closed' : ($now->lt($periodEnd) ? 'open' : 'open');
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
            ->load([
                'period',
                'karyawan.user',
                'karyawan.jabatan',
                'karyawan.departemen',
                'karyawan.penempatan',
                'atasanLangsung.user',
                'atasanLangsung.jabatan',
            ]);
        $items = KpiOpsItem::where('kpi_participant_id', $participant->id)
            ->orderBy('urutan', 'asc')
            ->get();

        $isWindowAllowed = ! KpiClock::now()->gt($this->normalEntryDeadline($period));
        $windowState = KpiClock::now()->gt($this->normalEntryDeadline($period)) ? 'closed' : 'open';
        $user = $request->user();
        $isSelf = $participant->karyawan_id === $user->karyawan_id;
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        // Configuration is an administrative mode for a participant opened
        // from KPI-Karyawan. A Super Admin's own record remains an employee
        // form so manager-level Super Admins can still complete their KPI.
        $isConfigurator = $isSuperAdmin && ! $isSelf;

        if ($request->isMethod('post')) {
            if ($isConfigurator) {
                $v = $request->validate([
                    'mode' => 'required|in:configure',
                    'items' => 'present|array',
                    'items.*.id' => 'nullable|integer',
                    'items.*.kpi_item' => 'required|string|max:255',
                    'items.*.maintenance' => 'nullable|string|max:1000',
                    'items.*.target_unit' => 'required|integer|min:1',
                    'items.*.tanda' => 'nullable|string|max:10',
                    'items.*.frekuensi' => 'nullable|string|max:100',
                    'items.*.hasil' => 'prohibited',
                    'items.*.aktivitas' => 'prohibited',
                    'items.*.bukti_evidence' => 'prohibited',
                    'items.*.bukti_path' => 'prohibited',
                ]);

                abort_if(
                    $items->contains(fn (KpiOpsItem $item) => in_array($item->status, ['submitted', 'approved', 'locked', 'auto_signed', 'not_filled'], true))
                        || KpiSignature::where('signable_type', KpiParticipant::class)
                            ->where('signable_id', $participant->id)
                            ->exists(),
                    422,
                    'Parameter Kinerja OPS sudah terkunci karena proses pengisian atau tanda tangan telah dimulai.'
                );

                $submittedIds = collect($v['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();
                $knownIds = $items->pluck('id')->map(fn ($id) => (int) $id);
                abort_unless($submittedIds->diff($knownIds)->isEmpty(), 422, 'Item parameter tidak valid untuk peserta ini.');

                DB::transaction(function () use ($participant, $period, $items, $v) {
                    $keepIds = $v['items'] ? collect($v['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id) : collect();
                    $items->filter(fn (KpiOpsItem $item) => ! $keepIds->contains((int) $item->id))->each->delete();

                    foreach ($v['items'] as $index => $itemData) {
                        $item = ! empty($itemData['id'])
                            ? $items->firstWhere('id', (int) $itemData['id'])
                            : new KpiOpsItem(['kpi_participant_id' => $participant->id]);

                        abort_unless($item, 422, 'Item parameter tidak ditemukan.');
                        $item->fill([
                            'kpi_participant_id' => $participant->id,
                            'urutan' => $index + 1,
                            'kpi_item' => $itemData['kpi_item'],
                            'maintenance' => $itemData['maintenance'] ?? null,
                            'target_unit' => (float) $itemData['target_unit'],
                            'tanda' => $itemData['tanda'] ?? null,
                            'frekuensi' => $itemData['frekuensi'] ?? null,
                            'sumber_data_snapshot' => $participant->jabatan_snapshot,
                            'target_bulanan' => (float) $itemData['target_unit'],
                            'bulan' => $period->bulan,
                            'beban_target' => 0,
                        ]);
                        $item->save();
                    }

                    $configuredItems = KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
                    $totalTargetUnit = $configuredItems->sum(fn (KpiOpsItem $item) => (float) $item->target_unit);
                    foreach ($configuredItems as $item) {
                        $item->update([
                            'beban_target' => $totalTargetUnit > 0
                                ? round(((float) $item->target_unit / $totalTargetUnit) * 10, 4)
                                : 0,
                        ]);
                    }
                });

                return back()->with('success', 'Parameter Kinerja OPS berhasil disimpan.');
            }

            abort_unless($isSelf, 403, 'Kinerja OPS hanya dapat diisi oleh pemilik laporan.');
            abort_unless($isWindowAllowed, 422, 'Batas normal pengisian Kinerja OPS telah berakhir.');
            abort_if(
                $items->contains(fn ($item) => in_array($item->status, ['locked', 'auto_signed', 'not_filled'], true))
                    || KpiSignature::where('signable_type', KpiParticipant::class)
                        ->where('signable_id', $participant->id)
                        ->exists(),
                422,
                'Kinerja OPS ini sudah dikirim atau memiliki tanda tangan sehingga tidak dapat diubah.'
            );

            $v = $request->validate([
                'mode' => 'required|in:employee',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer',
                'items.*.hasil' => 'nullable|numeric|min:0',
                'items.*.aktivitas' => 'nullable|string|max:1000',
                'items.*.bukti_evidence' => 'nullable|file|image|max:5120',
                'items.*.remove_bukti' => 'nullable|boolean',
            ]);

            $submittedIds = collect($v['items'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $currentIds = $items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            abort_unless($submittedIds->all() === $currentIds->all(), 422, 'Struktur parameter Kinerja OPS tidak valid.');

            $totalTargetUnit = $items->sum(fn (KpiOpsItem $item) => (float) $item->target_unit);
            abort_if($totalTargetUnit <= 0, 422, 'Total Target Unit harus lebih dari 0.');

            DB::transaction(function () use ($participant, $items, $v, $totalTargetUnit, $request) {
                foreach ($v['items'] as $index => $itemData) {
                    $existing = $items->firstWhere('id', (int) $itemData['id']);
                    abort_unless($existing, 422, 'Item Kinerja OPS tidak ditemukan pada peserta ini.');
                    $target = (float) $existing->target_unit;
                    $hasil = isset($itemData['hasil']) && $itemData['hasil'] !== '' && $itemData['hasil'] !== null ? (float) $itemData['hasil'] : null;

                    $bebanTarget = round(($target / $totalTargetUnit) * 10, 4);

                    $nilaiItem = 0;
                    if ($hasil !== null && $target > 0) {
                        $nilaiItem = round(($hasil / $target) * $bebanTarget, 4);
                    }

                    $buktiPath = $existing->bukti_path;
                    if ($request->hasFile("items.{$index}.bukti_evidence")) {
                        $file = $request->file("items.{$index}.bukti_evidence");
                        $storedPath = $file->store('kpi/ops-evidence', 'public');
                        $buktiPath = $storedPath;
                    } elseif ((bool) ($itemData['remove_bukti'] ?? false)) {
                        $buktiPath = null;
                    }

                    $hasContent = $hasil !== null || trim((string) ($itemData['aktivitas'] ?? '')) !== '' || $buktiPath !== null;
                    $existing->update([
                        // Parameter/snapshot columns intentionally remain unchanged here.
                        'beban_target' => $bebanTarget,
                        'hasil' => $hasil,
                        'aktivitas' => $itemData['aktivitas'] ?? null,
                        'bukti_path' => $buktiPath,
                        'nilai_item' => $nilaiItem,
                        'status' => $hasContent ? 'submitted' : 'draft',
                        'submit_type' => $hasContent ? 'manual' : null,
                        'submitted_at' => $hasContent ? KpiClock::now() : null,
                    ]);
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
                'signer_name' => User::with('karyawan.jabatan')->find($signature->signed_by_user_id)?->karyawan?->nama,
                'signer_position' => User::with('karyawan.jabatan')->find($signature->signed_by_user_id)?->karyawan?->jabatan?->nama_jabatan,
                'signature_url' => in_array($signature->source, ['manual', 'super_admin_takeover'], true) && $signature->signature_path
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
            'windowState' => $windowState,
            'isOwner' => (int) $participant->karyawan_id === (int) $user->karyawan_id,
            'isConfigurator' => $isConfigurator,
            'signatures' => $signatures,
            'canSignEmployee' => $hasSubmittedItems && ! $signatures->has('employee') && ((int) $participant->karyawan_id === (int) $user->karyawan_id || $isSuperAdmin),
            'canSignSupervisor' => $hasSubmittedItems && ! $signatures->has('atasan_langsung') && ((int) $participant->atasan_langsung_id === (int) $user->karyawan_id || $isSuperAdmin),
            'employeePeriods' => $this->employeePeriodOptions($participant->karyawan_id),
        ]);
    }

    // =========================================================================
    // BATCH 2 IMPLEMENTATION: MPA EVALUATOR ASSIGNMENT, ASSESSMENT, TAKEOVER, MONTHLY
    // =========================================================================

    /**
     * Assign the primary evaluator to one performance month. The assignment
     * may exist before its KPI period is generated.
     */
    public function assignEvaluator(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role()->value('nama_role') === 'super_admin', 403, 'Hanya Super Admin yang berwenang menetapkan Evaluator MPA.');

        $v = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'evaluator_id' => 'required|exists:users,id',
        ]);

        $evaluatorUser = User::with('karyawan.jabatan')->findOrFail($v['evaluator_id']);
        $period = KpiPeriod::query()
            ->where('tahun', $v['year'])
            ->where('bulan', $v['month'])
            ->first();

        abort_unless($evaluatorUser->is_active, 422, 'User evaluator tidak aktif.');
        abort_unless($evaluatorUser->karyawan_id, 422, 'Evaluator harus terikat data karyawan.');
        abort_unless($evaluatorUser->karyawan?->status_keaktifan === 'aktif', 422, 'Karyawan evaluator tidak aktif.');

        $jabatanNama = mb_strtolower($evaluatorUser->karyawan?->jabatan?->nama_jabatan ?? '');
        abort_if($this->isExcludedKpiPosition($jabatanNama), 422, 'Dirut dan Direktur tidak dapat ditunjuk sebagai evaluator reguler.');

        if ($period) {
            abort_if($this->mpaAssignmentHasActivity($period), 422, 'Penilai periode ini sudah mulai melakukan penilaian MPA dan tidak dapat diganti.');

            $isParticipant = KpiParticipant::where('kpi_period_id', $period->id)
                ->where('karyawan_id', $evaluatorUser->karyawan_id)
                ->exists();

            abort_unless($isParticipant, 422, 'Evaluator harus terdaftar sebagai peserta KPI pada periode tersebut.');
        }

        DB::transaction(function () use ($period, $evaluatorUser, $user, $v): void {
            $assignment = MpaEvaluatorAssignment::firstOrNew([
                'year' => $v['year'],
                'month' => $v['month'],
            ]);
            if (! $assignment->exists) {
                $assignment->created_by = $user->id;
            }
            $assignment->fill([
                'evaluator_id' => $evaluatorUser->id,
                'updated_by' => $user->id,
            ])->save();

            if (! $period) {
                return;
            }

            $period->update([
                'mpa_evaluator_id' => $evaluatorUser->id,
                'mpa_assigned_at' => KpiClock::now(),
            ]);

            KpiMonthly::query()
                ->whereIn('kpi_participant_id', $period->participants()->select('id'))
                ->update(['evaluator_id' => $evaluatorUser->id]);
        });

        $monthName = Carbon::create($v['year'], $v['month'], 1, 0, 0, 0, 'Asia/Jakarta')->translatedFormat('F Y');

        return back()->with('success', "Penilai MPA untuk periode {$monthName} berhasil ditetapkan.");
    }

    /**
     * MPA Assessment Page & Submission (Normal Evaluator window: Days 1-5)
     */
    public function mpa(Request $request, KpiPeriod $period)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';
        $isHrdOrDirektur = $isSuperAdmin || in_array(mb_strtolower($user->karyawan?->jabatan?->nama_jabatan ?? ''), ['direktur', 'hrd', 'dirut']);

        $assignment = MpaEvaluatorAssignment::with('evaluator.karyawan.jabatan')
            ->where('year', $period->tahun)
            ->where('month', $period->bulan)
            ->first();
        $assignedEvaluatorId = $assignment?->evaluator_id ?? $period->mpa_evaluator_id;

        // Check if assigned primary evaluator or HRD takeover
        $isAssignedEvaluator = (int) $assignedEvaluatorId === (int) $user->id;

        // Days 1-5 are the operational target only. Evaluator readiness is
        // determined per participant by completion of the HRD initial data.
        $isWindowOpen = false;
        $isBlocked = ! $assignedEvaluatorId;
        $assignmentLocked = $this->mpaAssignmentHasActivity($period);
        $isFormView = $request->filled('karyawan_id');

        // Normal evaluator list excludes Dirut/Direktur and the evaluator's own
        // participant. HRD/Super Admin can access the full administrative list.
        $targetKaryawanId = $request->input('karyawan_id');
        $participants = $period->participants()->with(['karyawan', 'karyawan.bawahan'])->get();
        $eligibleParticipants = $participants->filter(function (KpiParticipant $participant) use ($user, $isHrdOrDirektur) {
            $jabatan = mb_strtolower($participant->jabatan_snapshot ?? '');
            if (! $isHrdOrDirektur && $this->isExcludedKpiPosition($jabatan)) {
                return false;
            }
            return $isHrdOrDirektur || (int) $participant->karyawan_id !== (int) $user->karyawan_id;
        })->values();

        // Eligible candidates are loaded once for the administrative month table.
        $eligibleEvaluators = User::with(['karyawan.jabatan'])
            ->where('is_active', true)
            ->whereHas('karyawan', function ($q) {
                $q->where('status_keaktifan', 'aktif');
            })
            ->get()
            ->filter(function ($u) {
                $jab = mb_strtolower($u->karyawan?->jabatan?->nama_jabatan ?? '');
                return ! $this->isExcludedKpiPosition($jab);
            })
            ->map(fn (User $evaluator) => [
                'id' => $evaluator->id,
                'karyawan_id' => $evaluator->karyawan_id,
                'name' => $evaluator->karyawan?->nama,
                'position' => $evaluator->karyawan?->jabatan?->nama_jabatan,
                'eligible_months' => [],
            ])
            ->filter(fn (array $evaluator) => filled($evaluator['name']))
            ->values();

        // Selected participant for assessment
        $selectedParticipant = null;
        if ($targetKaryawanId) {
            $selectedParticipant = $eligibleParticipants->firstWhere('karyawan_id', (int) $targetKaryawanId);
            abort_unless($selectedParticipant, 403, 'Karyawan tersebut tidak tersedia pada daftar penilaian MPA Anda.');
        }
        if (! $selectedParticipant) {
            $selectedParticipant = $eligibleParticipants->first();
        }

        // Fetch existing monthly record for selected participant
        $monthly = null;
        if ($selectedParticipant) {
            $monthly = KpiMonthly::firstOrCreate(
                ['kpi_participant_id' => $selectedParticipant->id],
                ['evaluator_id' => $assignedEvaluatorId]
            );
            $monthly->load(['attendanceAdjustments', 'rewardPunishments']);
        }
        $isReadyForEvaluator = $this->isMpaInitialHrdReady($monthly) && $monthly->status !== 'completed';
        $isWindowOpen = $isReadyForEvaluator;

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
            abort_unless($isHrdOrDirektur || $isReadyForEvaluator, 422, 'MPA belum siap dinilai: komponen awal HRD untuk karyawan ini belum lengkap.');
            abort_if($monthly && $monthly->status === 'completed' && (! $isHrdOrDirektur || ($monthly->takeover_by && $monthly->takeover_by !== $user->id)), 422, 'Penilaian MPA yang sudah completed tidak dapat diubah secara normal.');

            // Assigned evaluators may only save progress. Finalization is an
            // administrative action reserved for Super Admin/HRD.
            $action = $request->input('action', 'draft');
            abort_unless(in_array($action, ['draft', 'complete'], true), 422, 'Aksi MPA tidak valid.');
            abort_unless($action === 'draft' || $isHrdOrDirektur, 403, 'Hanya Super Admin/HRD yang dapat menyelesaikan penilaian MPA.');
            if ($action === 'complete' && is_null($monthly->completed_at)) {
                abort_unless($request->boolean('confirm_hrd'), 422, 'Konfirmasi diperlukan karena data HRD belum diisi atau memang tidak diperlukan.');
            }
            // Leadership is not an applicable dimension for a participant
            // without snapshot subordinates. Normalize any stale frontend
            // value so it can never block saving that participant.
            if (! $hasSubordinatesSnapshot) {
                $request->merge(['kepemimpinan' => null]);
            }
            $required = $action === 'complete' ? 'required' : 'nullable';
            $v = $request->validate([
                'action' => 'nullable|string|in:draft,complete',
                'confirm_hrd' => 'nullable|boolean',
                'kinerja_operasional' => $required.'|integer|min:1|max:45',
                'sikap_kerja' => $required.'|integer|min:1|max:45',
                'team_work' => $required.'|integer|min:1|max:45',
                'inisiatif' => $required.'|integer|min:1|max:45',
                'kepemimpinan' => $hasSubordinatesSnapshot ? $required.'|integer|min:1|max:45' : 'nullable|integer|min:1|max:45',
                // Sections 5 and 6 are required before even a draft can be
                // stored, so evaluators cannot leave the narrative sections
                // empty while progressing the same record.
                'performance' => 'required|string|max:2000',
                'coaching' => 'required|string|max:2000',
                'takeover_reason' => ($action === 'complete' && ! $isAssignedEvaluator && $isHrdOrDirektur && ! $monthly->takeover_by) ? 'required|string|max:500' : 'nullable|string|max:500',
            ], [
                'kinerja_operasional.required' => 'Nilai Kinerja Operasional wajib diisi.',
                'kinerja_operasional.integer' => 'Nilai Kinerja Operasional harus berupa angka bulat.',
                'kinerja_operasional.min' => 'Nilai Kinerja Operasional minimal 1.',
                'kinerja_operasional.max' => 'Nilai Kinerja Operasional maksimal 45.',
                'sikap_kerja.required' => 'Nilai Sikap Kerja wajib diisi.',
                'sikap_kerja.integer' => 'Nilai Sikap Kerja harus berupa angka bulat.',
                'sikap_kerja.min' => 'Nilai Sikap Kerja minimal 1.',
                'sikap_kerja.max' => 'Nilai Sikap Kerja maksimal 45.',
                'team_work.required' => 'Nilai Team Work wajib diisi.',
                'team_work.integer' => 'Nilai Team Work harus berupa angka bulat.',
                'team_work.min' => 'Nilai Team Work minimal 1.',
                'team_work.max' => 'Nilai Team Work maksimal 45.',
                'inisiatif.required' => 'Nilai Inisiatif wajib diisi.',
                'inisiatif.integer' => 'Nilai Inisiatif harus berupa angka bulat.',
                'inisiatif.min' => 'Nilai Inisiatif minimal 1.',
                'inisiatif.max' => 'Nilai Inisiatif maksimal 45.',
                'kepemimpinan.required' => 'Nilai Kepemimpinan wajib diisi.',
                'kepemimpinan.integer' => 'Nilai Kepemimpinan harus berupa angka bulat.',
                'kepemimpinan.min' => 'Nilai Kepemimpinan minimal 1.',
                'kepemimpinan.max' => 'Nilai Kepemimpinan maksimal 45.',
                'performance.required' => 'Penjelasan Performance wajib diisi.',
                'performance.string' => 'Penjelasan Performance harus berupa teks.',
                'coaching.required' => 'Rencana Perbaikan/Coaching wajib diisi.',
                'coaching.string' => 'Rencana Perbaikan/Coaching harus berupa teks.',
                'takeover_reason.required' => 'Alasan pengambilalihan HRD wajib diisi.',
            ]);

            $ko = isset($v['kinerja_operasional']) ? (int) $v['kinerja_operasional'] : null;
            $sk = isset($v['sikap_kerja']) ? (int) $v['sikap_kerja'] : null;
            $tw = isset($v['team_work']) ? (int) $v['team_work'] : null;
            $in = isset($v['inisiatif']) ? (int) $v['inisiatif'] : null;
            $kp = $hasSubordinatesSnapshot && isset($v['kepemimpinan']) ? (int) $v['kepemimpinan'] : null;

            // Intermediate calculation (min 4 decimal places)
            // If no leadership: score = ((KO + Sikap + Team + Inisiatif) / 4) / 45 * 5
            // If has leadership: score = ((KO + Sikap + Team + Inisiatif + Kepemimpinan) / 5) / 45 * 5
            $sum = ($ko ?? 0) + ($sk ?? 0) + ($tw ?? 0) + ($in ?? 0) + ($hasSubordinatesSnapshot ? ($kp ?? 0) : 0);
            $countComponents = $hasSubordinatesSnapshot ? 5 : 4;
            $avgRating = $sum / $countComponents; // out of 45
            $mpaScoreRaw = $action === 'complete' ? (($avgRating / 45) * 5) : 0;

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
                'status' => $action === 'complete' ? 'completed' : 'draft',
            ];
            if ($action === 'complete') {
                $updateData['completed_at'] = KpiClock::now();
                $updateData['completed_by'] = $user->id;
            }

            // If takeover by HRD
            if (! $isAssignedEvaluator && $isHrdOrDirektur && ! $monthly->takeover_by) {
                $updateData['takeover_by'] = $user->id;
                $updateData['takeover_at'] = KpiClock::now();
                $updateData['takeover_reason'] = $v['takeover_reason'] ?? 'HRD Takeover completing unfulfilled MPA assessment';
            }

            $monthly->update($updateData);

            if ($action === 'complete') {
                // Finalizing MPA records the HRD signature exactly once. This
                // is an effect of the finalization action, not the day-9
                // deadline auto-sign process.
                KpiSignature::firstOrCreate(
                    [
                        'signable_type' => KpiMonthly::class,
                        'signable_id' => $monthly->id,
                        'role' => 'hrd_publish',
                    ],
                    [
                        'source' => 'automatic',
                        'signed_for_user_id' => $monthly->participant?->karyawan_id,
                        'signed_by_user_id' => $user->id,
                        'signed_at' => KpiClock::now(),
                        'reason' => 'MPA finalized by HRD',
                    ]
                );
            }

            return back()->with('success', $action === 'complete' ? 'Penilaian MPA berhasil diselesaikan.' : 'Draft MPA berhasil disimpan.');
        }

        // Map participants summary for MPA list view
        $participantList = $eligibleParticipants->map(function ($p) use ($user) {
            $m = KpiMonthly::where('kpi_participant_id', $p->id)->first();
            // A completed record must remain visibly completed even when it
            // was filled through an earlier HRD takeover.
            $status = in_array($m?->status, ['completed', 'published'], true)
                ? $m->status
                : ($m?->takeover_by ? 'takeover' : ($m?->status ?? 'scheduled'));
            return [
                'id' => $p->id,
                'karyawan_id' => $p->karyawan_id,
                'nama' => $p->karyawan?->nama ?? 'Karyawan',
                'jabatan' => $p->jabatan_snapshot,
                'departemen' => $p->departemen_snapshot,
                'status' => $status,
                'mpa_score' => $m?->mpa_score ?? 0,
                'is_self' => $p->karyawan_id === $user->karyawan_id,
                'is_takeover' => (bool) $m?->takeover_by,
            ];
        });
        $summary = [
            'total' => $participantList->count(),
            'completed' => $participantList->whereIn('status', ['completed', 'published'])->count(),
            'pending' => $participantList->whereIn('status', ['scheduled', 'draft'])->count(),
            'hrd' => $participantList->whereIn('status', ['takeover', 'HRD_INCOMPLETE'])->count(),
        ];

        $assignmentYear = $request->integer('assignment_year') ?: (int) $period->tahun;
        $yearPeriods = KpiPeriod::query()
            ->where('tahun', $assignmentYear)
            ->with('participants')
            ->get()
            ->keyBy('bulan');
        $eligibleEvaluators = $eligibleEvaluators->map(function (array $evaluator) use ($yearPeriods): array {
            $evaluator['eligible_months'] = collect(range(1, 12))
                ->filter(function (int $month) use ($yearPeriods, $evaluator): bool {
                    $monthPeriod = $yearPeriods->get($month);
                    return ! $monthPeriod || $monthPeriod->participants->contains('karyawan_id', $evaluator['karyawan_id']);
                })
                ->values()
                ->all();
            return $evaluator;
        })->values();
        $yearAssignments = MpaEvaluatorAssignment::query()
            ->where('year', $assignmentYear)
            ->with('evaluator.karyawan.jabatan')
            ->get()
            ->keyBy('month');

        $evaluatorAssignments = collect(range(1, 12))->map(function (int $month) use ($assignmentYear, $yearAssignments, $yearPeriods): array {
            $row = $yearAssignments->get($month);
            $monthPeriod = $yearPeriods->get($month);
            $locked = $monthPeriod ? $this->mpaAssignmentHasActivity($monthPeriod) : false;
            $evaluator = $row?->evaluator;

            return [
                'month' => $month,
                'year' => $assignmentYear,
                'evaluator_id' => $row?->evaluator_id,
                'evaluator_name' => $evaluator?->karyawan?->nama,
                'evaluator_position' => $evaluator?->karyawan?->jabatan?->nama_jabatan,
                'has_period' => (bool) $monthPeriod,
                'locked' => $locked,
                'status' => $locked ? 'locked' : ($row ? 'assigned' : 'unassigned'),
                'eligibility_warning' => $row && $monthPeriod && ! $monthPeriod->participants->contains('karyawan_id', $evaluator?->karyawan_id),
            ];
        })->values();

        $assignmentYears = collect([$assignmentYear, (int) $period->tahun])
            ->merge($yearAssignments->pluck('year'))
            ->merge(KpiPeriod::query()->pluck('tahun'))
            ->unique()->sortDesc()->values();

        return inertia('Internal/Kpi/MPA', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'eligibleEvaluators' => $eligibleEvaluators,
            'assignedEvaluatorId' => $assignedEvaluatorId,
            'assignedEvaluatorName' => User::with('karyawan')->find($assignedEvaluatorId)?->karyawan?->nama ?? 'Belum Ditentukan',
            'isAssignedEvaluator' => $isAssignedEvaluator,
            'isWindowOpen' => $isWindowOpen,
            'isBlocked' => $isBlocked,
            'assignmentLocked' => $assignmentLocked,
            'isFormView' => $isFormView,
            'isHrdOrDirektur' => $isHrdOrDirektur,
            'participants' => $participantList,
            'selectedParticipant' => $selectedParticipant,
            'hasSubordinatesSnapshot' => $hasSubordinatesSnapshot,
            'isRatingSelf' => $isRatingSelf,
            'monthly' => $monthly,
            'hrdData' => [
                'attendance' => $monthly?->attendanceAdjustments?->map(fn ($item) => [
                    'kode' => $item->kode,
                    'jumlah' => $item->jumlah,
                    'nilai' => $item->nilai,
                ])->values() ?? [],
                'rewards' => $monthly?->rewardPunishments?->map(fn ($item) => [
                    'jenis' => $item->jenis,
                    'jumlah' => $item->jumlah,
                    'nilai' => $item->nilai,
                ])->values() ?? [],
                'attendance_score' => $monthly?->attendance_score,
                'reward_punishment_score' => $monthly?->reward_punishment_score,
            ],
            'summary' => $summary,
            'assignmentYear' => $assignmentYear,
            'assignmentYears' => $assignmentYears,
            'evaluatorAssignments' => $evaluatorAssignments,
        ]);
    }

    private function mpaAssignmentHasActivity(KpiPeriod $period): bool
    {
        $monthlyIds = KpiMonthly::query()
            ->whereIn('kpi_participant_id', $period->participants()->select('id'))
            ->pluck('id');

        if ($monthlyIds->isEmpty()) {
            return false;
        }

        return KpiMonthly::query()
            ->whereIn('id', $monthlyIds)
            ->where(function ($query): void {
                $query->whereNotIn('status', ['scheduled', ''])
                    ->orWhereNotNull('kinerja_operasional')
                    ->orWhereNotNull('sikap_kerja')
                    ->orWhereNotNull('team_work')
                    ->orWhereNotNull('inisiatif')
                    ->orWhereNotNull('kepemimpinan')
                    ->orWhereNotNull('performance')
                    ->orWhereNotNull('coaching');
            })
            ->exists();
    }

    /**
     * HRD initial MPA readiness is explicit and per participant. A timestamp
     * alone is insufficient: both HRD component scores must have been saved
     * and the record must not already be in evaluator-finalized state.
     */
    private function isMpaInitialHrdReady(?KpiMonthly $monthly): bool
    {
        return (bool) ($monthly
            && $monthly->completed_at
            && ! is_null($monthly->attendance_score)
            && ! is_null($monthly->reward_punishment_score));
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
            'takeover_at' => KpiClock::now(),
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
        $useHrdWorkspace = $request->input('mode') === 'hrd';
        $targetParticipant = $request->filled('karyawan_id')
            ? $this->getParticipantOrTarget($request, $period)
            : null;
        // Monthly Individu is the default route for every account, including
        // Super Admin. The HRD/MPA workspace is opt-in via mode=hrd.
        if (! $useHrdWorkspace && ! $targetParticipant) {
            $targetParticipant = KpiParticipant::where('kpi_period_id', $period->id)
                ->where('karyawan_id', $user->karyawan_id)
                ->firstOrFail();
        }

        // Monthly Individu is a read-only result view. The period-wide HRD
        // workspace remains available explicitly through mode=hrd.
        if ($targetParticipant) {
            $targetParticipant->load(['karyawan.user', 'karyawan.jabatan', 'karyawan.departemen', 'atasanLangsung', 'atasanKedua']);
            $monthly = KpiMonthly::with(['attendanceAdjustments', 'rewardPunishments'])
                ->firstOrCreate(['kpi_participant_id' => $targetParticipant->id]);

            $signatures = KpiSignature::where('signable_type', KpiMonthly::class)
                ->where('signable_id', $monthly->id)
                ->whereIn('role', ['hrd_publish', 'employee', 'atasan_langsung', 'atasan_kedua'])
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

            $isOwner = (int) $targetParticipant->karyawan_id === (int) $user->karyawan_id;
            $isMonitoring = ! $isOwner;

            return inertia('Internal/Kpi/Monthly', [
                'user' => $this->userPayload($request),
                'period' => $period,
                'viewMode' => 'result',
                'participant' => $targetParticipant,
                'employeeHeader' => [
                    'nama' => $targetParticipant->karyawan?->nama ?? '-',
                    'nik' => $targetParticipant->karyawan?->nik ?? '-',
                    'perusahaan' => 'Kampoeng Radja',
                    'jabatan' => $targetParticipant->jabatan_snapshot ?? '-',
                    'departemen' => $targetParticipant->departemen_snapshot ?? '-',
                    'penempatan' => $targetParticipant->penempatan_snapshot ?? '-',
                    'atasan_langsung' => $targetParticipant->atasan_langsung_snapshot ?? '-',
                ],
                'monthlyDetail' => [
                    'id' => $monthly->id,
                    'status' => $monthly->status,
                    'kinerja_operasional' => $monthly->kinerja_operasional,
                    'sikap_kerja' => $monthly->sikap_kerja,
                    'team_work' => $monthly->team_work,
                    'inisiatif' => $monthly->inisiatif,
                    'kepemimpinan' => $monthly->kepemimpinan,
                    'mpa_score' => $monthly->mpa_score,
                    'performance' => $monthly->performance,
                    'coaching' => $monthly->coaching,
                    'attendance_score' => $monthly->attendance_score,
                    'reward_punishment_score' => $monthly->reward_punishment_score,
                    'adjustments' => $monthly->attendanceAdjustments,
                    'rewards' => $monthly->rewardPunishments,
                    'published_at' => $monthly->published_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                ],
                'signatures' => $signatures,
                'isOwner' => $isOwner,
                'isMonitoring' => $isMonitoring,
                'monitoringEmployeeId' => $isMonitoring ? $targetParticipant->karyawan_id : null,
                'employeePeriods' => $this->employeePeriodOptions($targetParticipant->karyawan_id),
                'hasLeadershipDimension' => KpiParticipant::where('kpi_period_id', $period->id)
                    ->where('atasan_langsung_id', $targetParticipant->karyawan_id)
                    ->exists(),
                'canSignEmployee' => in_array($monthly->status, ['completed', 'published'], true) && $signatures->has('hrd_publish') && $isOwner && ! $signatures->has('employee'),
                'canSignSupervisor' => in_array($monthly->status, ['completed', 'published'], true) && $signatures->has('hrd_publish')
                    && ((int) $targetParticipant->atasan_langsung_id === (int) $user->karyawan_id || $isSuperAdmin)
                    && ! $signatures->has('atasan_langsung'),
                'canSignSecondSupervisor' => in_array($monthly->status, ['completed', 'published'], true) && $signatures->has('hrd_publish')
                    && $targetParticipant->atasan_kedua_id
                    && ((int) $targetParticipant->atasan_kedua_id === (int) $user->karyawan_id || $isSuperAdmin)
                    && ! $signatures->has('atasan_kedua'),
            ]);
        }

        abort_unless($isHrdOrDirektur, 403, 'Anda tidak berwenang membuka workspace Monthly HRD.');

        // Keep one Monthly/MPA record per participant so the HRD workspace and
        // the employee result page always address the same row.
        $period->participants()->get()->each(fn (KpiParticipant $participant) =>
            KpiMonthly::firstOrCreate(
                ['kpi_participant_id' => $participant->id],
                ['evaluator_id' => $period->mpa_evaluator_id]
            )
        );

        $monthlyQuery = KpiMonthly::with(['participant.karyawan', 'attendanceAdjustments', 'rewardPunishments'])
            ->whereHas('participant', fn ($q) => $q->where('kpi_period_id', $period->id));

        $monthlies = $monthlyQuery
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
                    'is_complete' => in_array($m->status, ['completed', 'published'], true) && ! is_null($m->completed_at),
                ];
            });

        $now = KpiClock::now();
        $isPublishable = $monthlies->every(fn ($m) => $m['is_complete']);

        return inertia('Internal/Kpi/Monthly', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'viewMode' => 'hrd',
            'monthlies' => $monthlies,
            'isPublishable' => $isPublishable,
            'isMonitoring' => false,
            'monitoringEmployeeId' => null,
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
        abort_unless((int) $monthly->participant?->kpi_period_id === (int) $period->id, 422, 'Record Monthly tidak berada pada periode ini.');
        abort_if($monthly->status === 'published', 422, 'Monthly yang sudah dipublish tidak dapat diubah.');
        abort_if(
            KpiSignature::where('signable_type', KpiMonthly::class)->where('signable_id', $monthly->id)->exists(),
            422,
            'Monthly yang sudah memiliki tanda tangan tidak dapat diubah.'
        );

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

        DB::transaction(function () use ($monthly, $v, $rateMap, $rewardMap, $period) {
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

            $now = KpiClock::now();
            $isLate = $now->gt($this->monthlyApprovalDeadline($period));

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
            // `attendance_score` has a legacy default of zero; completed_at is
            // the explicit marker that HRD actually saved the HRD component.
            $hasAttendance = ! is_null($m->completed_at);

            if (! $hasMpa || ! $hasAttendance) {
                $incompleteCount++;
            }
        }

        abort_if($incompleteCount > 0, 422, "Gagal mem-publish! Terdapat {$incompleteCount} peserta yang belum lengkap penilaian MPA/Attendance-nya.");

        $now = KpiClock::now();
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
                        'source' => 'automatic',
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
        $monitoringParticipant = $request->filled('karyawan_id')
            ? $this->getParticipantOrTarget($request, $period)
            : (! $isSuperAdmin && $user->karyawan_id
                ? $period->participants()->where('karyawan_id', $user->karyawan_id)->first()
                : null);
        $isMonitoring = $monitoringParticipant !== null;

        $participantsQuery = $period->participants()->with(['karyawan', 'atasanLangsung']);
        if ($isMonitoring) {
            $participantsQuery->whereKey($monitoringParticipant->id);
        }
        $participants = $participantsQuery->get();
        $now = KpiClock::now();
        // Final Score is ready as soon as Monthly is complete. The normal
        // employee review/signature deadline is day 8 of the next month.
        $isLateFinalization = $period->bulan !== null && $now->gt($this->finalApprovalDeadline($period));

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

            $kategori = $totalScoreFinal >= 80.00 ? 'Reward' : 'Punishment';

            // Readiness is per participant. Monthly no longer needs a
            // period-wide publish or a calendar opening date; HRD finalization
            // and its signature are the prerequisite for the final score.
            $isKiReady = $ki && in_array($ki->status, ['submitted', 'approved', 'auto_submitted', 'not_filled']);
            $isOpsReady = $opsItems->isNotEmpty() && $opsItems->every(fn ($item) => in_array($item->status, ['submitted', 'approved', 'locked', 'auto_signed', 'not_filled']));
            $requiredMonthlyRoles = ['hrd_publish', 'employee', 'atasan_langsung'];
            if ($p->atasan_kedua_id) {
                $requiredMonthlyRoles[] = 'atasan_kedua';
            }
            $monthlySignatureRoles = $monthly
                ? KpiSignature::where('signable_type', KpiMonthly::class)
                    ->where('signable_id', $monthly->id)
                    ->pluck('role')
                : collect();
            $monthlySignaturesComplete = $monthly && collect($requiredMonthlyRoles)
                ->every(fn (string $role) => $monthlySignatureRoles->contains($role));
            $isMonthlyReady = $monthly
                && in_array($monthly->status, ['completed', 'published'], true)
                && ! is_null($monthly->completed_at)
                && ! is_null($monthly->mpa_score)
                && ! is_null($monthly->attendance_score)
                && $monthlySignaturesComplete;

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
            $finalSignature = $record ? KpiSignature::where('signable_type', KpiFinalScore::class)
                ->where('signable_id', $record->id)
                ->whereIn('role', ['employee', 'atasan_langsung'])
                ->orderByRaw("CASE WHEN role = 'employee' THEN 0 ELSE 1 END")
                ->latest('signed_at')
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
                'signature' => $finalSignature ? [
                    'role' => $finalSignature->role,
                    'source' => $finalSignature->source,
                    'signed_at' => $finalSignature->signed_at,
                    'signer_name' => User::find($finalSignature->signed_by_user_id)?->name ?? 'System',
                    'signature_path' => $finalSignature->signature_path,
                    'signature_url' => $finalSignature->signature_path
                        ? Storage::disk('public')->url($finalSignature->signature_path)
                        : null,
                ] : null,
            ];
        }

        return inertia('Internal/Kpi/FinalScore', [
            'user' => $this->userPayload($request),
            'period' => $period,
            'scores' => $finalScores,
            'isMonitoring' => (bool) $isMonitoring,
            'monitoringEmployeeId' => $monitoringParticipant?->karyawan_id,
            'employeePeriods' => $monitoringParticipant ? $this->employeePeriodOptions($monitoringParticipant->karyawan_id) : [],
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
            'role' => 'nullable|string|in:employee,atasan_langsung,atasan_kedua,hrd',
        ]);

        $employee = $user->karyawan;
        $signatureSource = 'manual';
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
        $participant = $v['signable_type'] === 'kinerja_ops'
            ? $record->load(['period', 'atasanLangsung.user', 'atasanKedua.user', 'karyawan.user'])
            : $record->participant()->with(['period', 'atasanLangsung.user', 'atasanKedua.user', 'karyawan.user'])->first();
        $period = $participant?->period;
        $isSuperAdmin = $user->role()->value('nama_role') === 'super_admin';

        // Authorization check based on expected signer role in period snapshot
        if ($v['signable_type'] === 'kinerja_individu') {
            $isDirectSupervisor = (int) $user->karyawan_id === (int) $participant->atasan_langsung_id;
            abort_unless($isDirectSupervisor || $isSuperAdmin, 403, 'Hanya Atasan Langsung snapshot atau Super Admin yang berwenang menandatangani Kinerja Individu.');
            abort_if(in_array($record->status, ['approved', 'auto_signed', 'locked'], true), 422, 'Kinerja Individu sudah dikunci.');
            $withinDeadline = $period && ! KpiClock::now()->gt($this->normalEntryDeadline($period));
            $signatureSource = $isDirectSupervisor && $withinDeadline ? 'manual' : 'super_admin_takeover';
            abort_unless($signatureSource === 'manual' || $isSuperAdmin, 403, 'Batas tanda tangan Atasan Langsung telah berakhir.');
        } elseif ($v['signable_type'] === 'kinerja_ops') {
            $participant = $record;
            $period = $participant->period()->first();
            $isOpsEmployee = (int) $user->karyawan_id === (int) $participant->karyawan_id;
            $isOpsSupervisor = (int) $user->karyawan_id === (int) $participant->atasan_langsung_id;
            $requestedRole = $v['role'];

            // Identity from the participant snapshot has priority. A Super
            // Admin who is also the employee/supervisor signs normally; only
            // another Super Admin creates a takeover signature.
            $withinDeadline = $period && ! KpiClock::now()->gt($this->normalEntryDeadline($period));
            if ($requestedRole === 'employee' && $isOpsEmployee && $withinDeadline) {
                $v['role'] = 'employee';
            } elseif ($requestedRole === 'atasan_langsung' && $isOpsSupervisor && $withinDeadline) {
                $v['role'] = 'atasan_langsung';
            } else {
                abort_unless($isSuperAdmin, 403, 'Hanya karyawan, Atasan Langsung snapshot, atau Super Admin yang berwenang menandatangani Kinerja OPS.');
                abort_unless(in_array($requestedRole, ['employee', 'atasan_langsung'], true), 422, 'Peran tanda tangan Kinerja OPS tidak valid.');
                $v['role'] = $requestedRole;
                $signatureSource = 'super_admin_takeover';
            }

            $opsItems = KpiOpsItem::where('kpi_participant_id', $participant->id)->get();
            abort_unless($opsItems->isNotEmpty(), 422, 'Kinerja OPS belum memiliki parameter.');
            abort_unless(
                $opsItems->every(fn (KpiOpsItem $item) => in_array($item->status, ['submitted', 'approved', 'locked', 'auto_signed', 'not_filled'], true)),
                422,
                'Kinerja OPS belum dikirim lengkap oleh pemilik laporan.'
            );
            abort_if(
                KpiSignature::where('signable_type', KpiParticipant::class)
                    ->where('signable_id', $participant->id)
                    ->where('role', $v['role'])
                    ->exists(),
                422,
                'Tanda tangan untuk peran ini sudah tersimpan.'
            );
        } elseif ($v['signable_type'] === 'monthly') {
            $participant = $record->participant()->with(['period'])->first();
            $period = $participant->period;
            abort_unless(in_array($record->status, ['completed', 'published'], true), 422, 'Monthly belum difinalisasi HRD dan belum dapat ditandatangani.');
            abort_unless(KpiSignature::where('signable_type', KpiMonthly::class)->where('signable_id', $record->id)->where('role', 'hrd_publish')->exists(), 422, 'Tanda tangan HRD belum tercatat.');
            // Role is derived from the period snapshot and authenticated
            // employee. The client only sends a generic signature action.
            $withinDeadline = ! KpiClock::now()->gt($this->monthlyApprovalDeadline($period));
            if ((int) $user->karyawan_id === (int) $participant->karyawan_id && $withinDeadline) {
                $v['role'] = 'employee';
            } elseif ((int) $user->karyawan_id === (int) $participant->atasan_langsung_id && $withinDeadline) {
                $v['role'] = 'atasan_langsung';
            } elseif ($participant->atasan_kedua_id && (int) $user->karyawan_id === (int) $participant->atasan_kedua_id && $withinDeadline) {
                $v['role'] = 'atasan_kedua';
            } elseif ($isSuperAdmin) {
                $v['role'] = $v['role'] ?: 'atasan_langsung';
                $signatureSource = 'super_admin_takeover';
            } else {
                abort(403, 'Batas tanda tangan normal telah berakhir atau Anda bukan pihak yang berwenang.');
            }
            if ($signatureSource === 'super_admin_takeover') {
                // The source is already established from the authenticated
                // Super Admin; do not re-apply the normal signer identity.
            } elseif ($v['role'] === 'employee') {
                abort_unless($user->karyawan_id === $participant->karyawan_id, 403, 'Anda bukan karyawan bersangkutan.');
            } elseif ($v['role'] === 'atasan_langsung') {
                abort_unless($user->karyawan_id === $participant->atasan_langsung_id, 403, 'Anda bukan Atasan Langsung karyawan.');
            } elseif ($v['role'] === 'atasan_kedua') {
                abort_unless($participant->atasan_kedua_id && $user->karyawan_id === $participant->atasan_kedua_id, 403, 'Anda bukan Atasan Kedua karyawan.');
            } else {
                abort(403, 'Peran ini tidak memiliki kewenangan menandatangani Monthly secara manual.');
            }
            abort_if(
                KpiSignature::where('signable_type', KpiMonthly::class)
                    ->where('signable_id', $record->id)
                    ->where('role', $v['role'])
                    ->exists(),
                422,
                'Tanda tangan Monthly untuk peran ini sudah tersimpan.'
            );
        } elseif ($v['signable_type'] === 'final_score') {
            $participant = $record->participant()->with(['period'])->first();
            $period = $participant->period;
            $withinDeadline = ! KpiClock::now()->gt($this->finalApprovalDeadline($period));
            if ((int) $user->karyawan_id === (int) $participant->karyawan_id && $withinDeadline) {
                $v['role'] = 'employee';
            } elseif ((int) $user->karyawan_id === (int) $participant->atasan_langsung_id && $withinDeadline) {
                $v['role'] = 'atasan_langsung';
            } elseif ($isSuperAdmin) {
                $v['role'] = $v['role'] ?: 'atasan_langsung';
                $signatureSource = 'super_admin_takeover';
            } else {
                abort(403, 'Batas tanda tangan Nilai Akhir telah berakhir atau Anda bukan pihak yang berwenang.');
            }
            abort_if(KpiSignature::where('signable_type', KpiFinalScore::class)->where('signable_id', $record->id)->where('role', $v['role'])->exists(), 422, 'Tanda tangan Nilai Akhir untuk peran ini sudah tersimpan.');
        }

        $signaturePath = $employee->foto_tanda_tangan;
        if ($v['signable_type'] === 'kinerja_individu') {
            $source = preg_replace('#^/?storage/#', '', trim((string) $employee->foto_tanda_tangan));
            abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Tanda tangan Atasan Langsung belum tersedia.');
            $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = 'kpi/individual-signatures/'.$record->id.'_'.KpiClock::now()->format('YmdHisv').'.'.$ext;
            abort_unless(Storage::disk('public')->copy($source, $signaturePath), 422, 'Snapshot tanda tangan gagal disimpan.');
        } elseif ($v['signable_type'] === 'kinerja_ops') {
            $source = preg_replace('#^/?storage/#', '', trim((string) $employee->foto_tanda_tangan));
            abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Foto tanda tangan belum tersedia.');
            $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = 'kpi/ops-signatures/'.$record->id.'_'.$v['role'].'_'.KpiClock::now()->format('YmdHisv').'.'.$ext;
            abort_unless(Storage::disk('public')->copy($source, $signaturePath), 422, 'Snapshot tanda tangan Kinerja OPS gagal disimpan.');
        } elseif ($v['signable_type'] === 'monthly') {
            $source = preg_replace('#^/?storage/#', '', trim((string) $employee->foto_tanda_tangan));
            abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Foto tanda tangan belum tersedia.');
            $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = 'kpi/monthly-signatures/'.$record->id.'_'.$v['role'].'_'.KpiClock::now()->format('YmdHisv').'.'.$ext;
            abort_unless(Storage::disk('public')->copy($source, $signaturePath), 422, 'Snapshot tanda tangan Monthly gagal disimpan.');
        } elseif ($v['signable_type'] === 'final_score') {
            $source = preg_replace('#^/?storage/#', '', trim((string) $employee->foto_tanda_tangan));
            abort_unless($source !== '' && Storage::disk('public')->exists($source), 422, 'Foto tanda tangan belum tersedia.');
            $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = 'kpi/final-signatures/'.$record->id.'_'.$v['role'].'_'.KpiClock::now()->format('YmdHisv').'.'.$ext;
            abort_unless(Storage::disk('public')->copy($source, $signaturePath), 422, 'Snapshot tanda tangan Nilai Akhir gagal disimpan.');
        }

        KpiSignature::firstOrCreate(
            [
                'signable_type' => $modelClass,
                'signable_id' => $record->id,
                'role' => $v['role'],
            ],
            [
                'source' => $signatureSource,
                'signed_for_user_id' => $v['signable_type'] === 'kinerja_individu' ? $participant->karyawan?->user?->id : $user->id,
                'signed_by_user_id' => $user->id,
                'signature_path' => $signaturePath,
                'signed_at' => KpiClock::now(),
                'reason' => 'Manual Signature',
            ]
        );

        if ($v['signable_type'] === 'kinerja_individu') {
            $record->update(['status' => 'approved']);
        } elseif ($v['signable_type'] === 'kinerja_ops') {
            $signaturesComplete = KpiSignature::where('signable_type', KpiParticipant::class)
                ->where('signable_id', $participant->id)
                ->whereIn('role', ['employee', 'atasan_langsung'])
                ->count() >= 2;
            if ($signaturesComplete) {
                $opsItems->where('status', 'submitted')->each(fn (KpiOpsItem $item) => $item->update(['status' => 'locked']));
            }
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
            $kategori = $totalScoreFinal >= 80.00 ? 'Reward' : 'Punishment';

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
                    'url' => route('dashboard.kpi.monthly', ['period' => $activePeriod->id, 'mode' => 'hrd']),
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
            $nowDate = KpiClock::now();
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
     * Build a read-only health snapshot for one KPI period. All organisation
     * attributes come from the participant snapshot; this method never mutates
     * workflow records.
     */
    private function periodMonitoringPayload(KpiPeriod $period, bool $withParticipants, bool $includeActions = true): array
    {
        $participants = $period->participants
            ->filter(fn (KpiParticipant $participant) => $participant->karyawan_id && $participant->karyawan)
            ->values();
        $total = $participants->count();
        $participantIds = $participants->pluck('id');
        $employeeIds = $participants->pluck('karyawan_id');
        $today = KpiClock::today()->startOfDay();
        $periodStart = Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();
        $relevantEnd = $periodEnd->copy()->min($today->copy()->endOfDay());

        $attendance = Absensi::query()
            ->whereIn('karyawan_id', $employeeIds)
            ->where('status_kehadiran', 'H')
            ->whereBetween('tanggal_absensi', [$periodStart->toDateString(), $relevantEnd->toDateString()])
            ->get(['karyawan_id', 'tanggal_absensi'])
            ->groupBy('karyawan_id');
        $dailyReports = KpiDailyReport::query()
            ->whereIn('karyawan_id', $employeeIds)
            ->whereBetween('tanggal', [$periodStart->toDateString(), $relevantEnd->toDateString()])
            ->get(['karyawan_id', 'tanggal', 'status'])
            ->groupBy('karyawan_id');
        $finalScores = KpiFinalScore::query()->whereIn('kpi_participant_id', $participantIds)->get()->keyBy('kpi_participant_id');
        $finalSignatures = KpiSignature::query()
            ->where('signable_type', KpiFinalScore::class)
            ->whereIn('signable_id', $finalScores->pluck('id'))
            ->get()->groupBy('signable_id');

        $hasSubordinate = $participants->groupBy('atasan_langsung_id')->map->count();
        $kiIds = $participants->pluck('individualScore.id')->filter();
        $kiSignatures = KpiSignature::query()->where('signable_type', KpiIndividualScore::class)->whereIn('signable_id', $kiIds)->get()->groupBy('signable_id');
        $opsSignatures = KpiSignature::query()->where('signable_type', KpiParticipant::class)->whereIn('signable_id', $participantIds)->get()->groupBy('signable_id');

        $issueCounts = [];
        $rows = [];
        $finalCompleteCount = 0;
        $stageCounts = array_fill_keys(['daily', 'individual', 'ops', 'mpa', 'monthly', 'final'], 0);
        $actionParticipantIds = collect();
        $addIssue = function (string $key, string $label, ?int $participantId = null) use (&$issueCounts, $actionParticipantIds): void {
            $issueCounts[$key] ??= ['key' => $key, 'label' => $label, 'count' => 0];
            $issueCounts[$key]['count']++;
        };
        if (! $period->mpa_evaluator_id) {
            $addIssue('missing_evaluator', 'Penilai MPA belum ditetapkan');
        }

        foreach ($participants as $participant) {
            $issues = [];
            $employee = $participant->karyawan;
            $account = $employee?->user;
            if (! $participant->atasan_langsung_id) {
                $issues[] = 'Struktur atasan tidak lengkap';
                $addIssue('missing_supervisor', 'Struktur atasan tidak lengkap', $participant->id);
                $actionParticipantIds->push($participant->id);
            }
            if (! $account || ! $account->is_active) {
                $issues[] = 'Akun karyawan belum aktif';
                $addIssue('inactive_account', 'Akun karyawan belum aktif', $participant->id);
                $actionParticipantIds->push($participant->id);
            }

            $attendanceDates = collect($attendance->get($participant->karyawan_id, collect()))->map(fn ($row) => Carbon::parse($row->tanggal_absensi)->toDateString());
            $requiredDaily = $attendanceDates->unique()->count();
            $completedDaily = collect($dailyReports->get($participant->karyawan_id, collect()))
                ->filter(fn ($report) => in_array($report->status, ['approved'], true) && $attendanceDates->contains(Carbon::parse($report->tanggal)->toDateString()))
                ->pluck('tanggal')->map(fn ($date) => Carbon::parse($date)->toDateString())->unique()->count();
            $dailyComplete = $completedDaily >= $requiredDaily;
            if ($dailyComplete) $stageCounts['daily']++;

            $individual = $participant->individualScore;
            $individualStatus = $individual?->status;
            $individualDone = in_array($individualStatus, ['approved', 'auto_signed', 'locked'], true);
            if ($individualDone) $stageCounts['individual']++;
            $individualLabel = $individualDone ? ($kiSignatures->get($individual?->id, collect())->contains(fn ($s) => $s->source === 'super_admin_takeover') ? 'Dialihkan' : 'Selesai') : match ($individualStatus) {
                'submitted' => 'Menunggu Approval', 'draft' => 'Draft', 'not_filled' => 'Belum', default => 'Belum',
            };

            $opsItems = $participant->opsItems;
            $opsDone = $opsItems->isNotEmpty() && $opsItems->every(fn (KpiOpsItem $item) => in_array($item->status, ['approved', 'auto_signed', 'locked'], true));
            $opsLabel = $opsItems->isEmpty() ? 'Parameter Kosong' : ($opsDone ? ($opsSignatures->get($participant->id, collect())->contains(fn ($s) => $s->source === 'super_admin_takeover') ? 'Dialihkan' : 'Selesai') : ($opsItems->contains(fn ($item) => $item->status === 'submitted') ? 'Menunggu Approval' : ($opsItems->contains(fn ($item) => filled($item->aktivitas) || ! is_null($item->hasil)) ? 'Draft' : 'Belum')));
            if ($opsDone) $stageCounts['ops']++;
            if ($opsItems->isEmpty()) { $issues[] = 'Parameter OPS belum ditetapkan'; $addIssue('missing_ops_parameter', 'Parameter OPS belum ditetapkan', $participant->id); $actionParticipantIds->push($participant->id); }

            $monthly = $participant->monthly;
            $initialReady = $this->isMpaInitialHrdReady($monthly);
            $leadershipRequired = $hasSubordinate->has($participant->karyawan_id);
            $evaluatorComplete = $monthly && filled($monthly->kinerja_operasional) && filled($monthly->sikap_kerja) && filled($monthly->team_work) && filled($monthly->inisiatif) && filled($monthly->performance) && filled($monthly->coaching) && (! $leadershipRequired || filled($monthly->kepemimpinan));
            $mpaDone = in_array($monthly?->status, ['completed', 'published'], true);
            if ($mpaDone) $stageCounts['mpa']++;
            $mpaLabel = $mpaDone ? 'Selesai' : (! $initialReady ? 'Belum Siap' : (! $evaluatorComplete ? 'Siap Dinilai' : 'Siap Finalisasi'));
            if ($monthly && $initialReady && ! $evaluatorComplete && $monthly->status === 'draft') $mpaLabel = 'Sedang Dinilai';
            if (! $period->mpa_evaluator_id) { $issues[] = 'Penilai MPA belum ditetapkan'; $actionParticipantIds->push($participant->id); }
            if ($initialReady && $evaluatorComplete && ! $mpaDone) { $addIssue('mpa_finalize', 'MPA siap difinalisasi HRD', $participant->id); $actionParticipantIds->push($participant->id); }

            $requiredRoles = ['hrd_publish', 'atasan_langsung'];
            if ($participant->atasan_kedua_id) $requiredRoles[] = 'atasan_kedua';
            $requiredRoles[] = 'employee';
            $signedRoles = $monthly?->signatures?->pluck('role')->unique() ?? collect();
            $signedCount = collect($requiredRoles)->filter(fn ($role) => $signedRoles->contains($role))->count();
            $monthlyDone = $mpaDone && $signedCount === count($requiredRoles);
            if ($monthlyDone) $stageCounts['monthly']++;
            $monthlyLabel = ! $mpaDone ? 'Belum Siap' : ($monthlyDone ? 'Selesai' : "{$signedCount} / ".count($requiredRoles).' TTD');
            if ($mpaDone && ! $monthlyDone) { $addIssue('waiting_signature', 'Menunggu tanda tangan', $participant->id); $actionParticipantIds->push($participant->id); }

            $final = $finalScores->get($participant->id);
            $finalEmployeeSigned = $final && $finalSignatures->get($final->id, collect())->contains(fn ($signature) => $signature->role === 'employee');
            $finalReady = $monthlyDone && $final;
            $finalDone = (bool) ($finalReady && $finalEmployeeSigned);
            if ($finalDone) $stageCounts['final']++;
            if ($dailyComplete && $individualDone && $opsDone && $mpaDone && $monthlyDone && $finalDone) $finalCompleteCount++;
            $finalLabel = ! $finalReady ? 'Belum Siap' : ($finalDone ? number_format((float) $final->score, 2).' · Selesai' : 'Menunggu TTD');
            $category = $finalDone ? ($final->kategori ?: '—') : '—';

            $waiting = 'Selesai';
            if (! $dailyComplete) $waiting = $individualDone ? 'Karyawan' : 'Karyawan';
            elseif (! $individualDone) $waiting = $individualStatus === 'submitted' ? 'Atasan Langsung' : 'Karyawan';
            elseif (! $opsDone) $waiting = $opsItems->isEmpty() ? 'HRD' : ($opsItems->contains(fn ($item) => $item->status === 'submitted') ? 'Atasan Langsung' : 'Karyawan');
            elseif (! $initialReady) $waiting = 'HRD';
            elseif (! $evaluatorComplete) $waiting = 'Penilai MPA';
            elseif (! $mpaDone) $waiting = 'HRD Finalisasi';
            elseif (! $monthlyDone) {
                $missingRole = collect($requiredRoles)->first(fn ($role) => ! $signedRoles->contains($role));
                $waiting = match ($missingRole) {
                    'hrd_publish' => 'HRD',
                    'atasan_langsung' => 'Atasan Langsung',
                    'atasan_kedua' => 'Atasan Kedua',
                    default => 'Karyawan',
                };
            } elseif (! $finalDone) $waiting = 'Karyawan';

            $overdue = (! $individualDone && KpiClock::now()->gt($this->normalEntryDeadline($period)))
                || (! $opsDone && KpiClock::now()->gt($this->normalEntryDeadline($period)))
                || ($mpaDone && ! $monthlyDone && KpiClock::now()->gt($this->monthlyApprovalDeadline($period)))
                || ($finalReady && ! $finalDone && KpiClock::now()->gt($this->finalApprovalDeadline($period)));
            if ($overdue) {
                $issues[] = 'Melewati deadline normal';
                $addIssue('overdue', 'Melewati deadline normal', $participant->id);
                $actionParticipantIds->push($participant->id);
            }

            if ($withParticipants) {
                $rows[] = [
                    'id' => $participant->id, 'karyawan_id' => $participant->karyawan_id,
                    'nama' => $employee?->nama ?? 'Karyawan', 'jabatan' => $participant->jabatan_snapshot ?: '-',
                    'daily' => ['completed' => $completedDaily, 'required' => $requiredDaily, 'complete' => $dailyComplete],
                    'individual_status' => $individualLabel, 'ops_status' => $opsLabel, 'mpa_status' => $mpaLabel,
                    'monthly_status' => $monthlyLabel, 'final_status' => $finalLabel, 'category' => $category,
                    'waiting_for' => $waiting, 'problems' => array_values(array_unique($issues)),
                    'detail_url' => $includeActions ? route('dashboard.kpi.daily', ['karyawan_id' => $participant->karyawan_id, 'period_id' => $period->id]) : null,
                ];
            }
        }

        $percentage = fn (int $value): int => $total > 0 ? (int) round(($value / $total) * 100) : 0;
        $progress = [];
        foreach ([
            'daily' => 'Daily Report', 'individual' => 'Kinerja Individu', 'ops' => 'Kinerja OPS',
            'mpa' => 'MPA', 'monthly' => 'Monthly', 'final' => 'Nilai Akhir',
        ] as $key => $label) {
            $progress[$key] = ['label' => $label, 'complete' => $stageCounts[$key], 'total' => $total, 'percentage' => $percentage($stageCounts[$key])];
        }
        $completeParticipants = $finalCompleteCount;
        $actionCount = $actionParticipantIds->unique()->count() + ($period->mpa_evaluator_id ? 0 : 1);
        $overallPercentage = $total > 0 ? (int) round(collect($stageCounts)->sum() / ($total * count($stageCounts)) * 100) : 0;
        $statusKey = $total > 0 && $completeParticipants === $total ? 'completed' : ($total === 0 ? 'draft' : 'active');
        $statusLabels = ['draft' => 'Persiapan', 'active' => 'Dalam Proses', 'completed' => 'Selesai'];

        return [
            'id' => $period->id, 'bulan' => $period->bulan, 'tahun' => $period->tahun,
            'status_key' => $statusKey, 'status_label' => $statusLabels[$statusKey],
            'evaluator_name' => $period->evaluator?->karyawan?->nama ?? $period->evaluator?->name,
            'evaluator_status' => $period->mpa_evaluator_id ? 'Berjalan' : 'Belum ditetapkan',
            'summary' => ['participants' => $total, 'completed' => $completeParticipants, 'action' => $actionCount, 'overall_percentage' => $overallPercentage],
            'progress' => $progress,
            'action_items' => collect($issueCounts)->filter(fn ($item) => $item['count'] > 0)->values()->all(),
            'configuration_errors' => collect($issueCounts)->sum('count'),
            'configuration_issues' => collect($issueCounts)->values()->all(),
            'participants' => $withParticipants ? $rows : [],
        ];
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
    private function employeePeriodOptions(int $karyawanId): array
    {
        $periods = KpiPeriod::query()
            ->whereHas('participants', fn ($query) => $query->where('karyawan_id', $karyawanId))
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get(['id', 'bulan', 'tahun']);
        $clockToday = KpiClock::today();
        $activeId = $periods
            ->first(fn (KpiPeriod $period) => (int) $period->tahun === (int) $clockToday->year && (int) $period->bulan === (int) $clockToday->month)
            ?->id ?? $periods->last()?->id;
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $periods->map(fn (KpiPeriod $period): array => [
            'id' => $period->id,
            'bulan' => $period->bulan,
            'tahun' => $period->tahun,
            'label' => ($months[((int) $period->bulan) - 1] ?? $period->bulan).' '.$period->tahun,
            'is_active' => (int) $period->id === (int) $activeId,
        ])->values()->all();
    }

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

    /** Normal entry target/deadline: period end through day one next month. */
    private function normalEntryDeadline(KpiPeriod $period): Carbon
    {
        return Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
            ->addMonthNoOverflow()->startOfMonth()->endOfDay();
    }

    /** Deadline for normal Monthly supervisor signatures (day 7 next month). */
    private function monthlyApprovalDeadline(KpiPeriod $period): Carbon
    {
        return Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
            ->addMonthNoOverflow()->startOfMonth()->addDays(6)->endOfDay();
    }

    /** Deadline for normal Final Score employee review/signature (day 8 next month). */
    private function finalApprovalDeadline(KpiPeriod $period): Carbon
    {
        return Carbon::create($period->tahun, $period->bulan, 1, 0, 0, 0, 'Asia/Jakarta')
            ->addMonthNoOverflow()->startOfMonth()->addDays(7)->endOfDay();
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
