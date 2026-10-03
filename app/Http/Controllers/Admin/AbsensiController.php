<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Attendance\MonthlyAttendanceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAttendanceEventDayRequest;
use App\Http\Requests\Admin\SaveAbsensiRequest;
use App\Models\Absensi;
use App\Models\AttendanceDay;
use App\Models\Karyawan;
use App\Support\AttendanceAccess;
use App\Support\AttendanceTimeliness;
use App\Services\WorkCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AbsensiController extends Controller
{
    public function index(Request $request, AttendanceAccess $access, AttendanceTimeliness $timeliness, WorkCalendarService $calendar): Response
    {
        $permissions = $access->for($request->user());
        abort_unless($permissions['canView'], 403);

        $today = CarbonImmutable::today('Asia/Jakarta');
        $yesterday = $today->subDay();
        $selectedDate = $this->selectedDate($request, $today);
        $dayStatus = $calendar->getDayStatus($selectedDate);
        $isWorkingDay = $dayStatus['is_working_day'];
        $canMutateDate = $selectedDate->betweenIncluded($yesterday, $today) && $isWorkingDay;
        $historicalEmployeeIds = Absensi::query()
            ->whereDate('tanggal_absensi', $selectedDate)
            ->select('karyawan_id');
        $employees = Karyawan::query()
            ->with('jabatan:id,nama_jabatan')
            ->when(
                $canMutateDate,
                fn ($query) => $query
                    ->where('status_keaktifan', 'aktif')
                    ->whereDate('tanggal_masuk', '<=', $selectedDate),
                fn ($query) => $query->where(function ($scope) use ($selectedDate, $historicalEmployeeIds): void {
                    $scope->where(function ($eligible) use ($selectedDate): void {
                        $eligible->where('status_keaktifan', 'aktif')
                            ->whereDate('tanggal_masuk', '<=', $selectedDate);
                    })->orWhereIn('id', $historicalEmployeeIds);
                }),
            )
            ->orderBy('nama')
            ->get();
        $attendance = Absensi::query()
            ->whereDate('tanggal_absensi', $selectedDate)
            ->whereIn('karyawan_id', $employees->pluck('id'))
            ->get()
            ->keyBy('karyawan_id');
        $attendanceDay = AttendanceDay::query()
            ->with(['schedules.members'])
            ->whereDate('tanggal', $selectedDate)
            ->first();

        $employeePayload = $employees
            ->map(function (Karyawan $employee) use ($attendance, $attendanceDay, $timeliness, $dayStatus): array {
                $record = $attendance->get($employee->id);
                $schedule = $timeliness->scheduleFor($attendanceDay, $employee->id);

                return [
                    'id' => $employee->id,
                    'nik' => $employee->nik,
                    'name' => $employee->nama,
                    'initials' => $this->initials($employee->nama),
                    'position' => $employee->jabatan?->nama_jabatan ?? '-',
                    'scheduleType' => $schedule['type'],
                    'scheduledTime' => $schedule['expectedTime'],
                    'toleranceMinutes' => $schedule['toleranceMinutes'],
                    'isWorkingDay' => $dayStatus['is_working_day'],
                    'dayType' => $dayStatus['type'],
                    'dayLabel' => $dayStatus['label'],
                    'dayReason' => $dayStatus['reason'],
                    'attendance' => $record ? [
                        'status' => $record->status_kehadiran,
                        'entryTime' => $this->timeValue($record->jam_masuk),
                        'exitTime' => $this->timeValue($record->jam_keluar),
                        'note' => $record->keterangan,
                        'timeliness' => $timeliness->calculate(
                            $record->status_kehadiran,
                            $this->timeValue($record->jam_masuk),
                            $schedule['expectedTime'],
                            $schedule['toleranceMinutes'],
                        ),
                    ] : null,
                ];
            });

        return Inertia::render('Internal/Absensi/Index', [
            'attendanceDate' => $selectedDate->toDateString(),
            'today' => $today->toDateString(),
            'yesterday' => $yesterday->toDateString(),
            'isToday' => $selectedDate->isSameDay($today),
            'dayStatus' => $dayStatus,
            'isWorkingDay' => $isWorkingDay,
            'canMutateDate' => $canMutateDate,
            'isSaved' => ! $isWorkingDay || ($employeePayload->isNotEmpty() && $attendance->count() === $employeePayload->count()),
            'employees' => $employeePayload,
            'attendanceDay' => $this->attendanceDayPayload($attendanceDay),
            'permissions' => $permissions,
            'reportYears' => $this->reportYears($today),
            'user' => $this->userPayload($request),
        ]);
    }

    public function saveEventDay(SaveAttendanceEventDayRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request): void {
            $day = AttendanceDay::query()->firstOrNew(['tanggal' => $validated['tanggal']]);

            if (! $day->exists) {
                $day->created_by = $request->user()->id;
            }

            $day->fill([
                'tipe_hari' => 'event',
                'nama_event' => trim($validated['nama_event']),
                'updated_by' => $request->user()->id,
            ])->save();

            $day->schedules()->delete();

            foreach ($validated['schedules'] as $index => $scheduleData) {
                $schedule = $day->schedules()->create([
                    'jam_masuk' => $scheduleData['jam_masuk'],
                    'toleransi_menit' => AttendanceTimeliness::EVENT_TOLERANCE_MINUTES,
                    'urutan' => $index,
                ]);

                $schedule->members()->createMany(
                    collect($scheduleData['member_ids'])->map(fn ($employeeId): array => [
                        'attendance_day_id' => $day->id,
                        'karyawan_id' => $employeeId,
                    ])->all(),
                );
            }
        });

        return to_route('admin.absensi.index', ['tanggal' => $validated['tanggal']])
            ->with('success', 'Konfigurasi Hari Event berhasil disimpan.');
    }

    public function destroyEventDay(Request $request, AttendanceAccess $access): RedirectResponse
    {
        abort_unless($access->for($request->user())['canManage'], 403);

        $validated = $request->validate(['tanggal' => ['required', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::today('Asia/Jakarta');
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $validated['tanggal'], 'Asia/Jakarta');
        abort_unless($date->betweenIncluded($today->subDay(), $today), 422);

        AttendanceDay::query()->whereDate('tanggal', $date)->delete();

        return to_route('admin.absensi.index', ['tanggal' => $validated['tanggal']])
            ->with('success', 'Tanggal dikembalikan menjadi Hari Normal.');
    }

    public function store(SaveAbsensiRequest $request, WorkCalendarService $calendar): RedirectResponse
    {
        $validated = $request->validated();
        $dayStatus = $calendar->getDayStatus($validated['tanggal_absensi']);
        abort_unless($dayStatus['is_working_day'], 422, 'Tanggal tersebut merupakan hari libur. Absensi tidak diperlukan.');

        DB::transaction(function () use ($validated): void {
            $attendanceDate = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $validated['tanggal_absensi'],
                'Asia/Jakarta',
            )->toDateString();

            foreach ($validated['records'] as $record) {
                $isPresent = $record['status_kehadiran'] === 'H';
                $attributes = [
                    'status_kehadiran' => $record['status_kehadiran'],
                    'jam_masuk' => $isPresent ? ($record['jam_masuk'] ?? null) : null,
                    'jam_keluar' => $isPresent ? ($record['jam_keluar'] ?? null) : null,
                    'keterangan' => $record['keterangan'] ?? null,
                ];
                $attendance = Absensi::query()
                    ->where('karyawan_id', $record['karyawan_id'])
                    ->whereDate('tanggal_absensi', $attendanceDate)
                    ->first();

                if ($attendance) {
                    $attendance->update($attributes);
                } else {
                    Absensi::create([
                        'karyawan_id' => $record['karyawan_id'],
                        'tanggal_absensi' => $attendanceDate,
                        ...$attributes,
                    ]);
                }
            }
        });

        return to_route('admin.absensi.index', ['tanggal' => $validated['tanggal_absensi']])
            ->with('success', 'Absensi berhasil disimpan.');
    }

    public function export(Request $request, AttendanceAccess $access): BinaryFileResponse
    {
        abort_unless($access->for($request->user())['canExport'], 403);

        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $month = (int) $validated['bulan'];
        $year = (int) $validated['tahun'];
        $period = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->locale('id');
        $periodStart = $period->startOfMonth()->toDateString();
        $periodEnd = $period->endOfMonth()->toDateString();
        $records = Absensi::query()
            ->with(['karyawan:id,nama,jabatan_id', 'karyawan.jabatan:id,nama_jabatan'])
            ->join('karyawan', 'absensi.karyawan_id', '=', 'karyawan.id')
            ->select('absensi.*')
            ->whereBetween('absensi.tanggal_absensi', [$periodStart, $periodEnd])
            ->orderBy('absensi.tanggal_absensi')
            ->orderBy('karyawan.nama')
            ->get();
        $attendanceDays = AttendanceDay::query()
            ->with(['schedules.members'])
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->get()
            ->keyBy(fn (AttendanceDay $day): string => $day->tanggal->toDateString());

        $filename = sprintf(
            'absensi-karyawan-%s-%d.xlsx',
            Str::slug($period->translatedFormat('F')),
            $year,
        );

        return Excel::download(new MonthlyAttendanceExport($records, $period, $attendanceDays), $filename);
    }

    private function selectedDate(Request $request, CarbonImmutable $today): CarbonImmutable
    {
        $date = $request->string('tanggal')->toString();

        if ($date === '') {
            return $today;
        }

        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
            404,
        );

        try {
            $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');
        } catch (\Throwable) {
            abort(404);
        }

        abort_if($selectedDate->isFuture(), 404);

        return $selectedDate;
    }

    /**
     * @return list<int>
     */
    private function reportYears(CarbonImmutable $today): array
    {
        return Absensi::query()
            ->distinct()
            ->pluck('tanggal_absensi')
            ->map(fn ($date): int => CarbonImmutable::parse($date)->year)
            ->push($today->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, initials: string, roleName: string, roleLabel: string}
     */
    private function userPayload(Request $request): array
    {
        $user = $request->user()->loadMissing(['role:id,nama_role', 'karyawan:id,nama']);
        $name = $user->karyawan?->nama ?? $user->username;
        $roleName = mb_strtolower($user->role?->nama_role ?? '');

        return [
            'name' => $name,
            'initials' => $this->initials($name),
            'roleName' => $roleName,
            'roleLabel' => str($roleName)->replace('_', ' ')->title()->toString(),
        ];
    }

    private function timeValue(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    private function attendanceDayPayload(?AttendanceDay $day): array
    {
        if (! $day || $day->tipe_hari !== 'event') {
            return [
                'type' => 'normal',
                'eventName' => null,
                'scheduleCount' => 0,
                'committeeCount' => 0,
                'schedules' => [],
            ];
        }

        return [
            'type' => 'event',
            'eventName' => $day->nama_event,
            'scheduleCount' => $day->schedules->count(),
            'committeeCount' => $day->schedules->sum(fn ($schedule): int => $schedule->members->count()),
            'schedules' => $day->schedules->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'entryTime' => substr((string) $schedule->jam_masuk, 0, 5),
                'memberIds' => $schedule->members->pluck('karyawan_id')->all(),
            ])->values(),
        ];
    }
}
