<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\ClosingEvent;
use App\Models\Karyawan;
use App\Support\AttendanceAccess;
use App\Support\ClosingEventAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        AttendanceAccess $attendanceAccess,
        ClosingEventAccess $closingEventAccess,
    ): Response {
        $authUser = $request->user()->loadMissing([
            'role:id,nama_role',
            'karyawan.jabatan:id,nama_jabatan',
            'karyawan.departemen:id,nama_departemen',
        ]);
        $employee = $authUser->karyawan;

        abort_unless($employee && $authUser->role, 403);

        $today = CarbonImmutable::today('Asia/Jakarta');
        $roleName = mb_strtolower($authUser->role->nama_role);
        $showsOrganizationWidgets = in_array($roleName, ['super_admin', 'admin'], true);
        $attendancePermissions = $attendanceAccess->for($authUser);
        $closingEventPermissions = $closingEventAccess->for($authUser);

        $employeeSummary = null;
        $attendanceSummary = null;

        if ($showsOrganizationWidgets) {
            $activeEmployees = Karyawan::query()->where('status_keaktifan', 'aktif')->count();
            $totalEmployees = Karyawan::query()->count();
            $attendanceCounts = $this->attendanceCounts($today, $activeEmployees);

            $employeeSummary = [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'presentToday' => $attendanceCounts['hadir'],
                'lateToday' => $attendanceCounts['terlambat'],
                'absentToday' => $attendanceCounts['izin'] + $attendanceCounts['alpha'],
                'presentPercentage' => $this->percentage($attendanceCounts['hadir'], $activeEmployees),
                'latePercentage' => $this->percentage($attendanceCounts['terlambat'], $activeEmployees),
                'absentPercentage' => $this->percentage($attendanceCounts['izin'] + $attendanceCounts['alpha'], $activeEmployees),
            ];
            $attendanceSummary = [
                'activeEmployees' => $activeEmployees,
                'hadir' => $this->attendanceMetric($attendanceCounts['hadir'], $activeEmployees),
                'izin' => $this->attendanceMetric($attendanceCounts['izin'], $activeEmployees),
                'alpha' => $this->attendanceMetric($attendanceCounts['alpha'], $activeEmployees),
                'terlambat' => $this->attendanceMetric($attendanceCounts['terlambat'], $activeEmployees),
                'pulangAwal' => $this->attendanceMetric($attendanceCounts['pulang_awal'], $activeEmployees),
            ];
        }

        $ownAttendance = null;

        if (! $showsOrganizationWidgets) {
            $record = Absensi::query()
                ->where('karyawan_id', $employee->id)
                ->whereDate('tanggal_absensi', $today)
                ->first(['status_kehadiran', 'jam_masuk', 'jam_keluar', 'keterangan']);

            $ownAttendance = [
                'status' => $record?->status_kehadiran,
                'label' => match ($record?->status_kehadiran) {
                    'H' => 'Hadir',
                    'I' => 'Izin',
                    'A' => 'Alfa',
                    default => 'Belum diinput',
                },
                'clockIn' => $this->formatTime($record?->jam_masuk),
                'clockOut' => $this->formatTime($record?->jam_keluar),
                'note' => $record?->keterangan,
            ];
        }

        $revenueChart = null;
        $closingEventSummary = null;

        if ($closingEventPermissions['canView']) {
            [$selectedMonth, $selectedYear] = $this->selectedPeriod($request, $today);
            $revenueChart = $this->revenueChart($selectedMonth, $selectedYear, $today);
            $closingEventSummary = $this->closingEventSummary($today);
        }

        $displayName = trim((string) ($employee->nama ?: $authUser->username ?: 'User'));

        return Inertia::render('Internal/Dashboard/Index', [
            'user' => [
                'name' => $displayName,
                'initials' => $this->initials($displayName),
                'position' => $employee->jabatan?->nama_jabatan,
                'roleName' => $roleName,
                'roleLabel' => str($roleName)->replace('_', ' ')->title()->toString(),
                'viewBadge' => $roleName === 'super_admin' ? 'SUPER ADMIN VIEW' : null,
            ],
            'permissions' => [
                'showsOrganizationWidgets' => $showsOrganizationWidgets,
                'canViewEmployee' => true,
                'canManageEmployeeMasters' => $roleName === 'super_admin',
                'canViewAttendance' => $attendancePermissions['canView'],
                'canManageAttendance' => $attendancePermissions['canManage'],
                'canViewClosingEvent' => $closingEventPermissions['canView'],
                'canManageClosingEventMaster' => $closingEventPermissions['canManageMaster'],
            ],
            'employeeSummary' => $employeeSummary,
            'attendanceSummary' => $attendanceSummary,
            'ownAttendance' => $ownAttendance,
            'revenueChart' => $revenueChart,
            'closingEventSummary' => $closingEventSummary,
        ]);
    }

    /** @return array{hadir:int,izin:int,alpha:int,terlambat:int,pulang_awal:int} */
    private function attendanceCounts(CarbonImmutable $today, int $activeEmployees): array
    {
        if ($activeEmployees === 0) {
            return ['hadir' => 0, 'izin' => 0, 'alpha' => 0, 'terlambat' => 0, 'pulang_awal' => 0];
        }

        $counts = Absensi::query()
            ->whereDate('tanggal_absensi', $today)
            ->whereIn('karyawan_id', Karyawan::query()->where('status_keaktifan', 'aktif')->select('id'))
            ->selectRaw("SUM(CASE WHEN status_kehadiran = 'H' THEN 1 ELSE 0 END) AS hadir")
            ->selectRaw("SUM(CASE WHEN status_kehadiran = 'I' THEN 1 ELSE 0 END) AS izin")
            ->selectRaw("SUM(CASE WHEN status_kehadiran = 'A' THEN 1 ELSE 0 END) AS alpha")
            ->selectRaw("SUM(CASE WHEN status_kehadiran = 'H' AND jam_masuk IS NOT NULL AND jam_masuk > '08:30:00' THEN 1 ELSE 0 END) AS terlambat")
            ->selectRaw("SUM(CASE WHEN status_kehadiran = 'H' AND jam_keluar IS NOT NULL AND jam_keluar < '16:30:00' THEN 1 ELSE 0 END) AS pulang_awal")
            ->first();

        return [
            'hadir' => (int) ($counts?->hadir ?? 0),
            'izin' => (int) ($counts?->izin ?? 0),
            'alpha' => (int) ($counts?->alpha ?? 0),
            'terlambat' => (int) ($counts?->terlambat ?? 0),
            'pulang_awal' => (int) ($counts?->pulang_awal ?? 0),
        ];
    }

    /** @return array{count:int,percentage:float} */
    private function attendanceMetric(int $count, int $activeEmployees): array
    {
        return ['count' => $count, 'percentage' => $this->percentage($count, $activeEmployees)];
    }

    private function percentage(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round(($count / $total) * 100, 1);
    }

    /** @return array{0:int,1:int} */
    private function selectedPeriod(Request $request, CarbonImmutable $today): array
    {
        $month = filter_var($request->query('month'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12],
        ]);
        $year = filter_var($request->query('year'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 2000, 'max_range' => 2100],
        ]);

        return [$month ?: $today->month, $year ?: $today->year];
    }

    /** @return array<string,mixed> */
    private function revenueChart(int $month, int $year, CarbonImmutable $today): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $dailyTotals = ClosingEvent::query()
            ->active()
            ->whereBetween('tanggal', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw('DATE(tanggal) AS event_date, SUM(harga_total) AS total')
            ->groupByRaw('DATE(tanggal)')
            ->pluck('total', 'event_date');

        $series = collect(range(1, $periodStart->daysInMonth))
            ->map(function (int $day) use ($periodStart, $dailyTotals): array {
                $date = $periodStart->setDay($day);

                return [
                    'date' => $date->toDateString(),
                    'day' => $day,
                    'dateLabel' => $date->locale('id')->translatedFormat('j F Y'),
                    'value' => (float) ($dailyTotals[$date->toDateString()] ?? 0),
                ];
            });
        $total = (float) $series->sum('value');
        $highest = $series->sortByDesc('value')->first();

        $earliestEvent = ClosingEvent::query()->min('tanggal');
        $latestEvent = ClosingEvent::query()->max('tanggal');
        $minimumYear = min($today->year - 4, $earliestEvent ? CarbonImmutable::parse($earliestEvent)->year : $today->year);
        $maximumYear = max($today->year + 1, $latestEvent ? CarbonImmutable::parse($latestEvent)->year : $today->year);

        return [
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'monthLabel' => $periodStart->locale('id')->translatedFormat('F Y'),
            'yearOptions' => range($maximumYear, $minimumYear),
            'series' => $series->values()->all(),
            'summary' => [
                'total' => $total,
                'highestDay' => $total > 0 ? [
                    'date' => $highest['date'],
                    'dateLabel' => $highest['dateLabel'],
                    'value' => $highest['value'],
                ] : null,
                'daysWithoutTransactions' => $series->where('value', 0.0)->count(),
            ],
        ];
    }

    /** @return array{eventsThisMonth:int,ongoingToday:int,cancelledThisMonth:int,visitorsThisMonth:int} */
    private function closingEventSummary(CarbonImmutable $today): array
    {
        $periodStart = $today->startOfMonth();
        $periodEnd = $today->endOfMonth();
        $periodScope = ClosingEvent::query()
            ->active()
            ->overlapping($periodStart, $periodEnd);
        $cancelledThisMonth = ClosingEvent::query()
            ->where('status_event', ClosingEvent::STATUS_CANCELLED)
            ->overlapping($periodStart, $periodEnd)
            ->count();
        $ongoingToday = ClosingEvent::query()
            ->active()
            ->whereDate('tanggal', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('tanggal_selesai', '>=', $today)
                    ->orWhere(function (Builder $query) use ($today): void {
                        $query->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $today);
                    });
            })
            ->count();

        return [
            'eventsThisMonth' => (clone $periodScope)->count(),
            'ongoingToday' => $ongoingToday,
            'cancelledThisMonth' => $cancelledThisMonth,
            'visitorsThisMonth' => (int) (clone $periodScope)->sum('jumlah_pengunjung'),
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'U';
        }

        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
