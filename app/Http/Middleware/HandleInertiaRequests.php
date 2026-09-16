<?php

namespace App\Http\Middleware;

use App\Support\AttendanceAccess;
use App\Support\ClosingEventAccess;
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
        $activePeriod = \App\Models\KpiPeriod::latest('id')->first();
        $roleName = $user?->role()->value('nama_role');
        $isHrdOrAdmin = in_array($roleName, ['admin', 'super_admin'], true) ||
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'closingEvent' => $closingEventPermissions,
                'attendance' => $attendancePermissions,
                'cms' => [
                    'canManage' => $cmsCanManage,
                ],
                'kpi' => [
                    'activePeriodId' => $activePeriod?->id,
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
