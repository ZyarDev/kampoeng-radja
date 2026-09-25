<?php

namespace App\Http\Middleware;

use App\Support\AttendanceAccess;
use App\Support\ClosingEventAccess;
use App\Support\KpiClock;
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
        $cmsCanManage = in_array(
            $request->user()?->role()->value('nama_role'),
            ['admin', 'super_admin'],
            true,
        );

        $user = $request->user();
        // The sidebar must open the KPI period currently in progress. Using
        // the latest database id can keep a user on an older month when a
        // newer period has been created out of order or imported later.
        $clockToday = KpiClock::today();
        $activePeriod = \App\Models\KpiPeriod::query()
            ->where('tahun', $clockToday->year)
            ->where('bulan', $clockToday->month)
            ->first()
            ?? \App\Models\KpiPeriod::latest('id')->first();
        $roleName = $user?->role()->value('nama_role');
        $isHrdOrAdmin = in_array($roleName, ['admin', 'super_admin'], true) ||
            in_array(mb_strtolower(trim($user?->karyawan?->jabatan?->nama_jabatan ?? '')), ['hrd', 'direktur', 'dirut', 'direktur utama'], true);
        $isHrdOrDirektur = $roleName === 'super_admin' ||
            in_array(mb_strtolower(trim($user?->karyawan?->jabatan?->nama_jabatan ?? '')), ['hrd', 'direktur', 'dirut', 'direktur utama'], true);

        $isSupervisor = false;
        if ($user?->karyawan_id && $activePeriod) {
            $isSupervisor = \App\Models\KpiParticipant::where('kpi_period_id', $activePeriod->id)
                ->where('atasan_langsung_id', $user->karyawan_id)
                ->exists();
        }

        $isMpaEvaluator = false;
        if ($user && $activePeriod) {
            $isMpaEvaluator = $activePeriod->mpa_evaluator_id === $user->id;
        }

        $hasPersonalKpiParticipant = false;
        if ($user?->karyawan_id && $activePeriod) {
            $hasPersonalKpiParticipant = \App\Models\KpiParticipant::query()
                ->where('kpi_period_id', $activePeriod->id)
                ->where('karyawan_id', $user->karyawan_id)
                ->exists();
        }
        $normalizedPosition = mb_strtolower(trim((string) ($user?->karyawan?->jabatan?->nama_jabatan ?? '')));
        $canViewPersonalKpi = $hasPersonalKpiParticipant
            && ! in_array($normalizedPosition, ['dirut', 'direktur', 'direktur utama'], true);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'closingEvent' => $closingEventPermissions,
                'attendance' => $attendancePermissions,
                'cms' => [
                    'canManage' => $cmsCanManage,
                ],
                'employeeMasters' => [
                    'canManage' => $roleName === 'super_admin',
                ],
                'kpi' => [
                    'activePeriodId' => $activePeriod?->id,
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
