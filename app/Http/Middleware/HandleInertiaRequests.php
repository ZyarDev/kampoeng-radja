<?php

namespace App\Http\Middleware;

use App\Support\AttendanceAccess;
use App\Support\CmsAccess;
use App\Support\ClosingEventAccess;
use App\Support\KpiClock;
use App\Services\KpiWorkingPeriodResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $closingEventPermissions = app(ClosingEventAccess::class)->for($request->user());
        $attendancePermissions = app(AttendanceAccess::class)->for($request->user());
        $cmsPermissions = app(CmsAccess::class)->for($request->user());

        $user = $request->user();
        // Keep calendar-active and KPI-working periods separate. During the
        // first days of a new month the sidebar should continue the prior
        // period's closing work, while Daily/Absensi can still use calendar
        // time through the separate activePeriodId prop.
        $clockToday = KpiClock::today();
        $periods = \App\Models\KpiPeriod::query()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();
        $workingPeriodResolver = app(KpiWorkingPeriodResolver::class);
        $activePeriod = $workingPeriodResolver->activeCalendarPeriod($periods, $clockToday);
        $workingPeriod = $workingPeriodResolver->resolveDefault($periods, $clockToday);
        $roleName = $user?->role()->value('nama_role');
        $isHrdOrAdmin = in_array($roleName, ['admin', 'super_admin'], true) ||
            in_array(mb_strtolower(trim($user?->karyawan?->jabatan?->nama_jabatan ?? '')), ['hrd', 'direktur', 'dirut', 'direktur utama'], true);
        $isHrdOrDirektur = $roleName === 'super_admin' ||
            in_array(mb_strtolower(trim($user?->karyawan?->jabatan?->nama_jabatan ?? '')), ['hrd', 'direktur', 'dirut', 'direktur utama'], true);

        $isSupervisor = false;
        if ($user?->karyawan_id && $workingPeriod) {
            $isSupervisor = \App\Models\KpiParticipant::where('kpi_period_id', $workingPeriod->id)
                ->where('atasan_langsung_id', $user->karyawan_id)
                ->exists();
        }

        $isMpaEvaluator = false;
        if ($user && $workingPeriod) {
            $isMpaEvaluator = $workingPeriod->mpa_evaluator_id === $user->id;
        }

        $hasPersonalKpiParticipant = false;
        if ($user?->karyawan_id && $workingPeriod) {
            $hasPersonalKpiParticipant = \App\Models\KpiParticipant::query()
                ->where('kpi_period_id', $workingPeriod->id)
                ->where('karyawan_id', $user->karyawan_id)
                ->exists();
        }
        $normalizedPosition = mb_strtolower(trim((string) ($user?->karyawan?->jabatan?->nama_jabatan ?? '')));
        $canViewPersonalKpi = $hasPersonalKpiParticipant
            && ! in_array($normalizedPosition, ['komisaris', 'dirut', 'direktur', 'direktur utama'], true);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'closingEvent' => $closingEventPermissions,
                'attendance' => $attendancePermissions,
                'cms' => $cmsPermissions,
                'employeeMasters' => [
                    'canManage' => $roleName === 'super_admin',
                ],
                'kpi' => [
                    'activePeriodId' => $activePeriod?->id,
                    'workingPeriodId' => $workingPeriod?->id,
                    'personalEmployeeId' => $user?->karyawan_id,
                    'canViewPersonal' => $canViewPersonalKpi,
                    'canManagePeriod' => $roleName === 'super_admin',
                    'canViewPeriod' => $isHrdOrAdmin || $roleName === 'user',
                    'canAccessMpa' => $isMpaEvaluator || $isHrdOrDirektur,
                    'isSupervisor' => $isSupervisor,
                    'isMpaEvaluator' => $isMpaEvaluator,
                    'isHrdOrAdmin' => $isHrdOrAdmin,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
            ],
        ];
    }
}
